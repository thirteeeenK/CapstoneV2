<?php

namespace App\Http\Controllers;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Services\GeminiService;
use App\Services\MapService;
use App\Services\Recommendations\RecommendationExplainer;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    /**
     * Display the user dashboard with AI Recommendations and Default Listings tabs.
     */
    public function index(GeminiService $geminiService, MapService $mapService, WeatherService $weatherService, RecommendationExplainer $explainer)
    {
        $user = Auth::user();
        $userVector = $user ? $this->parseVector($user->preferences_embedding) : null;
        $isPersonalized = ! empty($userVector) && $user->preferences_embedding !== '[0]' && array_sum(array_map('abs', $userVector)) > 0.0001;
        $userPreference = $user?->userPreference;

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

            $defaultPackages = Package::where('is_active', true)
                ->where('destination_id', $destination->id)
                ->with('destination')
                ->take(4)
                ->get();

            if ($defaultHotels->isNotEmpty() || $defaultActivities->isNotEmpty() || $defaultPackages->isNotEmpty()) {
                $defaultRecommendations[] = [
                    'destination' => $destination,
                    'hotels' => $defaultHotels,
                    'activities' => $defaultActivities,
                    'packages' => $defaultPackages,
                ];
            }

            // AI Recommendations for this destination
            if ($isPersonalized) {
                $allAiHotels = HotelModel::where('is_shown', true)
                    ->where('destination_id', $destination->id)
                    ->whereNotNull('embedding')
                    ->with(['destination', 'rooms'])
                    ->get();

                $rankedHotels = collect($geminiService->rankRecommendations($userVector, $allAiHotels, 5));
                $aiHotels = $rankedHotels->map(fn ($entry) => $entry['item']);

                $allAiActivities = ActivityModel::where('is_shown', true)
                    ->where('destination_id', $destination->id)
                    ->whereNotNull('embedding')
                    ->with('destination')
                    ->get();

                $rankedActivities = collect($geminiService->rankRecommendations($userVector, $allAiActivities, 5));
                $aiActivities = $rankedActivities->map(fn ($entry) => $entry['item']);

                $allAiPackages = Package::where('is_active', true)
                    ->where('destination_id', $destination->id)
                    ->whereNotNull('embedding')
                    ->with('destination')
                    ->get();

                if ($allAiPackages->isNotEmpty()) {
                    $rankedPackages = collect($geminiService->rankRecommendations($userVector, $allAiPackages, 4));
                    $aiPackages = $rankedPackages->map(fn ($entry) => $entry['item']);
                    if ($aiPackages->count() < 4) {
                        $remaining = Package::where('is_active', true)
                            ->where('destination_id', $destination->id)
                            ->whereNotIn('id', $aiPackages->pluck('id'))
                            ->with('destination')
                            ->take(4 - $aiPackages->count())
                            ->get();
                        $aiPackages = $aiPackages->concat($remaining);
                    }
                } else {
                    $aiPackages = Package::where('is_active', true)
                        ->where('destination_id', $destination->id)
                        ->with('destination')
                        ->take(4)
                        ->get();
                }

                $reasons = $userPreference
                    ? $explainer->forDestination($userPreference, $destination, $aiHotels, $aiActivities)
                    : ['hotels' => '', 'activities' => ''];

                if ($aiHotels->isNotEmpty() || $aiActivities->isNotEmpty() || $aiPackages->isNotEmpty()) {
                    $aiRecommendations[] = [
                        'destination' => $destination,
                        'hotels' => $aiHotels,
                        'activities' => $aiActivities,
                        'packages' => $aiPackages,
                        'hotels_reason' => $reasons['hotels'] ?? '',
                        'activities_reason' => $reasons['activities'] ?? '',
                    ];
                }
            }
        }

        // DSS overview markers (one per destination with weather + listing counts)
        $mapMarkers = $mapService->destinationMarkers();
        $weatherCards = [];
        foreach ($mapMarkers as $marker) {
            $destination = DestinationModel::find($marker['id']);
            if ($destination) {
                $weatherCards[$destination->id] = $weatherService->summaryForDestination($destination);
            }
        }

        return view('dashboard', compact('user', 'isPersonalized', 'aiRecommendations', 'defaultRecommendations', 'mapMarkers', 'weatherCards'));
    }

    /**
     * Parse vector embedding string / array into float array.
     */
    private function parseVector($raw): ?array
    {
        if (empty($raw)) {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            $clean = trim($raw, "[] \t\n\r");
            if (empty($clean)) {
                return null;
            }

            return array_map('floatval', explode(',', $clean));
        }

        return null;
    }
}
