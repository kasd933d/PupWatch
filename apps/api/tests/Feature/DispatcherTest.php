<?php

namespace Tests\Feature;

use App\Jobs\DispatchDueChecks;
use App\Jobs\RecoverStuckClaims;
use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_due_monitor_is_claimed_and_queued_once(): void
    {
        Queue::fake();

        $monitor = Monitor::factory()->create(['next_check_at' => now()->subMinute()]);

        $dispatched = (new DispatchDueChecks)->dispatchDue(now());

        $this->assertSame(1, $dispatched);
        Queue::assertPushed(RunMonitorCheck::class, 1);

        $this->assertNotNull($monitor->refresh()->claimed_at);
        $this->assertTrue($monitor->next_check_at->isFuture());
    }

    public function test_a_second_dispatcher_racing_the_same_slot_queues_nothing(): void
    {
        Queue::fake();

        $monitor = Monitor::factory()->create(['next_check_at' => now()->subMinute()]);
        $dispatch = new DispatchDueChecks;

        // Two dispatchers run over the same due row; only one may claim it.
        $first = $dispatch->dispatchDue(now());
        $second = $dispatch->dispatchDue(now());

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        Queue::assertPushed(RunMonitorCheck::class, 1);
        $this->assertSame(1, Monitor::whereKey($monitor->id)->whereNotNull('claimed_at')->count());
    }

    public function test_paused_monitors_are_never_dispatched(): void
    {
        Queue::fake();

        Monitor::factory()->create([
            'is_paused' => true,
            'next_check_at' => now()->subMinute(),
        ]);

        $this->assertSame(0, (new DispatchDueChecks)->dispatchDue(now()));
        Queue::assertNothingPushed();
    }

    public function test_monitors_scheduled_in_the_future_are_not_dispatched_early(): void
    {
        Queue::fake();

        Monitor::factory()->create(['next_check_at' => now()->addMinutes(4)]);

        $this->assertSame(0, (new DispatchDueChecks)->dispatchDue(now()));
        Queue::assertNothingPushed();
    }

    public function test_the_claim_is_released_after_a_check_completes(): void
    {
        Queue::fake();

        $monitor = Monitor::factory()->create(['next_check_at' => now()->subMinute()]);
        (new DispatchDueChecks)->dispatchDue(now());

        $this->assertNotNull($monitor->refresh()->claimed_at);
    }

    public function test_stuck_claims_are_released_and_made_due_again(): void
    {
        $monitor = Monitor::factory()->create([
            'claimed_at' => now()->subMinutes(10),
            'next_check_at' => now()->addMinutes(5),
        ]);

        (new RecoverStuckClaims)();

        $monitor->refresh();
        $this->assertNull($monitor->claimed_at);
        $this->assertTrue($monitor->next_check_at->lessThanOrEqualTo(now()));
    }

    public function test_fresh_claims_are_left_alone(): void
    {
        $monitor = Monitor::factory()->create([
            'claimed_at' => now()->subMinute(),
            'next_check_at' => now()->addMinutes(4),
        ]);

        (new RecoverStuckClaims)();

        $this->assertNotNull($monitor->refresh()->claimed_at);
    }

    public function test_the_dispatcher_only_touches_the_owners_monitors(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Monitor::factory()->create(['user_id' => $user->id, 'next_check_at' => now()->subMinute()]);
        Monitor::factory()->create(['next_check_at' => now()->subMinute()]);

        $this->assertSame(2, (new DispatchDueChecks)->dispatchDue(now()));
    }
}
