<?php

namespace App\Services;

use App\Concerns\ResolvesImages;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use Illuminate\Support\Str;

class MapService
{
    public function __construct(
        protected DistanceService $distance,
        protected WeatherService $weather,
    ) {}

    /**
     * Destination-level overview markers for the dashboard / explorer map.
     * Each marker carries weather + a point count so the map acts as a DSS.
     */
    public function destinationMarkers(float $userLat = 0, float $userLng = 0): array
    {
        return DestinationModel::withCount(['hotels', 'activities'])
            ->get()
            ->map(function (DestinationModel $destination) use ($userLat, $userLng) {
                $weather = $this->weather->summaryForDestination($destination);

                $coverImage = ResolvesImages::resolveImg(
                    $destination->image ?: collect($destination->hotels()->where('is_shown', true)->first()?->images ?? [])->first(),
                    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'
                );

                return [
                    'type' => 'destination',
                    'id' => $destination->id,
                    'name' => $destination->name,
                    'lat' => (float) $destination->latitude,
                    'lng' => (float) $destination->longitude,
                    'hotel_count' => $destination->hotels_count,
                    'activity_count' => $destination->activities_count,
                    'weather' => $weather['current'] ?? null,
                    'url' => $destination->hotels_count > 0 ? '/hotels?destination='.$destination->id : null,
                    'distance_km' => $userLat ? round($this->distance->haversine($userLat, $userLng, (float) $destination->latitude, (float) $destination->longitude), 2) : null,
                    'distance_label' => $userLat && $destination->latitude ? $this->distance->format($this->distance->haversine($userLat, $userLng, (float) $destination->latitude, (float) $destination->longitude)) : null,
                    'cover_image' => $coverImage,
                    'image' => $coverImage,
                    'images' => [$coverImage],
                    'destination_slug' => Str::slug($destination->name),
                ];
            })
            ->filter(fn ($m) => $m['lat'] && $m['lng'])
            ->values()
            ->all();
    }

    /**
     * Full marker set (hotels + activities) for the explorer map.
     */
    public function allMarkers(float $userLat = 0, float $userLng = 0): array
    {
        $hotels = HotelModel::where('is_shown', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['destination', 'rooms'])
            ->get();

        $activities = ActivityModel::where('is_shown', true)
            ->with('destination')
            ->get();

        $markers = [];

        foreach ($hotels as $hotel) {
            $markers[] = $this->hotelMarker($hotel, $userLat, $userLng);
        }

        foreach ($activities as $activity) {
            $markers[] = $this->activityMarker($activity, $userLat, $userLng);
        }

        return array_values(array_filter($markers, fn ($m) => $m['lat'] && $m['lng']));
    }

    /**
     * A single hotel + its nearby activities, used on the hotel show page.
     */
    public function hotelContextMarkers(HotelModel $hotel, int $nearbyLimit = 6): array
    {
        $userLat = (float) $hotel->latitude;
        $userLng = (float) $hotel->longitude;

        $activities = ActivityModel::where('is_shown', true)
            ->with('destination')
            ->get();

        $nearbyActivities = $activities
            ->filter(fn (ActivityModel $activity) => $activity->latitude !== null && $activity->longitude !== null)
            ->filter(function (ActivityModel $activity) use ($userLat, $userLng) {
                return $this->distance->haversine($userLat, $userLng, (float) $activity->latitude, (float) $activity->longitude) <= 15;
            })
            ->sortBy(function (ActivityModel $activity) use ($userLat, $userLng) {
                return $this->distance->haversine($userLat, $userLng, (float) $activity->latitude, (float) $activity->longitude);
            })
            ->values();

        // Activities without coordinates can't be ranked by proximity. Include
        // those from the hotel's own destination so the UI can say "no
        // location data" instead of silently dropping them.
        $noLocationActivities = $activities
            ->filter(fn (ActivityModel $activity) => $activity->latitude === null || $activity->longitude === null)
            ->filter(fn (ActivityModel $activity) => $activity->destination_id === $hotel->destination_id)
            ->sortBy('activity_name')
            ->values();

        // No-location items always get a slot (reserved first, listed last)
        // so the UI can surface the "no location data" message instead of
        // silently dropping them; they displace the farthest located ones.
        $noLocationActivities = $noLocationActivities->take($nearbyLimit);

        $nearbyActivities = $nearbyActivities
            ->take(max(0, $nearbyLimit - $noLocationActivities->count()))
            ->concat($noLocationActivities)
            ->values();

        return [
            'center' => $this->hotelMarker($hotel, $userLat, $userLng),
            'hotel' => $this->hotelMarker($hotel, $userLat, $userLng),
            'activities' => $nearbyActivities
                ->map(fn (ActivityModel $activity) => $this->activityMarker($activity, $userLat, $userLng))
                ->all(),
            'activityModels' => $nearbyActivities->all(),
        ];
    }

    protected function hotelMarker(HotelModel $hotel, float $userLat, float $userLng): array
    {
        $km = $this->distance->haversine($userLat, $userLng, (float) $hotel->latitude, (float) $hotel->longitude);
        $cheapest = $hotel->rooms->where('is_shown', true)->min('base_price');
        $cheapestRoom = $hotel->rooms->where('is_shown', true)->sortBy('base_price')->first();

        return [
            'type' => 'hotel',
            'id' => $hotel->id,
            'name' => $hotel->hotel_name,
            'subtitle' => $hotel->destination?->name,
            'lat' => (float) $hotel->latitude,
            'lng' => (float) $hotel->longitude,
            'address' => $hotel->specific_address,
            'rating' => $hotel->reviewSummary?->average_rating ?? null,
            'review_count' => $hotel->reviewSummary?->total_reviews ?? 0,
            'image' => collect($hotel->images ?? [])->first(),
            'url' => '/hotels/'.$hotel->id,
            'distance_km' => $userLat ? round($km, 2) : null,
            'distance_label' => $userLat ? $this->distance->format($km) : null,
            'images' => collect($hotel->images ?? [])->map(fn ($i) => ResolvesImages::resolveImg($i))->values()->all(),
            'cheapest_price' => $cheapest !== null ? (float) $cheapest : null,
            'cheapest_room_id' => $cheapestRoom?->id,
            'vibe_tags' => array_values(array_slice((array) ($hotel->vibe_tags ?? []), 0, 3)),
            'featured_amenities' => array_values(array_slice((array) ($hotel->featured_amenities ?? []), 0, 3)),
        ];
    }

    protected function activityMarker(ActivityModel $activity, float $userLat, float $userLng): array
    {
        $lat = $activity->latitude !== null ? (float) $activity->latitude : null;
        $lng = $activity->longitude !== null ? (float) $activity->longitude : null;

        if ($lat === null || $lng === null) {
            return [
                'type' => 'activity',
                'id' => $activity->id,
                'name' => $activity->activity_name,
                'subtitle' => $activity->destination?->name,
                'lat' => null,
                'lng' => null,
                'rate' => $activity->rate,
                'category' => $activity->category,
                'rating' => $activity->reviewSummary?->average_rating ?? null,
                'review_count' => $activity->reviewSummary?->total_reviews ?? 0,
                'image' => collect($activity->images ?? [])->first(),
                'url' => null,
                'distance_km' => null,
                'distance_label' => null,
                'no_location' => true,
                'images' => collect($activity->images ?? [])->map(fn ($i) => ResolvesImages::resolveActivityImage($i, $activity->activity_name, $activity->category))->values()->all(),
                'vibe_tags' => array_values(array_slice((array) ($activity->vibe_tags ?? []), 0, 3)),
                'inclusions' => array_values(array_slice((array) ($activity->inclusions ?? []), 0, 2)),
            ];
        }

        $km = $this->distance->haversine($userLat, $userLng, $lat, $lng);

        return [
            'type' => 'activity',
            'id' => $activity->id,
            'name' => $activity->activity_name,
            'subtitle' => $activity->destination?->name,
            'lat' => $lat,
            'lng' => $lng,
            'rate' => $activity->rate,
            'category' => $activity->category,
            'rating' => $activity->reviewSummary?->average_rating ?? null,
            'review_count' => $activity->reviewSummary?->total_reviews ?? 0,
            'image' => collect($activity->images ?? [])->first(),
            'url' => null,
            'distance_km' => $userLat ? round($km, 2) : null,
            'distance_label' => $userLat ? $this->distance->format($km) : null,
            'no_location' => false,
            'images' => collect($activity->images ?? [])->map(fn ($i) => ResolvesImages::resolveActivityImage($i, $activity->activity_name, $activity->category))->values()->all(),
            'vibe_tags' => array_values(array_slice((array) ($activity->vibe_tags ?? []), 0, 3)),
            'inclusions' => array_values(array_slice((array) ($activity->inclusions ?? []), 0, 2)),
        ];
    }

    /**
     * Compute a sensible default map center from a set of markers.
     */
    public function centerOf(array $markers): array
    {
        if (empty($markers)) {
            return ['lat' => 12.0, 'lng' => 122.0];
        }

        $lat = collect($markers)->avg('lat');
        $lng = collect($markers)->avg('lng');

        return ['lat' => round($lat, 5), 'lng' => round($lng, 5)];
    }
}
