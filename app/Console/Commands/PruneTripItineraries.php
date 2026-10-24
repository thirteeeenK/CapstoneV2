<?php

namespace App\Console\Commands;

use App\Models\CartItem;
use App\Models\TripItinerary;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PruneTripItineraries extends Command
{
    protected $signature = 'trips:prune-itineraries {--keep=5 : Generated schedule versions to keep per plan}';

    protected $description = 'Delete older generated trip schedules, keeping the latest versions and any still in a Trip Basket';

    /**
     * Chatbot draft snapshots are the plan's own history and are never pruned;
     * bookings keep their own copy of a schedule, so only the basket blocks deletion.
     */
    public function handle(): int
    {
        $keep = max(1, (int) $this->option('keep'));
        $generated = fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('snapshot->kind')->orWhere('snapshot->kind', '!=', 'chatbot_draft'));
        $deleted = 0;
        TripItinerary::query()->tap($generated)->select('trip_plan_id')->groupBy('trip_plan_id')->havingRaw('count(*) > ?', [$keep])
            ->pluck('trip_plan_id')->each(function (int $planId) use ($generated, $keep, &$deleted): void {
                $latest = TripItinerary::query()->tap($generated)->where('trip_plan_id', $planId)->latest('id')->limit($keep)->pluck('id');
                $deleted += TripItinerary::query()->tap($generated)->where('trip_plan_id', $planId)->whereNotIn('id', $latest)
                    ->whereNotIn('id', CartItem::query()->whereNotNull('trip_itinerary_id')->select('trip_itinerary_id'))
                    ->delete();
            });
        $this->info($deleted.' older trip schedule version(s) deleted.');

        return self::SUCCESS;
    }
}
