<?php

namespace App\Http\Resources;

use App\Models\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationChannel
 */
class NotificationChannelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'status' => $this->status,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'monitor_ids' => $this->whenLoaded('monitors', fn () => $this->monitors->pluck('id')->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
