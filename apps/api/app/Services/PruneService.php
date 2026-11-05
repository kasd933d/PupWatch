<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Retention pruning (PRD §4.9, §8): raw checks 30 days, daily stats 400 days.
 *
 * Deletes in bounded chunks so a large backlog never locks the table or blows
 * up a single statement.
 */
class PruneService
{
    public const CHUNK = 5000;

    public function checkResults(?Carbon $now = null): int
    {
        $now ??= now();
        $cutoff = $now->copy()->subDays((int) config('pupwatch.retention.check_results_days', 30));
        $deleted = 0;

        do {
            $count = DB::table('check_results')
                ->where('checked_at', '<', $cutoff)
                ->limit(self::CHUNK)
                ->delete();

            $deleted += $count;
        } while ($count > 0);

        return $deleted;
    }

    public function dailyStats(?Carbon $now = null): int
    {
        $now ??= now();
        $cutoff = $now->copy()->subDays((int) config('pupwatch.retention.daily_stats_days', 400));

        return DB::table('daily_stats')
            ->where('date', '<', $cutoff->toDateString())
            ->delete();
    }
}
