<?php

namespace App\Services;

use App\Jobs\SendAlert;
use App\Models\AlertLog;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Services\Alerts\AlertManager;
use App\Services\Alerts\AlertMessage;
use Throwable;

/**
 * Queues and records alert deliveries (PRD §4.5).
 */
class AlertService
{
    public function __construct(private readonly AlertManager $manager) {}

    /**
     * Queue one alert per linked, active channel on the dedicated `alerts` queue.
     *
     * @return int number of channels queued
     */
    public function queue(Monitor $monitor, string $event, ?Incident $incident = null): int
    {
        $channels = $monitor->channels()
            ->where('status', NotificationChannel::STATUS_ACTIVE)
            ->get();

        foreach ($channels as $channel) {
            SendAlert::dispatch($channel->id, $monitor->id, $incident?->id, $event);
        }

        return $channels->count();
    }

    /**
     * Send synchronously (the "Test alert" button) and record the outcome.
     *
     * @throws Throwable when delivery fails, after logging the failure
     */
    public function sendNow(NotificationChannel $channel, AlertMessage $message): AlertLog
    {
        try {
            $this->manager->driver($channel->type)->send($channel, $message);
        } catch (Throwable $e) {
            $this->record($channel, $message, AlertLog::STATUS_FAILED, $e->getMessage());

            throw $e;
        }

        $channel->forceFill([
            'status' => NotificationChannel::STATUS_ACTIVE,
            'last_used_at' => now(),
        ])->save();

        return $this->record($channel, $message, AlertLog::STATUS_SENT);
    }

    public function record(
        NotificationChannel $channel,
        AlertMessage $message,
        string $status,
        ?string $error = null,
    ): AlertLog {
        return AlertLog::create([
            'incident_id' => $message->incident?->id,
            'monitor_id' => $message->monitor?->id,
            'notification_channel_id' => $channel->id,
            'event' => $message->event,
            'status' => $status,
            'error' => $error === null ? null : mb_substr($error, 0, 255),
            'sent_at' => now(),
        ]);
    }
}
