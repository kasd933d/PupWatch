<?php

namespace App\Services\Alerts;

use App\Models\NotificationChannel;
use RuntimeException;

/**
 * Telegram delivery through the Bot API (PRD §4.5).
 */
class TelegramChannel implements AlertChannel
{
    public function __construct(private readonly TelegramBot $bot) {}

    public function send(NotificationChannel $channel, AlertMessage $message): void
    {
        $chatId = $channel->destination('chat_id');

        if ($chatId === null) {
            throw new RuntimeException('This Telegram channel is not linked to a chat.');
        }

        $this->bot->sendMessage($chatId, $message->body());
    }
}
