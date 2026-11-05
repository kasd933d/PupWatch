<?php

namespace App\Http\Resources;

use App\Models\Monitor;
use App\Models\StatusPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StatusPage
 */
class StatusPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'show_response_times' => (bool) $this->show_response_times,
            'show_error_details' => (bool) $this->show_error_details,
            'is_indexable' => (bool) $this->is_indexable,
            'accent_color' => $this->accent_color,
            'logo_url' => $this->logo_url,
            'public_path' => '/status/'.$this->slug,
            'monitors' => $this->whenLoaded('monitors', fn () => $this->monitors->map(fn (Monitor $monitor) => [
                'id' => $monitor->id,
                'name' => $monitor->pivot->display_name ?: $monitor->name,
                'display_name' => $monitor->pivot->display_name,
                'sort_order' => (int) $monitor->pivot->sort_order,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
