<?php

namespace Tests\Feature;

use App\Jobs\RunMonitorCheck;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Services\CheckService;
use App\Services\MonitorScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunMonitorCheckTest extends TestCase
{
    use RefreshDatabase;

    private function runCheck(Monitor $monitor): void
    {
        (new RunMonitorCheck($monitor->id))->handle($this->app->make(CheckService::class));
    }

    public function test_a_check_is_stored_and_the_monitor_turns_up(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/',
            'current_status' => Monitor::STATUS_PENDING,
        ]);

        $this->runCheck($monitor);

        $this->assertDatabaseCount('check_results', 1);

        $result = CheckResult::first();
        $this->assertSame($monitor->id, $result->monitor_id);
        $this->assertTrue($result->is_success);
        $this->assertSame(200, $result->status_code);
        $this->assertNotNull($result->response_time_ms);

        $monitor->refresh();
        $this->assertSame(Monitor::STATUS_UP, $monitor->current_status);
        $this->assertSame(0, $monitor->consecutive_failures);
        $this->assertNotNull($monitor->last_checked_at);
        $this->assertNull($monitor->claimed_at);
        $this->assertTrue($monitor->next_check_at->isFuture());
    }

    public function test_one_failure_with_threshold_two_does_not_mark_the_monitor_down(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/',
            'alert_after_failures' => 2,
            'current_status' => Monitor::STATUS_UP,
        ]);

        $this->runCheck($monitor);

        $monitor->refresh();
        $this->assertSame(1, $monitor->consecutive_failures);
        $this->assertSame(Monitor::STATUS_UP, $monitor->current_status);
    }

    public function test_the_second_failure_marks_the_monitor_down(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/',
            'alert_after_failures' => 2,
            'current_status' => Monitor::STATUS_UP,
        ]);

        $this->runCheck($monitor);
        $this->runCheck($monitor->refresh());

        $monitor->refresh();
        $this->assertSame(2, $monitor->consecutive_failures);
        $this->assertSame(Monitor::STATUS_DOWN, $monitor->current_status);
        $this->assertDatabaseCount('check_results', 2);
    }

    public function test_a_success_after_an_outage_recovers_the_monitor(): void
    {
        // First check fails, second succeeds.
        Http::fakeSequence()
            ->push('', 500)
            ->push('ok', 200);

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/',
            'alert_after_failures' => 1,
            'current_status' => Monitor::STATUS_UP,
        ]);

        $this->runCheck($monitor);
        $this->assertSame(Monitor::STATUS_DOWN, $monitor->refresh()->current_status);

        $this->runCheck($monitor->refresh());

        $monitor->refresh();
        $this->assertSame(Monitor::STATUS_UP, $monitor->current_status);
        $this->assertSame(0, $monitor->consecutive_failures);
    }

    public function test_a_paused_monitor_is_skipped_without_recording_a_check(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/',
            'is_paused' => true,
        ]);
        MonitorScheduler::pause($monitor);

        $this->runCheck($monitor->refresh());

        $this->assertDatabaseCount('check_results', 0);
        $this->assertSame(Monitor::STATUS_PAUSED, $monitor->refresh()->current_status);
        Http::assertNothingSent();
    }

    public function test_a_deleted_monitor_is_a_no_op(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $monitor = Monitor::factory()->create(['url' => 'https://example.com/']);
        $id = $monitor->id;
        $monitor->delete();

        (new RunMonitorCheck($id))->handle($this->app->make(CheckService::class));

        $this->assertDatabaseCount('check_results', 0);
        Http::assertNothingSent();
    }
}
