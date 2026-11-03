<?php

namespace App\Models;

use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory;

    public const TYPE_TELEGRAM = 'telegram';

    public const TYPE_DISCORD = 'discord';

    public const TYPE_EMAIL = 'email';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_NEEDS_ATTENTION = 'needs_attention';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'name',
        'config',
        'status',
        'last_used_at',
    ];

    /** @var list<string> */
    protected $hidden = ['config'];

    /** @var array<string, string> */
    protected $casts = [
        // PRD §5 — chat ids and webhook URLs are encrypted at rest.
        'config' => 'encrypted:array',
        'last_used_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsToMany<Monitor, $this> */
    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'monitor_channel')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * A single destination value, never logged and never exposed.
     */
    public function destination(string $key): ?string
    {
        $config = $this->config ?? [];

        return isset($config[$key]) ? (string) $config[$key] : null;
    }
}
