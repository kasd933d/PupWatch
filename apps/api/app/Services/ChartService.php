<?php

namespace App\Services;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Support\Carbon;

/**
 * Chart data for the monitor detail page (PRD §4.7), downsampled so the
 * browser never receives one point per raw check.
 */
class ChartService
{
    /**
     * @return array{range: string, bucket_seconds: int, points: list<array<string, mixed>>}
     */
    public function series(Monitor $monitor, string $range): array
    {
        [$from, $bucketSeconds] = match ($range) {
            '7d' => [now()->subDays(7), 3600],
            '30d' => [now()->subDays(30), 21600],
            default => [now()->subDay(), 300],
        };

        $rows = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $from)
            ->orderBy('checked_at')
            ->get(['checked_at', 'response_time_ms', 'is_success']);

        /** @var array<int, array{sum: int, count: int, min: int, max: int, checks: int, failures: int}> $buckets */
        $buckets = [];

        foreach ($rows as $row) {
            $timestamp = Carbon::parse($row->checked_at)->getTimestamp();
            $bucketStart = (int) (floor($timestamp / $bucketSeconds) * $bucketSeconds);

            $bucket = $buckets[$bucketStart] ?? [
                'sum' => 0,
                'count' => 0,
                'min' => PHP_INT_MAX,
                'max' => 0,
                'checks' => 0,
                'failures' => 0,
            ];

            $bucket['checks']++;

            if ($row->is_success) {
                $ms = (int) $row->response_time_ms;
                $bucket['sum'] += $ms;
                $bucket['count']++;
                $bucket['min'] = min($bucket['min'], $ms);
                $bucket['max'] = max($bucket['max'], $ms);
            } else {
                $bucket['failures']++;
            }

            $buckets[$bucketStart] = $bucket;
        }

        ksort($buckets);

        $points = [];

        foreach ($buckets as $bucketStart => $bucket) {
            $points[] = [
                't' => Carbon::createFromTimestamp($bucketStart)->toIso8601String(),
                'avg_ms' => $bucket['count'] === 0 ? null : (int) round($bucket['sum'] / $bucket['count']),
                'min_ms' => $bucket['count'] === 0 ? null : $bucket['min'],
                'max_ms' => $bucket['count'] === 0 ? null : $bucket['max'],
                'checks' => $bucket['checks'],
                'failures' => $bucket['failures'],
            ];
        }

        return [
            'range' => $range,
            'bucket_seconds' => $bucketSeconds,
            'points' => $points,
        ];
    }
}
