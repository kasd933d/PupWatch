<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertLog extends Model
{
    /** The delivery time is `sent_at`; there is no separate update timestamp. */
    public $timestamps = false;

    public const EVENT_DOWN = 'down';

    public const EVENT_RECOVERED = 'recovered';

    public const EVENT_UNSTABLE = 'unstable';

    public const EVENT_TEST = 'test';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'incident_id',
        'monitor_id',
        'notification_channel_id',
        'event',
        'status',
        'error',
        'sent_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /** @return BelongsTo<NotificationChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'notification_channel_id');
    }

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
