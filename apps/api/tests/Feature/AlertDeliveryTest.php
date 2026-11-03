<?php

namespace Tests\Feature;

use App\Jobs\SendAlert;
use App\Models\AlertLog;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Services\Alerts\AlertManager;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AlertDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function deliver(SendAlert $job): void
    {
        $job->handle($this->app->make(AlertManager::class), $this->app->make(AlertService::class));
    }

    public function test_discord_alerts_are_sent_as_coloured_embeds(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $monitor = Monitor::factory()->create(['url' => 'https://example.com/', 'consecutive_failures' => 2]);
        $channel = NotificationChannel::factory()->create(['user_id' => $monitor->user_id]);
        $incident = $monitor->incidents()->create([
            'started_at' => now()->subMinutes(2),
            'first_error_type' => 'timeout',
            'first_error_message' => 'timeout after 10s',
            'status' => Incident::STATUS_OPEN,
        ]);

        $this->deliver(new SendAlert($channel->id, $monitor->id, $incident->id, AlertLog::EVENT_DOWN));

        Http::assertSent(function ($request) use ($monitor) {
            $embed = $request['embeds'][0];

            return str_contains($request->url(), 'discord.com/api/webhooks')
                && $embed['color'] === 0xE74C3C
                && str_contains($embed['title'], $monitor->host)
                && str_contains($embed['description'], 'DOWN');
        });

        $this->assertDatabaseHas('alert_logs', [
            'notification_channel_id' => $channel->id,
            'event' => AlertLog::EVENT_DOWN,
            'status' => AlertLog::STATUS_SENT,
        ]);
        $this->assertNotNull($channel->refresh()->last_used_at);
    }

    public function test_recovery_embeds_are_green(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $monitor = Monitor::factory()->create(['url' => 'https://example.com/']);
        $channel = NotificationChannel::factory()->create(['user_id' => $monitor->user_id]);
        $incident = $monitor->incidents()->create([
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
            'duration_seconds' => 300,
            'status' => Incident::STATUS_RESOLVED,
        ]);

        $this->deliver(new SendAlert($channel->id, $monitor->id, $incident->id, AlertLog::EVENT_RECOVERED));

        Http::assertSent(fn ($request) => $request['embeds'][0]['color'] === 0x2ECC71);
    }

    public function test_telegram_alerts_are_sent_to_the_linked_chat(): void
    {
        config(['services.telegram.bot_token' => '123:token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $monitor = Monitor::factory()->create(['url' => 'https://example.com/']);
        $channel = NotificationChannel::factory()->telegram('998877')->create(['user_id' => $monitor->user_id]);

        $this->deliver(new SendAlert($channel->id, $monitor->id, null, AlertLog::EVENT_DOWN));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '998877'
            && str_contains($request['text'], 'DOWN'));

        $this->assertDatabaseHas('alert_logs', [
            'notification_channel_id' => $channel->id,
            'status' => AlertLog::STATUS_SENT,
        ]);
    }

    public function test_a_failed_delivery_records_a_failure_and_marks_the_channel(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 500)]);

        $monitor = Monitor::factory()->create(['url' => 'https://example.com/']);
        $channel = NotificationChannel::factory()->create(['user_id' => $monitor->user_id]);
        $job = new SendAlert($channel->id, $monitor->id, null, AlertLog::EVENT_DOWN);

        try {
            $this->deliver($job);
            $this->fail('A failed delivery must throw so the queue retries it.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('discord.com/api/webhooks', $e->getMessage());
        }

        $job->failed(new RuntimeException('Discord rejected the alert (HTTP 500).'));

        $this->assertDatabaseHas('alert_logs', [
            'notification_channel_id' => $channel->id,
            'status' => AlertLog::STATUS_FAILED,
        ]);
        $this->assertSame(NotificationChannel::STATUS_NEEDS_ATTENTION, $channel->refresh()->status);
    }

    public function test_a_successful_delivery_revives_a_channel_that_needed_attention(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $monitor = Monitor::factory()->create(['url' => 'https://example.com/']);
        $channel = NotificationChannel::factory()->needsAttention()->create(['user_id' => $monitor->user_id]);

        $this->deliver(new SendAlert($channel->id, $monitor->id, null, AlertLog::EVENT_DOWN));

        $this->assertSame(NotificationChannel::STATUS_ACTIVE, $channel->refresh()->status);
    }

    public function test_the_test_alert_endpoint_sends_and_logs(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $user = User::factory()->create();
        $channel = NotificationChannel::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/channels/'.$channel->id.'/test')
            ->assertOk()
            ->assertJsonPath('alert.status', AlertLog::STATUS_SENT);

        $this->assertDatabaseHas('alert_logs', [
            'notification_channel_id' => $channel->id,
            'event' => AlertLog::EVENT_TEST,
            'status' => AlertLog::STATUS_SENT,
        ]);
    }

    public function test_the_test_alert_endpoint_reports_delivery_failures(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 500)]);

        $user = User::factory()->create();
        $channel = NotificationChannel::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/channels/'.$channel->id.'/test')
            ->assertStatus(502);

        $this->assertDatabaseHas('alert_logs', [
            'notification_channel_id' => $channel->id,
            'status' => AlertLog::STATUS_FAILED,
        ]);
    }
}
