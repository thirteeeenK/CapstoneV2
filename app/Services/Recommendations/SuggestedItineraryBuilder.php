<?php

namespace App\Services\Recommendations;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\UserPreference;
use App\Services\ItineraryInsightsService;
use Illuminate\Support\Collection;

/**
 * Builds the dashboard's 3-day starter itinerary from the traveler's
 * top-ranked stay and experiences, with prices, forecast and road distances.
 */
class SuggestedItineraryBuilder
{
    private const NIGHTS = 2;

    public function __construct(private ItineraryInsightsService $insights) {}

    /**
     * @param  Collection<int, HotelModel>  $hotels  ranked best-first
     * @param  Collection<int, ActivityModel>  $activities  ranked best-first
     * @return array{destination: DestinationModel, pax: int, nights: int, hotel: HotelModel, room: ?RoomType, room_quantity: int, room_pax: int, nightly_rate: float, days: list<array{day: int, title: string, activities: list<array{activity: ActivityModel, price: float, leg: ?array}>}>, prices: array{stay: float, activities: float, total: float}, weather: ?array, personalized: bool}|null
     */
    public function build(DestinationModel $destination, Collection $hotels, Collection $activities, ?UserPreference $preference, bool $personalized): ?array
    {
        $hotel = $hotels->first();
        if (! $hotel) {
            return null;
        }

        $pax = $this->paxFor($preference?->traveler_type);
        ['room' => $room, 'quantity' => $roomQuantity, 'room_pax' => $roomPax] = $this->roomPlanFor($hotel, $pax);
        $nightlyRate = $room ? round($room->calculateNightlyRate($roomPax), 2) : 0.0;
        $picks = $activities->take(3)->values();
        $insights = $this->insights->forStay($destination, $hotel, $picks);

        $titles = ['Arrive, check in and explore', 'Full day of experiences', 'Last morning and check out'];
        $days = [];
        foreach ($titles as $index => $title) {
            $activity = $picks->get($index);
            $days[] = [
                'day' => $index + 1,
                'title' => $title,
                'activities' => $activity ? [[
                    'activity' => $activity,
                    'price' => $this->activityPrice($activity, $pax),
                    'leg' => $insights['legs'][$activity->id] ?? null,
                ]] : [],
            ];
        }

        $stay = round($nightlyRate * $roomQuantity * self::NIGHTS, 2);
        $activityTotal = round((float) collect($days)->flatMap(fn (array $day) => $day['activities'])->sum('price'), 2);

        return [
            'destination' => $destination,
            'pax' => $pax,
            'nights' => self::NIGHTS,
            'hotel' => $hotel,
            'room' => $room,
            'room_quantity' => $roomQuantity,
            'room_pax' => $roomPax,
            'nightly_rate' => $nightlyRate,
            'days' => $days,
            'prices' => ['stay' => $stay, 'activities' => $activityTotal, 'total' => round($stay + $activityTotal, 2)],
            'weather' => $insights['weather'],
            'personalized' => $personalized,
        ];
    }

    /**
     * Party size implied by the onboarding traveler type.
     */
    public function paxFor(?string $travelerType): int
    {
        $type = mb_strtolower((string) $travelerType);

        return match (true) {
            str_contains($type, 'solo') => 1,
            str_contains($type, 'family'), str_contains($type, 'friend'), str_contains($type, 'group') => 4,
            default => 2,
        };
    }

    /**
     * The cheapest way to sleep the whole party in one room type: one room
     * when it fits everyone, otherwise enough rooms of a smaller type, with
     * guests spread evenly so no room is over its capacity.
     *
     * @return array{room: ?RoomType, quantity: int, room_pax: int}
     */
    public function roomPlanFor(HotelModel $hotel, int $pax): array
    {
        $plans = $hotel->rooms
            ->filter(fn (RoomType $room) => $room->is_shown !== false && (float) $room->base_price > 0 && (int) $room->max_occupancy > 0)
            ->map(function (RoomType $room) use ($pax): array {
                $quantity = (int) ceil($pax / (int) $room->max_occupancy);
                $roomPax = (int) ceil($pax / $quantity);

                return ['room' => $room, 'quantity' => $quantity, 'room_pax' => $roomPax, 'cost' => $room->calculateNightlyRate($roomPax) * $quantity];
            })
            ->sortBy([['cost', 'asc'], ['quantity', 'asc']]);

        $plan = $plans->first();

        return $plan ? ['room' => $plan['room'], 'quantity' => $plan['quantity'], 'room_pax' => $plan['room_pax']] : ['room' => null, 'quantity' => 1, 'room_pax' => $pax];
    }

    private function activityPrice(ActivityModel $activity, int $pax): float
    {
        $rate = $activity->calculateRateForPax($pax);

        return round($activity->isPerPersonRate() ? $rate * $pax : $rate, 2);
    }
}
