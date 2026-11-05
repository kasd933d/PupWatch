<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MonitorController;
use App\Http\Controllers\Api\NotificationChannelController;
use App\Http\Controllers\Api\PublicStatusController;
use App\Http\Controllers\Api\StatusPageController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PupWatch API v1 (prefix: /api/v1)
|--------------------------------------------------------------------------
|
| Error shape: { "message": string, "errors"?: object }
| Pagination:  ?page=  ?per_page= (max 50)
|
*/

Route::prefix('v1')->group(function () {
    // --- Authentication -----------------------------------------------------
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'show']);
        Route::patch('/me', [AuthController::class, 'update']);
        Route::delete('/me', [AuthController::class, 'destroy']);
    });

    // --- Webhooks (public, secret-protected) --------------------------------
    Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class)
        ->middleware('throttle:public');

    // --- Public status pages (no auth, cached 60s) --------------------------
    Route::get('/public/status/{statusPage:slug}', [PublicStatusController::class, 'show'])
        ->middleware('throttle:public');

    // --- Authenticated API --------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        // Monitors
        Route::get('/monitors', [MonitorController::class, 'index']);
        Route::post('/monitors', [MonitorController::class, 'store']);
        Route::get('/monitors/{monitor}', [MonitorController::class, 'show'])->whereNumber('monitor');
        Route::patch('/monitors/{monitor}', [MonitorController::class, 'update'])->whereNumber('monitor');
        Route::delete('/monitors/{monitor}', [MonitorController::class, 'destroy'])->whereNumber('monitor');
        Route::post('/monitors/{monitor}/pause', [MonitorController::class, 'pause'])->whereNumber('monitor');
        Route::post('/monitors/{monitor}/resume', [MonitorController::class, 'resume'])->whereNumber('monitor');
        Route::post('/monitors/{monitor}/check', [MonitorController::class, 'checkNow'])->whereNumber('monitor');
        Route::get('/monitors/{monitor}/results', [MonitorController::class, 'results'])->whereNumber('monitor');
        Route::get('/monitors/{monitor}/checks', [MonitorController::class, 'checks'])->whereNumber('monitor');
        Route::get('/monitors/{monitor}/incidents', [IncidentController::class, 'forMonitor'])->whereNumber('monitor');
        Route::put('/monitors/{monitor}/channels', [MonitorController::class, 'syncChannels'])->whereNumber('monitor');

        // Incidents
        Route::get('/incidents', [IncidentController::class, 'index']);

        // Status pages
        Route::apiResource('status-pages', StatusPageController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Channels
        Route::get('/channels', [NotificationChannelController::class, 'index']);
        Route::post('/channels/discord', [NotificationChannelController::class, 'storeDiscord']);
        Route::post('/channels/telegram/link', [NotificationChannelController::class, 'telegramLink']);
        Route::post('/channels/{channel}/test', [NotificationChannelController::class, 'test'])->whereNumber('channel');
        Route::delete('/channels/{channel}', [NotificationChannelController::class, 'destroy'])->whereNumber('channel');
    });
});
