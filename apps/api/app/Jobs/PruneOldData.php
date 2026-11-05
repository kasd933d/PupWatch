<?php

namespace App\Jobs;

use App\Services\PruneService;

/**
 * Nightly retention job (PRD §4.9): 30 days of raw checks, 400 days of rollups.
 */
class PruneOldData
{
    public function __invoke(PruneService $prune): void
    {
        $prune->checkResults();
        $prune->dailyStats();
    }
}
