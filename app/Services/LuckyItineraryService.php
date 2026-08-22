<?php

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LuckyItineraryService
{
    public const MAX_ATTEMPTS = 40;

    /**
     * Room-price bands mirroring the frontend price filter in room/index.blade.php.
     */
    private const CATEGORY_BANDS = [
        'budget' => ['max' => 2500],
        'mid' => ['min' => 2500, 'max' => 6000],
        'luxury' => ['min' => 6000],
    ];

    /**
     * Generate a surprise itinerary constrained to a single destination and the
     * given filters. Total cost never exceeds the budget when a fitting combo exists.
     *
     * @return array{success: bool, itinerary?: array, message?: string}
     */
    public function generate(array $filters): array
    {
        $maxBudget = (float) ($filters['max_budget'] ?? 20000);
        $activityCount = (int) min(5, max(1, $filters['activity_count'] ?? 2));
        $nights = (int) min(7, max(1, $filters['nights'] ?? 2));
        $pax = (int) min(4, max(1, $filters['pax'] ?? 2));
        $category = $filters['hotel_category'] ?? null;
        $startDate = Carbon::parse($filters['start_date'] ?? Carbon::today()->addDays(7));
        $checkOutDate = $startDate->copy()->addDays($nights);

        $destinations = DestinationModel::query()
            ->when(! empty($filters['destination_id']), fn ($q) => $q->where('id', $filters['destination_id']))
            ->get();

        $candidateDestinations = $destinations->filter(
            fn (DestinationModel $destination) => $this->destinationHasEnoughInventory($destination, $activityCount, $category)
        );

        if ($candidateDestinations->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No destination currently has enough hotels and activities to match your filters. Try a different destination, fewer activities, or a wider hotel category.',
            ];
        }

        $best = null;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $destination = $candidateDestinations->random();

            $room = $this->pickRoom($destination, $category, $pax);
            $activities = $this->pickActivities($destination, $filters, $activityCount);
            if (! $room || $activities->count() < $activityCount) {
                continue;
            }

            $hotel = $room->hotel;
            $roomTotal = $room->calculateNightlyRate($pax) * $nights;
            $activitiesTotal = $activities->sum(fn ($activity) => $this->activityCost($activity, $pax));
            $total = round($roomTotal + $activitiesTotal, 2);

            $itinerary = $this->buildItinerary(
                $destination, $hotel, $room, $activities, $nights, $pax,
                $startDate, $checkOutDate, $total, $maxBudget
            );

            if ($total <= $maxBudget) {
                return $itinerary;
            }

            if ($best === null || $total < $best['total']) {
                $best = $itinerary;
            }
        }

        if ($best === null) {
            return [
                'success' => false,
                'message' => 'No hotels or activities match your filters. Try relaxing the hotel category or number of activities.',
            ];
        }

        // Nothing fit the budget — return the cheapest observed combo, flagged.
        $best['budget_exceeded'] = true;
        $best['budget_notice'] = 'Nothing matched your budget — showing the closest option. Increase your budget or adjust the filters, then shuffle again.';

        return $best;
    }

    /**
     * Verify an accepted itinerary server-side (single destination, same pricing
     * functions, budget re-check) before its items are added to the cart.
     *
     * @return array{room_id: int, activity_ids: int[], total: float, budget_exceeded: bool}
     *
     * @throws \RuntimeException when the itinerary is stale, mismatched, or over budget
     */
    public function verifyAcceptedItinerary(array $filters): array
    {
        $destinationId = (int) $filters['destination_id'];
        $nights = (int) $filters['nights'];
        $pax = (int) $filters['pax'];

        $room = RoomType::with('hotel.destination')->where('is_shown', true)->find($filters['room_id']);
        if (! $room || ! $room->hotel || ! $room->hotel->destination) {
            throw new \RuntimeException('The selected room is no longer available. Please shuffle for a new itinerary.');
        }
        if ((int) $room->hotel->destination_id !== $destinationId) {
            throw new \RuntimeException('The selected room does not belong to the itinerary destination.');
        }

        $activityIds = array_values(array_unique(array_map('intval', $filters['activity_ids'])));
        $activities = ActivityModel::where('is_shown', true)->whereIn('id', $activityIds)->get();
        if ($activities->count() !== count($activityIds)) {
            throw new \RuntimeException('One of the selected activities is no longer available. Please shuffle for a new itinerary.');
        }
        foreach ($activities as $activity) {
            if ((int) $activity->destination_id !== $destinationId) {
                throw new \RuntimeException('All itinerary items must be in the same destination.');
            }
        }

        $roomTotal = $room->calculateNightlyRate($pax) * $nights;
        $activitiesTotal = $activities->sum(fn ($activity) => $this->activityCost($activity, $pax));
        $total = round($roomTotal + $activitiesTotal, 2);

        $budgetExceeded = ! empty($filters['budget_exceeded']);
        if ($total > (float) $filters['max_budget'] && ! $budgetExceeded) {
            throw new \RuntimeException('This itinerary no longer fits your budget. Shuffle for a new one or raise your budget.');
        }

        return [
            'room_id' => $room->id,
            'activity_ids' => $activityIds,
            'total' => $total,
            'budget_exceeded' => $total > (float) $filters['max_budget'],
        ];
    }

    /**
     * Cost of one activity in the cart: per-person rates scale with pax, flat
     * group rates do not — mirrors CartItem::getSubtotalAttribute().
     */
    private function activityCost(ActivityModel $activity, int $pax): float
    {
        $rate = $activity->calculateRateForPax($pax);

        return $activity->isPerPersonRate() ? round($rate * $pax, 2) : round($rate, 2);
    }

    private function destinationHasEnoughInventory(DestinationModel $destination, int $activityCount, ?string $category): bool
    {
        $hasRoom = HotelModel::where('destination_id', $destination->id)
            ->where('is_shown', true)
            ->whereHas('rooms', function ($q) use ($category) {
                $q->where('is_shown', true);
                if ($category) {
                    $bands = self::CATEGORY_BANDS[$category] ?? null;
                    if ($bands) {
                        $q->where('base_price', '>=', $bands['min'] ?? 0)
                            ->where('base_price', '<', $bands['max'] ?? PHP_FLOAT_MAX);
                    }
                }
            })
            ->exists();

        $availableActivities = ActivityModel::where('destination_id', $destination->id)
            ->where('is_shown', true)
            ->count();

        return $hasRoom && $availableActivities >= $activityCount;
    }

    private function pickRoom(DestinationModel $destination, ?string $category, int $pax): ?RoomType
    {
        $hotels = HotelModel::where('destination_id', $destination->id)
            ->where('is_shown', true)
            ->with(['rooms' => fn ($q) => $q->where('is_shown', true)])
            ->get();

        $roomPools = [];
        foreach ($hotels as $hotel) {
            foreach ($hotel->rooms as $room) {
                if ((int) $room->max_occupancy < $pax) {
                    continue;
                }
                if ($category && ! $this->roomMatchesCategory($room, $category, $pax)) {
                    continue;
                }
                $roomPools[] = $room;
            }
        }

        if (empty($roomPools)) {
            return null;
        }

        return $roomPools[array_rand($roomPools)];
    }

    private function roomMatchesCategory(RoomType $room, string $category, int $pax): bool
    {
        $rate = $room->calculateNightlyRate($pax);

        return match ($category) {
            'budget' => $rate <= 2500,
            'mid' => $rate > 2500 && $rate < 6000,
            'luxury' => $rate >= 6000,
            default => true,
        };
    }

    private function pickActivities(DestinationModel $destination, array $filters, int $activityCount): Collection
    {
        $query = ActivityModel::where('destination_id', $destination->id)->where('is_shown', true);
        if (! empty($filters['activity_level'])) {
            $query->where('activity_level', $filters['activity_level']);
        }
        if (! empty($filters['activity_category'])) {
            $query->where('category', $filters['activity_category']);
        }

        $activities = $query->get();
        if ($activities->count() < $activityCount) {
            return collect();
        }

        return collect($activities->random($activityCount));
    }

    private function buildItinerary(
        DestinationModel $destination,
        HotelModel $hotel,
        RoomType $room,
        Collection $activities,
        int $nights,
        int $pax,
        Carbon $checkIn,
        Carbon $checkOut,
        float $total,
        float $maxBudget
    ): array {
        $hotelImages = $hotel->images;
        if (is_string($hotelImages)) {
            $hotelImages = json_decode($hotelImages, true);
        }
        $roomImages = $room->images;
        if (is_string($roomImages)) {
            $roomImages = json_decode($roomImages, true);
        }

        return [
            'destination' => [
                'id' => $destination->id,
                'name' => $destination->name,
                'region' => $destination->region,
                'image' => $destination->image,
            ],
            'hotel' => [
                'id' => $hotel->id,
                'hotel_name' => $hotel->hotel_name,
                'type' => $hotel->type,
                'image' => is_array($hotelImages) && count($hotelImages) > 0 ? $hotelImages[0] : null,
            ],
            'room' => [
                'id' => $room->id,
                'room_name' => $room->room_name,
                'nightly_rate' => $room->calculateNightlyRate($pax),
                'formatted_nightly_rate' => '₱'.number_format($room->calculateNightlyRate($pax), 2),
                'max_occupancy' => $room->max_occupancy,
                'bed_configuration' => $room->bed_configuration,
                'image' => is_array($roomImages) && count($roomImages) > 0 ? $roomImages[0] : null,
            ],
            'activities' => $activities->map(fn (ActivityModel $activity) => [
                'id' => $activity->id,
                'activity_name' => $activity->activity_name,
                'category' => $activity->category,
                'activity_level' => $activity->activity_level,
                'duration' => $activity->duration,
                'rate' => $activity->rate,
                'rate_for_pax' => $activity->calculateRateForPax($pax),
                'formatted_rate' => '₱'.number_format($this->activityCost($activity, $pax), 2),
            ])->values()->all(),
            'nights' => $nights,
            'pax' => $pax,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'total' => $total,
            'formatted_total' => '₱'.number_format($total, 2),
            'budget' => $maxBudget,
            'formatted_budget' => '₱'.number_format($maxBudget, 2),
            'budget_exceeded' => false,
        ];
    }
}
