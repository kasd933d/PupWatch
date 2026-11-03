<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLinkToken extends Model
{
    /** PRD §4.5 — a link code is valid for 15 minutes. */
    public const LIFETIME_MINUTES = 15;

    /** @var list<string> */
    protected $fillable = ['user_id', 'token', 'expires_at', 'used_at'];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
