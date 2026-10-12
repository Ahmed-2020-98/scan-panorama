<?php

namespace App\Jobs;

use App\Models\DriveConnection;
use App\Services\Drive\DriveMigration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Copies files to the new Drive account in short slices, re-queuing itself until done. */
class MigrateDriveFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1200;

    public function handle(DriveMigration $migration): void
    {
        if (! DriveConnection::target()) {
            return;
        }
        $done = $migration->run(480);
        // Re-queue unless finished, cancelled meanwhile, or blocked by failing files.
        if (! $done && DriveMigration::progress()['failed'] === 0 && DriveConnection::where('role', 'target')->exists()) {
            self::dispatch();
        }
    }
}
