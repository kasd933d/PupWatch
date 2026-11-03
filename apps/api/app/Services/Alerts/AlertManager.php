<?php

namespace App\Services\Alerts;

use App\Models\NotificationChannel;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class AlertManager
{
    public function __construct(private readonly Container $container) {}

    /**
     * @throws RuntimeException for a channel type PupWatch cannot deliver to
     */
    public function driver(string $type): AlertChannel
    {
        return match ($type) {
            NotificationChannel::TYPE_DISCORD => $this->container->make(DiscordChannel::class),
            NotificationChannel::TYPE_TELEGRAM => $this->container->make(TelegramChannel::class),
            default => throw new RuntimeException("Unsupported channel type [{$type}]."),
        };
    }
}
