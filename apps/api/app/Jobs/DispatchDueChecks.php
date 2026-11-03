<?php

namespace App\Jobs;

use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

/**
 * Finds due monitors and claims them atomically (PRD §8.1).
 *
 * The claim is a single conditional UPDATE: two dispatchers racing over the
 * same row produce exactly one affected row, so a slot is never checked twice.
 */
class DispatchDueChecks
{
    public const CHUNK_SIZE = 200;

    /** ± seconds of jitter applied to every next_check_at (PRD §4.3). */
    public const JITTER_SECONDS = 5;

    public function __invoke(): void
    {
        $this->dispatchDue(now());
    }

    public function dispatchDue(\DateTimeInterface $now): int
    {
        $due = Monitor::query()
            ->where('is_paused', false)
            ->whereNotNull('next_check_at')
            ->where('next_check_at', '<=', $now)
            ->orderBy('next_check_at')
            ->limit(self::CHUNK_SIZE)
            ->get(['id', 'interval_seconds']);

        $dispatched = 0;

        foreach ($due as $monitor) {
            $claim = DB::table('monitors')
                ->where('id', $monitor->id)
                ->where('is_paused', false)
                ->whereNotNull('next_check_at')
                ->where('next_check_at', '<=', $now)
                ->update([
                    'next_check_at' => $this->nextRun($now, $monitor->interval_seconds),
                    'claimed_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($claim === 1) {
                RunMonitorCheck::dispatch($monitor->id);
                $dispatched++;
            }
        }

        return $dispatched;
    }

    private function nextRun(\DateTimeInterface $now, int $intervalSeconds): string
    {
        $seconds = max(30, $intervalSeconds + random_int(-self::JITTER_SECONDS, self::JITTER_SECONDS));

        return (new \DateTimeImmutable($now->format('Y-m-d H:i:s')))
            ->modify("+{$seconds} seconds")
            ->format('Y-m-d H:i:s');
    }
}
