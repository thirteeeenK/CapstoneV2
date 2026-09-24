<?php

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\AdminAuditLog;
use App\Models\ChatbotAbuseReport;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\PassengerCategoryRule;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Chat\ChatbotModerationPolicy;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public function __construct(protected RoomAvailabilityService $availability) {}

    /** @var array<string, array<int, float>|null> */
    protected array $requestEmbeddingCache = [];

    /** @var array<string, float> Sorted per-catalog scores from the last resolveSemanticCatalog call. */
    public array $lastRoutingScores = [];

    /**
     * How the last catalog retrieval ran: 'semantic' (embedding ranked),
     * 'lexical' (embedding failed, plain DB listing), or 'constraint_only'
     * (no retrieval query — constraints/exact-match only). Copied into the
     * chat trace as retrieval_mode.
     */
    public string $lastRetrievalMode = 'semantic';

    /**
     * Every ₱ amount (string and float forms) seen in the last built
     * retrieval context. The chat layer rejects generated replies that
     * mention a price outside this set.
     *
     * @var array<int, string|float>
     */
    public array $lastContextPrices = [];

    /**
     * Diagnostics from the last generateChatResponse call, copied into the
     * chat trace. Reset at the start of every call so a skipped generation
     * path never reports a previous turn's numbers.
     */
    public ?string $lastFinishReason = null;

    public ?int $lastCandidateCount = null;

    /** @var array<string, int>|null */
    public ?array $lastTokenUsage = null;

    public ?int $lastLatencyMs = null;

    public ?string $lastPromptHash = null;

    /**
     * All peso amounts in a text, as comparable string + float forms
     * ('3,000.00' → '3000.00' and 3000.0, so both '₱3,000' and
     * '₱3,000.00' in a reply match '₱3,000.00' in the context).
     *
     * @return array<int, string|float>
     */
    public static function extractPesoAmounts(string $text): array
    {
        preg_match_all('/₱\s*([0-9,]+(?:\.\d{2})?)/', $text, $m);
        $out = [];
        foreach ($m[1] as $raw) {
            $norm = str_replace(',', '', $raw);
            $out[] = $norm;
            $out[] = (float) $norm;
        }

        return array_values(array_unique($out, SORT_REGULAR));
    }

    /**
     * Record the peso amounts of a just-built context string for later
     * grounding validation. Returns the input unchanged for inline use.
     */
    public function captureContextPrices(string $context): string
    {
        $this->lastContextPrices = self::extractPesoAmounts($context);

        return $context;
    }

    /**
     * Builds structured, semantically optimized text for ActivityModel embedding generation.
     */
    public function buildActivityEmbeddingText(ActivityModel $activity, ?string $destinationName = null): string
    {
        $destination = null;
        if (! $destinationName && $activity->destination_id) {
            $destination = DestinationModel::find($activity->destination_id);
            $destinationName = $destination ? $destination->name : null;
        } elseif ($activity->destination) {
            $destination = $activity->destination;
        }

        $dest = $destinationName ?? 'Unknown Destination';
        if ($destination && $destination->region) {
            $dest .= " ({$destination->region})";
        }
        $descRaw = $activity->description ?? '';
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($descRaw)));
        if (mb_strlen($desc) > 500) {
            $desc = mb_substr($desc, 0, 500).'…';
        }
        $notes = trim(preg_replace('/\s+/', ' ', strip_tags($activity->notes ?? '')));
        if (mb_strlen($notes) > 300) {
            $notes = mb_substr($notes, 0, 300).'…';
        }
        $reqs = trim(preg_replace('/\s+/', ' ', strip_tags($activity->requirements ?? '')));
        $vibeList = $this->formatListToString($activity->vibe_tags);
        $inclusionsList = $this->formatListToString($activity->inclusions);
        $exclusionsList = $this->formatListToString($activity->exclusions);
        $itineraryList = '';
        if (is_array($activity->itinerary)) {
            foreach ($activity->itinerary as $step) {
                if (isset($step['title'])) {
                    $itineraryList .= $step['title'].(isset($step['duration']) ? ' ('.$step['duration'].')' : '').'. ';
                }
            }
            if (mb_strlen($itineraryList) > 300) {
                $itineraryList = mb_substr($itineraryList, 0, 300).'…';
            }
        }

        $rateType = $activity->isPerPersonRate() ? 'per person/head/pax' : 'per group/unit';
        $coords = null;
        if ($activity->latitude && $activity->longitude) {
            $coords = "Coordinates: Latitude {$activity->latitude}, Longitude {$activity->longitude}";
        } elseif ($destination && $destination->latitude && $destination->longitude) {
            $coords = "Near Coordinates: Latitude {$destination->latitude}, Longitude {$destination->longitude} (destination center)";
        }

        return implode("\n", array_filter([
            "Activity Name: {$activity->activity_name}",
            "Category: {$activity->category}",
            "Activity Level: {$activity->activity_level}",
            "Destination: {$dest}",
            $activity->specific_address ? "Specific Address: {$activity->specific_address}" : null,
            $coords,
            $activity->duration ? "Duration: {$activity->duration}" : null,
            $activity->capacity ? "Group Capacity: {$activity->capacity}" : null,
            $activity->ideal_for ? "Ideal Participants: {$activity->ideal_for}" : null,
            "Rate / Pricing: {$activity->rate} ({$rateType})",
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
        if (! $destinationName && $addon->destination_id) {
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
                    $surchargeList .= "{$sur['name']} (₱{$sur['amount']}".(isset($sur['type']) ? ' '.$sur['type'] : '').'). ';
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
    public function buildPackageEmbeddingText(Package $package): string
    {
        $destination = $package->destination;
        $destinationName = $destination ? $destination->name : 'Philippines';
        $region = $destination?->region ? " ({$destination->region})" : '';
        $inclusions = is_array($package->generic_inclusions) ? implode(', ', $package->generic_inclusions) : '';

        // Validity window — critical for "is this package available in August?" queries
        $validity = null;
        if ($package->valid_from || $package->valid_to) {
            $from = $package->valid_from ? $package->valid_from->format('M d, Y') : 'open';
            $to = $package->valid_to ? $package->valid_to->format('M d, Y') : 'open';
            $validity = "Validity Period: {$from} to {$to}";
        } else {
            $validity = 'Validity Period: Year-round / No expiry';
        }
        $status = $package->is_active ? 'Status: Active — bookable now' : 'Status: Inactive';

        // Embed ALL linked hotel/activity names (per requirement)
        $hotelNames = '';
        try {
            $hotels = $package->hotels()->pluck('hotel_name')->all();
            if (! empty($hotels)) {
                $hotelNames = 'Hotels Included: '.implode(', ', array_slice($hotels, 0, 10));
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $activityNames = '';
        try {
            $activities = $package->activities()->pluck('activity_name')->all();
            if (! empty($activities)) {
                $activityNames = 'Activities Included: '.implode(', ', array_slice($activities, 0, 10));
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $roomNames = '';
        try {
            $rooms = $package->rooms()->pluck('room_name')->all();
            if (! empty($rooms)) {
                $roomNames = 'Room Types Included: '.implode(', ', array_slice($rooms, 0, 10));
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $addOnNames = '';
        try {
            $addOns = $package->addOns()->pluck('name')->all();
            if (! empty($addOns)) {
                $addOnNames = 'Add-ons Included: '.implode(', ', array_slice($addOns, 0, 10));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return implode("\n", array_filter([
            "Tour Package Name: {$package->name}",
            'Package Type: '.($package->type ?: 'Standard Tour Promo'),
            "Destination: {$destinationName}{$region}",
            'Rate / Price: ₱'.number_format($package->price, 2).' per pax (total = price × guests)',
            'Duration: '.($package->days ?: 3).' Days / '.($package->nights ?: 2).' Nights',
            'Minimum Guests Required: '.($package->min_pax ?: 2).' Pax',
            $validity,
            $status,
            $hotelNames ?: null,
            $roomNames ?: null,
            $activityNames ?: null,
            $addOnNames ?: null,
            $inclusions ? "Included Inclusions & Features: {$inclusions}" : 'All-inclusive promo package',
        ]));
    }

    /**
     * Builds structured, semantically optimized text for FAQ embedding generation.
     */
    public function buildFaqEmbeddingText(Faq $faq): string
    {
        return implode("\n", array_filter([
            "Question: {$faq->question}",
            $faq->category ? "Category: {$faq->category}" : null,
            $faq->keywords ? "Related Keywords: {$faq->keywords}" : null,
            "Answer: {$faq->answer}",
        ]));
    }

    /**
     * Builds deterministic discount context from passenger_category_rules (no embedding needed).
     */
    public function getPassengerDiscountContext(): string
    {
        try {
            $rules = PassengerCategoryRule::where('is_active', true)->orderBy('id')->get();
            if ($rules->isEmpty()) {
                return '';
            }
            $lines = ['=== PASSENGER PRICING RULES (live DB) ==='];
            foreach ($rules as $rule) {
                $type = $rule->adjustment_type === 'discount' ? 'discount' : ($rule->adjustment_type === 'surcharge' ? 'surcharge' : 'no adjustment');
                $amount = $rule->amount > 0 ? '₱'.number_format((float) $rule->amount, 2).' '.$type.' per pax' : 'no adjustment';
                $lines[] = "- {$rule->display_label} ({$rule->category_name}): {$amount}";
            }
            $lines[] = 'Select passenger category in Trip Basket / Checkout to apply automatically.';
            $lines[] = '=== END PASSENGER PRICING RULES ===';

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            Log::warning('getPassengerDiscountContext failed: '.$e->getMessage());

            return '';
        }
    }

    /**
     * Builds structured, semantically optimized text for Hotel embedding generation.
     */
    public function buildHotelEmbeddingText(HotelModel $hotel, ?string $destinationName = null): string
    {
        $destination = null;
        if (! $destinationName && $hotel->destination_id) {
            $destination = DestinationModel::find($hotel->destination_id);
            $destinationName = $destination ? $destination->name : null;
        } elseif ($hotel->destination) {
            $destination = $hotel->destination;
        }

        $dest = $destinationName ?? 'Unknown Destination';
        if ($destination && $destination->region) {
            $dest .= " ({$destination->region})";
        }
        $descRaw = $hotel->hotel_description ?? '';
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($descRaw)));
        if (mb_strlen($desc) > 500) {
            $desc = mb_substr($desc, 0, 500).'…';
        }
        $vibeList = $this->formatListToString($hotel->vibe_tags);
        $amenitiesList = $this->formatListToString($hotel->featured_amenities);

        // Price range — critical for "cheapest/most expensive hotel" ranking
        $priceRange = null;
        try {
            $minPrice = $hotel->rooms()->where('is_shown', true)->min('base_price');
            $maxPrice = $hotel->rooms()->where('is_shown', true)->max('base_price');
            $count = $hotel->rooms()->where('is_shown', true)->count();
            if ($minPrice !== null) {
                $priceRange = 'Price Range: From ₱'.number_format((float) $minPrice, 2).' to ₱'.number_format((float) $maxPrice, 2)."/night ({$count} room types)";
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Guest rating when review summary exists
        $ratingLine = null;
        try {
            $summary = $hotel->reviewSummary;
            if ($summary) {
                $ratingLine = "Guest Rating: {$summary->average_rating}/5 ({$summary->total_reviews} reviews)";
                if (! empty($summary->ai_summary_text)) {
                    $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($summary->ai_summary_text)));
                    if (mb_strlen($snippet) > 120) {
                        $snippet = mb_substr($snippet, 0, 120).'…';
                    }
                    $ratingLine .= " — {$snippet}";
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return implode("\n", array_filter([
            "Hotel Name: {$hotel->hotel_name}",
            "Destination: {$dest}",
            $hotel->type ? 'Hotel Category: '.ucwords(str_replace('-', ' ', $hotel->type)) : null,
            $priceRange,
            $ratingLine,
            $vibeList ? "Hotel Vibe & Atmosphere: {$vibeList}" : null,
            $amenitiesList ? "Featured Amenities & Facilities: {$amenitiesList}" : null,
            "Specific Address: {$hotel->specific_address}",
            ($hotel->latitude && $hotel->longitude) ? "Location Coordinates: Latitude {$hotel->latitude}, Longitude {$hotel->longitude}" : null,
            $desc ? "Detailed Overview: {$desc}" : null,
        ]));
    }

    /**
     * Builds structured, semantically optimized text for RoomType embedding generation.
     */
    public function buildRoomEmbeddingText(RoomType $room, ?string $hotelName = null, ?string $destinationName = null): string
    {
        if (! $hotelName && $room->hotel_id) {
            $hotel = $room->hotel ?? HotelModel::with('destination')->find($room->hotel_id);
            if ($hotel) {
                $hotelName = $hotel->hotel_name;
                $destinationName = $destinationName ?? ($hotel->destination?->name);
            }
        }

        $idealGuest = $room->ideal_guest ?? $room->ideal_for;
        $descRaw = $room->description ?? '';
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($descRaw)));
        if (mb_strlen($desc) > 500) {
            $desc = mb_substr($desc, 0, 500).'…';
        }
        $notesRaw = $room->additional_notes ?? '';
        $notes = trim(preg_replace('/\s+/', ' ', strip_tags($notesRaw)));
        if (mb_strlen($notes) > 300) {
            $notes = mb_substr($notes, 0, 300).'…';
        }
        $amenitiesList = $this->formatListToString($room->room_amenities);

        $baseOcc = (int) ($room->base_occupancy ?: 2);
        $maxOcc = (int) ($room->max_occupancy ?: ($room->occupancy ?: $baseOcc));
        $fee = (float) ($room->extra_person_fee ?: 0);

        $occupancyLine = "Base Occupancy: {$baseOcc} pax included in base price";
        $maxLine = "Maximum Occupancy: {$maxOcc} pax";
        $feeLine = $fee > 0 && $maxOcc > $baseOcc
            ? 'Extra Person Fee: ₱'.number_format($fee, 2).' per extra head per night beyond '.$baseOcc.' pax'
            : "No extra guests allowed — maximum {$maxOcc} guests";

        $rateExample = null;
        if ($maxOcc > $baseOcc && $fee > 0) {
            $examplePax = min($maxOcc, $baseOcc + 1);
            $exampleTotal = $room->calculateNightlyRate($examplePax);
            $rateExample = "Rate Example: {$baseOcc} pax = ₱".number_format($room->base_price, 2)."/night; {$examplePax} pax = ₱".number_format($exampleTotal, 2).'/night';
        }

        return implode("\n", array_filter([
            $hotelName ? "Hotel Name: {$hotelName}" : null,
            "Room Name: {$room->room_name}",
            $destinationName ? "Destination: {$destinationName}" : null,
            $idealGuest ? "Ideal Guest: {$idealGuest}" : null,
            $occupancyLine,
            $maxLine,
            $feeLine,
            $rateExample,
            $room->bed_configuration ? "Bed Layout: {$room->bed_configuration}" : null,
            $room->room_size ? "Room Dimensions: {$room->room_size}" : null,
            'Base Price: ₱'.number_format($room->base_price, 2).' per night',
            $amenitiesList ? "Room Amenities: {$amenitiesList}" : null,
            $notes ? "Additional Notes & Policies: {$notes}" : null,
            $room->view_type ? "Room View: {$room->view_type}" : null,
            "Inventory: {$room->total_rooms} rooms available",
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
                    $val = $item['name'] ?? $item['title'] ?? implode(', ', array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : null, $item)));
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
        $cacheKey = null;
        if ($taskType === 'RETRIEVAL_QUERY') {
            $cacheKey = hash('sha256', $taskType."\0".$title."\0".$text);
            if (array_key_exists($cacheKey, $this->requestEmbeddingCache)) {
                return $this->requestEmbeddingCache[$cacheKey];
            }
        }

        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            Log::warning('Gemini API key is not configured in services.gemini.api_key.');

            return $this->cacheQueryEmbedding($cacheKey, null);
        }

        $modelName = config('services.gemini.embedding_model') ?? 'models/text-embedding-001';
        $url = "https://generativelanguage.googleapis.com/v1beta/{$modelName}:embedContent?key={$apiKey}";

        $payload = [
            'model' => $modelName,
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
            'taskType' => $taskType,
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

                return $this->cacheQueryEmbedding($cacheKey, $this->normalizeVector($rawVector));
            }

            Log::error('Gemini Embedding Failed: ', ['response' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Gemini Embedding Exception: '.$e->getMessage());
        }

        return $this->cacheQueryEmbedding($cacheKey, null);
    }

    /**
     * @param  array<int, float>|null  $embedding
     * @return array<int, float>|null
     */
    protected function cacheQueryEmbedding(?string $cacheKey, ?array $embedding): ?array
    {
        if ($cacheKey !== null) {
            $this->requestEmbeddingCache[$cacheKey] = $embedding;
        }

        return $embedding;
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

        return array_map(fn ($v) => $v / $magnitude, $vector);
    }

    /**
     * Helper to format a vector array into pgvector or string representation for DB.
     */
    public function formatVectorForDb(?array $vector): ?string
    {
        if (empty($vector) || ! is_array($vector)) {
            return null;
        }

        return '['.implode(',', $vector).']';
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
                if (! empty($clean)) {
                    $itemVector = array_map('floatval', explode(',', $clean));
                }
            }

            if ($itemVector) {
                $score = $this->cosineSimilarity($userPreferenceVector, $itemVector);
                $scored[] = [
                    'item' => $item,
                    'score' => $score,
                ];
            }
        }

        usort($scored, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['item']->id <=> $b['item']->id));

        return array_slice($scored, 0, $limit);
    }

    /**
     * Minimum top-catalog cosine score for semantic routing to fire.
     * Calibrated 2026-09-16 on dev seed data (20 probes: 10 must-route
     * typos/paraphrases scored 0.649–0.761, 10 greetings/off-topic scored
     * 0.548–0.614). Floor sits in that gap; the margin gate rejects the one
     * high-scoring greeting ("what can you do", 0.614).
     * Re-evaluated 2026-09-24 via chatbot:eval harness (28 cases, intent_acc=0.958,
     * recall@5=0.842, MRR=0.816). No change justified — eval harness is now
     * the source of truth for floor calibration.
     */
    public const SEMANTIC_ROUTE_FLOOR = 0.60;

    /**
     * Minimum gap between the winning catalog and the runner-up.
     */
    public const SEMANTIC_ROUTE_MARGIN = 0.03;

    /**
     * Per-catalog retrieval floors (cosine). Evaluated 2026-09-24 via
     * chatbot:eval harness (28 cases, recall@5=0.842, wrong_dest=0.105,
     * no_result=0.322). No change justified — eval harness is now
     * the source of truth for floor calibration.
     */
    public const RETRIEVAL_FLOOR_ROOMS = 0.55;

    public const RETRIEVAL_FLOOR_HOTELS = 0.60;

    public const RETRIEVAL_FLOOR_ACTIVITIES = 0.55;

    public const RETRIEVAL_FLOOR_PACKAGES = 0.60;

    public const RETRIEVAL_FLOOR_ADDONS = 0.55;

    public const OVER_BUDGET_TOLERANCE = 0.10;

    public const OVER_BUDGET_MAX = 2;

    public function budgetCeiling(?float $maxPrice): ?float
    {
        if ($maxPrice === null || $maxPrice <= 0) {
            return null;
        }

        return $maxPrice * (1 + self::OVER_BUDGET_TOLERANCE);
    }

    public function applyRelevanceFloor(array $scored, float $floor, string $catalog): array
    {
        if (empty($scored)) {
            return [];
        }
        $top = max(array_map(fn ($e) => (float) $e['score'], $scored));
        $kept = array_values(array_filter($scored, fn ($e) => (float) $e['score'] >= $floor));
        Log::debug('retrieval floor', ['catalog' => $catalog, 'floor' => $floor, 'top_score' => round($top, 4), 'kept' => count($kept), 'dropped' => count($scored) - count($kept)]);

        return $kept;
    }

    public function splitBudgetOverflow(array $scored, callable $priceFor, ?float $maxPrice, int $overflowMax = 2): array
    {
        if ($maxPrice === null || $maxPrice <= 0) {
            return [$scored, []];
        }
        $ceiling = $this->budgetCeiling($maxPrice);
        $inBudget = [];
        $overflowCandidates = [];
        foreach ($scored as $entry) {
            $price = $priceFor($entry['item']);
            if ($price <= 0) {
                $inBudget[] = $entry;

                continue;
            }
            if ($price <= $maxPrice) {
                $inBudget[] = $entry;
            } elseif ($price <= $ceiling) {
                $entry['over_budget'] = true;
                $entry['over_by'] = $price - $maxPrice;
                $overflowCandidates[] = $entry;
            }
        }
        usort($overflowCandidates, fn ($a, $b) => ($priceFor($a['item']) <=> $priceFor($b['item'])) ?: (($b['score'] ?? 0) <=> ($a['score'] ?? 0)) ?: ($a['item']->id <=> $b['item']->id));
        $overflow = array_slice($overflowCandidates, 0, $overflowMax);
        Log::debug('budget split', ['max_price' => $maxPrice, 'ceiling' => $ceiling, 'in_budget' => count($inBudget), 'overflow' => count($overflow)]);

        return [$inBudget, $overflow];
    }

    public function cheapestFallback(array $scored, callable $priceFor, int $limit = 3): array
    {
        if (empty($scored)) {
            return [];
        }
        usort($scored, fn ($a, $b) => ($priceFor($a['item']) <=> $priceFor($b['item'])) ?: (($b['score'] ?? 0) <=> ($a['score'] ?? 0)) ?: ($a['item']->id <=> $b['item']->id));
        $fallback = array_slice(array_values($scored), 0, $limit);
        foreach ($fallback as &$entry) {
            $entry['fallback'] = true;
        }
        unset($entry);

        return $fallback;
    }

    public function roomPriceForPax(RoomType $room, ?int $pax): float
    {
        return $pax ? (float) $room->calculateNightlyRate($pax) : (float) $room->base_price;
    }

    /**
     * Canonical effective occupancy: NULL max_occupancy (e.g. Water Villa,
     * Beach Villa seeder rows) falls back to occupancy/base_occupancy so the
     * room stays visible to pax-filtered queries instead of vanishing.
     */
    public function effectiveMaxOccupancy(RoomType $room): int
    {
        return (int) ($room->max_occupancy ?: ($room->occupancy ?: ($room->base_occupancy ?: 2)));
    }

    public function largestRoom(?int $destinationId = null, ?int $hotelId = null): ?RoomType
    {
        return RoomType::with('hotel.destination')
            ->where('is_shown', true)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->when($destinationId, fn ($q) => $q->whereHas('hotel', fn ($qq) => $qq->where('destination_id', $destinationId)))
            ->orderByRaw('COALESCE(max_occupancy, base_occupancy, 2) DESC')
            ->orderBy('base_price', 'asc')
            ->first();
    }

    public function maxRoomOccupancy(?int $destinationId = null, ?int $hotelId = null): int
    {
        $room = $this->largestRoom($destinationId, $hotelId);

        return $room ? $this->effectiveMaxOccupancy($room) : 0;
    }

    /**
     * Cheapest 2-room same-hotel splits covering $pax, ignoring budget.
     * Each pair: ['rooms' => [RoomType, RoomType], 'pax' => [int, int], 'total' => float].
     * Empty when even two rooms cannot cover the group.
     */
    public function groupSplitOptions(?int $destinationId, ?int $hotelId, int $pax, ?array $queryVector = null, int $maxPairs = 2): array
    {
        $candidates = RoomType::with('hotel.destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->when($destinationId, fn ($q) => $q->whereHas('hotel', fn ($qq) => $qq->where('destination_id', $destinationId)))
            ->get();
        if ($candidates->isEmpty()) {
            return [];
        }

        $ranked = $queryVector
            ? array_map(fn ($e) => $e['item'], $this->rankRecommendations($queryVector, $candidates, 30))
            : $candidates->sortBy('base_price')->values()->all();

        $pairs = [];
        $seen = [];
        foreach (array_slice($ranked, 0, 10) as $roomA) {
            $effA = $this->effectiveMaxOccupancy($roomA);
            $best = null;
            foreach ($ranked as $roomB) {
                if ($roomB->id === $roomA->id || ($roomB->hotel_id !== $roomA->hotel_id)) {
                    continue;
                }
                $effB = $this->effectiveMaxOccupancy($roomB);
                if ($effA + $effB < $pax) {
                    continue;
                }
                $paxA = min($effA, $pax);
                $paxB = $pax - $paxA;
                if ($paxB < 1 || $paxB > $effB) {
                    continue;
                }
                $total = $roomA->calculateNightlyRate($paxA) + $roomB->calculateNightlyRate($paxB);
                if ($best === null || $total < $best['total']) {
                    $best = ['rooms' => [$roomA, $roomB], 'pax' => [$paxA, $paxB], 'total' => $total];
                }
            }
            if ($best !== null) {
                $key = min($best['rooms'][0]->id, $best['rooms'][1]->id).'-'.max($best['rooms'][0]->id, $best['rooms'][1]->id);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $pairs[] = $best;
                }
            }
        }

        usort($pairs, fn ($a, $b) => $a['total'] <=> $b['total']);

        return array_slice($pairs, 0, max(1, $maxPairs));
    }

    /**
     * Flatten group-split pairs into normal room entries flagged combo_* so
     * getRoomContext/formatRoomResults/budgetNotice keep working unchanged.
     */
    protected function flattenGroupPairs(array $pairs, ?float $maxPrice): array
    {
        $entries = [];
        foreach ($pairs as $gi => $pair) {
            [$roomA, $roomB] = $pair['rooms'];
            [$paxA, $paxB] = $pair['pax'];
            $flags = ['combo_group' => $gi + 1, 'combo_total' => round($pair['total'], 2)];
            if ($maxPrice !== null && $maxPrice > 0 && $pair['total'] > $maxPrice) {
                $flags[$pair['total'] <= $this->budgetCeiling($maxPrice) ? 'over_budget' : 'fallback'] = true;
                if (isset($flags['fallback'])) {
                    // ponytail: over_by only meaningful inside the ~10% window
                } else {
                    $flags['over_by'] = round($pair['total'] - $maxPrice, 2);
                }
            }
            $entries[] = array_merge(['item' => $roomA, 'score' => 1.0 - ($gi * 0.01), 'combo_with' => $roomB->room_name, 'combo_pax' => $paxA], $flags);
            $entries[] = array_merge(['item' => $roomB, 'score' => 1.0 - ($gi * 0.01) - 0.005, 'combo_with' => $roomA->room_name, 'combo_pax' => $paxB], $flags);
        }

        return $entries;
    }

    public function hotelMinPrice(HotelModel $hotel): ?float
    {
        $min = $hotel->rooms()->where('is_shown', true)->min('base_price');

        return $min !== null ? (float) $min : null;
    }

    public function hotelMaxOccupancy(HotelModel $hotel): int
    {
        return (int) ($hotel->rooms()->where('is_shown', true)->max('max_occupancy') ?: 0);
    }

    public function activityPriceForPax(ActivityModel $activity, ?int $pax): float
    {
        return (float) $activity->calculateRateForPax(max(1, $pax ?? 1));
    }

    public function addonPriceForPax(AddOnModel $addon, ?int $pax): float
    {
        return (float) $addon->getRateForPax(max(1, $pax ?? 1));
    }

    public function packagePrice(Package $package): float
    {
        return (float) $package->price;
    }

    /**
     * Semantic catalog routing: full-RAG fallback for keyword misses.
     *
     * Embeds the query once and scores it against all visible candidates per
     * catalog, returning the winning catalog key ('rooms', 'hotels',
     * 'activities', 'packages', 'addons'). Returns null when embeddings are
     * unavailable or no catalog wins clearly — the caller must keep its
     * keyword default in that case.
     */
    public function resolveSemanticCatalog(string $query, ?int $destinationId = null): ?string
    {
        $this->lastRoutingScores = [];

        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');
        if (! $queryVector) {
            return null;
        }

        $scores = [
            'activities' => $this->topCatalogScore(ActivityModel::with('destination')
                ->where('is_shown', true)
                ->whereNotNull('embedding')
                ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
                ->get(), $queryVector),
            'hotels' => $this->topCatalogScore(HotelModel::with('destination')
                ->where('is_shown', true)
                ->whereNotNull('embedding')
                ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
                ->get(), $queryVector),
            'rooms' => $this->topCatalogScore(RoomType::with('hotel.destination')
                ->where('is_shown', true)
                ->whereNotNull('embedding')
                ->when($destinationId, fn ($q) => $q->whereHas('hotel', fn ($qq) => $qq->where('destination_id', $destinationId)))
                ->get(), $queryVector),
            'packages' => $this->topCatalogScore(Package::with('destination')
                ->where('is_active', true)
                ->whereNotNull('embedding')
                ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
                ->get(), $queryVector),
            'addons' => $this->topCatalogScore(AddOnModel::with('destination')
                ->where('is_shown', true)
                ->whereNotNull('embedding')
                ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
                ->get(), $queryVector),
        ];

        $scores = array_filter($scores, fn ($s) => $s !== null);
        if (empty($scores)) {
            return null;
        }
        arsort($scores);
        $this->lastRoutingScores = $scores;
        $keys = array_keys($scores);
        $top = $scores[$keys[0]];
        if ($top < self::SEMANTIC_ROUTE_FLOOR) {
            return null;
        }
        if (isset($keys[1]) && ($top - $scores[$keys[1]]) < self::SEMANTIC_ROUTE_MARGIN) {
            return null;
        }

        return $keys[0];
    }

    protected function topCatalogScore($candidates, array $queryVector): ?float
    {
        if ($candidates->isEmpty()) {
            return null;
        }
        $ranked = $this->rankRecommendations($queryVector, $candidates, 1);
        if (empty($ranked)) {
            return null;
        }

        return (float) $ranked[0]['score'];
    }

    public function extractPricingContext($contextText, $userQuery)
    {
        // Final context (with any computed hints below) is what the model
        // sees, so its prices are the grounding allow-list.
        $this->lastContextPrices = self::extractPesoAmounts($contextText);

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

        if (empty($extractedPrices)) {
            return $contextText;
        }

        $calcHint = "\n\n--- PRE-COMPUTED CALCULATIONS (use these in your answer) ---\n";
        $hasCalc = false;

        foreach ($extractedPrices as $price) {
            $formatted = number_format($price);

            if ($paxCount) {
                $totalPax = $price * $paxCount;
                $calcHint .= "• ₱{$formatted} × {$paxCount} pax = ₱".number_format($totalPax)."\n";
                $hasCalc = true;
            }

            if ($nightCount) {
                $totalNight = $price * $nightCount;
                $calcHint .= "• ₱{$formatted} × {$nightCount} nights = ₱".number_format($totalNight)."\n";
                $hasCalc = true;
            }

            if ($paxCount && $nightCount) {
                $totalBoth = $price * $paxCount * $nightCount;
                $calcHint .= "• ₱{$formatted} × {$paxCount} pax × {$nightCount} nights = ₱".number_format($totalBoth)."\n";
            }
        }

        if (! $hasCalc) {
            return $contextText;
        }
        $calcHint .= "---\n";

        return $this->captureContextPrices($contextText.$calcHint);
    }

    // =========================================================================
    //  Hotel-Specific RAG & Recommendation Methods
    // =========================================================================

    /**
     * Semantic search: generates a RETRIEVAL_QUERY embedding from the user's natural-language
     * query, then ranks all embedded hotels by cosine similarity.
     *
     * @param  string  $query  The user's search query (e.g. "luxury beachfront resort in Boracay")
     * @param  int  $limit  Maximum results to return
     * @return array Scored results: [['item' => HotelModel, 'score' => float], ...]
     */
    public function searchHotels(string $query, int $limit = 5, ?int $hotelId = null, ?int $destinationId = null, array $constraints = []): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');

        if (! $queryVector) {
            Log::warning('searchHotels: Failed to generate query embedding.', ['query' => $query]);

            return $this->hotelLexicalFallback($limit, $destinationId, $constraints);
        }

        $vector = $this->formatVectorForDb($queryVector);
        if (! $vector) {
            return $this->hotelLexicalFallback($limit, $destinationId, $constraints);
        }

        $this->lastRetrievalMode = 'semantic';

        $maxPrice = isset($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $pax = isset($constraints['pax']) ? (int) $constraints['pax'] : null;
        $fetch = ($maxPrice !== null || $pax !== null) ? max($limit * 10, 50) : $limit;

        $hotels = HotelModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->select('*')
            ->selectRaw('1.0 - (embedding <=> ?) AS similarity', [$vector]);

        if ($hotelId) {
            $hotels->where('id', $hotelId);
        }

        if ($destinationId) {
            $hotels->where('destination_id', $destinationId);
        }

        if ($pax !== null) {
            $hotels->whereHas('rooms', fn ($q) => $q->where('is_shown', true)->where('max_occupancy', '>=', $pax));
        }

        $scored = $hotels
            ->orderByRaw('embedding <=> ? ASC, id ASC', [$vector])
            ->limit($fetch)
            ->get()
            ->map(fn (HotelModel $hotel): array => ['item' => $hotel, 'score' => (float) $hotel->similarity])
            ->all();

        $scored = $this->applyRelevanceFloor($scored, self::RETRIEVAL_FLOOR_HOTELS, 'hotels');
        if ($pax !== null) {
            $scored = array_values(array_filter($scored, fn ($e) => $this->hotelMaxOccupancy($e['item']) >= $pax));
        }
        if ($maxPrice !== null) {
            [$inBudget, $overflow] = $this->splitBudgetOverflow($scored, fn ($h) => $this->hotelMinPrice($h) ?? 0, $maxPrice, self::OVER_BUDGET_MAX);
            $merged = array_merge($inBudget, $overflow);
            if (empty($merged) && ! empty($scored)) {
                return $this->cheapestFallback($scored, fn ($h) => $this->hotelMinPrice($h) ?? PHP_FLOAT_MAX, min(3, $limit));
            }
            $scored = $merged;
        }

        return array_slice(array_values($scored), 0, $limit);
    }

    /**
     * Embedding-outage fallback for hotels: visibility + destination-scoped
     * plain listing ordered by name, all scores zeroed.
     *
     * @param  array  $constraints  Reserved for budget/pax filters (unused — lexical listing ignores them).
     * @return array Scored results: [['item' => HotelModel, 'score' => 0.0], ...]
     */
    protected function hotelLexicalFallback(int $limit, ?int $destinationId, array $constraints = []): array
    {
        $this->lastRetrievalMode = 'lexical';
        $rows = HotelModel::with('destination')
            ->where('is_shown', true)
            ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
            ->orderBy('hotel_name')
            ->limit(max($limit * 2, 10))
            ->get();

        return $rows->map(fn ($h) => ['item' => $h, 'score' => 0.0])->all();
    }

    /**
     * Formats scored hotel results into structured context text for RAG injection
     * into the Gemini chat prompt. Each hotel block includes all semantically
     * relevant fields so the LLM can reason about them accurately.
     *
     * @param  array  $scoredHotels  Output from searchHotels() or rankRecommendations()
     * @return string Formatted context string ready for prompt injection
     */
    /**
     * Objective-accurate rank labels: price-ordered sets say LOWEST/HIGHEST
     * PRICE, single exact matches say EXACT MATCH — never BEST MATCH, which
     * implies semantic relevance that a price sort did not compute.
     */
    public function rankLabel(int $rank, float|string $score, ?string $ordering = null): string
    {
        if ($ordering === 'price-asc' && $rank === 1) {
            return "Rank #{$rank} — LOWEST PRICE";
        }
        if ($ordering === 'price-desc' && $rank === 1) {
            return "Rank #{$rank} — HIGHEST PRICE";
        }
        if ($ordering === 'exact' && $rank === 1) {
            return "Rank #{$rank} — EXACT MATCH";
        }

        return $rank === 1 ? "Rank #{$rank} — BEST MATCH (relevance: {$score})" : "Rank #{$rank} — Alternative (relevance: {$score})";
    }

    /**
     * Escape untrusted catalog text so it cannot break out of its
     * <record> wrapper. Prices (peso sign), newlines, and em-dashes
     * survive; markup does not.
     */
    public function sanitizePromptField(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Wrap one catalog item's context block as an explicitly untrusted
     * record. The LLM must treat the contents as data, never directives.
     */
    public function wrapRecord(string $type, int|string $id, string $body): string
    {
        return "<record type=\"{$type}\" id=\"{$id}\" trusted=\"false\">\n".$this->sanitizePromptField($body)."\n</record>";
    }

    public function resultsHeader(int $count, ?string $ordering = null): string
    {
        if ($count <= 1) {
            return '';
        }
        if ($ordering === 'price-asc') {
            return "Ordered by price, lowest first: Rank #1 = lowest price.\n\n";
        }
        if ($ordering === 'price-desc') {
            return "Ordered by price, highest first: Rank #1 = highest price.\n\n";
        }

        return "Ranked by AI semantic relevance: Rank #1 = best match for the query, Rank #2+ = close alternatives.\n\n";
    }

    public function getHotelContext(array $scoredHotels, ?string $ordering = null): string
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

            $dest = $hotel->destination;
            $destName = $dest->name ?? 'Unknown Destination';
            if ($dest && $dest->region) {
                $destName .= " ({$dest->region})";
            }
            $typeLabel = ucwords(str_replace('-', ' ', $hotel->type ?? 'N/A'));

            $minPrice = $hotel->rooms()->where('is_shown', true)->where('base_price', '>', 100)->min('base_price');
            $maxPrice = $hotel->rooms()->where('is_shown', true)->where('base_price', '>', 100)->max('base_price');
            $count = $hotel->rooms()->where('is_shown', true)->count();
            $rates = $minPrice !== null
                ? 'Price Range: From ₱'.number_format((float) $minPrice, 2).' to ₱'.number_format((float) $maxPrice, 2)."/night ({$count} room types)"
                : 'Rates: not available';

            $vibes = $this->formatListToString($hotel->vibe_tags);
            $amenities = $this->formatListToString($hotel->featured_amenities);

            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($hotel->hotel_description ?? '')));
            if (mb_strlen($desc) > 500) {
                $desc = mb_substr($desc, 0, 500).'…';
            }

            $ratingLine = null;
            try {
                $summary = $hotel->reviewSummary;
                if ($summary) {
                    $ratingLine = "Guest Rating: {$summary->average_rating}/5 ({$summary->total_reviews} reviews)";
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $rankLabel = $this->rankLabel($rank, $score, $ordering);
            $lines = array_filter([
                "--- Hotel {$rankLabel} ---",
                "HOTEL[id={$hotel->id}].name = {$hotel->hotel_name}",
                $minPrice !== null ? 'HOTEL[id='.$hotel->id.'].minimum_room_price = '.((float) $minPrice) : null,
                "Name: {$hotel->hotel_name}",
                "Destination: {$destName}",
                "Category: {$typeLabel}",
                "Rates: {$rates}",
                $ratingLine,
                $vibes ? "Vibes & Atmosphere: {$vibes}" : null,
                $amenities ? "Featured Amenities: {$amenities}" : null,
                "Address: {$hotel->specific_address}",
                ($hotel->latitude && $hotel->longitude)
                ? "Coordinates: {$hotel->latitude}, {$hotel->longitude}"
                : null,
                $desc ? "Description: {$desc}" : null,
            ]);

            $blocks[] = $this->wrapRecord('hotel', $hotel->id, implode("\n", $lines));
        }

        $header = $this->resultsHeader(count($scoredHotels), $ordering);

        return $this->captureContextPrices("=== HOTEL DATABASE RESULTS ===\n\n".$header.implode("\n\n", $blocks)."\n\n=== END HOTEL RESULTS ===");
    }

    /**
     * Recommendation engine entry point: given a user's pre-computed preference vector,
     * retrieves all embedded hotels and ranks them by cosine similarity.
     *
     * @param  array  $userPreferenceVector  L2-normalized preference vector (e.g. from user profile)
     * @param  int  $limit  Maximum results to return
     * @return array Scored results: [['item' => HotelModel, 'score' => float], ...]
     */
    public function getHotelRecommendations(array $userPreferenceVector, int $limit = 5): array
    {
        $hotels = HotelModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
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
     * @param  string  $query  The user's search query (e.g. "king bed ocean view room in Boracay")
     * @param  int  $limit  Maximum results to return
     * @return array Scored results: [['item' => RoomType, 'score' => float], ...]
     */
    public function searchRooms(string $query, int $limit = 5): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');

        if (! $queryVector) {
            Log::warning('searchRooms: Failed to generate query embedding.', ['query' => $query]);

            return [];
        }

        $rooms = RoomType::with('hotel.destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
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
     * @return string Formatted context string ready for prompt injection
     */
    /**
     * RSC action: last date a price field changed, per item id, from admin
     * audit logs (create rows count — the price was set then). Single query
     * per result set. Falls back to the model's updated_at at call sites.
     *
     * @return array<int, string> id => 'M d, Y'
     */
    public function priceUpdatedDates(string $auditableType, array $ids, string $priceField): array
    {
        $dates = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return $dates;
        }
        try {
            $logs = AdminAuditLog::where('auditable_type', $auditableType)
                ->whereIn('auditable_id', $ids)
                ->orderByDesc('created_at')
                ->get(['auditable_id', 'new_values', 'created_at']);
            foreach ($logs as $log) {
                $id = (int) $log->auditable_id;
                if (isset($dates[$id])) {
                    continue;
                }
                $new = $log->new_values;
                if (is_array($new) && array_key_exists($priceField, $new)) {
                    $dates[$id] = $log->created_at->format('M d, Y');
                }
            }
        } catch (\Throwable $e) {
            Log::debug('priceUpdatedDates failed: '.$e->getMessage());
        }

        return $dates;
    }

    public function getRoomContext(array $scoredRooms, ?string $ordering = null): string
    {
        if (empty($scoredRooms)) {
            return '';
        }

        $blocks = [];
        $priceDates = $this->priceUpdatedDates('room', array_map(fn ($e) => $e['item']->id, $scoredRooms), 'base_price');

        foreach ($scoredRooms as $index => $entry) {
            /** @var RoomType $room */
            $room = $entry['item'];
            $score = round($entry['score'], 4);
            $rank = $index + 1;

            $hotel = $room->hotel;
            $hotelName = $hotel->hotel_name ?? 'Unknown Hotel';
            $destName = $hotel->destination->name ?? 'Unknown Destination';
            if ($hotel->destination && $hotel->destination->region) {
                $destName .= " ({$hotel->destination->region})";
            }

            $amenities = $this->formatListToString($room->room_amenities);
            $descRaw = $room->description ?? '';
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($descRaw)));
            if (mb_strlen($desc) > 500) {
                $desc = mb_substr($desc, 0, 500).'…';
            }
            $idealGuest = $room->ideal_guest ?? $room->ideal_for;
            $notesRaw = $room->additional_notes ?? '';
            $notes = trim(preg_replace('/\s+/', ' ', strip_tags($notesRaw)));
            if (mb_strlen($notes) > 300) {
                $notes = mb_substr($notes, 0, 300).'…';
            }

            $baseOcc = (int) ($room->base_occupancy ?: 2);
            $maxOcc = (int) ($room->max_occupancy ?: ($room->occupancy ?: $baseOcc));
            $fee = (float) ($room->extra_person_fee ?: 0);
            $feeLine = $fee > 0 && $maxOcc > $baseOcc
                ? 'Extra Person Fee: ₱'.number_format($fee, 2).' per extra head per night beyond '.$baseOcc.' pax'
                : "No extra guests allowed — maximum {$maxOcc} guests";
            $rateExample = null;
            if ($maxOcc > $baseOcc && $fee > 0) {
                $examplePax = min($maxOcc, $baseOcc + 1);
                $exampleTotal = $room->calculateNightlyRate($examplePax);
                $rateExample = "Rate Example: {$baseOcc} pax = ₱".number_format($room->base_price, 2)."/night; {$examplePax} pax = ₱".number_format($exampleTotal, 2).'/night';
            }

            $rankLabel = $this->rankLabel($rank, $score, $ordering);
            $priceDate = $priceDates[$room->id] ?? $room->updated_at?->format('M d, Y');
            $lines = array_filter([
                "--- Room {$rankLabel} ---",
                "ROOM[id={$room->id}].name = {$room->room_name}",
                'ROOM[id='.$room->id.'].base_price = '.((float) $room->base_price),
                "Room Name: {$room->room_name}",
                "Hotel: {$hotelName}",
                "Destination: {$destName}",
                $idealGuest ? "Ideal Guest: {$idealGuest}" : null,
                "Base Occupancy: {$baseOcc} pax included in base price",
                "Maximum Occupancy: {$maxOcc} pax",
                $feeLine,
                $rateExample,
                "Bed Configuration: {$room->bed_configuration}",
                $room->room_size ? "Room Size: {$room->room_size}" : null,
                'Base Price: ₱'.number_format($room->base_price, 2).' per night',
                $priceDate ? "Price last updated: {$priceDate}" : null,
                $room->view_type ? "View Type: {$room->view_type}" : null,
                "Total Physical Rooms: {$room->total_rooms} (inventory count, not live availability)",
                $amenities ? "Amenities: {$amenities}" : null,
                $notes ? "Additional Notes & Policies: {$notes}" : null,
                $desc ? "Description: {$desc}" : null,
            ]);

            $blocks[] = $this->wrapRecord('room', $room->id, implode("\n", $lines));
        }

        $header = $this->resultsHeader(count($scoredRooms), $ordering);

        return $this->captureContextPrices("=== ROOM DATABASE RESULTS ===\n\n".$header.implode("\n\n", $blocks)."\n\n=== END ROOM RESULTS ===");
    }

    /**
     * Recommendation engine entry point: given a user's pre-computed preference vector,
     * retrieves all embedded rooms and ranks them by cosine similarity.
     *
     * @param  array  $userPreferenceVector  L2-normalized preference vector (e.g. from user profile)
     * @param  int  $limit  Maximum results to return
     * @return array Scored results: [['item' => RoomType, 'score' => float], ...]
     */
    public function getRoomRecommendations(array $userPreferenceVector, int $limit = 5): array
    {
        $rooms = RoomType::with('hotel.destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
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
     * @param  string  $query  User's search query (e.g. "sunset island hopping in Boracay")
     * @param  int  $limit  Maximum results to return
     * @return array Scored results: [['item' => ActivityModel, 'score' => float], ...]
     */
    public function searchActivities(string $query, int $limit = 5, ?int $destinationId = null, array $constraints = []): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');

        if (! $queryVector) {
            Log::warning('searchActivities: Failed to generate query embedding.', ['query' => $query]);

            return $this->activityLexicalFallback($limit, $destinationId, $constraints);
        }

        $vector = $this->formatVectorForDb($queryVector);
        if (! $vector) {
            return $this->activityLexicalFallback($limit, $destinationId, $constraints);
        }

        $this->lastRetrievalMode = 'semantic';

        $maxPrice = isset($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $pax = $constraints['pax'] ?? $this->extractPaxFromQuery($query);
        $pax = $pax !== null ? (int) $pax : null;
        $fetch = ($maxPrice !== null || $pax !== null) ? max($limit * 10, 50) : $limit;

        $scored = ActivityModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
            ->select('*')
            ->selectRaw('1.0 - (embedding <=> ?) AS similarity', [$vector])
            ->orderByRaw('embedding <=> ? ASC, id ASC', [$vector])
            ->limit($fetch)
            ->get()
            ->map(fn (ActivityModel $activity): array => ['item' => $activity, 'score' => (float) $activity->similarity])
            ->all();

        $scored = $this->applyRelevanceFloor($scored, self::RETRIEVAL_FLOOR_ACTIVITIES, 'activities');
        if ($pax !== null) {
            $scored = array_values(array_filter($scored, fn ($e) => $e['item']->getMaxCapacityInt() >= $pax));
        }
        if ($maxPrice !== null) {
            $paxForPrice = $pax ?? 1;
            [$inBudget, $overflow] = $this->splitBudgetOverflow($scored, fn ($a) => $this->activityPriceForPax($a, $paxForPrice), $maxPrice, self::OVER_BUDGET_MAX);
            $merged = array_merge($inBudget, $overflow);
            if (empty($merged) && ! empty($scored)) {
                return $this->cheapestFallback($scored, fn ($a) => $this->activityPriceForPax($a, $paxForPrice), min(3, $limit));
            }
            $scored = $merged;
        }

        return array_slice(array_values($scored), 0, $limit);
    }

    /**
     * Embedding-outage fallback for activities: visibility + destination-scoped
     * plain listing ordered by name, all scores zeroed.
     *
     * @param  array  $constraints  Reserved for budget/pax filters (unused — lexical listing ignores them).
     * @return array Scored results: [['item' => ActivityModel, 'score' => 0.0], ...]
     */
    protected function activityLexicalFallback(int $limit, ?int $destinationId, array $constraints = []): array
    {
        $this->lastRetrievalMode = 'lexical';
        $rows = ActivityModel::with('destination')
            ->where('is_shown', true)
            ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
            ->orderBy('activity_name')
            ->limit(max($limit * 2, 10))
            ->get();

        return $rows->map(fn ($a) => ['item' => $a, 'score' => 0.0])->all();
    }

    protected function extractPaxFromQuery(string $query): ?int
    {
        if (preg_match('/(\d+)\s*(?:pax|persons?|people|guests?)/i', $query, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Formats scored activity results into structured context text for RAG prompt injection.
     *
     * @param  array  $scoredActivities  Output from searchActivities() or rankRecommendations()
     * @return string Formatted context string ready for prompt injection
     */
    public function getActivityContext(array $scoredActivities, ?string $ordering = null): string
    {
        if (empty($scoredActivities)) {
            return '';
        }

        $blocks = [];
        $priceDates = $this->priceUpdatedDates('activity', array_map(fn ($e) => $e['item']->id, $scoredActivities), 'rate');

        foreach ($scoredActivities as $index => $entry) {
            /** @var ActivityModel $activity */
            $activity = $entry['item'];
            $score = round($entry['score'], 4);
            $rank = $index + 1;

            $dest = $activity->destination;
            $destName = $dest->name ?? 'Unknown Destination';
            if ($dest && $dest->region) {
                $destName .= " ({$dest->region})";
            }

            $vibes = $this->formatListToString($activity->vibe_tags);
            $descRaw = $activity->description ?? '';
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($descRaw)));
            if (mb_strlen($desc) > 500) {
                $desc = mb_substr($desc, 0, 500).'…';
            }
            $notes = trim(preg_replace('/\s+/', ' ', strip_tags($activity->notes ?? '')));
            if (mb_strlen($notes) > 300) {
                $notes = mb_substr($notes, 0, 300).'…';
            }
            $reqs = trim(preg_replace('/\s+/', ' ', strip_tags($activity->requirements ?? '')));
            $inclusions = $this->formatListToString($activity->inclusions);
            if (mb_strlen($inclusions) > 400) {
                $inclusions = mb_substr($inclusions, 0, 400).'…';
            }
            $exclusions = $this->formatListToString($activity->exclusions);
            if (mb_strlen($exclusions) > 400) {
                $exclusions = mb_substr($exclusions, 0, 400).'…';
            }
            $itineraryText = '';
            if (is_array($activity->itinerary) && ! empty($activity->itinerary)) {
                $parts = [];
                foreach (array_slice($activity->itinerary, 0, 8) as $step) {
                    if (is_string($step)) {
                        $t = trim($step);
                        if ($t !== '') {
                            $parts[] = $t;
                        }
                    } elseif (is_array($step)) {
                        $title = $step['title'] ?? $step['name'] ?? null;
                        if ($title) {
                            $duration = $step['duration'] ?? null;
                            $parts[] = $duration ? trim($title).' ('.trim($duration).')' : trim($title);
                        }
                    }
                }
                if ($parts) {
                    $itineraryText = implode(' → ', $parts);
                    if (mb_strlen($itineraryText) > 400) {
                        $itineraryText = mb_substr($itineraryText, 0, 400).'…';
                    }
                }
            }
            $rateType = $activity->isPerPersonRate() ? 'per person/head/pax' : 'per group/unit';
            $coords = null;
            if ($activity->latitude && $activity->longitude) {
                $coords = "Coordinates: {$activity->latitude}, {$activity->longitude}";
            } elseif ($dest && $dest->latitude && $dest->longitude) {
                $coords = "Near Coordinates: {$dest->latitude}, {$dest->longitude} (destination center)";
            }

            $rankLabel = $this->rankLabel($rank, $score, $ordering);
            $priceDate = $priceDates[$activity->id] ?? $activity->updated_at?->format('M d, Y');
            $lines = array_filter([
                "--- Activity {$rankLabel} ---",
                "ACTIVITY[id={$activity->id}].name = {$activity->activity_name}",
                $activity->duration ? "ACTIVITY[id={$activity->id}].duration = {$activity->duration}" : null,
                "Activity Name: {$activity->activity_name}",
                "Destination: {$destName}",
                $activity->specific_address ? "Specific Address: {$activity->specific_address}" : null,
                $coords,
                "Category: {$activity->category}",
                "Activity Level: {$activity->activity_level}",
                $activity->duration ? "Duration: {$activity->duration}" : null,
                $activity->capacity ? "Group Capacity: {$activity->capacity}" : null,
                "Rate / Pricing: {$activity->rate} ({$rateType})",
                $priceDate ? "Price last updated: {$priceDate}" : null,
                $activity->ideal_for ? "Ideal Participants: {$activity->ideal_for}" : null,
                $vibes ? "Vibes & Tags: {$vibes}" : null,
                $reqs ? "Requirements & Restrictions: {$reqs}" : null,
                $inclusions ? "Inclusions: {$inclusions}" : null,
                $exclusions ? "Exclusions: {$exclusions}" : null,
                $itineraryText ? "Itinerary: {$itineraryText}" : null,
                $notes ? "Additional Notes: {$notes}" : null,
                $desc ? "Description: {$desc}" : null,
            ]);

            $blocks[] = $this->wrapRecord('activity', $activity->id, implode("\n", $lines));
        }

        $header = $this->resultsHeader(count($scoredActivities), $ordering);

        return $this->captureContextPrices("=== ACTIVITY & TOUR DATABASE RESULTS ===\n\n".$header.implode("\n\n", $blocks)."\n\n=== END ACTIVITY RESULTS ===");
    }

    /**
     * Recommendation engine entry point for Activities & Tours.
     *
     * @param  array  $userPreferenceVector  L2-normalized preference vector
     * @param  int  $limit  Maximum results to return
     * @return array Scored results: [['item' => ActivityModel, 'score' => float], ...]
     */
    public function getActivityRecommendations(array $userPreferenceVector, int $limit = 5): array
    {
        $activities = ActivityModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->get();

        if ($activities->isEmpty()) {
            return [];
        }

        return $this->rankRecommendations($userPreferenceVector, $activities, $limit);
    }

    // =========================================================================
    //  Package-Specific RAG & Recommendation Methods
    // =========================================================================

    /**
     * Semantic search over packages using pgvector cosine distance (<=>).
     */
    public function searchPackages(string $query, int $limit = 5, ?int $destinationId = null, array $constraints = []): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');

        if (! $queryVector) {
            Log::warning('searchPackages: Failed to generate query embedding.', ['query' => $query]);

            return $this->packageLexicalFallback($limit, $destinationId, $constraints);
        }

        $vectorStr = $this->formatVectorForDb($queryVector);
        if (! $vectorStr) {
            return $this->packageLexicalFallback($limit, $destinationId, $constraints);
        }

        $this->lastRetrievalMode = 'semantic';

        $maxPrice = isset($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $pax = isset($constraints['pax']) ? (int) $constraints['pax'] : null;
        $fetch = ($maxPrice !== null || $pax !== null) ? max($limit * 10, 50) : $limit;

        $packages = Package::with('destination')
            ->where('is_active', true)
            ->whereNotNull('embedding')
            ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
            ->when($pax !== null, fn ($q) => $q->where('min_pax', '<=', $pax))
            ->select('*')
            ->selectRaw('1.0 - (embedding <=> ?) AS similarity', [$vectorStr])
            ->orderByRaw('embedding <=> ? ASC, id ASC', [$vectorStr])
            ->limit($fetch)
            ->get();

        $results = [];
        foreach ($packages as $pkg) {
            $results[] = ['item' => $pkg, 'score' => (float) $pkg->similarity];
        }

        $results = $this->applyRelevanceFloor($results, self::RETRIEVAL_FLOOR_PACKAGES, 'packages');
        if ($maxPrice !== null) {
            [$inBudget, $overflow] = $this->splitBudgetOverflow($results, fn ($p) => $this->packagePrice($p), $maxPrice, self::OVER_BUDGET_MAX);
            $merged = array_merge($inBudget, $overflow);
            if (empty($merged) && ! empty($results)) {
                return $this->cheapestFallback($results, fn ($p) => $this->packagePrice($p), min(3, $limit));
            }
            $results = $merged;
        }

        return array_slice(array_values($results), 0, $limit);
    }

    /**
     * Embedding-outage fallback for packages: active + destination-scoped
     * plain listing ordered by name, all scores zeroed.
     *
     * @param  array  $constraints  Reserved for budget/pax filters (unused — lexical listing ignores them).
     * @return array Scored results: [['item' => Package, 'score' => 0.0], ...]
     */
    protected function packageLexicalFallback(int $limit, ?int $destinationId, array $constraints = []): array
    {
        $this->lastRetrievalMode = 'lexical';
        $rows = Package::with('destination')
            ->where('is_active', true)
            ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
            ->orderBy('name')
            ->limit(max($limit * 2, 10))
            ->get();

        return $rows->map(fn ($p) => ['item' => $p, 'score' => 0.0])->all();
    }

    /**
     * Semantic search over FAQs using pgvector cosine distance (&lt;=&gt;).
     */
    public function searchFaqs(string $query, int $limit = 3): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');

        if (! $queryVector) {
            Log::warning('searchFaqs: Failed to generate query embedding.', ['query' => $query]);

            return [];
        }

        $vectorStr = $this->formatVectorForDb($queryVector);
        if (! $vectorStr) {
            return [];
        }

        $faqs = Faq::where('is_active', true)
            ->whereNotNull('embedding')
            ->select('*')
            ->selectRaw('1.0 - (embedding <=> ?) AS similarity', [$vectorStr])
            ->orderByRaw('embedding <=> ? ASC, id ASC', [$vectorStr])
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($faqs as $faq) {
            $results[] = ['item' => $faq, 'score' => (float) $faq->similarity];
        }

        return $results;
    }

    /**
     * Formats scored package results into structured context text for RAG prompt injection.
     */
    public function getPackageContext(array $scoredPackages, ?string $ordering = null): string
    {
        if (empty($scoredPackages)) {
            return '';
        }

        $blocks = [];
        $priceDates = $this->priceUpdatedDates('package', array_map(fn ($e) => $e['item']->id, $scoredPackages), 'price');

        foreach ($scoredPackages as $index => $entry) {
            /** @var Package $package */
            $package = $entry['item'];
            $score = round($entry['score'], 4);
            $rank = $index + 1;

            $dest = $package->destination;
            $destName = $dest->name ?? 'Philippines';
            if ($dest && $dest->region) {
                $destName .= " ({$dest->region})";
            }
            $inclusions = is_array($package->generic_inclusions) ? implode(', ', $package->generic_inclusions) : '';

            $validity = null;
            if ($package->valid_from || $package->valid_to) {
                $from = $package->valid_from ? $package->valid_from->format('M d, Y') : 'open';
                $to = $package->valid_to ? $package->valid_to->format('M d, Y') : 'open';
                $validity = "Validity: {$from} to {$to}";
            } else {
                $validity = 'Validity: Year-round / No expiry';
            }
            $status = $package->is_active ? 'Status: Active' : 'Status: Inactive';

            $hotelNames = null;
            $roomNames = null;
            $activityNames = null;
            $addOnNames = null;
            try {
                $hotels = $package->hotels()->pluck('hotel_name')->all();
                if (! empty($hotels)) {
                    $hotelNames = 'Hotels Included: '.implode(', ', array_slice($hotels, 0, 10));
                }
                $rooms = $package->rooms()->pluck('room_name')->all();
                if (! empty($rooms)) {
                    $roomNames = 'Rooms Included: '.implode(', ', array_slice($rooms, 0, 10));
                }
                $activities = $package->activities()->pluck('activity_name')->all();
                if (! empty($activities)) {
                    $activityNames = 'Activities Included: '.implode(', ', array_slice($activities, 0, 10));
                }
                $addOns = $package->addOns()->pluck('name')->all();
                if (! empty($addOns)) {
                    $addOnNames = 'Add-ons Included: '.implode(', ', array_slice($addOns, 0, 10));
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $rankLabel = $this->rankLabel($rank, $score, $ordering);
            $priceDate = $priceDates[$package->id] ?? $package->updated_at?->format('M d, Y');
            $lines = array_filter([
                "--- Package {$rankLabel} ---",
                "PACKAGE[id={$package->id}].name = {$package->name}",
                'PACKAGE[id='.$package->id.'].price = '.((float) $package->price),
                "Package Name: {$package->name}",
                "Destination: {$destName}",
                'Type: '.($package->type ?: 'Standard Tour Promo'),
                'Price: ₱'.number_format($package->price, 2).' per pax (total = price × guests)',
                $priceDate ? "Price last updated: {$priceDate}" : null,
                "Duration: {$package->days}D/{$package->nights}N",
                "Minimum Guests: {$package->min_pax} pax",
                $validity,
                $status,
                $hotelNames,
                $roomNames,
                $activityNames,
                $addOnNames,
                $inclusions ? "Inclusions: {$inclusions}" : null,
            ]);

            $blocks[] = $this->wrapRecord('package', $package->id, implode("\n", $lines));
        }

        $header = $this->resultsHeader(count($scoredPackages), $ordering);

        return $this->captureContextPrices("=== PACKAGE DATABASE RESULTS ===\n\n".$header.implode("\n\n", $blocks)."\n\n=== END PACKAGE RESULTS ===");
    }

    /**
     * Semantic search over AddOns using pgvector / PHP ranking.
     */
    public function searchAddOns(string $query, int $limit = 5, ?int $destinationId = null, array $constraints = []): array
    {
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');
        if (! $queryVector) {
            Log::warning('searchAddOns: Failed to generate query embedding.', ['query' => $query]);

            return $this->addOnLexicalFallback($limit, $destinationId, $constraints);
        }

        $vector = $this->formatVectorForDb($queryVector);
        if (! $vector) {
            return $this->addOnLexicalFallback($limit, $destinationId, $constraints);
        }

        $this->lastRetrievalMode = 'semantic';

        $maxPrice = isset($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $pax = isset($constraints['pax']) ? (int) $constraints['pax'] : null;
        $fetch = ($maxPrice !== null || $pax !== null || $destinationId !== null) ? max($limit * 10, 50) : $limit;

        $scored = AddOnModel::with('destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding')
            ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
            ->select('*')
            ->selectRaw('1.0 - (embedding <=> ?) AS similarity', [$vector])
            ->orderByRaw('embedding <=> ? ASC, id ASC', [$vector])
            ->limit($fetch)
            ->get()
            ->map(fn (AddOnModel $addon): array => ['item' => $addon, 'score' => (float) $addon->similarity])
            ->all();

        $scored = $this->applyRelevanceFloor($scored, self::RETRIEVAL_FLOOR_ADDONS, 'addons');
        if ($pax !== null) {
            $scored = array_values(array_filter($scored, fn ($e) => $e['item']->getMaxPax() >= $pax));
        }
        if ($maxPrice !== null) {
            $paxForPrice = $pax ?? 1;
            [$inBudget, $overflow] = $this->splitBudgetOverflow($scored, fn ($a) => $this->addonPriceForPax($a, $paxForPrice), $maxPrice, self::OVER_BUDGET_MAX);
            $merged = array_merge($inBudget, $overflow);
            if (empty($merged) && ! empty($scored)) {
                return $this->cheapestFallback($scored, fn ($a) => $this->addonPriceForPax($a, $paxForPrice), min(3, $limit));
            }
            $scored = $merged;
        }

        return array_slice(array_values($scored), 0, $limit);
    }

    /**
     * Embedding-outage fallback for add-ons: visibility + destination-scoped
     * plain listing ordered by name, all scores zeroed.
     *
     * @param  array  $constraints  Reserved for budget/pax filters (unused — lexical listing ignores them).
     * @return array Scored results: [['item' => AddOnModel, 'score' => 0.0], ...]
     */
    protected function addOnLexicalFallback(int $limit, ?int $destinationId, array $constraints = []): array
    {
        $this->lastRetrievalMode = 'lexical';
        $rows = AddOnModel::with('destination')
            ->where('is_shown', true)
            ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
            ->orderBy('name')
            ->limit(max($limit * 2, 10))
            ->get();

        return $rows->map(fn ($a) => ['item' => $a, 'score' => 0.0])->all();
    }

    /**
     * Formats scored addon results into structured context text for RAG prompt injection.
     */
    public function getAddOnContext(array $scoredAddOns, ?string $ordering = null): string
    {
        if (empty($scoredAddOns)) {
            return '';
        }

        $blocks = [];
        $priceDates = $this->priceUpdatedDates('addon', array_map(fn ($e) => $e['item']->id, $scoredAddOns), 'pricing_tiers');

        foreach ($scoredAddOns as $index => $entry) {
            /** @var AddOnModel $addon */
            $addon = $entry['item'];
            $score = round($entry['score'], 4);
            $rank = $index + 1;

            $destName = $addon->destination->name ?? 'Unknown Destination';
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($addon->description ?? '')));
            if (mb_strlen($desc) > 500) {
                $desc = mb_substr($desc, 0, 500).'…';
            }
            $inclusions = $this->formatListToString($addon->inclusions);

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
                        $surchargeList .= "{$sur['name']} (₱{$sur['amount']}".(isset($sur['type']) ? ' '.$sur['type'] : '').'). ';
                    }
                }
            }

            $rankLabel = $this->rankLabel($rank, $score, $ordering);
            $priceDate = $priceDates[$addon->id] ?? $addon->updated_at?->format('M d, Y');
            $lines = array_filter([
                "--- AddOn {$rankLabel} ---",
                "ADDON[id={$addon->id}].name = {$addon->name}",
                "ADDON[id={$addon->id}].type = {$addon->type}",
                "AddOn Name: {$addon->name}",
                "Type: {$addon->type}",
                "Destination: {$destName}",
                $inclusions ? "Inclusions: {$inclusions}" : null,
                $pricingList ? "Tiered Pricing: {$pricingList}" : null,
                $surchargeList ? "Surcharges: {$surchargeList}" : null,
                $priceDate ? "Price last updated: {$priceDate}" : null,
                $desc ? "Description: {$desc}" : null,
            ]);

            $blocks[] = $this->wrapRecord('addon', $addon->id, implode("\n", $lines));
        }

        $header = $this->resultsHeader(count($scoredAddOns), $ordering);

        return $this->captureContextPrices("=== ADDON DATABASE RESULTS ===\n\n".$header.implode("\n\n", $blocks)."\n\n=== END ADDON RESULTS ===");
    }

    // =========================================================================
    //  Reviews: Sentiment Analysis & Multi-Level Summarization (DSS)
    // =========================================================================

    protected static array $promptCache = [];

    /**
     * Loads a system prompt from an external .md file in Services/SystemPrompts/.
     * Fails loudly (HTTP 500) if the file is missing — never silently degrades.
     */
    public function loadSystemPrompt(string $filename): string
    {
        if (isset(self::$promptCache[$filename])) {
            return self::$promptCache[$filename];
        }

        $path = app_path("Services/SystemPrompts/{$filename}");

        abort_unless(File::exists($path), 500, "Missing Gemini system prompt file: {$filename}");

        $mtime = File::lastModified($path);
        $cacheKey = "gemini:system_prompt:{$filename}";

        try {
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && ($cached['mtime'] ?? null) === $mtime) {
                return self::$promptCache[$filename] = $cached['content'];
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini prompt cache read failed, falling back to disk: '.$e->getMessage());
        }

        $content = File::get($path);

        try {
            Cache::put($cacheKey, ['content' => $content, 'mtime' => $mtime], now()->addDay());
        } catch (\Throwable $e) {
            Log::warning('Gemini prompt cache write failed: '.$e->getMessage());
        }

        return self::$promptCache[$filename] = $content;
    }

    /**
     * Sends a text generation request to the configured Gemini chat model.
     *
     * @return string|null The raw model output text, or null on failure.
     */
    public function generateContent(string $systemInstruction, string $prompt): ?string
    {
        $modelName = config('services.gemini.chat_model') ?? 'models/gemini-2.5-flash-lite';

        return $this->postGenerate($modelName, $systemInstruction, $prompt, 0.2);
    }

    /**
     * Same as generateContent() but against an explicit model + temperature
     * (e.g. a stronger judge model at temperature 0 for eval grading).
     *
     * @return string|null The raw model output text, or null on failure.
     */
    public function generateContentWithModel(string $modelName, string $systemInstruction, string $prompt, float $temperature = 0.0): ?string
    {
        return $this->postGenerate($modelName, $systemInstruction, $prompt, $temperature);
    }

    protected function postGenerate(string $modelName, string $systemInstruction, string $prompt, float $temperature): ?string
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            Log::warning('Gemini API key is not configured in services.gemini.api_key.');

            return null;
        }

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
                'temperature' => $temperature,
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
            Log::error('Gemini GenerateContent Exception: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Runs Gemini sentiment analysis + keyword extraction on a single review comment.
     *
     * @param  string  $comment  The review comment text (supports Taglish).
     * @return array ['sentiment' => 'positive'|'neutral'|'negative',
     *               'confidence_score' => float,
     *               'extracted_keywords' => string[]]
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
                    fn ($kw) => is_string($kw) ? mb_substr(trim($kw), 0, 40) : null,
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
            'ganda',
            'maganda',
            'super',
            'clean',
            'spotless',
            'linis',
            'friendly',
            'polite',
            'mabait',
            'amazing',
            'breathtaking',
            'love',
            'loved',
            'recommend',
            'excellent',
            'great',
            'nice',
            'good',
            'perfect',
            'beautiful',
            'comfortable',
            'worth',
            'masarap',
            'sulit',
            'fun',
            'enjoy',
            'best',
            'awesome',
            'courteous',
            'delicious',
            'quiet',
            'spacious',
            'cozy',
            'helpful',
            'fast',
            'mabilis',
            'bait',
            'ang galing',
        ];

        $negativeTerms = [
            'bad',
            'poor',
            'slow',
            'spotty',
            'dirty',
            'rude',
            'terrible',
            'worst',
            'expensive',
            'mahal',
            'pangit',
            'mabagal',
            'bagal',
            'maingay',
            'noisy',
            'broken',
            'mold',
            'smell',
            'smelled',
            'disappoint',
            'late',
            'delay',
            'delayed',
            'uncomfortable',
            'awful',
            'sucks',
            'problem',
            'issue',
            'masama',
            'mainit',
            'reklamo',
            'antipatiko',
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
            'the',
            'and',
            'was',
            'were',
            'with',
            'that',
            'this',
            'have',
            'has',
            'had',
            'for',
            'not',
            'but',
            'you',
            'our',
            'your',
            'from',
            'they',
            'there',
            'were',
            'ang',
            'ng',
            'sa',
            'at',
            'ako',
            'kami',
            'namin',
            'naman',
            'kasi',
            'kaya',
            'na',
            'si',
            'sila',
            'daw',
            'rin',
            'din',
            'po',
            'yung',
            'pero',
            'then',
            'also',
        ];

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($comment)) ?: [];

        $freq = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (mb_strlen($token) > 2 && ! in_array($token, $stopwords, true)) {
                $freq[$token] = ($freq[$token] ?? 0) + 1;
            }
        }

        foreach ($seedTerms as $seed) {
            $freq[$seed] = ($freq[$seed] ?? 0) + 2;
        }

        arsort($freq);

        return array_slice(array_keys($freq), 0, 6);
    }

    // =========================================================================
    //  Admin Booking Report Analysis (DSS)
    // =========================================================================

    /**
     * AI analysis of an admin booking report.
     *
     * @param  array  $stats  Compact report payload from AdminReportController.
     * @return array ['executive_summary' => string,
     *               'insights' => string[],
     *               'anomalies' => string[],
     *               'recommendations' => string[]]
     */
    public function analyzeBookingReport(array $stats): array
    {
        $systemInstruction = $this->loadSystemPrompt('booking-report-analysis-prompt.md');

        $prompt = "Booking Report Stats (JSON):\n".json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $raw = $this->generateContent($systemInstruction, $prompt);

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $stringList = fn ($list) => array_values(array_filter(array_map(
                    fn ($item) => is_string($item) ? mb_substr(trim($item), 0, 300) : null,
                    is_array($list) ? $list : []
                )));

                $summary = isset($decoded['executive_summary']) && is_string($decoded['executive_summary'])
                    ? mb_substr(trim($decoded['executive_summary']), 0, 1000)
                    : '';

                return [
                    'executive_summary' => $summary,
                    'insights' => $stringList($decoded['insights'] ?? []),
                    'anomalies' => $stringList($decoded['anomalies'] ?? []),
                    'recommendations' => $stringList($decoded['recommendations'] ?? []),
                ];
            }
        }

        return $this->fallbackBookingReportAnalysis($stats);
    }

    /**
     * Deterministic offline fallback for booking report analysis
     * (no API key, timeout, or malformed response).
     */
    public function fallbackBookingReportAnalysis(array $stats): array
    {
        $total = (int) ($stats['total_bookings'] ?? 0);
        $collected = (float) ($stats['revenue']['collected'] ?? 0);
        $estimated = (float) ($stats['revenue']['estimated_awaiting_payment'] ?? 0);
        $avg = (float) ($stats['average_booking_value'] ?? 0);
        $breakdown = $stats['status_breakdown'] ?? [];
        $range = $stats['range'] ?? '';
        $peak = $stats['peak_day'] ?? null;
        $stale = (int) ($stats['stale_pending_count'] ?? 0);
        $refunds = (int) ($stats['refunded_count'] ?? 0);
        $adjustments = (int) ($stats['admin_price_adjustments'] ?? 0);
        $expired = (int) ($stats['expired_count'] ?? 0);
        $statusLabel = $stats['status_filter'] ?? 'all statuses';

        $summary = "This report covers {$range} ({$statusLabel}). "
            .($total > 0
                ? "It contains {$total} booking".($total > 1 ? 's' : '').' worth ₱'.number_format($collected).' collected (paid) plus ₱'.number_format($estimated).' awaiting payment, averaging ₱'.number_format($avg, 2).' per booking.'
                : 'It contains no bookings matching the current filters.');

        $insights = [];
        if ($total > 0) {
            $insights[] = 'Average booking value is ₱'.number_format($avg, 2).' across the period.';
            if ($estimated > 0) {
                $insights[] = '₱'.number_format($estimated).' is awaiting payment from approved bookings not yet settled.';
            }
            foreach (['pending', 'paid', 'completed', 'rejected', 'cancelled', 'expired'] as $key) {
                if (isset($breakdown[$key]) && $breakdown[$key] > 0) {
                    $pct = round(($breakdown[$key] / $total) * 100);
                    $insights[] = ucfirst($key)." bookings account for {$pct}% of the period volume ({$breakdown[$key]} total).";
                }
            }
            if ($peak) {
                $insights[] = "Busiest day was {$peak['date']} with {$peak['bookings']} booking(s).";
            }
        }

        $anomalies = [];
        if ($stale > 0) {
            $anomalies[] = "{$stale} pending booking(s) have not been reviewed for over 48 hours.";
        }
        if ($expired > 0) {
            $anomalies[] = "{$expired} booking(s) expired without payment during this period.";
        }
        if ($refunds > 0) {
            $anomalies[] = "{$refunds} booking(s) were refunded, worth reviewing for recurring causes.";
        }
        if ($adjustments > 0) {
            $anomalies[] = "{$adjustments} booking(s) received manual admin price adjustments.";
        }
        if (empty($anomalies)) {
            $anomalies[] = 'No major anomalies detected in the current period.';
        }

        $recommendations = [
            'Review the oldest pending bookings first to keep the approval queue within 48 hours.',
            'Keep monitoring approved bookings against their payment deadlines to reduce expiries.',
        ];
        if ($refunds > 0 || $adjustments > 0) {
            $recommendations[] = 'Audit the reasons behind refunds and admin price adjustments for service improvements.';
        }

        return [
            'executive_summary' => $summary,
            'insights' => array_slice($insights, 0, 5),
            'anomalies' => array_slice($anomalies, 0, 4),
            'recommendations' => array_slice($recommendations, 0, 4),
        ];
    }

    /**
     * Generates an AI consensus summary for an entity from its recent reviews.
     *
     * @param  Collection|array  $reviews  Collection of Review models (or arrays) to summarize.
     * @param  string  $entityLabel  Human label of the entity (e.g. "Deluxe Ocean View Room at Villa Maria Resort").
     * @return array ['ai_summary_text' => string|null,
     *               'top_positive_highlights' => string[],
     *               'top_negative_highlights' => string[],
     *               'most_frequent_keywords' => ['keyword' => count, ...]]
     */
    public function summarizeReviews(Collection|array $reviews, string $entityLabel): array
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
        $stringList = fn ($list) => array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? mb_substr(trim($item), 0, 160) : null,
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
        $lines[] = "- Guests rate this experience {$avg}/5 across {$total} verified review".($total > 1 ? 's' : '').'.';
        if ($positive > 0) {
            $lines[] = '- '.round(($positive / $total) * 100).'% of guests shared positive feedback'.(count($topKeywords) ? ', often highlighting: '.implode(', ', array_slice($topKeywords, 0, 3)).'.' : '.');
        }
        if ($negative > 0) {
            $lines[] = '- A minority ('.round(($negative / $total) * 100).'%) noted concerns worth checking at check-in.';
        }
        $lines[] = '- Newest guest comments are available below for first-hand detail.';

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
                $lines[] = "AI Consensus Summary:\n".$summary->ai_summary_text;
            }
        }

        foreach ($reviews as $review) {
            $lines[] = "Review snippet ({$review->rating}/5, {$review->sentiment}): \"{$review->comment}\"";
        }

        return "=== REVIEW INSIGHTS ===\n\n".implode("\n", $lines)."\n\n=== END REVIEW INSIGHTS ===";
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
     * @param  User  $user  Authenticated registered user
     * @param  string  $message  User's chat input query
     * @return array|null Returns violation response payload if flagged/blocked, or null if clean
     */
    public function detectAbuseAndGuard(User $user, string $message): ?array
    {
        // 1. Account Suspension Check
        if ($user->isBanned()) {
            return [
                'blocked' => true,
                'response' => 'Your account has been suspended from using the AI Chatbot due to terms of service violations. Reason: '.($user->ban_reason ?? 'Repeated community guideline violations.'),
            ];
        }

        // 2. Category Detection via the shared moderation policy so guests and
        // authenticated users evaluate the same vocabulary.
        $match = ChatbotModerationPolicy::match($message);

        $flaggedCategory = $match['category'] ?? null;
        $flaggedReason = $match['reason'] ?? null;

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

    // =========================================================================
    //  Chatbot: Hybrid Search, Multi-Turn Chat, Itinerary Builder
    // =========================================================================

    public function searchRoomsHybrid(string $query, array $constraints, int $limit = 5): array
    {
        $maxPrice = ! empty($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $pax = ! empty($constraints['pax']) ? (int) $constraints['pax'] : null;
        $queryVector = $this->generateEmbedding($query, 'RETRIEVAL_QUERY');
        if (! $queryVector) {
            Log::warning('searchRoomsHybrid: query embedding failed, fallback to price-only', ['query' => $query, 'constraints' => $constraints]);

            $this->lastRetrievalMode = 'lexical';
            $fallback = RoomType::with('hotel.destination')
                ->where('is_shown', true)
                ->whereNotNull('embedding')
                ->when(! empty($constraints['destination_id']), fn ($q) => $q->whereHas('hotel', fn ($qq) => $qq->where('destination_id', $constraints['destination_id'])))
                ->when($pax, fn ($q) => $q->whereRaw('COALESCE(max_occupancy, base_occupancy, 2) >= ?', [$pax]))
                ->when(! empty($constraints['hotel_id']), fn ($q) => $q->where('hotel_id', $constraints['hotel_id']))
                ->when(! empty($constraints['room_id']), fn ($q) => $q->where('id', $constraints['room_id']))
                ->when(empty($constraints['room_id']) && ! empty($constraints['room_name']), fn ($q) => $q->where('room_name', 'ILIKE', $constraints['room_name']))
                ->orderBy('base_price', 'asc')
                ->limit(max($limit * 10, 50))
                ->get();

            if ($fallback->isEmpty() && empty($constraints['hotel_id']) && empty($constraints['room_id']) && empty($constraints['room_name'])) {
                $fallback = RoomType::with('hotel.destination')
                    ->where('is_shown', true)
                    ->whereNotNull('embedding')
                    ->when(! empty($constraints['destination_id']), fn ($q) => $q->whereHas('hotel', fn ($qq) => $qq->where('destination_id', $constraints['destination_id'])))
                    ->orderBy('base_price', 'asc')
                    ->limit(max($limit * 10, 50))
                    ->get();
            }

            if ($fallback->isEmpty() && $pax && empty($constraints['hotel_id']) && empty($constraints['room_id']) && empty($constraints['room_name'])) {
                $scopeMax = $this->maxRoomOccupancy($constraints['destination_id'] ?? null, null);
                if ($scopeMax > 0 && $pax > $scopeMax) {
                    $pairs = $this->groupSplitOptions($constraints['destination_id'] ?? null, null, $pax, null, 2);
                    if (! empty($pairs)) {
                        return $this->flattenGroupPairs($pairs, $maxPrice);
                    }
                }
            }

            if ($fallback->isEmpty()) {
                return [];
            }

            $scored = $fallback->map(fn ($r) => ['item' => $r, 'score' => 0.0])->all();
            if ($maxPrice !== null) {
                [$inBudget, $overflow] = $this->splitBudgetOverflow($scored, fn ($r) => $this->roomPriceForPax($r, $pax), $maxPrice, self::OVER_BUDGET_MAX);
                $merged = array_merge($inBudget, $overflow);
                if (empty($merged) && ! empty($scored)) {
                    return $this->cheapestFallback($scored, fn ($r) => $this->roomPriceForPax($r, $pax), min(3, $limit));
                }
                $scored = $merged;
            }

            return array_slice(array_values($scored), 0, $limit);
        }

        $this->lastRetrievalMode = 'semantic';

        $roomsQuery = RoomType::with('hotel.destination')
            ->where('is_shown', true)
            ->whereNotNull('embedding');

        if (! empty($constraints['destination_id'])) {
            $roomsQuery->whereHas('hotel', fn ($q) => $q->where('destination_id', $constraints['destination_id']));
        }
        if (! empty($constraints['hotel_id'])) {
            $roomsQuery->where('hotel_id', $constraints['hotel_id']);
        }
        if ($pax) {
            $roomsQuery->whereRaw('COALESCE(max_occupancy, base_occupancy, 2) >= ?', [$pax]);
        }
        if (! empty($constraints['room_id'])) {
            $roomsQuery->where('id', $constraints['room_id']);
        } elseif (! empty($constraints['room_name'])) {
            $roomsQuery->where('room_name', 'ILIKE', $constraints['room_name']);
        }

        $rooms = $roomsQuery->get();

        // If room-specific filter yielded nothing and hotel scope was present, try global room-name-only fallback (per answer 5 both)
        if ($rooms->isEmpty() && ! empty($constraints['room_name']) && ! empty($constraints['hotel_id'])) {
            $rooms = RoomType::with('hotel.destination')
                ->where('is_shown', true)
                ->whereNotNull('embedding')
                ->where('room_name', 'ILIKE', $constraints['room_name'])
                ->get();
        }

        // Group too big for any single room: suggest cheapest 2-room same-hotel
        // splits instead of returning nothing (pax is the blocker, not budget).
        if ($rooms->isEmpty() && $pax && empty($constraints['hotel_id']) && empty($constraints['room_id']) && empty($constraints['room_name'])) {
            $scopeMax = $this->maxRoomOccupancy($constraints['destination_id'] ?? null, null);
            if ($scopeMax > 0 && $pax > $scopeMax) {
                $pairs = $this->groupSplitOptions($constraints['destination_id'] ?? null, null, $pax, $queryVector, 2);
                if (! empty($pairs)) {
                    return $this->flattenGroupPairs($pairs, $maxPrice);
                }
            }
        }

        if ($rooms->isEmpty() && empty($constraints['hotel_id']) && empty($constraints['room_id']) && empty($constraints['room_name'])) {
            $rooms = RoomType::with('hotel.destination')
                ->where('is_shown', true)
                ->whereNotNull('embedding')
                ->when(! empty($constraints['destination_id']), fn ($q) => $q->whereHas('hotel', fn ($qq) => $qq->where('destination_id', $constraints['destination_id'])))
                ->get();
        }

        if ($rooms->isEmpty()) {
            return [];
        }

        $scored = $this->rankRecommendations($queryVector, $rooms, max($limit * 10, 50));
        $scored = $this->applyRelevanceFloor($scored, self::RETRIEVAL_FLOOR_ROOMS, 'rooms');
        if ($maxPrice !== null) {
            [$inBudget, $overflow] = $this->splitBudgetOverflow($scored, fn ($r) => $this->roomPriceForPax($r, $pax), $maxPrice, self::OVER_BUDGET_MAX);
            $merged = array_merge($inBudget, $overflow);
            if (empty($merged) && ! empty($scored)) {
                return $this->cheapestFallback($scored, fn ($r) => $this->roomPriceForPax($r, $pax), min(3, $limit));
            }
            $scored = $merged;
        }

        return array_slice(array_values($scored), 0, $limit);
    }

    /**
     * Create a Gemini cachedContents resource for the chat system prompt.
     */
    protected function createChatSystemPromptCache(string $systemInstruction, string $modelName): ?string
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/cachedContents?key={$apiKey}";

        $payload = [
            'model' => $modelName,
            'displayName' => 'sunnydot-chat-system-prompt',
            'contents' => [],
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'ttl' => '86400s',
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)->post($url, $payload);

            if (! $response->successful()) {
                Log::error('Gemini CachedContent Create Failed: ', ['response' => $response->body()]);

                return null;
            }

            $name = $response->json('name');

            return is_string($name) ? $name : null;
        } catch (\Exception $e) {
            Log::error('Gemini CachedContent Exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Resolve a cachedContents resource name for the chat system prompt.
     */
    public function resolveChatSystemPromptCache(string $systemInstruction, string $modelName): ?string
    {
        if (! config('services.gemini.chat_context_cache')) {
            return null;
        }

        if (strlen($systemInstruction) < 1024) {
            return null;
        }

        $key = 'gemini:chat_cache:'.md5($modelName.'|'.$systemInstruction);

        try {
            $cached = Cache::get($key);

            $freshThreshold = ($cached['negative'] ?? false)
                ? now()->timestamp
                : now()->addHour()->timestamp;

            if (is_array($cached) && ($cached['expires_at'] ?? 0) > $freshThreshold) {
                if ($cached['negative'] ?? false) {
                    return null;
                }

                if (is_string($cached['name'] ?? null)) {
                    return $cached['name'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini chat cache read failed, falling back to inline systemInstruction: '.$e->getMessage());

            return null;
        }

        $name = $this->createChatSystemPromptCache($systemInstruction, $modelName);

        if ($name === null) {
            try {
                Cache::put($key, ['name' => null, 'expires_at' => now()->addMinutes(10)->timestamp, 'negative' => true], now()->addMinutes(10));
            } catch (\Throwable $e) {
                Log::warning('Gemini chat cache negative marker write failed: '.$e->getMessage());
            }

            return null;
        }

        try {
            Cache::put($key, ['name' => $name, 'expires_at' => now()->addHours(23)->timestamp], now()->addDay());
        } catch (\Throwable $e) {
            Log::warning('Gemini chat cache write failed: '.$e->getMessage());
        }

        return $name;
    }

    /**
     * Multi-turn chat response (plain-text, no forced JSON).
     */
    /**
     * Chat generation settings by response mode. Grounded answers use
     * temperature 0 (deterministic wording over identical retrieval);
     * conversational chat keeps a small temperature for friendly tone.
     * Temperature zero reduces variation but cannot guarantee
     * byte-identical cloud-model text — the factual source of truth is the
     * normalized server context and retrieved IDs, not the prose.
     *
     * @return array{temperature: float, topP: float}
     */
    public function chatGenerationSettings(bool $conversational = false): array
    {
        if ($conversational) {
            return [
                'temperature' => (float) config('services.gemini.chat_conversational_temperature', 0.2),
                'topP' => 0.95,
            ];
        }

        return [
            'temperature' => (float) config('services.gemini.chat_grounded_temperature', 0),
            'topP' => 1.0,
        ];
    }

    public function generateChatResponse(string $systemInstruction, array $history, string $userPrompt, bool $conversational = false): ?string
    {
        $this->lastPromptHash = hash('sha256', $systemInstruction."\n".$userPrompt);
        $this->lastFinishReason = null;
        $this->lastCandidateCount = null;
        $this->lastTokenUsage = null;
        $this->lastLatencyMs = null;

        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            Log::warning('Gemini API key is not configured.');

            return null;
        }

        $modelName = config('services.gemini.chat_model') ?? 'models/gemini-2.5-flash-lite';
        $url = "https://generativelanguage.googleapis.com/v1beta/{$modelName}:generateContent?key={$apiKey}";

        $contents = [];
        foreach ($history as $entry) {
            $contents[] = [
                'role' => $entry['role'],
                'parts' => [['text' => $entry['parts'][0]['text'] ?? '']],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $userPrompt]]];

        $cacheName = $this->resolveChatSystemPromptCache($systemInstruction, $modelName);
        $useCache = $cacheName !== null;
        $cacheKey = 'gemini:chat_cache:'.md5($modelName.'|'.$systemInstruction);
        $response = null;
        $sawSuccessfulEmpty = false;
        $startedAt = (int) (microtime(true) * 1000);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $settings = $this->chatGenerationSettings($conversational);
            $payload = [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $settings['temperature'],
                    'topP' => $settings['topP'],
                    'maxOutputTokens' => 1024,
                ],
            ];

            if ($useCache) {
                $payload['cachedContent'] = $cacheName;
            } else {
                $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
            }

            $isCacheRelated = false;

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($url, $payload);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    if (is_string($text) && trim($text) !== '') {
                        $finishReason = $response->json('candidates.0.finishReason');
                        $this->lastFinishReason = $finishReason === 'SAFETY' ? 'safety' : $finishReason;
                        $this->lastCandidateCount = is_array($response->json('candidates')) ? count($response->json('candidates')) : null;
                        $usage = $response->json('usageMetadata');
                        $this->lastTokenUsage = is_array($usage) ? array_filter([
                            'prompt' => $usage['promptTokenCount'] ?? null,
                            'candidates' => $usage['candidatesTokenCount'] ?? null,
                            'total' => $usage['totalTokenCount'] ?? null,
                        ]) : null;
                        $this->lastLatencyMs = (int) (microtime(true) * 1000) - $startedAt;
                        Log::info('Gemini ChatResponse succeeded.', [
                            'finish_reason' => $this->lastFinishReason,
                            'latency_ms' => $this->lastLatencyMs,
                            'cached' => $useCache,
                        ]);

                        return trim($text);
                    }
                    $sawSuccessfulEmpty = true;
                }

                $isCacheRelated = $useCache && in_array($response->status(), [400, 404], true);
            } catch (ConnectionException $e) {
                $this->lastFinishReason = 'timeout';
                $this->lastLatencyMs = (int) (microtime(true) * 1000) - $startedAt;
                Log::error('Gemini ChatResponse timeout: '.$e->getMessage());

                return null;
            } catch (\Exception $e) {
                $this->lastFinishReason = 'error';
                $this->lastLatencyMs = (int) (microtime(true) * 1000) - $startedAt;
                Log::error('Gemini ChatResponse Exception: '.$e->getMessage());
            }

            if (! $useCache || ! $isCacheRelated) {
                break;
            }

            // Retry with inline system instruction; invalidate stale local cache.
            try {
                Cache::forget($cacheKey);
            } catch (\Throwable $e) {
                Log::warning('Gemini chat cache invalidation failed: '.$e->getMessage());
            }
            $useCache = false;
        }

        if ($response && $response->status() === 429) {
            $this->lastFinishReason = 'rate_limit';
        } elseif ($sawSuccessfulEmpty) {
            $this->lastFinishReason = 'empty';
        } elseif ($this->lastFinishReason === null) {
            $this->lastFinishReason = 'error';
        }
        $this->lastLatencyMs ??= (int) (microtime(true) * 1000) - $startedAt;

        Log::error('Gemini ChatResponse Failed: ', ['response' => $response ? $response->body() : 'no response']);

        return null;
    }

    /**
     * Load the chatbot system prompt from the standardized location.
     */
    public function loadChatbotSystemPrompt(): string
    {
        return $this->loadSystemPrompt('chatbot-system-prompt.md');
    }

    /**
     * Build a deterministic itinerary context for Gemini narration.
     *
     * @return array{success: bool, context?: string, data?: array, message?: string}
     */
    public function buildItineraryContext(string $query, array $constraints, int $pax, int $nights, float $maxBudget, ?string $checkIn = null, ?string $checkOut = null): array
    {
        $destinationId = $constraints['destination_id'] ?? null;
        if (! $destinationId) {
            return ['success' => false, 'message' => 'Which destination would you like an itinerary for?'];
        }

        $destination = DestinationModel::find($destinationId);
        if (! $destination) {
            return ['success' => false, 'message' => 'I could not find that destination.'];
        }

        $checkInDate = $this->parseItineraryDate($checkIn);
        $checkOutDate = $this->parseItineraryDate($checkOut);

        $selection = $this->selectItineraryHotelAndRoom((int) $destinationId, $pax, $nights, $maxBudget, $checkInDate, $checkOutDate);
        if (! $selection) {
            return ['success' => false, 'message' => "I could not find any available rooms in {$destination->name} for {$pax} guests."];
        }

        /** @var HotelModel $pickedHotel */
        $pickedHotel = $selection['hotel'];
        /** @var RoomType $pickedRoom */
        $pickedRoom = $selection['room'];

        $budgetForActivities = $maxBudget - ($pickedRoom->calculateNightlyRate($pax) * $nights);
        $requestedActivityName = $constraints['activity_name'] ?? null;
        $activities = $this->selectItineraryActivities((int) $destinationId, $pax, $nights, $budgetForActivities, $requestedActivityName ? (string) $requestedActivityName : null);

        $roomRate = $pickedRoom->calculateNightlyRate($pax);
        $roomTotal = $roomRate * $nights;
        $activitiesTotal = (float) $activities->sum('_computed_cost');
        $grandTotal = round($roomTotal + $activitiesTotal, 2);

        $context = $this->formatItineraryContextText(
            $destination->name,
            $pax,
            $nights,
            $maxBudget,
            $pickedHotel,
            $pickedRoom,
            $roomRate,
            $roomTotal,
            $activities,
            $activitiesTotal,
            $grandTotal
        );

        $data = $this->formatItineraryData(
            $destination,
            $pickedHotel,
            $pickedRoom,
            $roomRate,
            $roomTotal,
            $activities,
            $nights,
            $pax,
            $grandTotal,
            $maxBudget,
            $selection['available'] ?? true,
            $selection['over_budget'] ?? false
        );

        return [
            'success' => true,
            'context' => $context,
            'data' => $data,
        ];
    }

    /**
     * Select suitable hotel and room for itinerary. Every candidate is
     * scored deterministically: live availability dominates (+100), then
     * room-total budget tiers (<=50% budget +40, <=budget +20). The winner
     * is the top score, then cheapest total, then lowest hotel id. When
     * nothing fits the budget, the cheapest candidate wins flagged
     * over_budget so the caller can say so deterministically.
     *
     * @return array{hotel: HotelModel, room: RoomType, available: bool, over_budget: bool}|null
     */
    private function selectItineraryHotelAndRoom(int $destinationId, int $pax, int $nights, float $maxBudget, ?Carbon $checkIn = null, ?Carbon $checkOut = null): ?array
    {
        $hotels = HotelModel::where('destination_id', $destinationId)
            ->where('is_shown', true)
            ->with(['rooms' => fn ($q) => $q->where('is_shown', true)->where('max_occupancy', '>=', $pax)])
            ->get()
            ->filter(fn ($h) => $h->rooms->isNotEmpty());

        $candidates = [];
        foreach ($hotels as $hotel) {
            foreach ($hotel->rooms as $room) {
                $nightlyRate = $room->calculateNightlyRate($pax);
                $roomTotal = $nightlyRate * $nights;
                $available = ($checkIn && $checkOut)
                    ? (bool) ($this->availability->check($room, $checkIn, $checkOut)['available'] ?? false)
                    : true;

                $score = $available ? 100 : 0;
                if ($roomTotal <= $maxBudget * 0.5) {
                    $score += 40;
                } elseif ($roomTotal <= $maxBudget) {
                    $score += 20;
                }

                $candidates[] = [
                    'hotel' => $hotel,
                    'room' => $room,
                    'room_total' => $roomTotal,
                    'available' => $available,
                    'over_budget' => $roomTotal > $maxBudget,
                    'score' => $score,
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn ($a, $b) => [$b['score'], $a['room_total'], $a['hotel']->id]
            <=> [$a['score'], $b['room_total'], $b['hotel']->id]);

        $winner = $candidates[0];

        return [
            'hotel' => $winner['hotel'],
            'room' => $winner['room'],
            'available' => $winner['available'],
            'over_budget' => $winner['over_budget'],
        ];
    }

    private function parseItineraryDate(?string $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Select suitable activities for itinerary. Deterministic: the
     * requested activity (if it belongs to this destination) is always
     * first, then one cheapest pick per remaining category (diversity),
     * then the cheapest leftovers — all within the 80% activity budget
     * slice, ties broken by duration then id.
     */
    private function selectItineraryActivities(int $destinationId, int $pax, int $nights, float $budgetForActivities, ?string $requestedActivityName = null): Collection
    {
        $activityCount = min(4, $nights + 1);

        $requested = null;
        if ($requestedActivityName) {
            $requested = ActivityModel::where('destination_id', $destinationId)
                ->where('is_shown', true)
                ->where('activity_name', 'ILIKE', $requestedActivityName)
                ->first();
            if ($requested) {
                $cost = $requested->isPerPersonRate()
                    ? round($requested->calculateRateForPax($pax) * $pax, 2)
                    : round($requested->calculateRateForPax($pax), 2);
                $requested->_computed_cost = $cost;
            }
        }

        $candidates = ActivityModel::where('destination_id', $destinationId)
            ->where('is_shown', true)
            ->when($requested, fn ($q) => $q->where('id', '!=', $requested->id))
            ->get()
            ->map(function ($activity) use ($pax) {
                $cost = $activity->isPerPersonRate()
                    ? round($activity->calculateRateForPax($pax) * $pax, 2)
                    : round($activity->calculateRateForPax($pax), 2);
                $activity->_computed_cost = $cost;

                return $activity;
            })
            ->filter(fn ($a) => $a->_computed_cost <= $budgetForActivities * 0.8)
            ->sortBy([['_computed_cost', 'asc'], ['duration', 'asc'], ['id', 'asc']])
            ->values();

        $picked = collect();
        if ($requested) {
            $picked->push($requested);
        }

        // Diversity pass: cheapest pick per category first.
        $seenCategories = $picked->map(fn ($a) => $a->category)->filter()->all();
        foreach ($candidates as $candidate) {
            if ($picked->count() >= $activityCount) {
                break;
            }
            if ($picked->contains(fn ($a) => $a->id === $candidate->id)) {
                continue;
            }
            if ($candidate->category && in_array($candidate->category, $seenCategories, true)) {
                continue;
            }
            $picked->push($candidate);
            if ($candidate->category) {
                $seenCategories[] = $candidate->category;
            }
        }

        // Fill pass: cheapest remaining regardless of category.
        foreach ($candidates as $candidate) {
            if ($picked->count() >= $activityCount) {
                break;
            }
            if ($picked->contains(fn ($a) => $a->id === $candidate->id)) {
                continue;
            }
            $picked->push($candidate);
        }

        return $picked->take($activityCount)->values();
    }

    private function formatItineraryContextText(
        string $destName,
        int $pax,
        int $nights,
        float $maxBudget,
        HotelModel $hotel,
        RoomType $room,
        float $roomRate,
        float $roomTotal,
        Collection $activities,
        float $activitiesTotal,
        float $grandTotal
    ): string {
        $context = "DESTINATION: {$destName}\n";
        $context .= "PAX: {$pax} guest(s) | NIGHTS: {$nights} | BUDGET: ₱".number_format($maxBudget, 2)."\n\n";

        $context .= "HOTEL & ROOM:\n";
        $context .= "- {$hotel->hotel_name}: {$room->room_name}\n";
        $context .= "- Bed: {$room->bed_configuration} | Occupancy: {$room->max_occupancy} pax\n";
        $context .= '- Nightly rate: ₱'.number_format($roomRate, 2).' | '.$nights.' nights = ₱'.number_format($roomTotal, 2)."\n\n";

        $context .= 'ACTIVITIES (₱'.number_format($activitiesTotal, 2)." total):\n";
        foreach ($activities as $i => $activity) {
            $day = min($nights + 1, intdiv($i, 2) + 1);
            $slot = $i % 2 === 0 ? 'main activity' : 'afternoon activity';
            $context .= "- Day {$day} ({$slot}): {$activity->activity_name} | {$activity->category} | {$activity->duration} | ₱".number_format($activity->_computed_cost, 2)." for {$pax} pax\n";
        }

        $context .= "\nTOTAL: ₱".number_format($grandTotal, 2);
        $context .= $grandTotal > $maxBudget
            ? ' (OVER BUDGET by ₱'.number_format($grandTotal - $maxBudget, 2).' — inform the user)'
            : ' (WITHIN BUDGET)';

        return $context;
    }

    private function formatItineraryData(
        DestinationModel $destination,
        HotelModel $hotel,
        RoomType $room,
        float $roomRate,
        float $roomTotal,
        Collection $activities,
        int $nights,
        int $pax,
        float $grandTotal,
        float $maxBudget,
        bool $available = true,
        bool $overBudget = false
    ): array {
        return [
            'destination' => ['id' => $destination->id, 'name' => $destination->name],
            'hotel' => ['id' => $hotel->id, 'name' => $hotel->hotel_name],
            'room' => [
                'id' => $room->id,
                'room_name' => $room->room_name,
                'nightly_rate' => $roomRate,
                'formatted_nightly_rate' => '₱'.number_format($roomRate, 2),
                'total' => $roomTotal,
                'formatted_total' => '₱'.number_format($roomTotal, 2),
            ],
            'activities' => $activities->map(fn ($a) => [
                'id' => $a->id,
                'activity_name' => $a->activity_name,
                'category' => $a->category,
                'duration' => $a->duration,
                'cost' => $a->_computed_cost,
                'formatted_cost' => '₱'.number_format($a->_computed_cost, 2),
            ])->values()->all(),
            'nights' => $nights,
            'pax' => $pax,
            'grand_total' => $grandTotal,
            'formatted_grand_total' => '₱'.number_format($grandTotal, 2),
            'budget' => $maxBudget,
            'within_budget' => $grandTotal <= $maxBudget,
            'available' => $available,
            'over_budget' => $overBudget,
        ];
    }
}
