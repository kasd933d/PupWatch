<?php

namespace App\Jobs;

use App\Models\AlertLog;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Services\Alerts\AlertManager;
use App\Services\Alerts\AlertMessage;
use App\Services\AlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Delivers one alert to one channel (PRD §4.5).
 *
 * Alert deliveries are retried three times with backoff; a channel whose
 * delivery keeps failing is marked `needs_attention`.
 */
class SendAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $channelId,
        public int $monitorId,
        public ?int $incidentId,
        public string $event,
    ) {
        $this->onQueue('alerts');
    }

    /**
     * @return list<int> seconds between attempts
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(AlertManager $manager, AlertService $alerts): void
    {
        $channel = NotificationChannel::find($this->channelId);
        $monitor = Monitor::find($this->monitorId);

        if (! $channel || ! $monitor) {
            return;
        }

        $message = new AlertMessage(
            $this->event,
            $monitor,
            $this->incidentId === null ? null : Incident::find($this->incidentId),
        );

        $manager->driver($channel->type)->send($channel, $message);

        $channel->forceFill([
            'status' => NotificationChannel::STATUS_ACTIVE,
            'last_used_at' => now(),
        ])->save();

        $alerts->record($channel, $message, AlertLog::STATUS_SENT);
    }

    /**
     * Called by the queue after the final attempt fails.
     */
    public function failed(?Throwable $e): void
    {
        $channel = NotificationChannel::find($this->channelId);
        $monitor = Monitor::find($this->monitorId);

        if (! $channel || ! $monitor) {
            return;
        }

        $message = new AlertMessage(
            $this->event,
            $monitor,
            $this->incidentId === null ? null : Incident::find($this->incidentId),
        );

        app(AlertService::class)->record(
            $channel,
            $message,
            AlertLog::STATUS_FAILED,
            $e?->getMessage() ?? 'Alert delivery failed.',
        );

        $channel->forceFill(['status' => NotificationChannel::STATUS_NEEDS_ATTENTION])->save();
    }
}
