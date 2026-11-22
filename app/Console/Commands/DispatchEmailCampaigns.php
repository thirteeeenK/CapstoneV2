<?php

namespace App\Console\Commands;

use App\Services\EmailCampaignService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campaigns:dispatch-due')]
#[Description('Start due scheduled email campaigns and release promotional email within the daily sending cap')]
class DispatchEmailCampaigns extends Command
{
    public function handle(EmailCampaignService $campaigns): int
    {
        $released = $campaigns->tick();
        $this->info('Released '.$released.' promotional email(s). '.$campaigns->remainingToday().' left under today’s cap.');

        return self::SUCCESS;
    }
}
