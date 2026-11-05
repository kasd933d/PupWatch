<?php

namespace App\Services;

use App\Models\CheckResult;
use App\Models\DailyStat;
use App\Models\Monitor;
use Illuminate\Support\Carbon;

/**
 * Uptime percentages (PRD §11).
 *
 * 24h/7d/30d read raw check_results, which are retained for 30 days. The 90d
 * view cannot: it sums daily_stats for completed days and adds today's live
 * checks, since today has no rollup row yet (PRD §4.9, §11).
 */
class UptimeService
{
    /**
     * @return array{total: int, successful: int, uptime: float|null}
     */
    public function summary(Monitor $monitor, string $range = '24h'): array
    {
        if ($range === '90d') {
            return $this->fromDailyStats($monitor, 90);
        }

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

    /**
     * Sum rolled-up full days plus live checks from today.
     *
     * @return array{total: int, successful: int, uptime: float|null}
     */
    public function fromDailyStats(Monitor $monitor, int $days): array
    {
        $start = now()->subDays($days)->startOfDay();
        $today = now()->startOfDay();
        $lastFullDay = $today->copy()->subDay();

        $rolledUp = DailyStat::query()
            ->where('monitor_id', $monitor->id)
            ->whereBetween('date', [$start->toDateString(), $lastFullDay->toDateString()])
            ->selectRaw('COALESCE(SUM(total_checks), 0) as total, COALESCE(SUM(successful_checks), 0) as successful')
            ->first();

        $live = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $today)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(CASE WHEN is_success = 1 THEN 1 ELSE 0 END), 0) as successful')
            ->first();

        $total = (int) ($rolledUp->total ?? 0) + (int) ($live->total ?? 0);
        $successful = (int) ($rolledUp->successful ?? 0) + (int) ($live->successful ?? 0);

        return [
            'total' => $total,
            'successful' => $successful,
            'uptime' => $total === 0 ? null : round($successful / $total * 100, 2),
        ];
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
