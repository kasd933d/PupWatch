<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MonitorController;
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

    // --- Monitors ----------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
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
    });
});
