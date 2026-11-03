<?php

namespace App\Jobs;

use App\Models\Monitor;

/**
 * Releases claims whose worker died before finishing (PRD §8.2).
 *
 * A claim older than the longest possible check plus queue latency means the
 * job will never complete, so the monitor is made due again.
 */
class RecoverStuckClaims
{
    public const STALE_AFTER_MINUTES = 5;

    public function __invoke(): void
    {
        Monitor::query()
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->update([
                'claimed_at' => null,
                'next_check_at' => now(),
            ]);
    }
}
