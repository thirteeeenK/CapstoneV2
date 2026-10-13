<?php

namespace App\Http\Controllers;

use App\Models\DestinationModel;
use App\Services\DistanceService;
use App\Services\MapService;
use App\Services\RoutingService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DssController extends Controller
{
    public function __construct(
        protected MapService $map,
        protected WeatherService $weather,
        protected DistanceService $distance,
    ) {}

    /**
     * Weather forecast for a destination (by id) or by coordinates.
     */
    public function weather(Request $request): JsonResponse
    {
        $destinationId = $request->integer('destination_id');
        if ($destinationId) {
            $destination = DestinationModel::find($destinationId);
            if (! $destination) {
                return response()->json(['error' => 'Destination not found.'], 404);
            }

            return response()->json(['data' => $this->weather->forecastForDestination($destination)]);
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            return response()->json([
                'data' => $this->weather->forecast((float) $request->input('lat'), (float) $request->input('lng')),
            ]);
        }

        return response()->json(['error' => 'Provide destination_id or lat/lng.'], 422);
    }

    /**
     * Summary weather for the destionation overview map / dashboard cards.
     */
    public function destinations(Request $request): JsonResponse
    {
        $lat = (float) $request->input('lat', 0);
        $lng = (float) $request->input('lng', 0);

        return response()->json([
            'data' => $this->map->destinationMarkers($lat, $lng),
        ]);
    }

    /**
     * Full marker set (hotels + activities) for the explorer map.
     */
    public function markers(Request $request): JsonResponse
    {
        $lat = (float) $request->input('lat', 0);
        $lng = (float) $request->input('lng', 0);

        return response()->json([
            'data' => $this->map->allMarkers($lat, $lng),
        ]);
    }

    public function route(Request $request, RoutingService $routing): JsonResponse
    {
        $validated = $request->validate([
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat' => ['required', 'numeric', 'between:-90,90'],
            'to_lng' => ['required', 'numeric', 'between:-180,180'],
            'mode' => ['sometimes', 'in:walking,driving'],
        ]);

        return response()->json(['data' => $routing->route(
            ['lat' => (float) $validated['from_lat'], 'lng' => (float) $validated['from_lng']],
            ['lat' => (float) $validated['to_lat'], 'lng' => (float) $validated['to_lng']],
            $validated['mode'] ?? null,
        )]);
    }

    public function distances(Request $request, RoutingService $routing): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'targets' => ['required', 'array', 'min:1', 'max:100'],
            'targets.*' => ['required', 'array:lat,lng'],
            'targets.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'targets.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'mode' => ['sometimes', 'in:walking,driving'],
        ]);

        return response()->json(['data' => $routing->matrix(
            [['lat' => (float) $validated['lat'], 'lng' => (float) $validated['lng']]],
            $validated['targets'],
            $validated['mode'] ?? null,
        )]);
    }

    /**
     * Nearest listings to a coordinate (used for the "near me" distance rank).
     */
    public function nearby(Request $request): JsonResponse
    {
        $lat = (float) $request->input('lat', 0);
        $lng = (float) $request->input('lng', 0);
        $type = $request->input('type', 'hotels');

        if ($type === 'activities') {
            return response()->json(['data' => $this->distance->nearestActivities($lat, $lng)]);
        }

        return response()->json(['data' => $this->distance->nearestHotels($lat, $lng)]);
    }

    /**
     * Render the full-screen explorer map page.
     */
    public function explore(Request $request)
    {
        $markers = $this->map->allMarkers();
        $center = $this->map->centerOf($markers);
        $focus = $request->query('focus');

        return view('explore', compact('markers', 'center', 'focus'));
    }
}
