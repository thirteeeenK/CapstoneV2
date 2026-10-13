<?php

namespace App\Console\Commands;

use App\Services\TripJourneyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trips:process-journey')]
#[Description('Complete fulfilled trips and queue eligible trip journey emails')]
class ProcessTripJourney extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TripJourneyService $journey): int
    {
        $this->info('Queued '.$journey->process().' eligible journey messages.');

        return self::SUCCESS;
    }
}
