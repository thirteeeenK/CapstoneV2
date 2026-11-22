<?php

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;

/**
 * Weather and hotel-to-activity travel facts shared by the generated
 * itineraries (dashboard suggestion, I'm Feeling Lucky, chatbot), so each one
 * shows the same forecast outlook and mapped road distances.
 */
class ItineraryInsightsService
{
    public function __construct(
        private WeatherService $weather,
        private RoutingService $routing,
        private DistanceService $distance,
    ) {}

    /**
     * @param  iterable<ActivityModel>  $activities
     * @return array{weather: ?array, legs: array<int, array{distance_km: ?float, distance_label: ?string, duration_minutes: ?int, mode: string, estimated: bool}>}
     */
    public function forStay(DestinationModel $destination, ?HotelModel $hotel, iterable $activities): array
    {
        return [
            'weather' => $this->weatherFor($destination),
            'legs' => $hotel ? $this->legsFrom($hotel, collect($activities)->all()) : [],
        ];
    }

    /**
     * Compact forecast outlook for itinerary cards.
     *
     * @return array{level: string, label: string, reason: ?string, temp: ?float, description: ?string, icon: ?string, days: list<array{date: string, max: ?float, min: ?float, pop: float, icon: ?string, description: ?string}>, stale: bool}|null
     */
    public function weatherFor(DestinationModel $destination): ?array
    {
        try {
            $summary = $this->weather->summaryForDestination($destination);
        } catch (\Throwable) {
            return null;
        }

        if (empty($summary['current'])) {
            return null;
        }

        return [
            'level' => $summary['outlook']['level'] ?? 'unknown',
            'label' => $summary['outlook']['label'] ?? 'Forecast available',
            'reason' => $summary['outlook']['reasons'][0] ?? null,
            'temp' => $summary['current']['temp'] ?? null,
            'description' => $summary['current']['description'] ?? null,
            'icon' => $summary['current']['icon'] ?? null,
            'days' => array_map(fn (array $day) => [
                'date' => substr((string) $day['time'], 0, 10),
                'max' => $day['day_max'] ?? $day['temp'] ?? null,
                'min' => $day['day_min'] ?? $day['temp'] ?? null,
                'pop' => (float) ($day['day_pop'] ?? $day['pop'] ?? 0),
                'icon' => $day['icon'] ?? null,
                'description' => $day['description'] ?? null,
            ], array_slice($summary['daily'] ?? [], 0, 5)),
            'stale' => (bool) ($summary['stale'] ?? false),
        ];
    }

    /**
     * Mapped road distance and time from the hotel to each activity, keyed by
     * activity id. Activities without their own pin are measured from the
     * destination centre and flagged as estimated.
     *
     * @param  list<ActivityModel>  $activities
     * @return array<int, array{distance_km: ?float, distance_label: ?string, duration_minutes: ?int, mode: string, estimated: bool}>
     */
    public function legsFrom(HotelModel $hotel, array $activities): array
    {
        if ($hotel->latitude === null || $hotel->longitude === null || $activities === []) {
            return [];
        }

        $targets = [];
        $indexed = [];
        foreach ($activities as $activity) {
            $lat = $activity->latitude_with_fallback;
            $lng = $activity->longitude_with_fallback;
            if ($lat === null || $lng === null) {
                continue;
            }
            $targets[] = ['lat' => $lat, 'lng' => $lng];
            $indexed[] = $activity;
        }

        if ($targets === []) {
            return [];
        }

        try {
            $matrix = $this->routing->matrix([['lat' => (float) $hotel->latitude, 'lng' => (float) $hotel->longitude]], $targets);
        } catch (\Throwable) {
            return [];
        }

        $legs = [];
        foreach ($indexed as $index => $activity) {
            $km = $matrix['distances'][0][$index] ?? null;
            $seconds = $matrix['durations'][0][$index] ?? null;
            $legs[$activity->id] = [
                'distance_km' => $km !== null ? round((float) $km, 2) : null,
                'distance_label' => $km !== null ? $this->distance->format((float) $km) : null,
                'duration_minutes' => $seconds !== null ? (int) ceil($seconds / 60) : null,
                'mode' => (string) ($matrix['mode'] ?? 'walking'),
                'estimated' => ! $activity->hasExactLocation(),
            ];
        }

        return $legs;
    }
}
