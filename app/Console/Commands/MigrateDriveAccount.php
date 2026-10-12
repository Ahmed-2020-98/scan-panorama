<?php

namespace App\Console\Commands;

use App\Models\DriveConnection;
use App\Services\Drive\DriveMigration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('drive:migrate')]
#[Description('Copy all Drive files to the newly connected account and switch to it')]
class MigrateDriveAccount extends Command
{
    public function handle(DriveMigration $migration): int
    {
        if (! DriveConnection::target()) {
            $this->info('No migration in progress.');

            return self::SUCCESS;
        }
        do {
            $done = $migration->run(300);
            $progress = DriveMigration::progress();
            $this->line("Copied {$progress['copied']} / {$progress['total']}, failed {$progress['failed']}");
            if (! $done) {
                sleep(1);
            }
        } while (! $done && $progress['failed'] === 0);

        if (! $done) {
            $this->error('Some files could not be copied. Run again to retry them.');

            return self::FAILURE;
        }
        $this->info('Switched to the new Drive account.');

        return self::SUCCESS;
    }
}
