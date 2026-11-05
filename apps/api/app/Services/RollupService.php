<?php

namespace App\Services;

use App\Models\DailyStat;
use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nightly aggregation of check_results into daily_stats (PRD §4.9).
 *
 * Idempotent: re-running a day replaces that day's rows, so a backfill or a
 * retried job never double counts. Raw checks are pruned after 30 days, so the
 * 90-day views depend on this being correct.
 */
class RollupService
{
    public const CHUNK_SIZE = 200;

    /**
     * Roll up every monitor for one calendar day. Returns how many monitors
     * produced a row (monitors with no checks are skipped).
     */
    public function day(Carbon $day): int
    {
        $start = $day->copy()->startOfDay();
        $end = $start->copy()->addDay();
        $written = 0;

        Monitor::query()->orderBy('id')->chunkById(self::CHUNK_SIZE, function ($monitors) use ($start, $end, &$written) {
            foreach ($monitors as $monitor) {
                if ($this->rollupMonitorDay($monitor, $start, $end)) {
                    $written++;
                }
            }
        });

        return $written;
    }

    private function rollupMonitorDay(Monitor $monitor, Carbon $start, Carbon $end): bool
    {
        $row = DB::table('check_results')
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $end)
            ->selectRaw(
                'COUNT(*) as total, '.
                'COALESCE(SUM(CASE WHEN is_success = 1 THEN 1 ELSE 0 END), 0) as successful, '.
                'AVG(response_time_ms) as avg_ms, MIN(response_time_ms) as min_ms, MAX(response_time_ms) as max_ms'
            )
            ->first();

        $total = (int) ($row->total ?? 0);

        // A paused (or brand new) monitor has no checks; there is no row to keep.
        if ($total === 0) {
            return false;
        }

        DailyStat::query()->updateOrCreate(
            ['monitor_id' => $monitor->id, 'date' => $start->toDateString()],
            [
                'total_checks' => $total,
                'successful_checks' => (int) $row->successful,
                'avg_response_ms' => $row->avg_ms === null ? null : (int) round((float) $row->avg_ms),
                'min_response_ms' => $row->min_ms === null ? null : (int) $row->min_ms,
                'max_response_ms' => $row->max_ms === null ? null : (int) $row->max_ms,
                'p95_response_ms' => $this->p95($monitor, $start, $end),
                'downtime_seconds' => $this->downtimeSeconds($monitor, $start, $end),
            ]
        );

        return true;
    }

    /**
     * Nearest-rank p95: the value at ceil(0.95 * n) in ascending order.
     * Portable between SQLite and MySQL because it is an ORDER BY + OFFSET.
     */
    private function p95(Monitor $monitor, Carbon $start, Carbon $end): ?int
    {
        // Only timed checks count; failed DNS/timeout checks have no duration.
        $timed = DB::table('check_results')
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $end)
            ->whereNotNull('response_time_ms')
            ->count();

        if ($timed === 0) {
            return null;
        }

        $offset = max(0, min($timed - 1, (int) ceil($timed * 0.95) - 1));

        $value = DB::table('check_results')
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $end)
            ->whereNotNull('response_time_ms')
            ->orderBy('response_time_ms')
            ->offset($offset)
            ->limit(1)
            ->value('response_time_ms');

        return $value === null ? null : (int) $value;
    }

    /**
     * Seconds of open incidents that overlap the day, clipped to its bounds.
     */
    private function downtimeSeconds(Monitor $monitor, Carbon $start, Carbon $end): int
    {
        $incidents = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('started_at', '<', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('ended_at')->orWhere('ended_at', '>', $start);
            })
            ->get(['started_at', 'ended_at']);

        $seconds = 0;

        foreach ($incidents as $incident) {
            $from = $incident->started_at->greaterThan($start) ? $incident->started_at : $start;
            $until = $incident->ended_at === null || $incident->ended_at->greaterThan($end)
                ? $end
                : $incident->ended_at;

            if ($until->greaterThan($from)) {
                $seconds += (int) $from->diffInSeconds($until);
            }
        }

        return $seconds;
    }
}
