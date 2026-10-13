<?php

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\HotelModel;
use Illuminate\Database\Eloquent\Collection;

class DistanceService
{
    public function __construct(protected RoutingService $routing) {}

    /**
     * Format a distance (km) into a human-friendly label.
     */
    public function format(float $km): string
    {
        if ($km < 1) {
            return round($km * 1000).' m';
        }

        return rtrim(rtrim(number_format($km, 1), '0'), '.').' km';
    }

    /**
     * Add a computed 'distance_km' + 'distance_label' to each model that has
     * resolvable coordinates, sorted nearest-first.
     */
    public function withDistance(Collection $items, float $userLat, float $userLng): Collection
    {
        $items = $items->values();
        $targets = $items->map(function ($item): array {
            [$lat, $lng] = $this->coordsOf($item);

            return ['lat' => $lat, 'lng' => $lng];
        })->all();
        $matrix = $this->routing->matrix([['lat' => $userLat, 'lng' => $userLng]], $targets);

        return $items->map(function ($item, int $index) use ($matrix) {
            $km = $matrix['distances'][0][$index] ?? null;
            $item->distance_km = $km;
            $item->distance_label = $km !== null ? $this->format($km) : null;
            $seconds = $matrix['durations'][0][$index] ?? null;
            $item->duration_minutes = $seconds !== null ? (int) ceil($seconds / 60) : null;

            return $item;
        })->filter(fn ($item) => $item->distance_km !== null)
            ->sortBy('distance_km')
            ->values();
    }

    /**
     * Nearest hotels to a given coordinate.
     */
    public function nearestHotels(float $userLat, float $userLng, int $limit = 5): Collection
    {
        $hotels = HotelModel::where('is_shown', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('destination')
            ->get();

        return $this->withDistance($hotels, $userLat, $userLng)->take($limit);
    }

    /**
     * Nearest activities with real meeting-point coordinates.
     */
    public function nearestActivities(float $userLat, float $userLng, int $limit = 5): Collection
    {
        $activities = ActivityModel::where('is_shown', true)
            ->with('destination')
            ->get();

        return $this->withDistance($activities, $userLat, $userLng)->take($limit);
    }

    /**
     * Resolve coordinates for an arbitrary entity (hotel, activity, destination,
     * or anything exposing latitude/longitude or a destination relation).
     */
    public function coordsOf($entity): array
    {
        $lat = $entity->latitude;
        $lng = $entity->longitude;

        return [$lat !== null ? (float) $lat : null, $lng !== null ? (float) $lng : null];
    }
}
