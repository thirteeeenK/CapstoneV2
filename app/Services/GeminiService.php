<?php

namespace App\Services;

use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\User;
use App\Models\ChatbotAbuseReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class GeminiService
{
    /**
     * Builds structured, semantically optimized text for ActivityModel embedding generation.
     */
    public function buildActivityEmbeddingText(ActivityModel $activity, ?string $destinationName = null): string
    {
        if (!$destinationName && $activity->destination_id) {
            $destination = DestinationModel::find($activity->destination_id);
            $destinationName = $destination ? $destination->name : null;
        }

        $dest = $destinationName ?? 'Unknown Destination';
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($activity->description ?? '')));
        $notes = trim(preg_replace('/\s+/', ' ', strip_tags($activity->notes ?? '')));
        $reqs = trim(preg_replace('/\s+/', ' ', strip_tags($activity->requirements ?? '')));
        $vibeList = $this->formatListToString($activity->vibe_tags);
        $inclusionsList = $this->formatListToString($activity->inclusions);
        $exclusionsList = $this->formatListToString($activity->exclusions);
        $itineraryList = '';
        if (is_array($activity->itinerary)) {
            foreach ($activity->itinerary as $step) {
                if (isset($step['title'])) {
                    $itineraryList .= $step['title'] . (isset($step['duration']) ? ' (' . $step['duration'] . ')' : '') . '. ';
                }
            }
        }

        return implode("\n", array_filter([
            "Activity Name: {$activity->activity_name}",
            "Category: {$activity->category}",
            "Activity Level: {$activity->activity_level}",
            "Destination: {$dest}",
            $activity->duration ? "Duration: {$activity->duration}" : null,
            $activity->capacity ? "Group Capacity: {$activity->capacity}" : null,
            $activity->ideal_for ? "Ideal Participants: {$activity->ideal_for}" : null,
            "Rate / Pricing: {$activity->rate}",
            $reqs ? "Requirements & Restrictions: {$reqs}" : null,
            $vibeList ? "Experience Vibes & Tags: {$vibeList}" : null,
            $inclusionsList ? "Inclusions: {$inclusionsList}" : null,
            $exclusionsList ? "Exclusions: {$exclusionsList}" : null,
            $itineraryList ? "Itinerary: {$itineraryList}" : null,
            $notes ? "Additional Notes: {$notes}" : null,
            $desc ? "Detailed Experience Description: {$desc}" : null,
        ]));
    }

    /**
     * Builds structured, semantically optimized text for AddOnModel embedding generation.
     */
    public function buildAddOnEmbeddingText(AddOnModel $addon, ?string $destinationName = null): string
    {
        if (!$destinationName && $addon->destination_id) {
            $destination = DestinationModel::find($addon->destination_id);
            $destinationName = $destination ? $destination->name : null;
        }

        $dest = $destinationName ?? 'Unknown Destination';
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($addon->description ?? '')));
        $inclusionsList = $this->formatListToString($addon->inclusions);

        $pricingList = '';
        if (is_array($addon->pricing_tiers)) {
            foreach ($addon->pricing_tiers as $tier) {
                if (isset($tier['rate'])) {
                    $min = $tier['min_pax'] ?? 1;
                    $max = $tier['max_pax'] ?? $min;
                    $pricingList .= "{$min}-{$max} pax: ₱{$tier['rate']}/person. ";
                }
            }
        }

        $surchargeList = '';
        if (is_array($addon->surcharges)) {
            foreach ($addon->surcharges as $sur) {
                if (isset($sur['name'], $sur['amount'])) {
                    $surchargeList .= "{$sur['name']} (₱{$sur['amount']}" . (isset($sur['type']) ? ' ' . $sur['type'] : '') . "). ";
                }
            }
        }

        return implode("\n", array_filter([
            "Add-on / Service Name: {$addon->name}",
            "Service Type: {$addon->type}",
            "Destination: {$dest}",
            $inclusionsList ? "Inclusions & Features: {$inclusionsList}" : null,
            $pricingList ? "Tiered Pricing Rates: {$pricingList}" : null,
            $surchargeList ? "Surcharges & Additional Fees: {$surchargeList}" : null,
            $desc ? "Detailed Overview: {$desc}" : null,
        ]));
    }

    /**
     * Builds structured, semantically optimized text for Package embedding generation.
     */
    public function buildPackageEmbeddingText(\App\Models\Package $package): string
    {
        $destinationName = $package->destination ? $package->destination->name : 'Philippines';
        $inclusions = is_array($package->generic_inclusions) ? implode(', ', $package->generic_inclusions) : '';

        return implode("\n", array_filter([
            "Tour Package Name: {$package->name}",
            "Package Type: " . ($package->type ?: 'Standard Tour Promo'),
            "Destination: {$destinationName}",
            "Rate / Price: ₱" . number_format($package->price, 2),
            "Duration: " . ($package->days ?: 3) . " Days / " . ($package->nights ?: 2) . " Nights",
            "Minimum Guests Required: " . ($package->min_pax ?: 2) . " Pax",
            $inclusions ? "Included Inclusions & Features: {$inclusions}" : "All-inclusive promo package",
        ]));
    }

    /**
     * Builds structured, semantically optimized text for Hotel embedding generation.
     */
    public function buildHotelEmbeddingText(HotelModel $hotel, ?string $destinationName = null): string
    {
        if (!$destinationName && $hotel->destination_id) {
            $destination = DestinationModel::find($hotel->destination_id);
            $destinationName = $destination ? $destination->name : null;
        }

        $dest = $destinationName ?? 'Unknown Destination';
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($hotel->hotel_description ?? '')));
        $vibeList = $this->formatListToString($hotel->vibe_tags);
        $amenitiesList = $this->formatListToString($hotel->featured_amenities);

        return implode("\n", array_filter([
            "Hotel Name: {$hotel->hotel_name}",
            "Destination: {$dest}",
            $hotel->type ? "Hotel Category: " . ucwords(str_replace('-', ' ', $hotel->type)) : null,
            $vibeList ? "Hotel Vibe & Atmosphere: {$vibeList}" : null,
            $amenitiesList ? "Featured Amenities & Facilities: {$amenitiesList}" : null,
            "Specific Address: {$hotel->specific_address}",
            ($hotel->latitude && $hotel->longitude) ? "Location Coordinates: Latitude {$hotel->latitude}, Longitude {$hotel->longitude}" : null,
            "Detailed Overview: {$desc}"
        ]));
    }

    /**
     * Builds structured, semantically optimized text for RoomType embedding generation.
     */
    public function buildRoomEmbeddingText(RoomType $room, ?string $hotelName = null, ?string $destinationName = null): string
    {
        if (!$hotelName && $room->hotel_id) {
            $hotel = $room->hotel ?? HotelModel::with('destination')->find($room->hotel_id);
            if ($hotel) {
                $hotelName = $hotel->hotel_name;
                $destinationName = $destinationName ?? ($hotel->destination?->name);
            }
        }

        $idealGuest = $room->ideal_guest ?? $room->ideal_for;
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($room->description ?? '')));
        $notes = trim(preg_replace('/\s+/', ' ', strip_tags($room->additional_notes ?? '')));
        $amenitiesList = $this->formatListToString($room->room_amenities);

        return implode("\n", array_filter([
            $hotelName ? "Hotel Name: {$hotelName}" : null,
            "Room Name: {$room->room_name}",
            $destinationName ? "Destination: {$destinationName}" : null,
            $idealGuest ? "Ideal Guest: {$idealGuest}" : null,
            $room->occupancy ? "Occupancy: {$room->occupancy} guests maximum" : null,
            $room->bed_configuration ? "Bed Layout: {$room->bed_configuration}" : null,
            $room->room_size ? "Room Dimensions: {$room->room_size}" : null,
            "Base Price: ₱" . number_format($room->base_price, 2) . " per night",
            $amenitiesList ? "Room Amenities: {$amenitiesList}" : null,
            $notes ? "Additional Notes & Policies: {$notes}" : null,
            $room->view_type ? "Room View: {$room->view_type}" : null,
            "Inventory Capacity: {$room->total_rooms} total rooms available",
            $desc ? "Detailed Room Description: {$desc}" : null,
        ]));
    }

    /**
     * Safely formats an array or string list of items (e.g. amenities, tags, inclusions) into a clean, comma-separated string.
     * Prevents TypeError when items inside arrays are sub-arrays or non-string types.
     */
    public function formatListToString(mixed $data): string
    {
        if (empty($data)) {
            return '';
        }

        if (is_string($data)) {
            return trim($data);
        }

        if (is_array($data)) {
            $items = [];
            foreach ($data as $item) {
                if (is_string($item)) {
                    $trimmed = trim($item);
                    if ($trimmed !== '') {
                        $items[] = $trimmed;
                    }
                } elseif (is_array($item)) {
                    $val = $item['name'] ?? $item['title'] ?? implode(', ', array_filter(array_map(fn($v) => is_string($v) ? trim($v) : null, $item)));
                    if (is_string($val) && trim($val) !== '') {
                        $items[] = trim($val);
                    }
                } elseif (is_scalar($item)) {
                    $trimmed = trim((string) $item);
                    if ($trimmed !== '') {
                        $items[] = $trimmed;
                    }
                }
            }
            return implode(', ', $items);
        }

        return '';
    }

    /**
     * Generates an L2-normalized vector embedding using Gemini API.
     * Task types: RETRIEVAL_DOCUMENT (for storing DB records) or RETRIEVAL_QUERY (for searching).
     */
    public function generateEmbedding(string $text, string $taskType = 'RETRIEVAL_DOCUMENT', ?string $title = null): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            Log::warning('Gemini API key is not configured in services.gemini.api_key.');
            return null;
        }

        $modelName = config('services.gemini.embedding_model') ?? 'models/text-embedding-001';
        $url = "https://generativelanguage.googleapis.com/v1beta/{$modelName}:embedContent?key={$apiKey}";

        $payload = [
            'model' => $modelName,
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ],
            'taskType' => $taskType
        ];

        if ($title) {
            $payload['title'] = $title;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, $payload);

            if ($response->successful() && isset($response->json()['embedding']['values'])) {
                $rawVector = $response->json()['embedding']['values'];
                return $this->normalizeVector($rawVector);
            }

            Log::error('Gemini Embedding Failed: ', ['response' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Gemini Embedding Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Normalizes a vector embedding using L2-norm (Euclidean norm).
     * Ensures dot product equals cosine similarity for fast vector retrieval.
     */
    public function normalizeVector(array $vector): array
    {
        $sum = 0.0;
        foreach ($vector as $val) {
            $sum += $val * $val;
        }
        $magnitude = sqrt($sum);

        if ($magnitude == 0) {
            return $vector;
        }

        return array_map(fn($v) => $v / $magnitude, $vector);
    }

    /**
     * Helper to format a vector array into pgvector or string representation for DB.
     */
    public function formatVectorForDb(?array $vector): ?string
    {
        if (empty($vector) || !is_array($vector)) {
            return null;
        }
        return '[' . implode(',', $vector) . ']';
    }

    /**
     * Cosine similarity calculation between two normalized vectors.
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        foreach ($vecA as $key => $value) {
            if (isset($vecB[$key])) {
                $dotProduct += $value * $vecB[$key];
            }
        }
        return $dotProduct;
    }

    /**
     * Ranks a collection of Eloquent models (Hotels, Rooms, Activities) based on cosine similarity
     * against the user's normalized preference vector.
     */
    public function rankRecommendations(array $userPreferenceVector, $items, int $limit = 5): array
    {
        $scored = [];

        foreach ($items as $item) {
            $itemVector = null;
            if (is_array($item->embedding)) {
                $itemVector = $item->embedding;
            } elseif (is_string($item->embedding)) {
                $clean = trim($item->embedding, "[] \t\n\r");
                if (!empty($clean)) {
                    $itemVector = array_map('floatval', explode(',', $clean));
                }
            }

            if ($itemVector) {
                $score = $this->cosineSimilarity($userPreferenceVector, $itemVector);
                $scored[] = [
                    'item' => $item,
                    'score' => $score
                ];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    public function extractPricingContext($contextText, $userQuery)
    {
        $paxCount = null;
        if (preg_match('/(\d+)\s*(?:pax|persons?|people|tao|katao|miyembro|guests?)/i', $userQuery, $paxMatch)) {
            $paxCount = (int) $paxMatch[1];
        }

        $nightCount = null;
        if (preg_match('/(\d+)\s*(?:nights?|gabi|days?|araw)/i', $userQuery, $nightMatch)) {
            $nightCount = (int) $nightMatch[1];
        }

        preg_match_all(
            '/(?:₱|php|peso)?\s*(\d[\d,]{2,})\s*(?:per\s*(?:pax|person|head|night|room)|minimum|starting|from)?/i',
            $contextText,
            $priceMatches
        );

        $extractedPrices = [];
        foreach (($priceMatches[1] ?? []) as $raw) {
            $clean = (int) str_replace(',', '', $raw);
            if ($clean >= 100 && $clean <= 500000) {
                $extractedPrices[] = $clean;
            }
        }
        $extractedPrices = array_unique($extractedPrices);

        if (empty($extractedPrices))
            return $contextText;

        $calcHint = "\n\n--- PRE-COMPUTED CALCULATIONS (use these in your answer) ---\n";
        $hasCalc = false;

        foreach ($extractedPrices as $price) {
            $formatted = number_format($price);

            if ($paxCount) {
                $totalPax = $price * $paxCount;
                $calcHint .= "• ₱{$formatted} × {$paxCount} pax = ₱" . number_format($totalPax) . "\n";
                $hasCalc = true;
            }

            if ($nightCount) {
                $totalNight = $price * $nightCount;
                $calcHint .= "• ₱{$formatted} × {$nightCount} nights = ₱" . number_format($totalNight) . "\n";
                $hasCalc = true;
            }

            if ($paxCount && $nightCount) {
                $totalBoth = $price * $paxCount * $nightCount;
                $calcHint .= "• ₱{$formatted} × {$paxCount} pax × {$nightCount} nights = ₱" . number_format($totalBoth) . "\n";
            }
        }

        if (!$hasCalc)
            return $contextText;
        $calcHint .= "---\n";

        return $contextText . $calcHint;
    }

    // =========================================================================
    //  Hotel-Specific RAG & Recommendation Methods
    // =========================================================================

    /**
     * Semantic search: generates a RETRIEVAL_QUERY embedding from the user's natural-language
     * query, then ranks all embedded hotels by cosine similarity.
     *
     * @param  string  $query   The user's search query (e.g. "luxury beachfront resort in Boracay")
     * @param  int     $limit   Maximum results to return
     * @return array   Scored results: [['item' => HotelModel, 'score' => float], ...]
     */
    public function searchHotels(string $query, int $limit = 5): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');

        if (!$queryVector) {
            Log::warning('searchHotels: Failed to generate query embedding.', ['query' => $query]);
            return [];
        }

        $hotels = HotelModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->where('embedding', '!=', '')
            ->get();

        if ($hotels->isEmpty()) {
            return [];
        }

        return $this->rankRecommendations($queryVector, $hotels, $limit);
    }

    /**
     * Formats scored hotel results into structured context text for RAG injection
     * into the Gemini chat prompt. Each hotel block includes all semantically
     * relevant fields so the LLM can reason about them accurately.
     *
     * @param  array  $scoredHotels  Output from searchHotels() or rankRecommendations()
     * @return string  Formatted context string ready for prompt injection
     */
    public function getHotelContext(array $scoredHotels): string
    {
        if (empty($scoredHotels)) {
            return '';
        }

        $blocks = [];

        foreach ($scoredHotels as $index => $entry) {
            /** @var HotelModel $hotel */
            $hotel = $entry['item'];
            $score = round($entry['score'], 4);
            $rank = $index + 1;

            $destName = $hotel->destination->name ?? 'Unknown Destination';
            $typeLabel = ucwords(str_replace('-', ' ', $hotel->type ?? 'N/A'));

            $vibes = $this->formatListToString($hotel->vibe_tags);
            $amenities = $this->formatListToString($hotel->featured_amenities);

            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($hotel->hotel_description ?? '')));

            $lines = array_filter([
                "--- Hotel #{$rank} (relevance: {$score}) ---",
                "Name: {$hotel->hotel_name}",
                "Destination: {$destName}",
                "Category: {$typeLabel}",
                $vibes ? "Vibes & Atmosphere: {$vibes}" : null,
                $amenities ? "Featured Amenities: {$amenities}" : null,
                "Address: {$hotel->specific_address}",
                ($hotel->latitude && $hotel->longitude)
                ? "Coordinates: {$hotel->latitude}, {$hotel->longitude}"
                : null,
                $desc ? "Description: {$desc}" : null,
            ]);

            $blocks[] = implode("\n", $lines);
        }

        return "=== HOTEL DATABASE RESULTS ===\n\n" . implode("\n\n", $blocks) . "\n\n=== END HOTEL RESULTS ===";
    }

    /**
     * Recommendation engine entry point: given a user's pre-computed preference vector,
     * retrieves all embedded hotels and ranks them by cosine similarity.
     *
     * @param  array  $userPreferenceVector  L2-normalized preference vector (e.g. from user profile)
     * @param  int    $limit                 Maximum results to return
     * @return array  Scored results: [['item' => HotelModel, 'score' => float], ...]
     */
    public function getHotelRecommendations(array $userPreferenceVector, int $limit = 5): array
    {
        $hotels = HotelModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->where('embedding', '!=', '')
            ->get();

        if ($hotels->isEmpty()) {
            return [];
        }

        return $this->rankRecommendations($userPreferenceVector, $hotels, $limit);
    }

    // =========================================================================
    //  Room-Specific RAG & Recommendation Methods
    // =========================================================================

    /**
     * Semantic search: generates a RETRIEVAL_QUERY embedding from the user's natural-language
     * query, then ranks all embedded rooms by cosine similarity.
     *
     * @param  string  $query   The user's search query (e.g. "king bed ocean view room in Boracay")
     * @param  int     $limit   Maximum results to return
     * @return array   Scored results: [['item' => RoomType, 'score' => float], ...]
     */
    public function searchRooms(string $query, int $limit = 5): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');

        if (!$queryVector) {
            Log::warning('searchRooms: Failed to generate query embedding.', ['query' => $query]);
            return [];
        }

        $rooms = RoomType::with('hotel.destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->where('embedding', '!=', '')
            ->get();

        if ($rooms->isEmpty()) {
            return [];
        }

        return $this->rankRecommendations($queryVector, $rooms, $limit);
    }

    /**
     * Formats scored room results into structured context text for RAG injection
     * into the Gemini chat prompt. Each room block includes all semantically
     * relevant fields so the LLM can reason about them accurately.
     *
     * @param  array  $scoredRooms  Output from searchRooms() or rankRecommendations()
     * @return string  Formatted context string ready for prompt injection
     */
    public function getRoomContext(array $scoredRooms): string
    {
        if (empty($scoredRooms)) {
            return '';
        }

        $blocks = [];

        foreach ($scoredRooms as $index => $entry) {
            /** @var RoomType $room */
            $room = $entry['item'];
            $score = round($entry['score'], 4);
            $rank = $index + 1;

            $hotel = $room->hotel;
            $hotelName = $hotel->hotel_name ?? 'Unknown Hotel';
            $destName = $hotel->destination->name ?? 'Unknown Destination';

            $amenities = $this->formatListToString($room->room_amenities);

            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($room->description ?? '')));

            $idealGuest = $room->ideal_guest ?? $room->ideal_for;
            $notes = trim(preg_replace('/\s+/', ' ', strip_tags($room->additional_notes ?? '')));
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($room->description ?? '')));

            $lines = array_filter([
                "--- Room #{$rank} (relevance: {$score}) ---",
                "Room Name: {$room->room_name}",
                "Hotel: {$hotelName}",
                "Destination: {$destName}",
                $idealGuest ? "Ideal Guest: {$idealGuest}" : null,
                "Occupancy: {$room->occupancy} guest(s)",
                "Bed Configuration: {$room->bed_configuration}",
                $room->room_size ? "Room Size: {$room->room_size}" : null,
                "Base Price: ₱" . number_format($room->base_price, 2),
                $room->view_type ? "View Type: {$room->view_type}" : null,
                "Available Rooms: {$room->total_rooms}",
                $amenities ? "Amenities: {$amenities}" : null,
                $notes ? "Additional Notes & Fees: {$notes}" : null,
                $desc ? "Description: {$desc}" : null,
            ]);

            $blocks[] = implode("\n", $lines);
        }

        return "=== ROOM DATABASE RESULTS ===\n\n" . implode("\n\n", $blocks) . "\n\n=== END ROOM RESULTS ===";
    }

    /**
     * Recommendation engine entry point: given a user's pre-computed preference vector,
     * retrieves all embedded rooms and ranks them by cosine similarity.
     *
     * @param  array  $userPreferenceVector  L2-normalized preference vector (e.g. from user profile)
     * @param  int    $limit                 Maximum results to return
     * @return array  Scored results: [['item' => RoomType, 'score' => float], ...]
     */
    public function getRoomRecommendations(array $userPreferenceVector, int $limit = 5): array
    {
        $rooms = RoomType::with('hotel.destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->where('embedding', '!=', '')
            ->get();

        if ($rooms->isEmpty()) {
            return [];
        }

        return $this->rankRecommendations($userPreferenceVector, $rooms, $limit);
    }

    // =========================================================================
    //  Activity & Tour RAG & Recommendation Methods
    // =========================================================================

    /**
     * Semantic search: generates a RETRIEVAL_QUERY embedding from the user's query,
     * then ranks all embedded activities by cosine similarity.
     *
     * @param  string  $query   User's search query (e.g. "sunset island hopping in Boracay")
     * @param  int     $limit   Maximum results to return
     * @return array   Scored results: [['item' => ActivityModel, 'score' => float], ...]
     */
    public function searchActivities(string $query, int $limit = 5): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');

        if (!$queryVector) {
            Log::warning('searchActivities: Failed to generate query embedding.', ['query' => $query]);
            return [];
        }

        $activities = ActivityModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->where('embedding', '!=', '')
            ->get();

        if ($activities->isEmpty()) {
            return [];
        }

        return $this->rankRecommendations($queryVector, $activities, $limit);
    }

    /**
     * Formats scored activity results into structured context text for RAG prompt injection.
     *
     * @param  array  $scoredActivities  Output from searchActivities() or rankRecommendations()
     * @return string  Formatted context string ready for prompt injection
     */
    public function getActivityContext(array $scoredActivities): string
    {
        if (empty($scoredActivities)) {
            return '';
        }

        $blocks = [];

        foreach ($scoredActivities as $index => $entry) {
            /** @var ActivityModel $activity */
            $activity = $entry['item'];
            $score = round($entry['score'], 4);
            $rank = $index + 1;

            $destName = $activity->destination->name ?? 'Unknown Destination';

            $vibes = $this->formatListToString($activity->vibe_tags);

            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($activity->description ?? '')));
            $notes = trim(preg_replace('/\s+/', ' ', strip_tags($activity->notes ?? '')));
            $reqs = trim(preg_replace('/\s+/', ' ', strip_tags($activity->requirements ?? '')));

            $lines = array_filter([
                "--- Activity #{$rank} (relevance: {$score}) ---",
                "Activity Name: {$activity->activity_name}",
                "Destination: {$destName}",
                "Category: {$activity->category}",
                "Activity Level: {$activity->activity_level}",
                $activity->duration ? "Duration: {$activity->duration}" : null,
                $activity->capacity ? "Group Capacity: {$activity->capacity}" : null,
                "Rate / Pricing: {$activity->rate}",
                $activity->ideal_for ? "Ideal Participants: {$activity->ideal_for}" : null,
                $vibes ? "Vibes & Tags: {$vibes}" : null,
                $reqs ? "Requirements & Restrictions: {$reqs}" : null,
                $notes ? "Inclusions & Notes: {$notes}" : null,
                $desc ? "Description: {$desc}" : null,
            ]);

            $blocks[] = implode("\n", $lines);
        }

        return "=== ACTIVITY & TOUR DATABASE RESULTS ===\n\n" . implode("\n\n", $blocks) . "\n\n=== END ACTIVITY RESULTS ===";
    }

    /**
     * Recommendation engine entry point for Activities & Tours.
     *
     * @param  array  $userPreferenceVector  L2-normalized preference vector
     * @param  int    $limit                 Maximum results to return
     * @return array  Scored results: [['item' => ActivityModel, 'score' => float], ...]
     */
    public function getActivityRecommendations(array $userPreferenceVector, int $limit = 5): array
    {
        $activities = ActivityModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->where('embedding', '!=', '')
            ->get();

        if ($activities->isEmpty()) {
            return [];
        }

        return $this->rankRecommendations($userPreferenceVector, $activities, $limit);
    }

    // =========================================================================
    //  Reviews: Sentiment Analysis & Multi-Level Summarization (DSS)
    // =========================================================================

    protected static array $promptCache = [];

    /**
     * Loads a system prompt from an external .md file in Services/SystemPrompts/.
     * Fails loudly (HTTP 500) if the file is missing — never silently degrades.
     */
    protected function loadSystemPrompt(string $filename): string
    {
        if (isset(self::$promptCache[$filename])) {
            return self::$promptCache[$filename];
        }

        $path = app_path("Services/SystemPrompts/{$filename}");

        abort_unless(File::exists($path), 500, "Missing Gemini system prompt file: {$filename}");

        return self::$promptCache[$filename] = File::get($path);
    }

    /**
     * Sends a text generation request to the configured Gemini chat model.
     *
     * @return string|null The raw model output text, or null on failure.
     */
    public function generateContent(string $systemInstruction, string $prompt): ?string
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            Log::warning('Gemini API key is not configured in services.gemini.api_key.');
            return null;
        }

        $modelName = config('services.gemini.chat_model') ?? 'models/gemini-2.5-flash-lite';
        $url = "https://generativelanguage.googleapis.com/v1beta/{$modelName}:generateContent?key={$apiKey}";

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, $payload);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text');
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            }

            Log::error('Gemini GenerateContent Failed: ', ['response' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Gemini GenerateContent Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Runs Gemini sentiment analysis + keyword extraction on a single review comment.
     *
     * @param  string  $comment  The review comment text (supports Taglish).
     * @return array  ['sentiment' => 'positive'|'neutral'|'negative',
     *                'confidence_score' => float,
     *                'extracted_keywords' => string[]]
     */
    public function analyzeReviewSentiment(string $comment): array
    {
        $systemInstruction = $this->loadSystemPrompt('sentiment-analysis-prompt.md');

        $prompt = "Review Text: '{$comment}'";

        $raw = $this->generateContent($systemInstruction, $prompt);

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $sentiment = in_array($decoded['sentiment'] ?? null, [
                    Review::SENTIMENT_POSITIVE,
                    Review::SENTIMENT_NEUTRAL,
                    Review::SENTIMENT_NEGATIVE,
                ], true) ? $decoded['sentiment'] : Review::SENTIMENT_NEUTRAL;

                $score = (float) ($decoded['confidence_score'] ?? 0.5);
                $score = max(0.0, min(1.0, $score));

                $keywords = array_values(array_filter(array_map(
                    fn($kw) => is_string($kw) ? mb_substr(trim($kw), 0, 40) : null,
                    $decoded['extracted_keywords'] ?? []
                )));

                return [
                    'sentiment' => $sentiment,
                    'confidence_score' => round($score, 4),
                    'extracted_keywords' => array_slice($keywords, 0, 6),
                ];
            }
        }

        return $this->fallbackSentimentAnalysis($comment);
    }

    /**
     * Rule-based Taglish sentiment fallback used when the Gemini API is unavailable
     * (no API key, timeout, or malformed response). Keeps AI columns non-null and
     * makes the system fully demo-able offline.
     */
    public function fallbackSentimentAnalysis(string $comment): array
    {
        $lower = mb_strtolower($comment);

        $positiveTerms = [
            'ganda', 'maganda', 'super', 'clean', 'spotless', 'linis', 'friendly',
            'polite', 'mabait', 'amazing', 'breathtaking', 'love', 'loved',
            'recommend', 'excellent', 'great', 'nice', 'good', 'perfect',
            'beautiful', 'comfortable', 'worth', 'masarap', 'sulit', 'fun',
            'enjoy', 'best', 'awesome', 'courteous', 'delicious', 'quiet',
            'spacious', 'cozy', 'helpful', 'fast', 'mabilis', 'bait', 'ang galing',
        ];

        $negativeTerms = [
            'bad', 'poor', 'slow', 'spotty', 'dirty', 'rude', 'terrible',
            'worst', 'expensive', 'mahal', 'pangit', 'mabagal', 'bagal',
            'maingay', 'noisy', 'broken', 'mold', 'smell', 'smelled',
            'disappoint', 'late', 'delay', 'delayed', 'uncomfortable', 'awful',
            'sucks', 'problem', 'issue', 'masama', 'mainit', 'reklamo', 'antipatiko',
        ];

        $matchedPositive = [];
        $matchedNegative = [];

        foreach ($positiveTerms as $term) {
            if (str_contains($lower, $term)) {
                $matchedPositive[] = $term;
            }
        }

        foreach ($negativeTerms as $term) {
            if (str_contains($lower, $term)) {
                $matchedNegative[] = $term;
            }
        }

        $posCount = count($matchedPositive);
        $negCount = count($matchedNegative);

        if ($posCount > $negCount) {
            $sentiment = Review::SENTIMENT_POSITIVE;
            $score = min(0.95, 0.65 + ($posCount * 0.08));
        } elseif ($negCount > $posCount) {
            $sentiment = Review::SENTIMENT_NEGATIVE;
            $score = min(0.95, 0.65 + ($negCount * 0.08));
        } else {
            $sentiment = Review::SENTIMENT_NEUTRAL;
            $score = 0.5;
        }

        return [
            'sentiment' => $sentiment,
            'confidence_score' => round($score, 4),
            'extracted_keywords' => $this->extractKeywordsFallback($comment, array_merge($matchedPositive, $matchedNegative)),
        ];
    }

    /**
     * Naive keyword extraction for the offline fallback: picks meaningful
     * frequent tokens (len > 2, non-stopword) from the comment.
     */
    public function extractKeywordsFallback(string $comment, array $seedTerms = []): array
    {
        $stopwords = [
            'the', 'and', 'was', 'were', 'with', 'that', 'this', 'have', 'has', 'had',
            'for', 'not', 'but', 'you', 'our', 'your', 'from', 'they', 'there', 'were',
            'ang', 'ng', 'sa', 'at', 'ako', 'kami', 'namin', 'naman', 'kasi', 'kaya',
            'na', 'si', 'sila', 'daw', 'rin', 'din', 'po', 'yung', 'pero', 'then', 'also',
        ];

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($comment)) ?: [];

        $freq = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (mb_strlen($token) > 2 && !in_array($token, $stopwords, true)) {
                $freq[$token] = ($freq[$token] ?? 0) + 1;
            }
        }

        foreach ($seedTerms as $seed) {
            $freq[$seed] = ($freq[$seed] ?? 0) + 2;
        }

        arsort($freq);

        return array_slice(array_keys($freq), 0, 6);
    }

    /**
     * Generates an AI consensus summary for an entity from its recent reviews.
     *
     * @param  array  $reviews   Collection of Review models (or arrays) to summarize.
     * @param  string $entityLabel  Human label of the entity (e.g. "Deluxe Ocean View Room at Villa Maria Resort").
     * @return array  ['ai_summary_text' => string|null,
     *                'top_positive_highlights' => string[],
     *                'top_negative_highlights' => string[],
     *                'most_frequent_keywords' => ['keyword' => count, ...]]
     */
    public function summarizeReviews($reviews, string $entityLabel): array
    {
        $reviews = collect($reviews)->values();

        if ($reviews->isEmpty()) {
            return [
                'ai_summary_text' => null,
                'top_positive_highlights' => [],
                'top_negative_highlights' => [],
                'most_frequent_keywords' => [],
            ];
        }

        $lines = $reviews->take(50)->map(function ($review) {
            $rating = $review->rating ?? 0;
            $comment = $review->comment ?? '';
            return "[{$rating}/5] {$comment}";
        })->implode("\n");

        $systemInstruction = $this->loadSystemPrompt('review-summary-prompt.md');

        $prompt = "Entity: {$entityLabel}\n\nReviews:\n{$lines}";

        $raw = $this->generateContent($systemInstruction, $prompt);

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeSummaryOutput($decoded, $reviews);
            }
        }

        return $this->fallbackSummarizeReviews($reviews);
    }

    /**
     * Sanitizes/normalizes raw Gemini summary JSON into DB-ready arrays.
     */
    protected function normalizeSummaryOutput(array $decoded, $reviews): array
    {
        $stringList = fn($list) => array_values(array_filter(array_map(
            fn($item) => is_string($item) ? mb_substr(trim($item), 0, 160) : null,
            is_array($list) ? $list : []
        )));

        $keywords = [];
        foreach ((is_array($decoded['most_frequent_keywords'] ?? null) ? $decoded['most_frequent_keywords'] : []) as $item) {
            if (is_array($item) && isset($item['keyword'])) {
                $keyword = mb_substr(trim((string) $item['keyword']), 0, 60);
                $keywords[$keyword] = (int) ($item['count'] ?? 1);
            }
        }

        if (empty($keywords)) {
            foreach ($reviews as $review) {
                foreach (($review->extracted_keywords ?? []) as $kw) {
                    $kw = trim((string) $kw);
                    if ($kw !== '') {
                        $keywords[$kw] = ($keywords[$kw] ?? 0) + 1;
                    }
                }
            }
        }

        return [
            'ai_summary_text' => isset($decoded['ai_summary_text']) && is_string($decoded['ai_summary_text'])
                ? mb_substr(trim($decoded['ai_summary_text']), 0, 3000)
                : null,
            'top_positive_highlights' => $stringList($decoded['top_positive_highlights'] ?? []),
            'top_negative_highlights' => $stringList($decoded['top_negative_highlights'] ?? []),
            'most_frequent_keywords' => $keywords,
        ];
    }

    /**
     * Deterministic offline fallback for consensus summarization (no API needed).
     */
    public function fallbackSummarizeReviews($reviews): array
    {
        $reviews = collect($reviews)->values();

        $avg = (float) round($reviews->avg('rating'), 2);
        $positive = $reviews->where('sentiment', Review::SENTIMENT_POSITIVE)->count();
        $negative = $reviews->where('sentiment', Review::SENTIMENT_NEGATIVE)->count();
        $total = max(1, $reviews->count());

        $keywordCounts = [];
        foreach ($reviews as $review) {
            foreach (($review->extracted_keywords ?? []) as $kw) {
                $kw = trim((string) $kw);
                if ($kw !== '') {
                    $keywordCounts[$kw] = ($keywordCounts[$kw] ?? 0) + 1;
                }
            }
        }
        arsort($keywordCounts);

        $topKeywords = array_slice(array_keys($keywordCounts), 0, 5);

        $lines = [];
        $lines[] = "- Guests rate this experience {$avg}/5 across {$total} verified review" . ($total > 1 ? 's' : '') . ".";
        if ($positive > 0) {
            $lines[] = "- " . round(($positive / $total) * 100) . "% of guests shared positive feedback" . (count($topKeywords) ? ", often highlighting: " . implode(', ', array_slice($topKeywords, 0, 3)) . "." : ".");
        }
        if ($negative > 0) {
            $lines[] = "- A minority (" . round(($negative / $total) * 100) . "%) noted concerns worth checking at check-in.";
        }
        $lines[] = "- Newest guest comments are available below for first-hand detail.";

        $positiveHighlights = [];
        $negativeHighlights = [];

        $reviews->take(20)->each(function ($review) use (&$positiveHighlights, &$negativeHighlights) {
            $headline = mb_substr(trim($review->comment ?? ''), 0, 90);
            if ($headline === '') {
                return;
            }
            if (($review->sentiment ?? null) === Review::SENTIMENT_POSITIVE && count($positiveHighlights) < 4) {
                $positiveHighlights[] = $headline;
            } elseif (($review->sentiment ?? null) === Review::SENTIMENT_NEGATIVE && count($negativeHighlights) < 4) {
                $negativeHighlights[] = $headline;
            }
        });

        return [
            'ai_summary_text' => implode("\n", $lines),
            'top_positive_highlights' => $positiveHighlights,
            'top_negative_highlights' => $negativeHighlights,
            'most_frequent_keywords' => array_slice($keywordCounts, 0, 10, true),
        ];
    }

    /**
     * Builds a ready-to-inject RAG context block for the (future) SunnyTrips
     * chatbot: entity summary + recent review snippets.
     */
    public function buildReviewRagContext(string $entityType, int $entityId, int $recentLimit = 3): string
    {
        $summary = ReviewSummary::where('summarizable_type', $entityType)
            ->where('summarizable_id', $entityId)
            ->first();

        $reviews = Review::published()
            ->ofEntity($entityType, $entityId)
            ->with('user')
            ->latest()
            ->limit($recentLimit)
            ->get();

        $entityLabel = 'Listing';
        $instance = null;

        if (class_exists($entityType)) {
            $instance = $entityType::find($entityId);
        }

        if ($instance) {
            $entityLabel = $instance->hotel_name ?? $instance->room_name ?? $instance->activity_name ?? $instance->name ?? "Listing #{$entityId}";
        }

        $lines = [
            "Entity: {$entityLabel}",
        ];

        if ($summary) {
            $lines[] = "Average Rating: {$summary->average_rating} / 5.0 ({$summary->total_reviews} reviews)";
            $lines[] = "Sentiment Split: {$summary->positive_percentage}% positive / {$summary->neutral_percentage}% neutral / {$summary->negative_percentage}% negative";
            if ($summary->ai_summary_text) {
                $lines[] = "AI Consensus Summary:\n" . $summary->ai_summary_text;
            }
        }

        foreach ($reviews as $review) {
            $lines[] = "Review snippet ({$review->rating}/5, {$review->sentiment}): \"{$review->comment}\"";
        }

        return "=== REVIEW INSIGHTS ===\n\n" . implode("\n", $lines) . "\n\n=== END REVIEW INSIGHTS ===";
    }

    // =========================================================================
    //  Chatbot Abuse Detection & Safety Guard System
    // =========================================================================

    /**
     * Chatbot Safety Guard & Abuse Detection System.
     *
     * Analyzes user input queries for inappropriate/sexual terms, prompt injection hacks,
     * sensitive prohibited content, or off-topic system abuse.
     *
     * @param  User    $user     Authenticated registered user
     * @param  string  $message  User's chat input query
     * @return array|null        Returns violation response payload if flagged/blocked, or null if clean
     */
    public function detectAbuseAndGuard(User $user, string $message): ?array
    {
        // 1. Account Suspension Check
        if ($user->is_banned) {
            return [
                'blocked' => true,
                'response' => 'Your account has been suspended from using the AI Chatbot due to terms of service violations. Reason: ' . ($user->ban_reason ?? 'Repeated community guideline violations.'),
            ];
        }

        $lowerMsg = mb_strtolower($message);

        // 2. Category Detection Patterns
        $categories = [
            'Sexual/Inappropriate' => [
                'nsfw',
                'porn',
                'naked',
                'nude',
                'sexual',
                'sex',
                'strip',
                'erotic',
                'boobs',
                'penis',
                'vagina'
            ],
            'Sensitive/Prohibited' => [
                'suicide',
                'bomb',
                'terrorist',
                'hack bank',
                'credit card fraud',
                'illegal drugs',
                'kill',
                'murder'
            ],
            'Prompt Injection' => [
                'ignore previous instructions',
                'ignore all rules',
                'system prompt',
                'you are now DAN',
                'bypass restriction'
            ],
        ];

        $flaggedCategory = null;
        $flaggedReason = null;

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $lowerMsg)) {
                    $flaggedCategory = $category;
                    $flaggedReason = "Query contains prohibited phrase: '{$kw}'";
                    break 2;
                }
            }
        }

        // 3. If flagged, log abuse report and increment user flag count
        if ($flaggedCategory) {
            ChatbotAbuseReport::create([
                'user_id' => $user->id,
                'message' => $message,
                'category' => $flaggedCategory,
                'reason' => $flaggedReason,
                'status' => 'pending',
            ]);

            $user->increment('chatbot_flag_count');

            return [
                'blocked' => true,
                'response' => 'Your message contains content that violates our community guidelines. This incident has been logged for administrator review.',
            ];
        }

        return null; // Clean query
    }
}
