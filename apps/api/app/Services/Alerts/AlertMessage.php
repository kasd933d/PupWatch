<?php

namespace App\Services\Alerts;

use App\Models\AlertLog;
use App\Models\Incident;
use App\Models\Monitor;

/**
 * Everything a channel needs to render one alert (PRD §4.5).
 */
final readonly class AlertMessage
{
    public function __construct(
        public string $event,
        public ?Monitor $monitor = null,
        public ?Incident $incident = null,
    ) {}

    public static function test(): self
    {
        return new self(AlertLog::EVENT_TEST);
    }

    public function title(): string
    {
        $host = $this->monitor?->host ?? 'PupWatch';

        return match ($this->event) {
            AlertLog::EVENT_DOWN => "🐶 PupWatch: {$host} is DOWN",
            AlertLog::EVENT_RECOVERED => "🐶 PupWatch: {$host} is back UP",
            AlertLog::EVENT_UNSTABLE => "🐶 PupWatch: {$host} is unstable",
            default => '🐶 PupWatch: test alert',
        };
    }

    /**
     * Plain-text body, used for Telegram and as the Discord description.
     */
    public function body(): string
    {
        if ($this->event === AlertLog::EVENT_TEST || $this->monitor === null) {
            return "This is a test alert from PupWatch.\nIf you can read this, the channel works.";
        }

        $monitor = $this->monitor;
        $since = $this->incident?->started_at?->copy()->utc()->format('H:i').' UTC';

        $lines = match ($this->event) {
            AlertLog::EVENT_DOWN => array_filter([
                'Reason: '.($this->incident?->first_error_message ?? 'the check failed'),
                'Since: '.$since.' · Failed checks: '.$monitor->consecutive_failures,
            ]),
            AlertLog::EVENT_RECOVERED => array_filter([
                'Downtime: '.$this->humanDuration($this->incident?->duration_seconds),
                'Recovered: '.now()->utc()->format('H:i').' UTC',
            ]),
            AlertLog::EVENT_UNSTABLE => [
                'It has opened several incidents in the last hour.',
                'Recovery alerts are paused for the next hour.',
            ],
            default => [],
        };

        return implode("\n", array_merge([$this->title()], $lines, [$this->url()]));
    }

    public function url(): string
    {
        $base = rtrim((string) config('pupwatch.app_url'), '/');

        if ($this->monitor === null) {
            return $base.'/channels';
        }

        return "{$base}/monitors/{$this->monitor->id}";
    }

    /**
     * Discord embed colour (PRD §4.5: red down, green recovery, yellow unstable).
     */
    public function color(): int
    {
        return match ($this->event) {
            AlertLog::EVENT_DOWN => 0xE74C3C,
            AlertLog::EVENT_RECOVERED => 0x2ECC71,
            AlertLog::EVENT_UNSTABLE => 0xF1C40F,
            default => 0x3498DB,
        };
    }

    public function emoji(): string
    {
        return match ($this->event) {
            AlertLog::EVENT_DOWN => '🔴',
            AlertLog::EVENT_RECOVERED => '🟢',
            AlertLog::EVENT_UNSTABLE => '🟡',
            default => '🔵',
        };
    }

    private function humanDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'unknown';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60).'m '.($seconds % 60).'s';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return $hours.'h '.$minutes.'m';
    }
}
