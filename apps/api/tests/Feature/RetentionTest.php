<?php

namespace Tests\Feature;

use App\Jobs\PruneOldData;
use App\Models\CheckResult;
use App\Models\DailyStat;
use App\Models\Monitor;
use App\Services\PruneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private function checkAt(Monitor $monitor, int $daysAgo): CheckResult
    {
        return CheckResult::create([
            'monitor_id' => $monitor->id,
            'checked_at' => now()->subDays($daysAgo),
            'status_code' => 200,
            'response_time_ms' => 120,
            'is_success' => true,
        ]);
    }

    public function test_raw_checks_older_than_thirty_days_are_deleted(): void
    {
        $monitor = Monitor::factory()->create();
        $old = $this->checkAt($monitor, 31);
        $recent = $this->checkAt($monitor, 5);

        $deleted = (new PruneService)->checkResults();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('check_results', ['id' => $old->id]);
        $this->assertDatabaseHas('check_results', ['id' => $recent->id]);
    }

    public function test_pruning_runs_in_chunks_and_removes_everything_old(): void
    {
        $monitor = Monitor::factory()->create();

        foreach (range(1, 12) as $day) {
            $this->checkAt($monitor, 30 + $day);
        }

        (new PruneService)->checkResults();

        $this->assertSame(0, CheckResult::where('checked_at', '<', now()->subDays(30))->count());
    }

    public function test_daily_stats_are_kept_for_four_hundred_days(): void
    {
        $monitor = Monitor::factory()->create();

        DailyStat::create([
            'monitor_id' => $monitor->id,
            'date' => now()->subDays(401)->toDateString(),
            'total_checks' => 10,
            'successful_checks' => 10,
        ]);

        DailyStat::create([
            'monitor_id' => $monitor->id,
            'date' => now()->subDays(399)->toDateString(),
            'total_checks' => 10,
            'successful_checks' => 9,
        ]);

        (new PruneService)->dailyStats();

        $this->assertSame(1, DailyStat::count());
        $this->assertDatabaseHas('daily_stats', ['date' => now()->subDays(399)->toDateString()]);
    }

    public function test_the_nightly_job_prunes_both_tables(): void
    {
        $monitor = Monitor::factory()->create();
        $this->checkAt($monitor, 45);
        DailyStat::create([
            'monitor_id' => $monitor->id,
            'date' => now()->subDays(500)->toDateString(),
            'total_checks' => 1,
            'successful_checks' => 1,
        ]);

        (new PruneOldData)($this->app->make(PruneService::class));

        $this->assertSame(0, CheckResult::count());
        $this->assertSame(0, DailyStat::count());
    }
}
