<?php

namespace App\Console\Commands;

use App\Services\CenterAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('center:alerts')]
#[Description('Remind the admin and marketing about doctors who stopped referring and visits that are due')]
class SendCenterAlerts extends Command
{
    public function handle(): int
    {
        $this->info(CenterAlerts::run().' alerts sent.');

        return self::SUCCESS;
    }
}
