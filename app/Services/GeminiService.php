<?php

namespace App\Services;

use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $vibeList = is_array($activity->vibe_tags) ? implode(', ', $activity->vibe_tags) : $activity->vibe_tags;

        return implode("\n", array_filter([
            "Activity Name: {$activity->activity_name}",
            "Category: {$activity->category}",
            "Activity Level: {$activity->activity_level}",
            "Destination: {$dest}",
            $vibeList ? "Experience Vibes & Tags: {$vibeList}" : null,
            $activity->ideal_for ? "Ideal Travelers: {$activity->ideal_for}" : null,
            "Rate / Pricing: {$activity->rate}",
            $desc ? "Detailed Experience Description: {$desc}" : null,
            $notes ? "Notes / Inclusions: {$notes}" : null,
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
        $vibeList = is_array($hotel->vibe_tags) ? implode(', ', $hotel->vibe_tags) : $hotel->vibe_tags;
        $amenitiesList = is_array($hotel->featured_amenities) ? implode(', ', $hotel->featured_amenities) : $hotel->featured_amenities;

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
        $amenitiesList = is_array($room->room_amenities)
            ? implode(', ', array_filter(array_map('trim', $room->room_amenities)))
            : (is_string($room->room_amenities) ? trim($room->room_amenities) : '');

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

        $modelName = config('services.gemini.embedding_model') ?? 'models/text-embedding-004';
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
            $rank  = $index + 1;

            $destName = $hotel->destination->name ?? 'Unknown Destination';
            $typeLabel = ucwords(str_replace('-', ' ', $hotel->type ?? 'N/A'));

            $vibes = is_array($hotel->vibe_tags)
                ? implode(', ', $hotel->vibe_tags)
                : ($hotel->vibe_tags ?? '');

            $amenities = is_array($hotel->featured_amenities)
                ? implode(', ', $hotel->featured_amenities)
                : ($hotel->featured_amenities ?? '');

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
            $room  = $entry['item'];
            $score = round($entry['score'], 4);
            $rank  = $index + 1;

            $hotel     = $room->hotel;
            $hotelName = $hotel->hotel_name ?? 'Unknown Hotel';
            $destName  = $hotel->destination->name ?? 'Unknown Destination';

            $amenities = is_array($room->room_amenities)
                ? implode(', ', $room->room_amenities)
                : ($room->room_amenities ?? '');

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
            ->whereNotNull('embedding')
            ->where('embedding', '!=', '')
            ->get();

        if ($rooms->isEmpty()) {
            return [];
        }

        return $this->rankRecommendations($userPreferenceVector, $rooms, $limit);
    }
}
