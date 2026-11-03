<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckResult extends Model
{
    /** High-volume time-series table: no updated_at, no remember state. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'monitor_id',
        'checked_at',
        'status_code',
        'response_time_ms',
        'is_success',
        'error_type',
        'error_message',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'checked_at' => 'datetime',
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'is_success' => 'boolean',
    ];

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
