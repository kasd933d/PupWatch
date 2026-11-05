<?php

use App\Jobs\DispatchDueChecks;
use App\Jobs\PruneExpiredTelegramTokens;
use App\Jobs\PruneOldData;
use App\Jobs\RecoverStuckClaims;
use App\Jobs\RollupDailyStats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler (PRD §8.2)
|--------------------------------------------------------------------------
|
| One cron entry drives everything: `* * * * * php artisan schedule:run`.
|
*/

Schedule::call(new DispatchDueChecks)
    ->everyMinute()
    ->name('dispatch-due-checks')
    ->withoutOverlapping();

Schedule::call(new RecoverStuckClaims)
    ->everyFiveMinutes()
    ->name('recover-stuck-claims')
    ->withoutOverlapping();

Schedule::call(new PruneExpiredTelegramTokens)
    ->everyTenMinutes()
    ->name('prune-telegram-tokens')
    ->withoutOverlapping();

// PRD §4.9 — roll yesterday up just after midnight, then prune old raw checks.
Schedule::call(new RollupDailyStats)
    ->dailyAt('00:05')
    ->name('rollup-daily-stats')
    ->withoutOverlapping();

Schedule::call(new PruneOldData)
    ->dailyAt('03:10')
    ->name('prune-old-data')
    ->withoutOverlapping();
