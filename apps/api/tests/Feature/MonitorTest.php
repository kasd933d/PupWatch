<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function actingUser(): array
    {
        $user = User::factory()->create();

        return [$user, $user->createToken('web')->plainTextToken];
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Marketing site',
            'url' => 'https://example.com/health',
            'method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 10,
            'expected_status' => '200-399',
            'alert_after_failures' => 2,
        ], $overrides);
    }

    public function test_a_user_can_create_and_list_their_monitors(): void
    {
        [, $token] = $this->actingUser();

        $create = $this->withToken($token)->postJson('/api/v1/monitors', $this->validPayload());
        $create->assertStatus(201)->assertJsonPath('monitor.name', 'Marketing site')
            ->assertJsonPath('monitor.current_status', 'pending')
            ->assertJsonPath('monitor.host', 'example.com');

        $this->withToken($token)->getJson('/api/v1/monitors')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_monitors_require_valid_input(): void
    {
        [, $token] = $this->actingUser();

        $this->withToken($token)->postJson('/api/v1/monitors', $this->validPayload([
            'url' => 'ftp://example.com',
            'interval_seconds' => 45,
            'expected_status' => 'twenty',
            'timeout_seconds' => 120,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['url', 'interval_seconds', 'expected_status', 'timeout_seconds']);
    }

    public function test_ssrf_targets_are_refused_at_save_time(): void
    {
        [, $token] = $this->actingUser();

        foreach ([
            'http://localhost/admin',
            'http://127.0.0.1/',
            'http://169.254.169.254/latest/meta-data',
            'http://192.168.1.1/',
            'http://foo.internal/',
            'http://user:pass@example.com/',
            'https://example.com:22/',
        ] as $url) {
            $this->withToken($token)->postJson('/api/v1/monitors', $this->validPayload(['url' => $url]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['url']);
        }
    }

    public function test_users_cant_see_or_edit_each_others_monitors(): void
    {
        [, $token] = $this->actingUser();
        $other = Monitor::factory()->create();

        $this->withToken($token)->getJson('/api/v1/monitors/'.$other->id)->assertForbidden();
        $this->withToken($token)->patchJson('/api/v1/monitors/'.$other->id, ['name' => 'hacked'])->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/monitors/'.$other->id)->assertForbidden();

        $this->assertDatabaseHas('monitors', ['id' => $other->id, 'name' => $other->name]);
    }

    public function test_a_user_can_update_pause_resume_and_delete_a_monitor(): void
    {
        [$user, $token] = $this->actingUser();
        $monitor = Monitor::factory()->create(['user_id' => $user->id, 'name' => 'Old name']);

        $this->withToken($token)->patchJson('/api/v1/monitors/'.$monitor->id, ['name' => 'New name'])
            ->assertOk()->assertJsonPath('monitor.name', 'New name');

        $this->withToken($token)->postJson('/api/v1/monitors/'.$monitor->id.'/pause')
            ->assertOk()->assertJsonPath('monitor.current_status', 'paused');

        $this->assertNull($monitor->refresh()->next_check_at);

        $this->withToken($token)->postJson('/api/v1/monitors/'.$monitor->id.'/resume')
            ->assertOk()->assertJsonPath('monitor.current_status', 'pending');

        $this->assertNotNull($monitor->refresh()->next_check_at);

        $this->withToken($token)->deleteJson('/api/v1/monitors/'.$monitor->id)->assertOk();
        $this->assertDatabaseMissing('monitors', ['id' => $monitor->id]);
    }

    public function test_plan_limits_a_user_to_ten_monitors(): void
    {
        [$user, $token] = $this->actingUser();
        Monitor::factory()->count(10)->create(['user_id' => $user->id]);

        $this->withToken($token)->postJson('/api/v1/monitors', $this->validPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_keyword_checks_require_a_keyword_mode(): void
    {
        [, $token] = $this->actingUser();

        $this->withToken($token)->postJson('/api/v1/monitors', $this->validPayload([
            'keyword' => 'ok',
            'keyword_mode' => null,
        ]))->assertStatus(422)->assertJsonValidationErrors(['keyword_mode']);
    }

    public function test_check_now_is_throttled_to_once_per_30s(): void
    {
        [$user, $token] = $this->actingUser();
        $monitor = Monitor::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->postJson('/api/v1/monitors/'.$monitor->id.'/check')->assertStatus(202);
        $this->withToken($token)->postJson('/api/v1/monitors/'.$monitor->id.'/check')->assertStatus(429);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/monitors')->assertUnauthorized();
        $this->postJson('/api/v1/monitors', $this->validPayload())->assertUnauthorized();
    }
}
