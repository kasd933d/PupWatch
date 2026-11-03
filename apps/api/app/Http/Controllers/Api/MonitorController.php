<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\LinkChannelsRequest;
use App\Http\Requests\Monitor\StoreMonitorRequest;
use App\Http\Requests\Monitor\UpdateMonitorRequest;
use App\Http\Resources\MonitorResource;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Services\ChartService;
use App\Services\MonitorScheduler;
use App\Services\UptimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class MonitorController extends Controller
{
    public function __construct(private readonly UptimeService $uptime)
    {
        $this->authorizeResource(Monitor::class, 'monitor');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $monitors = $request->user()
            ->monitors()
            ->orderBy('created_at')
            ->paginate(min((int) $request->input('per_page', 50), 50));

        $summaries = $this->uptime->summariesFor($monitors->pluck('id')->all(), '24h');

        $monitors->getCollection()->each(function (Monitor $monitor) use ($summaries) {
            $summary = $summaries[$monitor->id] ?? null;
            $monitor->uptime_summary = $summary;
        });

        return MonitorResource::collection($monitors);
    }

    public function store(StoreMonitorRequest $request): JsonResponse
    {
        $monitor = $request->user()->monitors()->create(
            array_merge($request->validated(), [
                'host' => parse_url($request->validated('url'), PHP_URL_HOST),
                'current_status' => Monitor::STATUS_PENDING,
                'consecutive_failures' => 0,
                // First check within 60s (PRD §4.2).
                'next_check_at' => now()->addSeconds(random_int(0, 30)),
            ])
        );

        return response()->json(['monitor' => new MonitorResource($monitor->refresh())], 201);
    }

    public function show(Monitor $monitor): JsonResponse
    {
        $monitor->uptime_summary = $this->uptime->summary($monitor, '24h');
        $monitor->uptime_ranges = $this->uptime->allRanges($monitor);

        return response()->json(['monitor' => new MonitorResource($monitor)]);
    }

    public function update(UpdateMonitorRequest $request, Monitor $monitor): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('url', $data)) {
            $data['host'] = parse_url($data['url'], PHP_URL_HOST);
        }

        $monitor->fill($data)->save();

        return response()->json(['monitor' => new MonitorResource($monitor->refresh())]);
    }

    public function destroy(Monitor $monitor): JsonResponse
    {
        $monitor->delete();

        return response()->json(['message' => 'Monitor deleted.']);
    }

    public function pause(Monitor $monitor): JsonResponse
    {
        MonitorScheduler::pause($monitor);

        return response()->json(['monitor' => new MonitorResource($monitor->refresh())]);
    }

    public function resume(Monitor $monitor): JsonResponse
    {
        MonitorScheduler::resume($monitor);

        return response()->json(['monitor' => new MonitorResource($monitor->refresh())]);
    }

    /**
     * POST /monitors/{id}/check — "Check now", throttled to once per 30s (PRD §4.7).
     * Marks the monitor due; the dispatcher picks it up on its next run.
     */
    public function checkNow(Request $request, Monitor $monitor): JsonResponse
    {
        $key = 'monitor:check-throttle:'.$monitor->id;
        $last = Cache::get($key);

        if ($last) {
            $wait = max(0, 30 - (now()->timestamp - (int) $last));

            return response()->json([
                'message' => "Already checked. Try again in {$wait} seconds.",
            ], 429);
        }

        Cache::put($key, now()->timestamp, 60);

        MonitorScheduler::due($monitor);

        return response()->json(['monitor' => new MonitorResource($monitor->refresh())], 202);
    }

    /**
     * PUT /monitors/{id}/channels — set which alert channels receive this monitor.
     */
    public function syncChannels(LinkChannelsRequest $request, Monitor $monitor): JsonResponse
    {
        $monitor->channels()->sync($request->input('channel_ids', []));

        return response()->json([
            'monitor' => new MonitorResource($monitor->load('channels')),
        ]);
    }

    /**
     * GET /monitors/{id}/results?range=24h|7d|30d — downsampled chart data.
     */
    public function results(Request $request, Monitor $monitor, ChartService $charts): JsonResponse
    {
        $range = (string) $request->query('range', '24h');

        abort_unless(in_array($range, ['24h', '7d', '30d'], true), 422, 'Range must be 24h, 7d or 30d.');

        return response()->json($charts->series($monitor, $range));
    }

    /**
     * GET /monitors/{id}/checks — last 50 raw checks.
     */
    public function checks(Monitor $monitor): JsonResponse
    {
        $checks = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->orderByDesc('checked_at')
            ->limit(50)
            ->get(['id', 'checked_at', 'status_code', 'response_time_ms', 'is_success', 'error_type', 'error_message']);

        return response()->json(['data' => $checks]);
    }
}
