<?php

namespace App\Services;

use App\Models\DailyStat;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the public, cache-friendly payload for a status page (PRD §4.8, §11).
 *
 * Everything a client may see is decided here: hidden response times, hidden
 * error messages and display names never leak through another endpoint.
 */
class StatusPageService
{
    public const BAR_DAYS = 90;

    public const INCIDENT_DAYS = 14;

    /** @return array<string, mixed> */
    public function payload(StatusPage $page): array
    {
        $page->load('monitors');

        $monitors = $page->monitors;
        $ids = $monitors->pluck('id')->all();
        $bars = $this->barsByMonitor($ids);

        $monitorPayload = $monitors->map(function (Monitor $monitor) use ($page, $bars) {
            $series = $bars[$monitor->id] ?? [];
            $totals = ['total' => 0, 'successful' => 0];

            foreach ($series as $bar) {
                $totals['total'] += $bar['total'];
                $totals['successful'] += $bar['successful'];
            }

            return [
                'id' => $monitor->id,
                'name' => $monitor->pivot->display_name ?: $monitor->name,
                'status' => $monitor->is_paused ? Monitor::STATUS_PAUSED : $monitor->current_status,
                'is_paused' => $monitor->is_paused,
                'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
                // PRD §4.8 — response times are hidden unless the owner opts in.
                'last_response_ms' => $page->show_response_times ? $monitor->last_response_ms : null,
                'uptime_90d' => [
                    'total' => $totals['total'],
                    'successful' => $totals['successful'],
                    'uptime' => $totals['total'] === 0
                        ? null
                        : round($totals['successful'] / $totals['total'] * 100, 2),
                ],
                'bars' => $series,
            ];
        })->values()->all();

        return [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'description' => $page->description,
                'show_response_times' => $page->show_response_times,
                'show_error_details' => $page->show_error_details,
                'is_indexable' => $page->is_indexable,
                'accent_color' => $page->accent_color,
                'logo_url' => $page->logo_url,
            ],
            'overall' => $this->overall($monitors),
            'monitors' => $monitorPayload,
            'incidents' => $this->incidents($page, $ids),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @param Collection<int, Monitor> $monitors */
    private function overall(Collection $monitors): string
    {
        if ($monitors->isEmpty()) {
            return 'unknown';
        }

        $active = $monitors->filter(fn (Monitor $monitor) => ! $monitor->is_paused);

        if ($active->isEmpty()) {
            return 'operational';
        }

        $down = $active->filter(
            fn (Monitor $monitor) => $monitor->current_status === Monitor::STATUS_DOWN
        )->count();

        if ($down === 0) {
            return 'operational';
        }

        return $down === $active->count() ? 'major_outage' : 'partial_outage';
    }

    /**
     * One bar per day for the last 90 days, oldest first, using daily_stats for
     * completed days and live checks for today.
     *
     * @param  list<int>  $monitorIds
     * @return array<int, list<array{date: string, total: int, successful: int, uptime: float|null}>>
     */
    private function barsByMonitor(array $monitorIds): array
    {
        if ($monitorIds === []) {
            return [];
        }

        $today = now()->startOfDay();
        $start = $today->copy()->subDays(self::BAR_DAYS - 1);

        $rolledUp = DailyStat::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('date', '>=', $start->toDateString())
            ->get(['monitor_id', 'date', 'total_checks', 'successful_checks'])
            ->groupBy('monitor_id')
            ->map(fn (Collection $rows) => $rows->keyBy(fn (DailyStat $row) => (string) $row->date));

        $live = DB::table('check_results')
            ->whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', $today)
            ->groupBy('monitor_id')
            ->selectRaw('monitor_id, COUNT(*) as total, COALESCE(SUM(CASE WHEN is_success = 1 THEN 1 ELSE 0 END), 0) as successful')
            ->get()
            ->keyBy('monitor_id');

        $bars = [];

        foreach ($monitorIds as $id) {
            $series = [];

            for ($offset = 0; $offset < self::BAR_DAYS; $offset++) {
                $date = $start->copy()->addDays($offset);
                $key = $date->toDateString();

                if ($date->equalTo($today)) {
                    $row = $live->get($id);
                    $total = (int) ($row->total ?? 0);
                    $successful = (int) ($row->successful ?? 0);
                } else {
                    $row = $rolledUp->get($id)?->get($key);
                    $total = (int) ($row->total_checks ?? 0);
                    $successful = (int) ($row->successful_checks ?? 0);
                }

                $series[] = [
                    'date' => $key,
                    'total' => $total,
                    'successful' => $successful,
                    'uptime' => $total === 0 ? null : round($successful / $total * 100, 2),
                ];
            }

            $bars[$id] = $series;
        }

        return $bars;
    }

    /**
     * Incidents from the last 14 days touching this page's monitors.
     *
     * @param  list<int>  $monitorIds
     * @return list<array<string, mixed>>
     */
    private function incidents(StatusPage $page, array $monitorIds): array
    {
        if ($monitorIds === []) {
            return [];
        }

        $since = now()->subDays(self::INCIDENT_DAYS);

        $incidents = Incident::query()
            ->with('monitor')
            ->whereIn('monitor_id', $monitorIds)
            ->where(function ($query) use ($since) {
                $query->where('started_at', '>=', $since)
                    ->orWhereNull('ended_at');
            })
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        $names = $page->monitors->mapWithKeys(
            fn (Monitor $monitor) => [$monitor->id => $monitor->pivot->display_name ?: $monitor->name]
        );

        return $incidents->map(fn (Incident $incident) => [
            'id' => $incident->id,
            'monitor_id' => $incident->monitor_id,
            'monitor_name' => $names[$incident->monitor_id] ?? $incident->monitor?->name,
            'started_at' => $incident->started_at?->toIso8601String(),
            'ended_at' => $incident->ended_at?->toIso8601String(),
            'duration_seconds' => $incident->duration_seconds,
            'status' => $incident->status,
            // PRD §4.8 — error details only when the owner explicitly shares them.
            'error_type' => $page->show_error_details ? $incident->first_error_type : null,
            'error_message' => $page->show_error_details ? $incident->first_error_message : null,
        ])->values()->all();
    }

    /** Cache key used by the public endpoint. */
    public static function cacheKey(string $slug): string
    {
        return 'status-page:'.$slug;
    }

    /** Drop the cached payload after the owner changes the page. */
    public static function forget(StatusPage $page): void
    {
        Cache::forget(self::cacheKey($page->slug));
    }

    /** Carbon helper kept public for tests that need the same window. */
    public function barStart(): Carbon
    {
        return now()->startOfDay()->subDays(self::BAR_DAYS - 1);
    }
}
