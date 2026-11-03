<?php

namespace App\Services;

use App\Models\Monitor;

/**
 * Owns every write to `monitors.next_check_at` and `monitors.claimed_at`
 * (PRD §8.1), so scheduling rules live in one auditable place: jitter on every
 * schedule, immediate stop on pause, immediate pickup on resume.
 */
class MonitorScheduler
{
    /** PRD §4.3 — jitter applied to every schedule to avoid thundering herds. */
    public const JITTER_SECONDS = 5;

    /**
     * Make a monitor due at `$when` and release any claim.
     */
    public static function due(Monitor $monitor, ?\DateTimeInterface $when = null): void
    {
        $monitor->forceFill([
            'next_check_at' => $when ?? now(),
            'claimed_at' => null,
        ])->save();
    }

    /**
     * Schedule the next run: now + interval ± jitter (PRD §4.3, §8.1).
     */
    public static function scheduleNext(Monitor $monitor): void
    {
        $jitter = random_int(-self::JITTER_SECONDS, self::JITTER_SECONDS);

        $monitor->forceFill([
            'next_check_at' => now()->addSeconds($monitor->interval_seconds + $jitter),
            'claimed_at' => null,
        ])->save();
    }

    /**
     * PRD §4.2 — pausing stops checks immediately.
     */
    public static function pause(Monitor $monitor): void
    {
        $monitor->forceFill([
            'is_paused' => true,
            'current_status' => Monitor::STATUS_PAUSED,
            'next_check_at' => null,
            'claimed_at' => null,
        ])->save();
    }

    /**
     * PRD §4.2 — resuming schedules the next check in under one interval.
     */
    public static function resume(Monitor $monitor): void
    {
        $monitor->forceFill([
            'is_paused' => false,
            'current_status' => Monitor::STATUS_PENDING,
            'consecutive_failures' => 0,
            'next_check_at' => now(),
            'claimed_at' => null,
        ])->save();
    }
}
