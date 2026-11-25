<?php

namespace App\Console\Commands;

use App\Services\ProjectScheduleDelayService;
use Illuminate\Console\Command;

class SyncProjectScheduleDelays extends Command
{
    protected $signature = 'projects:sync-schedule-delays';

    protected $description = 'Przesuwa zależne etapy o opóźnienie poprzedników bez zmiany ich terminów planowanych';

    public function handle(ProjectScheduleDelayService $delays): int
    {
        $delays->syncAll();

        return self::SUCCESS;
    }
}
