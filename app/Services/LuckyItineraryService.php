<?php

namespace App\Services;

use App\Concerns\ResolvesImages;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LuckyItineraryService
{
    use ResolvesImages;

    public function __construct(private RoomAvailabilityService $availability) {}

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

        // Each destination's eligible rooms (free on these dates) and activities are loaded once for every draw.
        $pools = $candidateDestinations->mapWithKeys(fn (DestinationModel $destination) => [$destination->id => [
            'destination' => $destination,
            'rooms' => $this->roomPool($destination, $category, $pax, $startDate, $checkOutDate),
            'activities' => $this->activityPool($destination, $filters),
        ]])->filter(fn (array $pool) => $pool['rooms']->isNotEmpty() && $pool['activities']->count() >= $activityCount);

        if ($pools->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No hotels or activities match your filters. Try relaxing the hotel category or number of activities.',
            ];
        }

        // The cheapest combination per destination proves whether anything can fit the budget at all.
        $cheapest = $pools->map(fn (array $pool) => $this->cheapestCombination($pool, $activityCount, $nights, $pax));
        $affordable = $pools->filter(fn (array $pool, int $id) => $cheapest[$id]['total'] <= $maxBudget);
        $itineraryFor = fn (array $combo) => $this->buildItinerary(
            $combo['destination'], $combo['room']->hotel, $combo['room'], $combo['activities'], $nights, $pax,
            $startDate, $checkOutDate, $combo['total'], $maxBudget
        );

        if ($affordable->isEmpty()) {
            $lowest = $cheapest->sortBy('total')->first();
            $itinerary = $itineraryFor($lowest);
            $itinerary['budget_exceeded'] = true;
            $itinerary['budget_notice'] = 'No combination fits ₱'.number_format($maxBudget, 2).' with these filters — the lowest possible total is ₱'
                .number_format($lowest['total'], 2).'. Increase your budget or adjust the filters.';

            return $itinerary;
        }

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $pool = $affordable->random();
            $combo = $this->combination($pool['destination'], $pool['rooms']->random(), collect($pool['activities']->random($activityCount)), $nights, $pax);
            if ($combo['total'] <= $maxBudget) {
                return $itineraryFor($combo);
            }
        }

        // Random draws kept missing a rare fit; a destination's cheapest combination is known to fit.
        return $itineraryFor($cheapest[$affordable->keys()->random()]);
    }

    /**
     * @return array{destination: DestinationModel, room: RoomType, activities: Collection, total: float}
     */
    private function combination(DestinationModel $destination, RoomType $room, Collection $activities, int $nights, int $pax): array
    {
        $total = $room->calculateNightlyRate($pax) * $nights + $activities->sum(fn ($activity) => $this->activityCost($activity, $pax));

        return ['destination' => $destination, 'room' => $room, 'activities' => $activities->values(), 'total' => round($total, 2)];
    }

    /**
     * @param  array{destination: DestinationModel, rooms: Collection, activities: Collection}  $pool
     * @return array{destination: DestinationModel, room: RoomType, activities: Collection, total: float}
     */
    private function cheapestCombination(array $pool, int $activityCount, int $nights, int $pax): array
    {
        return $this->combination(
            $pool['destination'],
            $pool['rooms']->sortBy(fn (RoomType $room) => $room->calculateNightlyRate($pax))->first(),
            $pool['activities']->sortBy(fn (ActivityModel $activity) => $this->activityCost($activity, $pax))->take($activityCount),
            $nights,
            $pax,
        );
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

    /**
     * Shown rooms that fit the party and category and still have a room free for the stay.
     *
     * @return Collection<int, RoomType>
     */
    private function roomPool(DestinationModel $destination, ?string $category, int $pax, Carbon $checkIn, Carbon $checkOut): Collection
    {
        $hotels = HotelModel::where('destination_id', $destination->id)
            ->where('is_shown', true)
            ->with(['rooms' => fn ($q) => $q->where('is_shown', true)])
            ->get();

        return $hotels->flatMap(function (HotelModel $hotel) {
            $hotel->rooms->each(fn (RoomType $room) => $room->setRelation('hotel', $hotel));

            return $hotel->rooms;
        })->filter(fn (RoomType $room) => (int) $room->max_occupancy >= $pax
            && (! $category || $this->roomMatchesCategory($room, $category, $pax))
            && $this->availability->check($room, $checkIn->copy()->startOfDay(), $checkOut->copy()->startOfDay())['available'])->values();
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

    /**
     * @return Collection<int, ActivityModel>
     */
    private function activityPool(DestinationModel $destination, array $filters): Collection
    {
        $query = ActivityModel::where('destination_id', $destination->id)->where('is_shown', true);
        if (! empty($filters['activity_level'])) {
            $query->where('activity_level', $filters['activity_level']);
        }
        if (! empty($filters['activity_category'])) {
            $query->where('category', $filters['activity_category']);
        }

        return $query->get();
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
        $roomRaw = is_array($roomImages) && count($roomImages) > 0 ? $roomImages[0] : null;
        $hotelRaw = is_array($hotelImages) && count($hotelImages) > 0 ? $hotelImages[0] : null;

        return [
            'destination' => [
                'id' => $destination->id,
                'name' => $destination->name,
                'region' => $destination->region,
                'image' => self::resolveImg($destination->image),
            ],
            'hotel' => [
                'id' => $hotel->id,
                'hotel_name' => $hotel->hotel_name,
                'type' => $hotel->type,
                'image' => self::resolveImg($hotelRaw),
            ],
            'room' => [
                'id' => $room->id,
                'room_name' => $room->room_name,
                'nightly_rate' => $room->calculateNightlyRate($pax),
                'formatted_nightly_rate' => '₱'.number_format($room->calculateNightlyRate($pax), 2),
                'max_occupancy' => $room->max_occupancy,
                'bed_configuration' => $room->bed_configuration,
                'image' => self::resolveImg($roomRaw ?: $hotelRaw),
            ],
            'activities' => $activities->map(function (ActivityModel $activity) use ($pax) {
                $images = $activity->images;
                if (is_string($images)) {
                    $images = json_decode($images, true);
                }
                $raw = is_array($images) && count($images) > 0 ? $images[0] : null;

                return [
                    'id' => $activity->id,
                    'activity_name' => $activity->activity_name,
                    'category' => $activity->category,
                    'activity_level' => $activity->activity_level,
                    'duration' => $activity->duration,
                    'rate' => $activity->rate,
                    'rate_for_pax' => $activity->calculateRateForPax($pax),
                    'formatted_unit_rate' => '₱'.number_format($activity->calculateRateForPax($pax), 2),
                    'is_per_person' => $activity->isPerPersonRate(),
                    'formatted_rate' => '₱'.number_format($this->activityCost($activity, $pax), 2),
                    'image' => self::resolveActivityImage($raw, $activity->activity_name, $activity->category),
                ];
            })->values()->all(),
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
