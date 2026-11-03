<?php

namespace App\Jobs;

use App\Models\CheckResult as CheckResultModel;
use App\Models\Monitor;
use App\Services\CheckEngine\CheckResult;
use App\Services\CheckService;
use App\Services\IncidentService;
use App\Services\MonitorScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One execution of one monitor (PRD §8.1).
 *
 * `tries = 1`: a failed check is data, not a transient error worth retrying.
 * The claim is released by scheduling the next run, so a crashed worker is
 * recovered by RecoverStuckClaims rather than retried here.
 */
class RunMonitorCheck implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Slightly above the maximum check timeout (30s) per PRD §8.1. */
    public int $timeout = 45;

    public function __construct(public int $monitorId)
    {
        $this->onQueue('checks');
    }

    public function handle(CheckService $checks, IncidentService $incidents): void
    {
        $monitor = Monitor::find($this->monitorId);

        if (! $monitor) {
            return;
        }

        if ($monitor->is_paused) {
            MonitorScheduler::releaseClaim($monitor);

            return;
        }

        $result = $checks->run($monitor);

        CheckResultModel::create([
            'monitor_id' => $monitor->id,
            'checked_at' => now(),
            'status_code' => $result->statusCode,
            'response_time_ms' => $result->responseTimeMs,
            'is_success' => $result->success,
            'error_type' => $result->errorType,
            'error_message' => $result->errorMessage,
        ]);

        $this->applyResult($monitor, $result);

        // PRD §4.4 — the state machine runs after the monitor row is current.
        $incidents->afterCheck($monitor, $result);
    }

    private function applyResult(Monitor $monitor, CheckResult $result): void
    {
        $monitor->last_checked_at = now();
        $monitor->last_response_ms = $result->responseTimeMs;

        if ($result->success) {
            $monitor->consecutive_failures = 0;
            $monitor->current_status = Monitor::STATUS_UP;
        } else {
            $monitor->consecutive_failures = $monitor->consecutive_failures + 1;

            if ($monitor->consecutive_failures >= $monitor->alert_after_failures) {
                $monitor->current_status = Monitor::STATUS_DOWN;
            }
        }

        $monitor->save();

        MonitorScheduler::scheduleNext($monitor);
    }
}
