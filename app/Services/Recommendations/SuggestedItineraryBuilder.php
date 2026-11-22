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
     * @return array{destination: DestinationModel, pax: int, nights: int, hotel: HotelModel, room: ?RoomType, days: list<array{day: int, title: string, activities: list<array{activity: ActivityModel, price: float, leg: ?array}>}>, prices: array{stay: float, activities: float, total: float}, weather: ?array, personalized: bool}|null
     */
    public function build(DestinationModel $destination, Collection $hotels, Collection $activities, ?UserPreference $preference, bool $personalized): ?array
    {
        $hotel = $hotels->first();
        if (! $hotel) {
            return null;
        }

        $pax = $this->paxFor($preference?->traveler_type);
        $room = $this->roomFor($hotel, $pax);
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

        $stay = $room ? round($room->calculateNightlyRate($pax) * self::NIGHTS, 2) : 0.0;
        $activityTotal = round((float) collect($days)->flatMap(fn (array $day) => $day['activities'])->sum('price'), 2);

        return [
            'destination' => $destination,
            'pax' => $pax,
            'nights' => self::NIGHTS,
            'hotel' => $hotel,
            'room' => $room,
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
     * Cheapest visible room that sleeps the party, else the cheapest visible room.
     */
    private function roomFor(HotelModel $hotel, int $pax): ?RoomType
    {
        $rooms = $hotel->rooms->filter(fn (RoomType $room) => $room->is_shown !== false && (float) $room->base_price > 0);

        return $rooms->filter(fn (RoomType $room) => (int) $room->max_occupancy >= $pax)->sortBy('base_price')->first()
            ?? $rooms->sortBy('base_price')->first();
    }

    private function activityPrice(ActivityModel $activity, int $pax): float
    {
        $rate = $activity->calculateRateForPax($pax);

        return round($activity->isPerPersonRate() ? $rate * $pax : $rate, 2);
    }
}
