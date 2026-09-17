<?php

namespace App\Http\Controllers;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RecommendationHit;
use App\Services\GeminiService;
use App\Services\MapService;
use App\Services\Recommendations\RecommendationExplainer;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        $preferredName = trim((string) $userPreference?->destination);
        $isWildcard = $preferredName === '' || $preferredName === 'Open to Any Destination';
        $preferredDest = $isWildcard
            ? $destinations->firstWhere('name', 'Boracay')
            : $destinations->firstWhere('name', $preferredName);
        // Fallback: Boracay hard-coded for wildcard, or first available if stale name.
        $preferredDestId = $preferredDest?->id
            ?? $destinations->firstWhere('name', 'Boracay')?->id
            ?? $destinations->first()?->id;

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

        // Hit-rate tracking: one session per user, locked on their first click.
        // Until then the impression row (and its token) is reused across reloads so
        // the real first click still lands. After the first click, detection stops.
        // ponytail: impression+clicks in one table, split into recommendation_sessions if rival-index scans ever matter.
        $recSessionToken = null;
        if ($user && $isPersonalized && $aiRecommendations !== []) {
            $hasClicked = RecommendationHit::where('user_id', $user->id)
                ->whereIn('entity_type', [RecommendationHit::TYPE_HOTEL, RecommendationHit::TYPE_ACTIVITY, RecommendationHit::TYPE_NAV])
                ->exists();

            if (! $hasClicked) {
                $impression = RecommendationHit::where('user_id', $user->id)
                    ->where('entity_type', RecommendationHit::TYPE_IMPRESSION)
                    ->first();

                if ($impression) {
                    $recSessionToken = $impression->session_token;
                } else {
                    $recSessionToken = (string) Str::uuid();
                    RecommendationHit::create([
                        'user_id' => $user->id,
                        'session_token' => $recSessionToken,
                        'mode' => RecommendationHit::MODE_AI,
                        'entity_type' => RecommendationHit::TYPE_IMPRESSION,
                    ]);
                }
            }
        }

        return view('dashboard', compact('user', 'isPersonalized', 'aiRecommendations', 'defaultRecommendations', 'mapMarkers', 'weatherCards', 'preferredDestId', 'recSessionToken'));
    }

    /**
     * Fire-and-forget beacon: a recommendation card click, or a nav-exit
     * (same-origin link/button outside the rec cards), within a tracked session.
     */
    public function click(Request $request)
    {
        // Only the user's first click is ever recorded.
        $alreadyTracked = RecommendationHit::where('user_id', $request->user()->id)
            ->whereIn('entity_type', [RecommendationHit::TYPE_HOTEL, RecommendationHit::TYPE_ACTIVITY, RecommendationHit::TYPE_NAV])
            ->exists();

        if ($alreadyTracked) {
            return response()->noContent();
        }

        $data = $request->validate([
            'session_token' => ['required', 'string', 'max:64'],
            'mode' => ['required', 'in:ai,default'],
            'entity_type' => ['required', 'in:hotel,activity,nav'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'rank' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        // Ignore beacons for unknown sessions (stale page, double render).
        $impression = RecommendationHit::where('session_token', $data['session_token'])
            ->where('entity_type', RecommendationHit::TYPE_IMPRESSION)
            ->first();

        if (! $impression || (int) $impression->user_id !== (int) $request->user()->id) {
            return response()->noContent();
        }

        RecommendationHit::create($data + ['user_id' => $request->user()->id]);

        return response()->noContent();
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
