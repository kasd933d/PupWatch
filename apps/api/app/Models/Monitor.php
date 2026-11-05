<?php

namespace App\Models;

use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UP = 'up';

    public const STATUS_DOWN = 'down';

    public const STATUS_PAUSED = 'paused';

    public const KEYWORD_MUST_CONTAIN = 'must_contain';

    public const KEYWORD_MUST_NOT_CONTAIN = 'must_not_contain';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'name',
        'url',
        'host',
        'method',
        'interval_seconds',
        'timeout_seconds',
        'expected_status',
        'keyword',
        'keyword_mode',
        'follow_redirects',
        'custom_headers',
        'alert_after_failures',
        'is_paused',
        'current_status',
        'consecutive_failures',
        'last_checked_at',
        'last_response_ms',
        'next_check_at',
        'claimed_at',
        'unstable_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'custom_headers' => 'encrypted:array',
        'follow_redirects' => 'boolean',
        'is_paused' => 'boolean',
        'interval_seconds' => 'integer',
        'timeout_seconds' => 'integer',
        'alert_after_failures' => 'integer',
        'consecutive_failures' => 'integer',
        'last_response_ms' => 'integer',
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'claimed_at' => 'datetime',
        'unstable_until' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<CheckResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(CheckResult::class);
    }

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /** @return HasMany<DailyStat, $this> */
    public function dailyStats(): HasMany
    {
        return $this->hasMany(DailyStat::class);
    }

    /** @return BelongsToMany<NotificationChannel, $this> */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(NotificationChannel::class, 'monitor_channel')->withTimestamps();
    }

    /**
     * Accepted status codes as a list of [min, max] inclusive ranges.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function expectedStatusRanges(): array
    {
        $ranges = [];

        foreach (preg_split('/\s*,\s*/', (string) $this->expected_status, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (str_contains($part, '-')) {
                [$low, $high] = array_map('intval', explode('-', $part, 2));
            } else {
                $low = $high = (int) $part;
            }

            $ranges[] = [$low, $high];
        }

        return $ranges;
    }
}
