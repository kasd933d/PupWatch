<?php

namespace App\Services\Alerts;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Discord webhook delivery (PRD §4.5). Alerts are embeds coloured by event.
 */
class DiscordChannel implements AlertChannel
{
    /**
     * PRD §4.5 — a Discord webhook URL, nothing else.
     */
    public const WEBHOOK_PATTERN = '#^https://discord\.com/api/webhooks/[0-9]+/[A-Za-z0-9_\-]+$#';

    public static function isValidWebhook(string $url): bool
    {
        return preg_match(self::WEBHOOK_PATTERN, $url) === 1;
    }

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $webhook = $channel->destination('webhook_url');

        if ($webhook === null || ! self::isValidWebhook($webhook)) {
            throw new RuntimeException('This Discord channel has an invalid webhook URL.');
        }

        $payload = [
            'username' => 'PupWatch',
            'embeds' => [[
                'title' => $message->title(),
                'description' => $message->body(),
                'url' => $message->url(),
                'color' => $message->color(),
                'timestamp' => now()->toIso8601String(),
                'footer' => ['text' => 'PupWatch · uptime monitoring'],
            ]],
        ];

        $response = Http::timeout(10)->asJson()->post($webhook, $payload);

        if ($response->failed()) {
            // Never include the webhook URL (PRD §5 — never log secrets).
            throw new RuntimeException('Discord rejected the alert (HTTP '.$response->status().').');
        }
    }
}
