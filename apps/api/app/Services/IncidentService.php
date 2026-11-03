<?php

namespace App\Services;

use App\Models\AlertLog;
use App\Models\Incident;
use App\Models\Monitor;
use App\Services\CheckEngine\CheckResult;

/**
 * The incident state machine (PRD §4.4).
 *
 *  - down after N consecutive failures (N = monitor.alert_after_failures)
 *  - up after a single successful check
 *  - exactly one down alert and one recovery alert per incident
 *  - flapping guard: 4 incidents within an hour suppresses recovery alerts for
 *    an hour and sends a single "unstable" alert instead
 */
class IncidentService
{
    /** PRD §4.4 — incidents inside this window count towards flapping. */
    public const FLAPING_WINDOW_MINUTES = 60;

    /** PRD §4.4 — an hour of suppressed recovery alerts. */
    public const SUPPRESSION_MINUTES = 60;

    public function __construct(private readonly AlertService $alerts) {}

    /**
     * Called once per check, after the monitor row has been updated.
     */
    public function afterCheck(Monitor $monitor, CheckResult $result): void
    {
        if ($result->success) {
            $this->resolveIfOpen($monitor);

            return;
        }

        // Only the check that crosses the threshold opens an incident.
        $justTurnedDown = $monitor->current_status === Monitor::STATUS_DOWN
            && $monitor->consecutive_failures === $monitor->alert_after_failures
            && ! $monitor->incidents()->open()->exists();

        if ($justTurnedDown) {
            $this->open($monitor, $result);
        }
    }

    public function open(Monitor $monitor, CheckResult $result): Incident
    {
        $incident = $monitor->incidents()->create([
            'started_at' => now(),
            'first_error_type' => $result->errorType,
            'first_error_message' => $result->errorMessage,
            'status' => Incident::STATUS_OPEN,
        ]);

        $recentIncidents = $monitor->incidents()
            ->where('started_at', '>=', now()->subMinutes(self::FLAPING_WINDOW_MINUTES))
            ->count();

        if ($recentIncidents >= 4) {
            $alreadySuppressed = $monitor->unstable_until !== null && $monitor->unstable_until->isFuture();

            $monitor->forceFill(['unstable_until' => now()->addMinutes(self::SUPPRESSION_MINUTES)])->save();

            // A single unstable alert per suppression window, nothing else.
            if (! $alreadySuppressed) {
                $this->alerts->queue($monitor, AlertLog::EVENT_UNSTABLE, $incident);
            }

            return $incident;
        }

        $this->alerts->queue($monitor, AlertLog::EVENT_DOWN, $incident);

        return $incident;
    }

    public function resolveIfOpen(Monitor $monitor): ?Incident
    {
        $incident = $monitor->incidents()->open()->latest('started_at')->first();

        if (! $incident) {
            return null;
        }

        $endedAt = now();

        $incident->forceFill([
            'ended_at' => $endedAt,
            'duration_seconds' => (int) $incident->started_at->diffInSeconds($endedAt),
            'status' => Incident::STATUS_RESOLVED,
        ])->save();

        if ($monitor->unstable_until !== null && $monitor->unstable_until->isFuture()) {
            // Flapping guard: recovery alerts are suppressed for this hour.
            return $incident;
        }

        if ($monitor->unstable_until !== null) {
            $monitor->forceFill(['unstable_until' => null])->save();
        }

        $this->alerts->queue($monitor, AlertLog::EVENT_RECOVERED, $incident);

        return $incident;
    }
}
