<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Services\CheckEngine\CheckResult;
use App\Services\CheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckEngineTest extends TestCase
{
    use RefreshDatabase;

    private function check(Monitor $monitor): CheckResult
    {
        return $this->app->make(CheckService::class)->run($monitor);
    }

    public function test_a_healthy_site_is_recorded_as_a_success(): void
    {
        Http::fake(['*' => Http::response('hello', 200)]);

        $result = $this->check(Monitor::factory()->create(['url' => 'https://example.com/']));

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->statusCode);
        $this->assertNotNull($result->responseTimeMs);
        $this->assertNull($result->errorType);
        Http::assertSentCount(1);
    }

    public function test_an_unexpected_status_fails_the_check(): void
    {
        Http::fake(['*' => Http::response('oops', 500)]);

        $result = $this->check(Monitor::factory()->create([
            'url' => 'https://example.com/',
            'expected_status' => '200-299',
        ]));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::UNEXPECTED_STATUS, $result->errorType);
        $this->assertSame(500, $result->statusCode);
    }

    public function test_a_status_list_accepts_any_listed_range(): void
    {
        Http::fake(['*' => Http::response('', 204)]);

        $result = $this->check(Monitor::factory()->create([
            'url' => 'https://example.com/',
            'expected_status' => '200-299,301-308',
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(204, $result->statusCode);
    }

    public function test_a_missing_keyword_fails_a_must_contain_check(): void
    {
        Http::fake(['*' => Http::response('welcome to the site', 200)]);

        $result = $this->check(Monitor::factory()->create([
            'url' => 'https://example.com/',
            'keyword' => 'checkout',
            'keyword_mode' => Monitor::KEYWORD_MUST_CONTAIN,
        ]));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::KEYWORD_MISSING, $result->errorType);
    }

    public function test_a_present_keyword_passes_a_must_contain_check(): void
    {
        Http::fake(['*' => Http::response('welcome to the checkout', 200)]);

        $result = $this->check(Monitor::factory()->create([
            'url' => 'https://example.com/',
            'keyword' => 'checkout',
            'keyword_mode' => Monitor::KEYWORD_MUST_CONTAIN,
        ]));

        $this->assertTrue($result->success);
    }

    public function test_a_present_keyword_fails_a_must_not_contain_check(): void
    {
        Http::fake(['*' => Http::response('fatal error: database is down', 200)]);

        $result = $this->check(Monitor::factory()->create([
            'url' => 'https://example.com/',
            'keyword' => 'fatal error',
            'keyword_mode' => Monitor::KEYWORD_MUST_NOT_CONTAIN,
        ]));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::KEYWORD_FOUND, $result->errorType);
    }

    public function test_only_the_first_megabyte_is_searched_for_a_keyword(): void
    {
        Http::fake(['*' => Http::response(str_repeat('a', 1024 * 1024 + 100).'needle', 200)]);

        $result = $this->check(Monitor::factory()->create([
            'url' => 'https://example.com/',
            'keyword' => 'needle',
            'keyword_mode' => Monitor::KEYWORD_MUST_CONTAIN,
        ]));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::KEYWORD_MISSING, $result->errorType);
    }

    public function test_a_timeout_is_recorded_as_data(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 10000 milliseconds');
        });

        $result = $this->check(Monitor::factory()->create(['url' => 'https://example.com/']));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::TIMEOUT, $result->errorType);
        $this->assertNotNull($result->errorMessage);
        $this->assertLessThanOrEqual(255, strlen((string) $result->errorMessage));
    }

    public function test_a_dns_failure_is_recorded_as_data(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 6: Could not resolve host: gone.example');
        });

        $result = $this->check(Monitor::factory()->create(['url' => 'https://gone.example/']));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::DNS_FAILURE, $result->errorType);
    }

    public function test_a_connection_refusal_is_recorded_as_data(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 7: Failed to connect: Connection refused');
        });

        $result = $this->check(Monitor::factory()->create(['url' => 'https://example.com/']));

        $this->assertSame(CheckResult::CONNECTION_REFUSED, $result->errorType);
    }

    public function test_an_ssl_error_is_recorded_as_data(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 60: SSL certificate problem: certificate has expired');
        });

        $result = $this->check(Monitor::factory()->create(['url' => 'https://example.com/']));

        $this->assertSame(CheckResult::SSL_ERROR, $result->errorType);
    }

    public function test_a_target_that_resolves_privately_is_never_requested(): void
    {
        $this->dns->set('rebind.example', ['10.0.0.7']);
        Http::fake(['*' => Http::response('should not happen', 200)]);

        $result = $this->check(Monitor::factory()->create(['url' => 'https://rebind.example/']));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::BLOCKED_TARGET, $result->errorType);
        Http::assertNothingSent();
    }

    public function test_a_redirect_resolving_privately_is_blocked_mid_chain(): void
    {
        Http::fake([
            'example.com/start' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
        ]);

        $result = $this->check(Monitor::factory()->create(['url' => 'https://example.com/start']));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::BLOCKED_TARGET, $result->errorType);
        Http::assertSentCount(1);
    }

    public function test_redirects_are_followed_and_the_final_status_is_used(): void
    {
        Http::fake([
            'example.com/start' => Http::response('', 302, ['Location' => '/final']),
            'example.com/final' => Http::response('arrived', 200),
        ]);

        $result = $this->check(Monitor::factory()->create(['url' => 'https://example.com/start']));

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->statusCode);
        Http::assertSentCount(2);
    }

    public function test_relative_and_absolute_redirects_both_work(): void
    {
        Http::fake([
            'example.com/a' => Http::response('', 301, ['Location' => 'https://other.example/b']),
            'other.example/b' => Http::response('', 302, ['Location' => '/c']),
            'other.example/c' => Http::response('done', 200),
        ]);

        $result = $this->check(Monitor::factory()->create(['url' => 'https://example.com/a']));

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->statusCode);
        Http::assertSentCount(3);
    }

    public function test_more_than_five_redirects_fails(): void
    {
        Http::fake(['example.com/loop' => Http::response('', 302, ['Location' => '/loop'])]);

        $result = $this->check(Monitor::factory()->create(['url' => 'https://example.com/loop']));

        $this->assertFalse($result->success);
        $this->assertSame(CheckResult::TOO_MANY_REDIRECTS, $result->errorType);
    }

    public function test_redirects_are_not_followed_when_disabled(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => '/elsewhere'])]);

        $result = $this->check(Monitor::factory()->create([
            'url' => 'https://example.com/',
            'follow_redirects' => false,
            'expected_status' => '300-399',
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(302, $result->statusCode);
        Http::assertSentCount(1);
    }
}
