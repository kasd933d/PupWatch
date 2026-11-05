<?php

namespace App\Jobs;

use App\Services\RollupService;
use Illuminate\Support\Carbon;

/**
 * Aggregates one completed day into daily_stats (PRD §4.9).
 *
 * Scheduled just after midnight for the previous day; passing a date lets an
 * operator backfill after an outage without inventing a second code path.
 */
class RollupDailyStats
{
    public function __construct(public ?string $date = null) {}

    public function __invoke(RollupService $rollups): void
    {
        $day = $this->date === null
            ? now()->subDay()->startOfDay()
            : Carbon::parse($this->date)->startOfDay();

        $rollups->day($day);
    }
}
