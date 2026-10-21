<?php

namespace App\Console\Commands;

use App\Models\TripPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneGuestTripPlans extends Command
{
    protected $signature = 'trips:prune-guest-plans {--days=30 : Delete unclaimed guest plans older than this}';

    protected $description = 'Delete chatbot trip plans still owned by a browser session that was never signed in';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;
        TripPlan::query()->whereNull('user_id')->where('updated_at', '<', $cutoff)
            ->chunkById(200, function ($plans) use (&$deleted): void {
                foreach ($plans as $plan) {
                    $plan->delete();
                    $deleted++;
                }
            });
        $this->info($deleted.' unclaimed guest trip plan(s) deleted.');

        return self::SUCCESS;
    }
}
