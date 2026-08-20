<?php

namespace App\Http\Controllers;

use App\Models\DestinationModel;
use App\Models\OnboardingOption;
use App\Models\UserPreference;
use App\Services\GeminiService;
use App\Services\Recommendations\RecommendationExplainer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $userVector = $user ? $this->parseVector($user->preferences_embedding) : null;
        $isPersonalized = ! empty($userVector) && $user->preferences_embedding !== '[0]' && array_sum(array_map('abs', $userVector)) > 0.0001;

        // If user already has personalized preferences and visits /onboarding without edit flag, redirect to dashboard
        if ($isPersonalized && ! request()->has('edit')) {
            return redirect()->route('dashboard');
        }

        // Only show 'Skip for now' if user does NOT have personalized preferences yet!
        $canSkip = ! $isPersonalized;
        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $options = OnboardingOption::active()->orderBy('sort_order')->get();

        $vibeOptions = $options->where('type', 'vibe')->map(fn ($option) => [
            'name' => $option->name,
            'icon' => $option->icon,
            'desc' => $option->description,
        ])->values();

        $groupTypes = $options->where('type', 'traveler_type')->map(fn ($option) => [
            'name' => $option->name,
            'icon' => $option->icon,
            'desc' => $option->description,
        ])->values();

        $amenityPills = $options->where('type', 'amenity')->pluck('name')->values();

        return view('onboarding.index', compact('destinations', 'canSkip', 'isPersonalized', 'vibeOptions', 'groupTypes', 'amenityPills'));
    }

    public function store(Request $request, GeminiService $geminiService, RecommendationExplainer $explainer)
    {
        $request->validate([
            'vibes' => 'nullable|array',
            'destination' => 'nullable|string',
            'traveler_type' => 'nullable|string',
            'amenities' => 'nullable|array',
            'notes' => 'nullable|string|max:500',
        ]);

        $vibesList = ! empty($request->vibes) ? implode(', ', $request->vibes) : 'Beachfront, Island Energy';
        $amenitiesList = ! empty($request->amenities) ? implode(', ', $request->amenities) : 'Standard Luxuries';
        $destName = $request->destination ?: 'Any Island Sanctuary';
        $travelerType = $request->traveler_type ?: 'Vacationer';
        $notesText = trim($request->notes ?? '');

        $semanticText = implode("\n", array_filter([
            'User Travel Preferences & Profile:',
            "Preferred Destination: {$destName}",
            "Travel Atmosphere & Vibe Preferences: {$vibesList}",
            "Traveler Group Type: {$travelerType}",
            "Desired Amenities & Activities: {$amenitiesList}",
            $notesText ? "Custom Requests: {$notesText}" : null,
        ]));

        $vector = $geminiService->generateEmbedding($semanticText, 'RETRIEVAL_QUERY');

        $user = Auth::user();
        if ($user) {
            if ($vector) {
                $user->preferences_embedding = $geminiService->formatVectorForDb($vector);
            } else {
                // Fallback zero vector if API is offline
                $zeroVector = array_fill(0, 3072, 0.0);
                $user->preferences_embedding = $geminiService->formatVectorForDb($zeroVector);
            }
            $user->save();

            $preference = UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'destination' => $request->destination,
                    'traveler_type' => $request->traveler_type,
                    'vibes' => $request->vibes ?? [],
                    'amenities' => $request->amenities ?? [],
                    'notes' => $request->input('notes'),
                ]
            );

            $explainer->invalidateForUser($preference);
        }

        return redirect()->route('dashboard')->with('success', 'Your AI Travel Profile has been saved! Welcome to your Dashboard.');
    }

    /**
     * Re-trigger onboarding edit mode.
     */
    public function reset()
    {
        return redirect()->route('onboarding.index', ['edit' => 1]);
    }

    /**
     * Skip onboarding quiz and set 3072D zero vector marker.
     */
    public function skip(GeminiService $geminiService)
    {
        $user = Auth::user();
        if ($user) {
            $zeroVector = array_fill(0, 3072, 0.0);
            $user->preferences_embedding = $geminiService->formatVectorForDb($zeroVector);
            $user->save();

            UserPreference::where('user_id', $user->id)->delete();
        }

        return redirect()->route('dashboard')->with('info', 'Onboarding skipped. Showing popular island highlights.');
    }

    /**
     * Helper to parse vector embedding format.
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
