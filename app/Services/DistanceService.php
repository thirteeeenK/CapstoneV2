<?php

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use Illuminate\Database\Eloquent\Collection;

class DistanceService
{
    /**
     * Earth mean radius in kilometers.
     */
    public const EARTH_RADIUS_KM = 6371.0;

    /**
     * Haversine great-circle distance in kilometers.
     */
    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        $dLat = $lat2 - $lat1;
        $dLng = $lng2 - $lng1;

        $a = sin($dLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Format a distance (km) into a human-friendly label.
     */
    public function format(float $km): string
    {
        if ($km < 1) {
            return round($km * 1000) . ' m';
        }
        return rtrim(rtrim(number_format($km, 1), '0'), '.') . ' km';
    }

    /**
     * Add a computed 'distance_km' + 'distance_label' to each model that has
     * resolvable coordinates, sorted nearest-first.
     */
    public function withDistance(Collection $items, float $userLat, float $userLng): Collection
    {
        return $items->map(function ($item) use ($userLat, $userLng) {
            [$lat, $lng] = $this->coordsOf($item);
            if ($lat === null || $lng === null) {
                $item->distance_km = null;
                $item->distance_label = null;
                return $item;
            }
            $km = $this->haversine($userLat, $userLng, (float) $lat, (float) $lng);
            $item->distance_km = $km;
            $item->distance_label = $this->format($km);
            return $item;
        })->filter(fn($item) => $item->distance_km !== null)
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
     * Nearest activities to a given coordinate (uses destination fallback).
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
        if ($entity instanceof ActivityModel) {
            return [(float) $entity->latitude_with_fallback, (float) $entity->longitude_with_fallback];
        }

        if ($entity instanceof DestinationModel) {
            return [(float) $entity->latitude, (float) $entity->longitude];
        }

        if ($entity instanceof HotelModel) {
            return [(float) $entity->latitude, (float) $entity->longitude];
        }

        $lat = $entity->latitude ?? $entity->destination?->latitude ?? null;
        $lng = $entity->longitude ?? $entity->destination?->longitude ?? null;

        return [(float) $lat, (float) $lng];
    }
}
