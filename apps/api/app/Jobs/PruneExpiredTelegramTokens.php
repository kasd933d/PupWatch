<?php

namespace App\Jobs;

use App\Models\TelegramLinkToken;

/**
 * Deletes expired link codes (PRD §8.2, every 10 minutes).
 */
class PruneExpiredTelegramTokens
{
    public function __invoke(): void
    {
        TelegramLinkToken::query()
            ->where('expires_at', '<', now()->subMinutes(30))
            ->delete();
    }
}
