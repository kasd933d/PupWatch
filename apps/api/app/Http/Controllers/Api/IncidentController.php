<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IncidentController extends Controller
{
    /**
     * GET /incidents?status=&monitor_id= — the signed-in user's incidents.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Incident::query()
            ->with('monitor')
            ->whereHas('monitor', fn ($monitors) => $monitors->where('user_id', $request->user()->id))
            ->orderByDesc('started_at');

        if (in_array($request->query('status'), ['open', 'resolved'], true)) {
            $query->where('status', $request->query('status'));
        }

        if ($monitorId = $request->query('monitor_id')) {
            $query->where('monitor_id', (int) $monitorId);
        }

        return IncidentResource::collection(
            $query->paginate(min((int) $request->input('per_page', 50), 50))
        );
    }

    /**
     * GET /monitors/{id}/incidents — history for one monitor.
     */
    public function forMonitor(Monitor $monitor): AnonymousResourceCollection
    {
        $this->authorize('view', $monitor);

        return IncidentResource::collection(
            $monitor->incidents()->with('monitor')->orderByDesc('started_at')->paginate(50)
        );
    }
}
