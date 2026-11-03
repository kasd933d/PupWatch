<?php

namespace App\Services\Alerts;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Telegram Bot API.
 */
class TelegramBot
{
    public function token(): string
    {
        return (string) config('services.telegram.bot_token');
    }

    public function isConfigured(): bool
    {
        return $this->token() !== '';
    }

    /**
     * @throws RuntimeException
     */
    public function sendMessage(string $chatId, string $text): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('The Telegram bot token is not configured.');
        }

        $response = Http::timeout(10)
            ->asJson()
            ->post($this->endpoint('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);

        if ($response->failed() || $response->json('ok') === false) {
            // Telegram's description is safe to surface; the token never is.
            throw new RuntimeException(
                'Telegram rejected the message: '.($response->json('description') ?? 'HTTP '.$response->status())
            );
        }
    }

    private function endpoint(string $method): string
    {
        return 'https://api.telegram.org/bot'.$this->token().'/'.$method;
    }
}
