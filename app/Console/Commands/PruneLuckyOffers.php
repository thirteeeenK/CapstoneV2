<?php

namespace App\Console\Commands;

use App\Models\LuckyOffer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneLuckyOffers extends Command
{
    protected $signature = 'lucky:prune-offers {--days=30 : Delete unused Lucky itineraries that expired more than this many days ago}';

    protected $description = 'Delete expired Lucky itineraries that were never accepted or redeemed, keeping every code a booking or basket used';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays(max(1, (int) $this->option('days')));
        // Offers in a basket, a redemption or an accepted group stay for audit; so does any
        // Lucky trip plan's link, which is by uuid only.
        $deleted = LuckyOffer::query()->whereNotNull('expires_at')->where('expires_at', '<', $cutoff)->whereNull('accepted_at')
            ->whereDoesntHave('redemption')->whereDoesntHave('cartItems')
            ->whereNotIn('uuid', fn ($plans) => $plans->select('source_suggestion_uuid')->from('trip_plans')->where('source', 'lucky')->whereNotNull('source_suggestion_uuid'))
            ->delete();
        $this->info($deleted.' expired, unused Lucky itinerary offer(s) deleted.');

        return self::SUCCESS;
    }
}
