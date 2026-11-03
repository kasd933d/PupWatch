<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Services\Alerts\TelegramBot;
use App\Services\MonitorScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Telegram updates (PRD §4.5).
 *
 * The endpoint is protected by a secret path segment that must match
 * TELEGRAM_WEBHOOK_SECRET, and accounts are only linked through a one-time code.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, TelegramBot $bot): JsonResponse
    {
        $expected = (string) config('services.telegram.webhook_secret');

        // PRD §5 — verify the secret on every call; never leak why it failed.
        abort_if($expected === '' || ! hash_equals($expected, $secret), 403);

        $message = $request->input('message');

        if (! is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) data_get($message, 'chat.id', '');
        $text = trim((string) data_get($message, 'text', ''));
        $username = (string) data_get($message, 'from.username', '');

        if ($chatId === '' || $text === '') {
            return response()->json(['ok' => true]);
        }

        try {
            $this->handle($bot, $chatId, $username, $text);
        } catch (Throwable) {
            // A webhook must always answer 200 so Telegram does not retry forever.
        }

        return response()->json(['ok' => true]);
    }

    private function handle(TelegramBot $bot, string $chatId, string $username, string $text): void
    {
        if (preg_match('#^/start(?:@\w+)?\s+(\S+)$#', $text, $matches) === 1) {
            $this->link($bot, $chatId, $username, strtoupper($matches[1]));

            return;
        }

        $user = $this->userForChat($chatId);

        if (! $user) {
            $bot->sendMessage($chatId, 'This chat is not linked yet. Open PupWatch → Channels → Connect Telegram and send the code shown there as "/start CODE".');

            return;
        }

        if (str_starts_with($text, '/status')) {
            $bot->sendMessage($chatId, $this->statusText($user));
        } elseif (preg_match('#^/pause\s+(.+)$#', $text, $matches) === 1) {
            $bot->sendMessage($chatId, $this->setPaused($user, trim($matches[1]), true));
        } elseif (preg_match('#^/resume\s+(.+)$#', $text, $matches) === 1) {
            $bot->sendMessage($chatId, $this->setPaused($user, trim($matches[1]), false));
        } else {
            $bot->sendMessage($chatId, $this->helpText());
        }
    }

    private function link(TelegramBot $bot, string $chatId, string $username, string $code): void
    {
        $token = TelegramLinkToken::where('token', $code)->first();

        if (! $token || ! $token->isUsable()) {
            $bot->sendMessage($chatId, 'That link code is invalid or has expired. Generate a new one in PupWatch → Channels.');

            return;
        }

        $token->forceFill(['used_at' => now()])->save();

        $name = $username !== '' ? '@'.$username : 'Telegram chat';

        $channel = NotificationChannel::firstOrNew([
            'user_id' => $token->user_id,
            'type' => NotificationChannel::TYPE_TELEGRAM,
            'name' => $name,
        ]);

        $channel->fill([
            'config' => ['chat_id' => $chatId, 'username' => $username],
            'status' => NotificationChannel::STATUS_ACTIVE,
        ])->save();

        $bot->sendMessage($chatId, "Linked! PupWatch will send alerts to this chat.\n\n".$this->helpText());
    }

    private function userForChat(string $chatId): ?User
    {
        // Config is encrypted, so the matching channel is found after decryption.
        $channel = NotificationChannel::query()
            ->where('type', NotificationChannel::TYPE_TELEGRAM)
            ->get()
            ->first(fn (NotificationChannel $channel) => $channel->destination('chat_id') === $chatId);

        return $channel?->owner;
    }

    private function statusText(User $user): string
    {
        $monitors = $user->monitors()->orderBy('name')->limit(20)->get();

        if ($monitors->isEmpty()) {
            return 'You have no monitors yet.';
        }

        $lines = $monitors->map(function (Monitor $monitor) {
            $emoji = match ($monitor->current_status) {
                Monitor::STATUS_UP => '✅',
                Monitor::STATUS_DOWN => '🔴',
                Monitor::STATUS_PAUSED => '⏸',
                default => '⏳',
            };

            $ms = $monitor->last_response_ms === null ? '' : " · {$monitor->last_response_ms}ms";

            return "{$emoji} {$monitor->name} ({$monitor->host}){$ms}";
        });

        return "Your monitors:\n".$lines->implode("\n");
    }

    private function setPaused(User $user, string $name, bool $paused): string
    {
        $monitor = $user->monitors()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($name).'%'])
            ->first();

        if (! $monitor) {
            return "No monitor matched \"{$name}\".";
        }

        if ($paused) {
            MonitorScheduler::pause($monitor);

            return "Paused {$monitor->name}.";
        }

        MonitorScheduler::resume($monitor);

        return "Resumed {$monitor->name}. The next check runs within a minute.";
    }

    private function helpText(): string
    {
        return implode("\n", [
            'Commands:',
            '/status — list your monitors',
            '/pause <name> — pause a monitor',
            '/resume <name> — resume a monitor',
            '/help — show this message',
        ]);
    }
}
