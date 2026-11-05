<?php

namespace Tests\Feature;

use App\Jobs\RollupDailyStats;
use App\Models\CheckResult;
use App\Models\DailyStat;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use App\Services\RollupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RollupTest extends TestCase
{
    use RefreshDatabase;

    private function check(Monitor $monitor, Carbon $at, bool $success = true, ?int $ms = 100): CheckResult
    {
        return CheckResult::create([
            'monitor_id' => $monitor->id,
            'checked_at' => $at,
            'status_code' => $success ? 200 : null,
            'response_time_ms' => $ms,
            'is_success' => $success,
            'error_type' => $success ? null : 'timeout',
            'error_message' => $success ? null : 'timed out',
        ]);
    }

    private function rollup(string $date): void
    {
        (new RollupDailyStats($date))($this->app->make(RollupService::class));
    }

    public function test_a_day_is_rolled_up_with_aggregates_and_p95(): void
    {
        $monitor = Monitor::factory()->create();
        $day = Carbon::parse('2026-10-01');

        // 20 checks, 50..1000 ms, one of them failed.
        for ($i = 1; $i <= 20; $i++) {
            $this->check($monitor, $day->copy()->addMinutes($i * 10), $i !== 7, $i * 50);
        }

        Incident::create([
            'monitor_id' => $monitor->id,
            'started_at' => $day->copy()->addHours(10),
            'ended_at' => $day->copy()->addHours(11)->addMinutes(30),
            'duration_seconds' => 5400,
            'first_error_type' => 'timeout',
            'first_error_message' => 'timed out',
            'status' => Incident::STATUS_RESOLVED,
        ]);

        $this->rollup('2026-10-01');

        $stat = DailyStat::where('monitor_id', $monitor->id)->sole();

        $this->assertSame(20, $stat->total_checks);
        $this->assertSame(19, $stat->successful_checks);
        $this->assertSame(525, $stat->avg_response_ms);
        $this->assertSame(50, $stat->min_response_ms);
        $this->assertSame(1000, $stat->max_response_ms);
        // Nearest rank: ceil(0.95 * 20) = 19th value of 50..1000 = 950.
        $this->assertSame(950, $stat->p95_response_ms);
        $this->assertSame(5400, $stat->downtime_seconds);
        $this->assertSame($day->toDateString(), (string) $stat->date);
    }

    public function test_rerunning_a_rollup_is_idempotent(): void
    {
        $monitor = Monitor::factory()->create();
        $day = Carbon::parse('2026-10-02');

        $this->check($monitor, $day->copy()->addHour(), true, 120);
        $this->check($monitor, $day->copy()->addHours(2), false, null);

        $this->rollup('2026-10-02');
        $this->rollup('2026-10-02');

        $this->assertSame(1, DailyStat::where('monitor_id', $monitor->id)->count());
        $stat = DailyStat::where('monitor_id', $monitor->id)->sole();
        $this->assertSame(2, $stat->total_checks);
        $this->assertSame(1, $stat->successful_checks);
    }

    public function test_a_day_without_checks_creates_no_row(): void
    {
        $monitor = Monitor::factory()->create();

        $this->check($monitor, Carbon::parse('2026-10-01 12:00'), true, 100);
        $this->rollup('2026-10-02');

        $this->assertSame(0, DailyStat::count());
    }

    public function test_checks_outside_the_day_are_not_counted(): void
    {
        $monitor = Monitor::factory()->create();

        $this->check($monitor, Carbon::parse('2026-10-01 23:59'), true, 100);
        $this->check($monitor, Carbon::parse('2026-10-02 00:00'), false, null);

        $this->rollup('2026-10-01');

        $stat = DailyStat::where('monitor_id', $monitor->id)->sole();
        $this->assertSame(1, $stat->total_checks);
        $this->assertSame(1, $stat->successful_checks);
    }

    public function test_ninety_day_uptime_uses_rollups_plus_today(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::factory()->create(['user_id' => $user->id]);

        DailyStat::create([
            'monitor_id' => $monitor->id,
            'date' => now()->subDays(60)->toDateString(),
            'total_checks' => 100,
            'successful_checks' => 99,
        ]);

        $this->check($monitor, now()->subMinutes(5), true, 100);

        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/monitors/'.$monitor->id)
            ->assertOk()
            ->assertJsonPath('monitor.uptime_ranges.90d.total', 101)
            ->assertJsonPath('monitor.uptime_ranges.90d.successful', 100)
            ->assertJsonPath('monitor.uptime_ranges.90d.uptime', 99.01)
            // 24h still reads raw checks, so only today's check counts.
            ->assertJsonPath('monitor.uptime_ranges.24h.total', 1);
    }
}
