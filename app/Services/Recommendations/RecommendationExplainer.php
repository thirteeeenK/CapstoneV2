<?php

namespace App\Services\Recommendations;

use App\Models\UserPreference;
use App\Services\GeminiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RecommendationExplainer
{
    public function __construct(private GeminiService $geminiService) {}

    /**
     * Return the saved 2-3 sentence overviews for a destination, generating and persisting
     * them on first view. They only regenerate when the user updates their preferences.
     *
     * @return array{hotels: string, activities: string}
     */
    public function forDestination(UserPreference $preference, $destination, Collection $hotels, Collection $activities): array
    {
        if ($hotels->isEmpty() && $activities->isEmpty()) {
            return ['hotels' => '', 'activities' => ''];
        }

        $saved = $preference->recommendation_explanations ?? [];
        $key = (string) $destination->id;

        if (array_key_exists($key, $saved)) {
            return $saved[$key];
        }

        $generated = $this->generateAndMerge($preference, $destination, $hotels, $activities);
        $saved[$key] = $generated;
        $preference->recommendation_explanations = $saved;
        $preference->save();

        return $generated;
    }

    /**
     * Drop every saved explanation for a user so the next dashboard view regenerates them.
     */
    public function invalidateForUser(UserPreference $preference): void
    {
        $preference->recommendation_explanations = null;
        $preference->save();
    }

    /**
     * Call Gemini (batched per destination) and fill any missing section with the fallback.
     * Grounded to the displayed island so Boracay copy never says "in El Nido".
     *
     * @return array{hotels: string, activities: string}
     */
    private function generateAndMerge(UserPreference $preference, $destination, Collection $hotels, Collection $activities, ?string $profileText = null): array
    {
        $profileText ??= $this->buildProfileText($preference);
        $fallback = $this->fallbackExplanations($preference, $destination, $hotels, $activities);
        $generated = $this->generateWithGemini($profileText, $destination, $hotels, $activities);

        if (! $generated) {
            return $fallback;
        }

        return [
            'hotels' => trim($generated['hotels'] ?? '') ?: $fallback['hotels'],
            'activities' => trim($generated['activities'] ?? '') ?: $fallback['activities'],
        ];
    }

    /**
     * Build a concise text summary of the user's stored preferences.
     */
    private function buildProfileText(UserPreference $preference): string
    {
        $lines = ['Traveler Profile:'];

        if ($preference->destination) {
            $lines[] = "Preferred destination: {$preference->destination}";
        }

        if ($preference->traveler_type) {
            $lines[] = "Traveler type: {$preference->traveler_type}";
        }

        if (! empty($preference->vibes)) {
            $lines[] = 'Vibes: '.implode(', ', $preference->vibes);
        }

        if (! empty($preference->amenities)) {
            $lines[] = 'Amenities & activities: '.implode(', ', $preference->amenities);
        }

        if ($preference->notes) {
            $lines[] = "Notes: {$preference->notes}";
        }

        return implode("\n", $lines);
    }

    /**
     * Send a batched prompt to Gemini and parse the JSON response.
     *
     * @return array{hotels: string, activities: string}|null
     */
    private function generateWithGemini(string $profileText, $destination, Collection $hotels, Collection $activities): ?array
    {
        $systemPath = app_path('Services/SystemPrompts/recommendation-explainer-prompt.md');
        if (! file_exists($systemPath)) {
            Log::warning('Recommendation explainer system prompt is missing: '.$systemPath);

            return null;
        }

        $system = file_get_contents($systemPath);
        $prompt = $this->buildPrompt($profileText, $destination, $hotels, $activities);

        $raw = $this->geminiService->generateContent($system, $prompt);

        if (! $raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            Log::warning('Recommendation explainer returned unexpected JSON: '.substr($raw, 0, 200));

            return null;
        }

        return [
            'hotels' => isset($decoded['hotels']) ? trim((string) $decoded['hotels']) : '',
            'activities' => isset($decoded['activities']) ? trim((string) $decoded['activities']) : '',
        ];
    }

    /**
     * Format the prompt payload for Gemini — always names the displayed island.
     */
    private function buildPrompt(string $profileText, $destination, Collection $hotels, Collection $activities): string
    {
        $displayName = is_object($destination) ? ($destination->name ?? '') : (string) $destination;
        $lines = [$profileText, '', "Currently showing recommendations for: {$displayName}", '', 'Ranked Recommendations:', ''];

        if ($hotels->isNotEmpty()) {
            $lines[] = 'HOTELS:';
            foreach ($hotels as $hotel) {
                $tags = array_merge(
                    $this->arrayify($hotel->vibe_tags),
                    $this->arrayify($hotel->featured_amenities)
                );
                $lines[] = "- ID {$hotel->id}: {$hotel->hotel_name} | ".implode(', ', $tags);
            }
        }

        if ($activities->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'ACTIVITIES:';
            foreach ($activities as $activity) {
                $tags = array_filter(array_merge(
                    $this->arrayify($activity->vibe_tags),
                    [$activity->category],
                    [$activity->ideal_for]
                ));
                $lines[] = "- ID {$activity->id}: {$activity->activity_name} | ".implode(', ', $tags);
            }
        }

        $lines[] = '';
        $lines[] = 'Return only the JSON object described in your instructions.';

        return implode("\n", $lines);
    }

    /**
     * Deterministic fallback when Gemini is unavailable: build a 2-3 sentence overview per section.
     * Grounded to displayed island, not preference.
     *
     * @return array{hotels: string, activities: string}
     */
    private function fallbackExplanations(UserPreference $preference, $destination, Collection $hotels, Collection $activities): array
    {
        return [
            'hotels' => $this->fallbackSectionText($preference, $destination, $hotels, 'hotel', 'stays'),
            'activities' => $this->fallbackSectionText($preference, $destination, $activities, 'activity', 'experiences'),
        ];
    }

    /**
     * Build the fallback overview paragraph for a single section — grounded to displayed island.
     * Mirrors the Gemini prompt scaffold (grounding + standout pick + benefit close) so the
     * offline copy still reads as a clear 3-sentence "why these were picked" explanation.
     */
    private function fallbackSectionText(UserPreference $preference, $destination, Collection $items, string $kind, string $noun): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $displayName = is_object($destination) ? ($destination->name ?? '') : (string) $destination;
        $dest = $displayName ? " in {$displayName}" : '';
        $traveler = trim((string) $preference->traveler_type);
        $matches = $this->matchedTags($preference, $items, $kind);
        $display = $matches !== [] ? implode(' & ', array_slice($matches, 0, 2)) : '';

        $first = $items->first();
        $standout = $kind === 'hotel'
            ? ($first->hotel_name ?? null)
            : ($first->activity_name ?? null);
        $standoutTag = $this->standoutTagForItem($preference, $first, $kind) ?? ($matches[0] ?? null);

        if ($display === '') {
            $sentence1 = "Based on your AI travel profile, these top {$noun}{$dest} were handpicked for you.";
            $sentence2 = $standout
                ? "Includes {$standout} for a balanced mix of comfort and character."
                : 'Each one balances comfort and character for easy choosing.';
            $sentence3 = $traveler !== ''
                ? "Perfect if you want a smooth {$traveler} escape{$dest} without the guesswork."
                : "Perfect if you want a smooth, easy escape{$dest} without the guesswork.";

            return "{$sentence1} {$sentence2} {$sentence3}";
        }

        $sentence1 = "Based on your preference for {$display}, these top {$noun}{$dest} were picked for you.";
        $sentence2 = $standout && $standoutTag
            ? "Includes {$standout} for its {$standoutTag}."
            : "Each one matches your {$display} taste.";
        $sentence3 = $traveler !== ''
            ? "Perfect if you want {$display} experiences made for {$traveler} travelers."
            : "Perfect if you want {$display} without the guesswork.";

        return "{$sentence1} {$sentence2} {$sentence3}";
    }

    /**
     * Find the first user tag (in display form) that appears on the given item.
     */
    private function standoutTagForItem(UserPreference $preference, mixed $item, string $kind): ?string
    {
        if (! $item) {
            return null;
        }

        $userTagMap = $this->normalizeTagMap(array_merge(
            $this->arrayify($preference->vibes),
            $this->arrayify($preference->amenities)
        ));

        if ($userTagMap === []) {
            return null;
        }

        $itemTags = $kind === 'hotel'
            ? $this->normalizeTags(array_merge(
                $this->arrayify($item->vibe_tags ?? null),
                $this->arrayify($item->featured_amenities ?? null)
            ))
            : $this->normalizeTags(array_filter(array_merge(
                $this->arrayify($item->vibe_tags ?? null),
                [$item->category ?? null],
                [$item->ideal_for ?? null]
            )));

        foreach ($itemTags as $normalized) {
            if (isset($userTagMap[$normalized])) {
                return $userTagMap[$normalized];
            }
        }

        return null;
    }

    /**
     * Return the user's tags (normalized to their original display form) that appear on any item.
     *
     * @return array<int, string>
     */
    private function matchedTags(UserPreference $preference, Collection $items, string $kind): array
    {
        $userTagMap = $this->normalizeTagMap(array_merge(
            $this->arrayify($preference->vibes),
            $this->arrayify($preference->amenities)
        ));

        $matches = [];

        foreach ($items as $item) {
            if ($kind === 'hotel') {
                $itemTags = $this->normalizeTags(array_merge(
                    $this->arrayify($item->vibe_tags),
                    $this->arrayify($item->featured_amenities)
                ));
            } else {
                $itemTags = $this->normalizeTags(array_filter(array_merge(
                    $this->arrayify($item->vibe_tags),
                    [$item->category],
                    [$item->ideal_for]
                )));
            }

            foreach ($itemTags as $normalized) {
                if (isset($userTagMap[$normalized])) {
                    $matches[] = $userTagMap[$normalized];
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * Convert a json/array/string value into a clean array of strings.
     */
    private function arrayify(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return array_filter(array_map('trim', explode(',', $value)));
        }

        return [];
    }

    /**
     * Return a map of normalized tag => original tag.
     */
    private function normalizeTagMap(array $tags): array
    {
        $map = [];
        foreach ($tags as $tag) {
            $normalized = strtolower(trim((string) $tag));
            if ($normalized === '') {
                continue;
            }
            if (! isset($map[$normalized])) {
                $map[$normalized] = trim((string) $tag);
            }
        }

        return $map;
    }

    /**
     * Return a simple list of normalized tags.
     */
    private function normalizeTags(array $tags): array
    {
        return array_values(array_filter(array_map(
            fn ($tag) => strtolower(trim((string) $tag)),
            $tags
        )));
    }
}
