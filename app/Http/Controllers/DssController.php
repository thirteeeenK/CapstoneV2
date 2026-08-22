<?php

namespace App\Http\Controllers;

use App\Models\DestinationModel;
use App\Services\DistanceService;
use App\Services\MapService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DssController extends Controller
{
    public function __construct(
        protected MapService $map,
        protected WeatherService $weather,
        protected DistanceService $distance,
    ) {
    }

    /**
     * Weather forecast for a destination (by id) or by coordinates.
     */
    public function weather(Request $request): JsonResponse
    {
        $destinationId = $request->integer('destination_id');
        if ($destinationId) {
            $destination = DestinationModel::find($destinationId);
            if (!$destination) {
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