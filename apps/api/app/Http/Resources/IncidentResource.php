<?php

namespace App\Http\Resources;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Incident
 */
class IncidentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'monitor_id' => $this->monitor_id,
            'monitor_name' => $this->whenLoaded('monitor', fn () => $this->monitor?->name),
            'monitor_host' => $this->whenLoaded('monitor', fn () => $this->monitor?->host),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'first_error_type' => $this->first_error_type,
            'first_error_message' => $this->first_error_message,
            'status' => $this->status,
        ];
    }
}
