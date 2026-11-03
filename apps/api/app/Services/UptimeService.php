<?php

namespace App\Services;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Support\Carbon;

/**
 * Uptime percentages (PRD §11).
 *
 * v1 reads raw check_results; Milestone 5 switches the full past days to
 * daily_stats and keeps today live.
 */
class UptimeService
{
    /**
     * @return array{total: int, successful: int, uptime: float|null}
     */
    public function summary(Monitor $monitor, string $range = '24h'): array
    {
        $counts = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $this->from($range))
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(CASE WHEN is_success = 1 THEN 1 ELSE 0 END), 0) as successful')
            ->first();

        $total = (int) ($counts->total ?? 0);
        $successful = (int) ($counts->successful ?? 0);

        return [
            'total' => $total,
            'successful' => $successful,
            'uptime' => $total === 0 ? null : round($successful / $total * 100, 2),
        ];
    }

    /**
     * Uptime for several monitors at once (dashboard list).
     *
     * @param  list<int>  $monitorIds
     * @return array<int, array{total: int, successful: int, uptime: float|null}>
     */
    public function summariesFor(array $monitorIds, string $range = '24h'): array
    {
        if ($monitorIds === []) {
            return [];
        }

        $rows = CheckResult::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', $this->from($range))
            ->groupBy('monitor_id')
            ->selectRaw('monitor_id, COUNT(*) as total, COALESCE(SUM(CASE WHEN is_success = 1 THEN 1 ELSE 0 END), 0) as successful')
            ->get();

        $summaries = [];

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $successful = (int) $row->successful;

            $summaries[(int) $row->monitor_id] = [
                'total' => $total,
                'successful' => $successful,
                'uptime' => $total === 0 ? null : round($successful / $total * 100, 2),
            ];
        }

        return $summaries;
    }

    /**
     * The full PRD §4.7 set: 24h, 7d, 30d and 90d.
     *
     * @return array<string, array{total: int, successful: int, uptime: float|null}>
     */
    public function allRanges(Monitor $monitor): array
    {
        $ranges = [];

        foreach (['24h', '7d', '30d', '90d'] as $range) {
            $ranges[$range] = $this->summary($monitor, $range);
        }

        return $ranges;
    }

    private function from(string $range): Carbon
    {
        return match ($range) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDay(),
        };
    }
}
