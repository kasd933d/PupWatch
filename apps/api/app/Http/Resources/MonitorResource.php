<?php

namespace App\Http\Resources;

use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Monitor
 */
class MonitorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'host' => $this->host,
            'method' => $this->method,
            'interval_seconds' => $this->interval_seconds,
            'timeout_seconds' => $this->timeout_seconds,
            'expected_status' => $this->expected_status,
            'keyword' => $this->keyword,
            'keyword_mode' => $this->keyword_mode,
            'follow_redirects' => $this->follow_redirects,
            'custom_headers' => $this->custom_headers === null
                ? null
                : array_fill_keys(array_keys($this->custom_headers), '•••'),
            'custom_header_keys' => $this->custom_headers === null ? [] : array_keys($this->custom_headers),
            'alert_after_failures' => $this->alert_after_failures,
            'is_paused' => $this->is_paused,
            'current_status' => $this->current_status,
            'consecutive_failures' => $this->consecutive_failures,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_response_ms' => $this->last_response_ms,
            'next_check_at' => $this->next_check_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
