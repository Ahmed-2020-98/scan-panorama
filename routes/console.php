<?php

use App\Services\CenterAlerts;
use Illuminate\Support\Facades\Schedule;

Schedule::command('uploads:prune')->hourly();
Schedule::command('doctors:purge')->daily();
Schedule::call(fn () => CenterAlerts::runDaily())->dailyAt('09:00')->name('center-alerts');

// Shared hosting has no supervisor, so the scheduler (cron every minute) drains the
// queue: Drive transfers run here. The lock outlives the longest job (timeout 1800s).
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=5 --timeout=1800')
    ->everyMinute()
    ->withoutOverlapping(35)
    ->runInBackground();
