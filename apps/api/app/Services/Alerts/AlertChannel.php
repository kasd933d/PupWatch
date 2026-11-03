<?php

namespace App\Services\Alerts;

use App\Models\NotificationChannel;

/**
 * A delivery destination. Implementations throw on failure; the SendAlert job
 * owns retries and logging.
 */
interface AlertChannel
{
    /**
     * @throws \RuntimeException when the destination is missing or rejects the message
     */
    public function send(NotificationChannel $channel, AlertMessage $message): void;
}
