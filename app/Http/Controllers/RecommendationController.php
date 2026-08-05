<?php

namespace App\Http\Controllers;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    /**
     * Display the user dashboard with AI Recommendations and Default Listings tabs.
     */
    public function index(GeminiService $geminiService)
    {
        $user = Auth::user();
        $userVector = $user ? $this->parseVector($user->preferences_embedding) : null;
        $isPersonalized = !empty($userVector) && $user->preferences_embedding !== '[0]' && array_sum(array_map('abs', $userVector)) > 0.0001;

        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $aiRecommendations = [];
        $defaultRecommendations = [];

        foreach ($destinations as $destination) {
            // Default listings for this destination
            $defaultHotels = HotelModel::where('is_shown', true)
                ->where('destination_id', $destination->id)
                ->with(['destination', 'rooms'])
                ->take(5)
                ->get();

            $defaultActivities = ActivityModel::where('is_shown', true)
                ->where('destination_id', $destination->id)
                ->with('destination')
                ->take(5)
                ->get();

            if ($defaultHotels->isNotEmpty() || $defaultActivities->isNotEmpty()) {
                $defaultRecommendations[] = [
                    'destination' => $destination,
                    'hotels' => $defaultHotels,
                    'activities' => $defaultActivities,
                ];
            }

            // AI Recommendations for this destination
            if ($isPersonalized) {
                $aiHotels = HotelModel::where('is_shown', true)
                    ->where('destination_id', $destination->id)
                    ->whereNotNull('embedding')
                    ->with(['destination', 'rooms'])
                    ->get()
                    ->sortByDesc(function ($hotel) use ($userVector, $geminiService) {
                        $hotelVector = $this->parseVector($hotel->embedding);
                        return $hotelVector ? $geminiService->cosineSimilarity($userVector, $hotelVector) : 0;
                    })
                    ->take(5)
                    ->values();

                $aiActivities = ActivityModel::where('is_shown', true)
                    ->where('destination_id', $destination->id)
                    ->whereNotNull('embedding')
                    ->with('destination')
                    ->get()
                    ->sortByDesc(function ($activity) use ($userVector, $geminiService) {
                        $actVector = $this->parseVector($activity->embedding);
                        return $actVector ? $geminiService->cosineSimilarity($userVector, $actVector) : 0;
                    })
                    ->take(5)
                    ->values();

                if ($aiHotels->isNotEmpty() || $aiActivities->isNotEmpty()) {
                    $aiRecommendations[] = [
                        'destination' => $destination,
                        'hotels' => $aiHotels,
                        'activities' => $aiActivities,
                    ];
                }
            }
        }

        return view('dashboard', compact('user', 'isPersonalized', 'aiRecommendations', 'defaultRecommendations'));
    }

    /**
     * Parse vector embedding string / array into float array.
     */
    private function parseVector($raw): ?array
    {
        if (empty($raw)) return null;
        if (is_array($raw)) return $raw;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            $clean = trim($raw, "[] \t\n\r");
            if (empty($clean)) return null;
            return array_map('floatval', explode(',', $clean));
        }

        return null;
    }
}
