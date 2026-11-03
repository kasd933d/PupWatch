<?php

namespace Tests\Feature;

use App\Jobs\PruneExpiredTelegramTokens;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123:token',
            'services.telegram.bot_username' => 'pupwatch_bot',
            'services.telegram.webhook_secret' => 'top-secret',
        ]);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
    }

    /** @return array<string, mixed> */
    private function update(string $text, string $chatId = '555', string $username = 'tester'): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => $chatId],
                'from' => ['username' => $username],
                'text' => $text,
            ],
        ];
    }

    private function webhook(string $secret, array $payload): TestResponse
    {
        return $this->postJson('/api/v1/telegram/webhook/'.$secret, $payload);
    }

    public function test_the_wrong_secret_is_forbidden(): void
    {
        $before = NotificationChannel::count();

        $this->webhook('wrong-secret', $this->update('/start ABCD1234'))->assertForbidden();

        $this->assertSame($before, NotificationChannel::count());
        Http::assertNothingSent();
    }

    public function test_an_unconfigured_secret_forbids_every_call(): void
    {
        config(['services.telegram.webhook_secret' => '']);

        // With no secret configured, even an "empty" guess must not pass.
        $this->webhook('anything', $this->update('/status'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_a_start_code_links_the_chat_and_creates_a_channel(): void
    {
        $user = User::factory()->create();
        $token = $user->telegramLinkTokens()->create([
            'token' => 'LINKCODE',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->webhook('top-secret', $this->update('/start LINKCODE', '555', 'tester'))->assertOk();

        $channel = NotificationChannel::where('user_id', $user->id)->first();

        $this->assertNotNull($channel);
        $this->assertSame(NotificationChannel::TYPE_TELEGRAM, $channel->type);
        $this->assertSame('555', $channel->destination('chat_id'));
        $this->assertNotNull($token->refresh()->used_at);

        Http::assertSent(fn ($request) => str_contains($request['text'], 'Linked!'));
    }

    public function test_an_expired_code_links_nothing(): void
    {
        $user = User::factory()->create();
        $user->telegramLinkTokens()->create([
            'token' => 'OLDLINK',
            'expires_at' => now()->subMinutes(1),
        ]);

        $this->webhook('top-secret', $this->update('/start OLDLINK'))->assertOk();

        $this->assertDatabaseCount('notification_channels', 0);
        Http::assertSent(fn ($request) => str_contains($request['text'], 'invalid or has expired'));
    }

    public function test_a_used_code_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $user->telegramLinkTokens()->create([
            'token' => 'USEDCODE',
            'expires_at' => now()->addMinutes(10),
            'used_at' => now()->subMinute(),
        ]);

        $this->webhook('top-secret', $this->update('/start USEDCODE'))->assertOk();

        $this->assertDatabaseCount('notification_channels', 0);
    }

    public function test_status_lists_the_linked_users_monitors(): void
    {
        $user = User::factory()->create();
        NotificationChannel::factory()->telegram('555')->create(['user_id' => $user->id]);
        Monitor::factory()->create([
            'user_id' => $user->id,
            'name' => 'Client site',
            'url' => 'https://example.com/',
            'current_status' => Monitor::STATUS_DOWN,
        ]);

        $this->webhook('top-secret', $this->update('/status'))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['text'], 'Client site')
            && str_contains($request['text'], '🔴'));
    }

    public function test_pause_and_resume_work_by_name(): void
    {
        $user = User::factory()->create();
        NotificationChannel::factory()->telegram('555')->create(['user_id' => $user->id]);
        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'name' => 'Marketing site',
            'url' => 'https://example.com/',
        ]);

        $this->webhook('top-secret', $this->update('/pause marketing'))->assertOk();
        $this->assertTrue($monitor->refresh()->is_paused);

        $this->webhook('top-secret', $this->update('/resume marketing'))->assertOk();
        $this->assertFalse($monitor->refresh()->is_paused);
    }

    public function test_an_unknown_command_returns_help(): void
    {
        NotificationChannel::factory()->telegram('555')->create(['user_id' => User::factory()->create()->id]);

        $this->webhook('top-secret', $this->update('/nonsense'))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['text'], '/status'));
    }

    public function test_an_unlinked_chat_is_told_how_to_link(): void
    {
        $this->webhook('top-secret', $this->update('/status', '9999'))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['text'], 'not linked yet'));
    }

    public function test_expired_link_tokens_are_pruned(): void
    {
        $user = User::factory()->create();
        $user->telegramLinkTokens()->create([
            'token' => 'EXPIRED1',
            'expires_at' => now()->subHour(),
        ]);

        (new PruneExpiredTelegramTokens)();

        $this->assertDatabaseMissing('telegram_link_tokens', ['token' => 'EXPIRED1']);
    }
}
