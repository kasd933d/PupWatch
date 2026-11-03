<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\StoreDiscordChannelRequest;
use App\Http\Resources\NotificationChannelResource;
use App\Models\NotificationChannel;
use App\Models\TelegramLinkToken;
use App\Services\Alerts\AlertMessage;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Throwable;

class NotificationChannelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationChannelResource::collection(
            $request->user()->channels()->with('monitors:id')->orderBy('created_at')->get()
        );
    }

    /**
     * POST /channels/discord — add a Discord webhook (PRD §4.5).
     */
    public function storeDiscord(StoreDiscordChannelRequest $request): JsonResponse
    {
        $channel = $request->user()->channels()->create([
            'type' => NotificationChannel::TYPE_DISCORD,
            'name' => $request->string('name')->toString(),
            'config' => ['webhook_url' => $request->string('webhook_url')->toString()],
            'status' => NotificationChannel::STATUS_ACTIVE,
        ]);

        return response()->json(['channel' => new NotificationChannelResource($channel->load('monitors:id'))], 201);
    }

    /**
     * POST /channels/telegram/link — a one-time code valid for 15 minutes.
     */
    public function telegramLink(Request $request): JsonResponse
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (TelegramLinkToken::where('token', $code)->exists());

        $token = $request->user()->telegramLinkTokens()->create([
            'token' => $code,
            'expires_at' => now()->addMinutes(TelegramLinkToken::LIFETIME_MINUTES),
        ]);

        $username = (string) config('services.telegram.bot_username');

        return response()->json([
            'code' => $token->token,
            'expires_at' => $token->expires_at->toIso8601String(),
            'bot_username' => $username,
            'deep_link' => $username === ''
                ? null
                : 'https://t.me/'.$username.'?start='.$token->token,
            'instructions' => 'Open Telegram and send "/start '.$token->token.'" to the PupWatch bot.',
        ]);
    }

    /**
     * POST /channels/{channel}/test — send a test alert right now.
     */
    public function test(NotificationChannel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        try {
            $log = app(AlertService::class)->sendNow($channel, AlertMessage::test());
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Test alert failed: '.$e->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Test alert sent.',
            'alert' => [
                'id' => $log->id,
                'status' => $log->status,
                'sent_at' => $log->sent_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(NotificationChannel $channel): JsonResponse
    {
        $this->authorize('delete', $channel);

        $channel->delete();

        return response()->json(['message' => 'Channel removed.']);
    }
}
