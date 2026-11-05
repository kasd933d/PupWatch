<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rolled-up day of checks for a monitor (PRD §4.9, §9).
 *
 * Raw checks are pruned after 30 days, so this table is the only source for
 * the 90-day uptime bars and the 90-day uptime percentage.
 */
class DailyStat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'monitor_id',
        'date',
        'total_checks',
        'successful_checks',
        'avg_response_ms',
        'min_response_ms',
        'max_response_ms',
        'p95_response_ms',
        'downtime_seconds',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_checks' => 'integer',
            'successful_checks' => 'integer',
            'downtime_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
