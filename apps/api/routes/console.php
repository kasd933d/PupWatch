<?php

use App\Jobs\DispatchDueChecks;
use App\Jobs\RecoverStuckClaims;
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
