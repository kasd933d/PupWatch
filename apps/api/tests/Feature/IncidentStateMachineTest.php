<?php

namespace Tests\Feature;

use App\Jobs\RunMonitorCheck;
use App\Jobs\SendAlert;
use App\Models\AlertLog;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Services\CheckService;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IncidentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function check(Monitor $monitor): void
    {
        (new RunMonitorCheck($monitor->id))->handle(
            $this->app->make(CheckService::class),
            $this->app->make(IncidentService::class),
        );
    }

    private function monitored(User $user, array $overrides = []): Monitor
    {
        $monitor = Monitor::factory()->create(array_merge([
            'user_id' => $user->id,
            'url' => 'https://example.com/',
            'alert_after_failures' => 2,
        ], $overrides));

        $channel = NotificationChannel::factory()->create(['user_id' => $user->id]);
        $monitor->channels()->attach($channel);

        return $monitor;
    }

    public function test_one_failure_with_threshold_two_opens_no_incident_and_sends_nothing(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]);

        $monitor = $this->monitored(User::factory()->create());

        $this->check($monitor);

        $this->assertDatabaseCount('incidents', 0);
        Queue::assertNotPushed(SendAlert::class);
    }

    public function test_crossing_the_threshold_opens_exactly_one_incident_and_one_down_alert(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]);

        $monitor = $this->monitored(User::factory()->create());

        $this->check($monitor);
        $this->check($monitor->refresh());

        $this->assertDatabaseCount('incidents', 1);

        $incident = Incident::first();
        $this->assertSame(Incident::STATUS_OPEN, $incident->status);
        $this->assertNotNull($incident->started_at);

        Queue::assertPushed(SendAlert::class, 1);
        Queue::assertPushed(SendAlert::class, fn (SendAlert $job) => $job->event === AlertLog::EVENT_DOWN);
    }

    public function test_further_failures_do_not_open_more_incidents_or_send_more_down_alerts(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]);

        $monitor = $this->monitored(User::factory()->create());

        $this->check($monitor);
        $this->check($monitor->refresh());
        $this->check($monitor->refresh());
        $this->check($monitor->refresh());

        $this->assertDatabaseCount('incidents', 1);
        Queue::assertPushed(SendAlert::class, 1);
    }

    public function test_a_success_resolves_the_incident_and_sends_one_recovery_alert(): void
    {
        Queue::fake();
        Http::fakeSequence()
            ->push('', 500)
            ->push('', 500)
            ->push('ok', 200);

        $monitor = $this->monitored(User::factory()->create());

        $this->check($monitor);
        $this->check($monitor->refresh());
        $this->check($monitor->refresh());

        $incident = Incident::first();
        $this->assertSame(Incident::STATUS_RESOLVED, $incident->status);
        $this->assertNotNull($incident->ended_at);
        $this->assertNotNull($incident->duration_seconds);

        Queue::assertPushed(SendAlert::class, 2);
        Queue::assertPushed(SendAlert::class, fn (SendAlert $job) => $job->event === AlertLog::EVENT_RECOVERED);
    }

    public function test_a_monitor_without_channels_records_the_incident_without_queueing_alerts(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]);

        $monitor = Monitor::factory()->create([
            'user_id' => User::factory()->create()->id,
            'url' => 'https://example.com/',
            'alert_after_failures' => 1,
        ]);

        $this->check($monitor);

        $this->assertDatabaseCount('incidents', 1);
        Queue::assertNothingPushed();
    }

    public function test_the_flapping_guard_sends_a_single_unstable_alert_and_suppresses_recovery(): void
    {
        Queue::fake();
        Http::fakeSequence()
            ->push('', 500)   // opens incident #4
            ->push('ok', 200); // recovery, must be suppressed

        $user = User::factory()->create();
        $monitor = $this->monitored($user, ['alert_after_failures' => 1]);

        // Three incidents already resolved inside the last hour.
        foreach (range(1, 3) as $i) {
            $monitor->incidents()->create([
                'started_at' => now()->subMinutes(30 - $i),
                'ended_at' => now()->subMinutes(29 - $i),
                'duration_seconds' => 60,
                'first_error_type' => 'timeout',
                'status' => Incident::STATUS_RESOLVED,
            ]);
        }

        $this->check($monitor);
        $this->assertDatabaseCount('incidents', 4);
        Queue::assertPushed(SendAlert::class, 1);
        Queue::assertPushed(SendAlert::class, fn (SendAlert $job) => $job->event === AlertLog::EVENT_UNSTABLE);

        $this->check($monitor->refresh());

        $this->assertSame(Incident::STATUS_RESOLVED, Incident::latest('id')->first()->status);
        // Still only the single unstable alert; no recovery alert was queued.
        Queue::assertPushed(SendAlert::class, 1);
        $this->assertNotNull($monitor->refresh()->unstable_until);
    }

    public function test_a_fifth_incident_in_the_window_sends_no_further_alerts(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]);

        $user = User::factory()->create();
        $monitor = $this->monitored($user, ['alert_after_failures' => 1]);

        foreach (range(1, 3) as $i) {
            $monitor->incidents()->create([
                'started_at' => now()->subMinutes(30 - $i),
                'status' => Incident::STATUS_RESOLVED,
            ]);
        }

        $this->check($monitor); // 4th: unstable alert
        $monitor->refresh();
        $monitor->forceFill(['unstable_until' => now()->addHour()])->save();

        // A fresh incident while already suppressed must stay silent.
        $monitor->incidents()->create(['started_at' => now()->subMinute(), 'status' => Incident::STATUS_RESOLVED]);
        $this->check($monitor->refresh());

        Queue::assertPushed(SendAlert::class, 1);
    }
}
