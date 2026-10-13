<?php

use App\Models\DriveConnection;
use App\Services\CenterAlerts;
use App\Services\Drive\DriveMigration;
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

// Moving Drive to another account: copy a short slice every minute, so it finishes
// even with the Drive page closed (the migration's own lock prevents overlap).
Schedule::call(fn () => app(DriveMigration::class)->run(45))
    ->everyMinute()
    ->when(fn () => DriveConnection::target() !== null)
    ->name('drive-migration');
