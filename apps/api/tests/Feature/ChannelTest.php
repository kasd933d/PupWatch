<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function actingUser(): array
    {
        $user = User::factory()->create();

        return [$user, $user->createToken('web')->plainTextToken];
    }

    public function test_a_discord_webhook_can_be_added(): void
    {
        [$user, $token] = $this->actingUser();

        $this->withToken($token)->postJson('/api/v1/channels/discord', [
            'name' => 'Ops',
            'webhook_url' => 'https://discord.com/api/webhooks/123456789/abcdef',
        ])->assertStatus(201)
            ->assertJsonPath('channel.type', 'discord')
            ->assertJsonPath('channel.status', 'active');

        $this->assertDatabaseCount('notification_channels', 1);

        // PRD §5 — the webhook URL is encrypted at rest and never returned.
        $raw = DB::table('notification_channels')->value('config');
        $this->assertStringNotContainsString('discord.com', (string) $raw);
        $this->assertStringNotContainsString('webhook_url', (string) $raw);
    }

    public function test_non_discord_webhooks_are_rejected(): void
    {
        [, $token] = $this->actingUser();

        foreach ([
            'https://evil.example/api/webhooks/1/abc',
            'http://discord.com/api/webhooks/1/abc',
            'https://discord.com/api/webhooks/notanid/abc',
            'https://discord.com/channels/1/2',
        ] as $url) {
            $this->withToken($token)->postJson('/api/v1/channels/discord', [
                'name' => 'Bad',
                'webhook_url' => $url,
            ])->assertStatus(422)->assertJsonValidationErrors(['webhook_url']);
        }
    }

    public function test_channels_are_listed_without_secrets(): void
    {
        [$user, $token] = $this->actingUser();
        NotificationChannel::factory()->create(['user_id' => $user->id]);
        NotificationChannel::factory()->telegram()->create(['user_id' => $user->id]);

        $response = $this->withToken($token)->getJson('/api/v1/channels')->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertStringNotContainsString('webhook_url', $response->getContent());
        $this->assertStringNotContainsString('chat_id', $response->getContent());
    }

    public function test_users_cannot_test_or_delete_another_users_channel(): void
    {
        [, $token] = $this->actingUser();
        $foreign = NotificationChannel::factory()->create();

        $this->withToken($token)->postJson('/api/v1/channels/'.$foreign->id.'/test')->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/channels/'.$foreign->id)->assertForbidden();

        $this->assertDatabaseHas('notification_channels', ['id' => $foreign->id]);
    }

    public function test_a_channel_can_be_deleted_by_its_owner(): void
    {
        [$user, $token] = $this->actingUser();
        $channel = NotificationChannel::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->deleteJson('/api/v1/channels/'.$channel->id)->assertOk();

        $this->assertDatabaseMissing('notification_channels', ['id' => $channel->id]);
    }

    public function test_monitor_channels_can_be_synced(): void
    {
        [$user, $token] = $this->actingUser();
        $monitor = Monitor::factory()->create(['user_id' => $user->id]);
        $channel = NotificationChannel::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->putJson("/api/v1/monitors/{$monitor->id}/channels", [
            'channel_ids' => [$channel->id],
        ])->assertOk();

        $this->assertDatabaseHas('monitor_channel', [
            'monitor_id' => $monitor->id,
            'notification_channel_id' => $channel->id,
        ]);
    }

    public function test_linking_another_users_channel_is_rejected(): void
    {
        [$user, $token] = $this->actingUser();
        $monitor = Monitor::factory()->create(['user_id' => $user->id]);
        $foreign = NotificationChannel::factory()->create();

        $this->withToken($token)->putJson("/api/v1/monitors/{$monitor->id}/channels", [
            'channel_ids' => [$foreign->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['channel_ids.0']);
    }

    public function test_users_cannot_link_channels_on_another_users_monitor(): void
    {
        [, $token] = $this->actingUser();
        $foreignMonitor = Monitor::factory()->create();

        $this->withToken($token)->putJson("/api/v1/monitors/{$foreignMonitor->id}/channels", [
            'channel_ids' => [],
        ])->assertForbidden();
    }

    public function test_a_telegram_link_code_is_issued_with_a_fifteen_minute_expiry(): void
    {
        config([
            'services.telegram.bot_username' => 'pupwatch_bot',
            'services.telegram.webhook_secret' => 'secret',
        ]);

        [, $token] = $this->actingUser();

        $response = $this->withToken($token)->postJson('/api/v1/channels/telegram/link')->assertOk();

        $code = $response->json('code');
        $this->assertIsString($code);
        $this->assertSame(8, strlen($code));
        $this->assertStringContainsString('https://t.me/pupwatch_bot?start='.$code, $response->json('deep_link'));

        $this->assertDatabaseHas('telegram_link_tokens', ['token' => $code]);
        $expiresAt = Carbon::parse($response->json('expires_at'));
        $this->assertTrue($expiresAt->between(now()->addMinutes(14), now()->addMinutes(16)));
    }

    public function test_telegram_link_requires_authentication(): void
    {
        $this->postJson('/api/v1/channels/telegram/link')->assertUnauthorized();
    }
}
