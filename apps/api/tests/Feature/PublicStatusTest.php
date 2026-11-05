<?php

namespace Tests\Feature;

use App\Models\CheckResult;
use App\Models\DailyStat;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use App\Services\StatusPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicStatusTest extends TestCase
{
    use RefreshDatabase;

    private function tomorrow(): string
    {
        return now()->subDay()->toDateString();
    }

    private function seedDay(Monitor $monitor, int $total, int $successful): void
    {
        DailyStat::create([
            'monitor_id' => $monitor->id,
            'date' => $this->tomorrow(),
            'total_checks' => $total,
            'successful_checks' => $successful,
        ]);
    }

    private function checkNow(Monitor $monitor, bool $success, ?int $ms = 130): void
    {
        CheckResult::create([
            'monitor_id' => $monitor->id,
            'checked_at' => now()->subMinute(),
            'status_code' => $success ? 200 : null,
            'response_time_ms' => $ms,
            'is_success' => $success,
            'error_type' => $success ? null : 'connection_error',
            'error_message' => $success ? null : 'Connection refused',
        ]);
    }

    /** @return array{0: StatusPage, 1: array<int, Monitor>} */
    private function pageWithThreeMonitors(array $attributes = []): array
    {
        $user = User::factory()->create();
        $page = StatusPage::factory()->create([
            'user_id' => $user->id,
            'slug' => 'acme-status',
            'title' => 'Acme Status',
            ...$attributes,
        ]);

        $names = ['API', 'Website', 'Client portal'];
        $monitors = [];

        foreach ($names as $index => $name) {
            $monitor = Monitor::factory()->create([
                'user_id' => $user->id,
                'name' => $name,
                'current_status' => Monitor::STATUS_UP,
                'last_checked_at' => now()->subMinute(),
                'last_response_ms' => 180,
            ]);
            $page->monitors()->attach($monitor->id, ['sort_order' => $index]);
            $monitors[] = $monitor;
        }

        return [$page, $monitors];
    }

    public function test_the_public_endpoint_needs_no_auth_and_returns_ninety_day_bars(): void
    {
        [, $monitors] = $this->pageWithThreeMonitors();
        [$api, $site, $portal] = $monitors;

        $this->seedDay($api, 100, 100);
        $this->seedDay($site, 100, 98);
        $this->seedDay($portal, 100, 90);
        $this->checkNow($api, true);
        $this->checkNow($site, true);
        $this->checkNow($portal, false);
        // A failed check is what turns the monitor down (the job writes both).
        $portal->update(['current_status' => Monitor::STATUS_DOWN]);

        $response = $this->getJson('/api/v1/public/status/acme-status')->assertOk();

        $response->assertJsonPath('page.title', 'Acme Status')
            ->assertJsonPath('page.is_indexable', true)
            ->assertJsonPath('overall', 'partial_outage')
            ->assertJsonCount(3, 'monitors')
            ->assertJsonCount(90, 'monitors.0.bars')
            ->assertJsonPath('monitors.0.name', 'API')
            ->assertJsonPath('monitors.0.uptime_90d.total', 101)
            ->assertJsonPath('monitors.0.uptime_90d.successful', 101)
            ->assertJsonPath('monitors.0.uptime_90d.uptime', 100)
            ->assertJsonPath('monitors.1.uptime_90d.uptime', 98.02)
            ->assertJsonPath('monitors.2.status', 'down');

        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));

        $bars = $response->json('monitors.0.bars');
        $this->assertSame(now()->startOfDay()->subDays(89)->toDateString(), $bars[0]['date']);
        $this->assertSame(now()->toDateString(), $bars[89]['date']);
    }

    public function test_response_times_and_error_details_are_hidden_by_default(): void
    {
        [$page, $monitors] = $this->pageWithThreeMonitors();
        $monitor = $monitors[0];

        Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subHours(20),
            'duration_seconds' => 4 * 3600,
            'first_error_type' => 'timeout',
            'first_error_message' => 'Connection to db.internal timed out',
            'status' => Incident::STATUS_RESOLVED,
        ]);

        $response = $this->getJson('/api/v1/public/status/'.$page->slug)->assertOk();

        $response->assertJsonPath('monitors.0.last_response_ms', null)
            ->assertJsonPath('incidents.0.error_type', null)
            ->assertJsonPath('incidents.0.error_message', null)
            ->assertJsonPath('incidents.0.monitor_name', 'API');

        $this->assertStringNotContainsString('db.internal', $response->getContent());
    }

    public function test_owners_can_opt_into_showing_times_and_error_details(): void
    {
        [$page, $monitors] = $this->pageWithThreeMonitors([
            'show_response_times' => true,
            'show_error_details' => true,
        ]);

        Incident::create([
            'monitor_id' => $monitors[0]->id,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subHours(20),
            'duration_seconds' => 3600,
            'first_error_type' => 'timeout',
            'first_error_message' => 'Timed out after 10s',
            'status' => Incident::STATUS_RESOLVED,
        ]);

        $this->getJson('/api/v1/public/status/'.$page->slug)
            ->assertOk()
            ->assertJsonPath('monitors.0.last_response_ms', 180)
            ->assertJsonPath('incidents.0.error_type', 'timeout')
            ->assertJsonPath('incidents.0.error_message', 'Timed out after 10s');
    }

    public function test_display_names_replace_internal_monitor_names(): void
    {
        [$page, $monitors] = $this->pageWithThreeMonitors();
        $page->monitors()->syncWithoutDetaching([
            $monitors[0]->id => ['display_name' => 'Acme API', 'sort_order' => 0],
        ]);

        $this->getJson('/api/v1/public/status/'.$page->slug)
            ->assertOk()
            ->assertJsonPath('monitors.0.name', 'Acme API');
    }

    public function test_the_payload_is_cached_for_sixty_seconds(): void
    {
        [$page] = $this->pageWithThreeMonitors();

        $this->getJson('/api/v1/public/status/'.$page->slug)->assertOk();

        $this->assertTrue(Cache::has(StatusPageService::cacheKey($page->slug)));

        $page->update(['title' => 'Renamed']);
        $this->getJson('/api/v1/public/status/'.$page->slug)
            ->assertOk()
            ->assertJsonPath('page.title', 'Acme Status');

        Cache::flush();
        $this->getJson('/api/v1/public/status/'.$page->slug)
            ->assertOk()
            ->assertJsonPath('page.title', 'Renamed');
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->getJson('/api/v1/public/status/nope')->assertNotFound();
    }

    public function test_overall_status_reflects_down_monitors(): void
    {
        [$page, $monitors] = $this->pageWithThreeMonitors();

        $monitors[0]->update(['current_status' => Monitor::STATUS_DOWN]);
        $monitors[1]->update(['current_status' => Monitor::STATUS_DOWN]);
        $monitors[2]->update(['current_status' => Monitor::STATUS_DOWN]);

        Cache::flush();
        $this->getJson('/api/v1/public/status/'.$page->slug)
            ->assertOk()
            ->assertJsonPath('overall', 'major_outage');

        $monitors[0]->update(['current_status' => Monitor::STATUS_UP]);
        $monitors[1]->update(['current_status' => Monitor::STATUS_UP]);
        Cache::flush();
        $this->getJson('/api/v1/public/status/'.$page->slug)
            ->assertOk()
            ->assertJsonPath('overall', 'partial_outage');
    }
}
