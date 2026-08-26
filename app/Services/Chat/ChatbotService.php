<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Models\SupportInquiry;
use App\Models\User;
use App\Services\DistanceService;
use App\Services\GeminiService;
use App\Services\RoomAvailabilityService;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    public function __construct(
        protected GeminiService $gemini,
        protected IntentRouter $intentRouter,
        protected ConversationManager $conversation,
        protected RoomAvailabilityService $availability,
        protected WeatherService $weather,
        protected DistanceService $distance,
        protected FaqService $faq,
    ) {}

    public function handle(ChatSession $session, ?User $user, string $message, ?float $userLat = null, ?float $userLng = null): array
    {
        $abuse = $this->checkAbuse($user, $message);
        if ($abuse) {
            return $abuse;
        }

        $this->conversation->persist($session, 'user', $message);

        $inquiry = SupportInquiry::where('chat_session_id', $session->id)
            ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
            ->first();

        if ($inquiry) {
            if ($inquiry->status === SupportInquiry::STATUS_PENDING) {
                return [
                    'status' => 'pending_assignment',
                    'control' => 'pending',
                    'reply' => 'An administrator will be with you shortly. Your message has been added to the queue.',
                    'session_token' => $session->session_token,
                ];
            }

            if ($inquiry->status === SupportInquiry::STATUS_HUMAN_ACTIVE) {
                return [
                    'status' => 'human_support_active',
                    'control' => 'admin',
                    'session_token' => $session->session_token,
                ];
            }
        }

        $lastBot = $session->messages()->where('sender', 'bot')->latest()->first();
        if ($this->isFollowUpQuery($message, $lastBot)) {
            $reply = $this->handleFollowUp($message, $session);
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge(['status' => 'success', 'control' => 'ai', 'session_token' => $session->session_token, 'reply' => $text], $reply);
        }

        $faq = $this->faq->findBestMatch($message);
        if ($faq) {
            $reply = ['reply' => $faq->answer, 'faq' => ['id' => $faq->id, 'question' => $faq->question, 'answer' => $faq->answer]];
            $this->conversation->persist($session, 'bot', $reply['reply'], $reply);

            return array_merge(['status' => 'success', 'control' => 'ai', 'session_token' => $session->session_token], $reply);
        }

        $intent = $this->intentRouter->classify($message);
        $constraints = in_array($intent, [IntentRouter::GENERAL_TALK, IntentRouter::DESTINATIONS_OVERVIEW], true)
            ? []
            : $this->intentRouter->extractConstraints($message);

        $reply = match ($intent) {
            IntentRouter::ROOM_SEARCH => $this->handleRoomSearch($message, $constraints, $user, $session),
            IntentRouter::HOTEL_SEARCH => $this->handleHotelSearch($message, $constraints, $user, $session),
            IntentRouter::ACTIVITY_SEARCH => $this->handleActivitySearch($message, $constraints, $user, $session),
            IntentRouter::PACKAGE_SEARCH => $this->handlePackageSearch($message, $constraints, $user, $session),
            IntentRouter::ADDON_SEARCH => $this->handleAddOnSearch($message, $constraints, $user, $session),
            IntentRouter::ITINERARY_QUERY => $this->handleItineraryQuery($message, $constraints, $user, $session),
            IntentRouter::AVAILABILITY_QUERY => $this->handleAvailabilityQuery($message, $constraints, $user, $session),
            IntentRouter::MAP_QUERY => $this->handleMapQuery($message, $constraints, $session, $userLat, $userLng),
            IntentRouter::WEATHER_QUERY => $this->handleWeatherQuery($message, $constraints, $session),
            IntentRouter::DESTINATIONS_OVERVIEW => $this->handleDestinationsOverview($session),
            default => $this->handleGeneralChat($message, $session),
        };

        $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
        $this->conversation->persist($session, 'bot', $text, $reply);

        return array_merge(['status' => 'success', 'control' => 'ai', 'session_token' => $session->session_token, 'reply' => $text], $reply);
    }

    // ────────────────────────────────────────────────
    //  Follow-up (conversational memory)
    // ────────────────────────────────────────────────

    /**
     * Detect whether a message continues the previous turn (ChatGPT-style
     * follow-up) rather than starting a brand-new search.
     */
    protected function isFollowUpQuery(string $query, ?ChatMessage $lastBot): bool
    {
        if (! $lastBot) {
            return false;
        }

        $lower = mb_strtolower(trim($query));
        if ($lower === '') {
            return false;
        }

        if (count(preg_split('/\s+/', $lower)) > 10) {
            return false;
        }

        if ($this->isRefinementQuery($lower, $lastBot)) {
            return true;
        }

        if ($this->startsNewSearch($lower)) {
            return false;
        }

        $referential = '/\b(it|its|they|them|their|his|her|those|these|this one|that one|the one|which one|the first|the second|the other)\b/';
        $continuation = '/^(how much|how many|how about|what about|what is|what are|what\'s|and what|what else|and how|tell me more|more info|more details|more options|why|is it|are they|does it|do they|can you|give me the|whose|price of|prices of|cost of)/';

        return (bool) (preg_match($referential, $lower) || preg_match($continuation, $lower));
    }

    /**
     * A refinement narrows the previous recommendation ("no, in Boracay only",
     * "focusing on budget", "actually just Palawan") without starting a fresh
     * search. It must carry a refinement prefix, tie back to the previous
     * context (a destination, a previously recommended item, or a price word),
     * and not introduce a fresh-searchable entity.
     */
    protected function isRefinementQuery(string $lower, ChatMessage $lastBot): bool
    {
        if (! preg_match('/^(no|nope|not that|actually|just|only|yes|yep|ok|okay|alright|ah|focus|focusing on|focus on)\b/', $lower)) {
            return false;
        }

        if (preg_match('/\b(hotel|hotels|room|rooms|resort|resorts|activity|activities|tour|tours|package|packages|itinerary|availability|weather|where is|how far)\b.*\b(in|near|at|for|with)\b/', $lower)) {
            return false;
        }

        foreach (DestinationModel::pluck('name') as $name) {
            $name = mb_strtolower((string) $name);
            if ($name !== '' && str_contains($lower, $name)) {
                return true;
            }
        }

        $data = $lastBot->context_data ?: [];
        $names = [];
        foreach (($data['retrieved_rooms'] ?? []) as $r) {
            $names[] = $r['room_name'] ?? '';
            $names[] = $r['hotel_name'] ?? '';
        }
        foreach (($data['retrieved_hotels'] ?? []) as $h) {
            $names[] = $h['hotel_name'] ?? '';
        }
        foreach (($data['retrieved_activities'] ?? []) as $a) {
            $names[] = $a['activity_name'] ?? '';
        }
        foreach (($data['retrieved_packages'] ?? []) as $p) {
            $names[] = $p['name'] ?? '';
        }
        foreach ($names as $name) {
            $name = mb_strtolower(trim((string) $name));
            if ($name !== '' && str_contains($lower, $name)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(price|prices|cheap|cheaper|cheapest|expensive|budget|best|better|top|under|less than)\b/', $lower);
    }

    /**
     * A follow-up must not steal a query that introduces a fresh searchable
     * destination or entity.
     */
    protected function startsNewSearch(string $lower): bool
    {
        foreach (DestinationModel::pluck('name') as $name) {
            $name = mb_strtolower((string) $name);
            if ($name !== '' && str_contains($lower, $name)) {
                return true;
            }
        }

        // If query names a specific room or add-on, treat as fresh search regardless of follow-up heuristic
        if ($this->intentRouter->extractRoomName($lower) || $this->intentRouter->extractAddOnName($lower)) {
            return true;
        }

        return (bool) preg_match('/\b(hotel|hotels|room|rooms|resort|resorts|activity|activities|tour|tours|package|packages|itinerary|availability|weather|where is|how far)\b.*\b(in|near|at|for)\b/', $lower)
            || (bool) preg_match('/\b(book|reserve|booking)\b/', $lower);
    }

    protected function handleFollowUp(string $query, ChatSession $session): array
    {
        $lastBot = $session->messages()->where('sender', 'bot')->latest()->first();
        $context = $lastBot ? $this->followUpContext($lastBot) : '';

        $header = "TASK: Answer the user's follow-up question using ONLY the previous conversation and the last recommendation details below.";
        $rules = [
            'The user is continuing a previous conversation, not starting a new search.',
            'Base your answer ONLY on the PREVIOUS RECOMMENDATIONS and the conversation history. Do not run a new database search.',
            'Never invent hotels, rooms, activities, prices, availability, or other details that are not listed below or in the conversation history.',
            'If the requested information is not available in the previous recommendations or history, say so and offer to search again.',
            'Use **bold** for short labels and - for bullet lists. Do not output HTML.',
            'You may reply in English or Taglish depending on the user\'s language.',
            'Keep responses friendly, concise, and helpful.',
        ];

        $prompt = "{$header}\n\nRULES:\n- ".implode("\n- ", $rules)
            ."\n\n=== PREVIOUS RECOMMENDATIONS ===\n{$context}\n=== END PREVIOUS RECOMMENDATIONS ===\n\nFOLLOW-UP QUESTION: {$query}";

        $reply = $this->geminiChatResponse($prompt, $session);

        // Carry the previous turn's recommendation cards so the widget can
        // keep them clickable (image, preview, Add to Trip Basket) while the
        // user refines or asks follow-ups about the same items.
        $carry = [];
        if ($lastBot) {
            $data = $lastBot->context_data ?: [];
            $carry = array_intersect_key($data, array_flip([
                'retrieved_rooms',
                'retrieved_hotels',
                'retrieved_activities',
                'retrieved_packages',
                'itinerary',
                'availability',
            ]));

            $hotelName = $this->intentRouter->extractHotelName($query);
            if ($hotelName) {
                $hotelId = HotelModel::where('hotel_name', 'ILIKE', $hotelName)->value('id');
                if ($hotelId) {
                    $filtered = [];
                    foreach (['retrieved_rooms' => 'hotel_id', 'retrieved_hotels' => 'id'] as $key => $idKey) {
                        if (! empty($carry[$key])) {
                            $filtered[$key] = array_values(array_filter(
                                $carry[$key],
                                fn ($item) => (int) ($item[$idKey] ?? 0) === (int) $hotelId
                            ));
                        }
                    }
                    // If filtering by new hotel empties all cards but user clearly switched hotel (e.g., "how about for happiness?"),
                    // fall back to fresh search instead of empty follow-up.
                    $hasFiltered = ! empty($filtered['retrieved_rooms']) || ! empty($filtered['retrieved_hotels']);
                    $hadCards = ! empty($carry['retrieved_rooms']) || ! empty($carry['retrieved_hotels']);
                    if ($hadCards && ! $hasFiltered) {
                        $constraints = $this->intentRouter->extractConstraints($query);
                        $fresh = $this->handleRoomSearch($query, $constraints, $session->user ? User::find($session->user_id) : null, $session);
                        if (! empty($fresh['retrieved_rooms'])) {
                            return $fresh;
                        }
                    }
                    foreach ($filtered as $k => $v) {
                        $carry[$k] = $v;
                    }
                }
            }
        }

        return array_merge(['reply' => $reply], $carry);
    }

    protected function followUpContext(ChatMessage $lastBot): string
    {
        $blocks = [];
        $data = $lastBot->context_data ?: [];

        if (! empty($data['retrieved_rooms'])) {
            foreach ($data['retrieved_rooms'] as $r) {
                $price = isset($r['base_price']) ? '₱'.number_format((float) $r['base_price'], 2) : 'n/a';
                $baseOcc = $r['base_occupancy'] ?? 2;
                $maxOcc = $r['max_occupancy'] ?? $r['occupancy'] ?? 2;
                $fee = $r['extra_person_fee'] ?? 0;
                $occupancyText = $fee > 0 && $maxOcc > $baseOcc
                    ? "Base {$baseOcc}/Max {$maxOcc}, Extra ₱".number_format((float) $fee, 2).'/head/night'
                    : "No extra guests allowed — maximum {$maxOcc} guests";
                $blocks[] = "- Room: {$r['room_name']} at {$r['hotel_name']} — {$price}/night — {$occupancyText} (Total physical rooms not live — check dates for real availability)";
            }
        }

        if (! empty($data['retrieved_hotels'])) {
            foreach ($data['retrieved_hotels'] as $h) {
                $price = isset($h['price_from']) ? '₱'.number_format((float) $h['price_from'], 2) : 'n/a';
                $blocks[] = "- Hotel: {$h['hotel_name']} ({$h['destination']}) — from {$price}/night";

                if (! empty($h['id'])) {
                    $rooms = RoomType::where('hotel_id', $h['id'])
                        ->where('is_shown', true)
                        ->orderBy('base_price')
                        ->get(['room_name', 'base_price', 'base_occupancy', 'max_occupancy', 'extra_person_fee']);

                    foreach ($rooms as $room) {
                        $baseOcc = (int) ($room->base_occupancy ?: 2);
                        $maxOcc = (int) ($room->max_occupancy ?: 2);
                        $fee = (float) ($room->extra_person_fee ?: 0);
                        $extra = $fee > 0 && $maxOcc > $baseOcc
                            ? ', Extra ₱'.number_format($fee, 2).'/head'
                            : ', No extra guests';
                        $blocks[] = "  - {$room->room_name}: ₱".number_format((float) $room->base_price, 2)."/night (Base {$baseOcc}/Max {$maxOcc}{$extra})";
                    }
                }
            }
        }

        if (! empty($data['retrieved_activities'])) {
            foreach ($data['retrieved_activities'] as $a) {
                $rate = isset($a['rate']) ? '₱'.number_format((float) $a['rate'], 2) : 'n/a';
                $blocks[] = "- Activity: {$a['activity_name']} — {$rate}";
            }
        }

        if (! empty($data['retrieved_packages'])) {
            foreach ($data['retrieved_packages'] as $p) {
                $blocks[] = "- Package: {$p['name']} — ₱".number_format((float) $p['price'], 2)." ({$p['days']}D/{$p['nights']}N)";
            }
        }

        if (empty($blocks)) {
            $blocks[] = '- (The previous reply did not include specific recommendations.)';
        }

        $text = $lastBot->message ? trim($lastBot->message) : '';
        $prefix = $text !== '' ? "Previous reply: \"{$text}\"\n\n" : '';

        return $prefix.implode("\n", $blocks);
    }

    protected function checkAbuse(?User $user, string $message): ?array
    {
        if ($user) {
            return $this->gemini->detectAbuseAndGuard($user, $message);
        }

        $lower = mb_strtolower($message);
        $bannedPatterns = [
            // --- 1. EXISTING PATTERNS ---
            'nsfw',
            'porn',
            'naked',
            'nude',
            'sexual',
            'sex',
            'strip',
            'erotic',
            'suicide',
            'bomb',
            'terrorist',
            'hack bank',
            'credit card fraud',
            'illegal drugs',
            'kill',
            'murder',
            'ignore previous instructions',
            'ignore all rules',
            'system prompt',
            'you are now DAN',
            'bypass restriction',

            // --- 2. HATE SPEECH & PROFANITY (Tagalog & English) ---
            'putangina',
            'gago',
            'bobo',
            'tanga',
            'ulol',
            'inamo',
            'hayop ka',
            'tarantado',
            'fuck',
            'shit',
            'bitch',
            'asshole',
            'cunt',
            'retard',
            'bastard',

            // --- 3. NSFW & EXPLICIT CONTENT (Tagalog & Extra English) ---
            'bold',
            'hubad',
            'bastos',
            'kantot',
            'iyot',
            'pepe',
            'titi',
            'pokpok',
            'escort',
            'prostitute',
            'onlyfans',
            'sugar daddy',
            'sugar baby',

            // --- 4. HARM, VIOLENCE & ILLEGAL ACTS (Tagalog & Extra English) ---
            'magpakamatay',
            'patayin',
            'saksak',
            'baril',
            'droga',
            'shabu',
            'adik',
            'weapon',
            'shoot',
            'self-harm',
            'cut myself',
            'rape',

            // --- 5. ADVANCED PROMPT INJECTION & AI MANIPULATION ---
            'developer mode',
            'forget everything',
            'act as a developer',
            'print prompt',
            'output your instructions',
            'jailbreak',
            'do anything now',
            'system message',
            'admin mode',
            'override commands',

            // --- 6. TRAVEL-SPECIFIC ABUSE & SCAMS ---
            'human trafficking',
            'smuggle',
            'fake passport',
            'fake visa',
            'bypass immigration',
            'tnt',
            'tago ng tago',
            'peke na ticket',
            'scam',
            'money laundering',
        ];
        foreach ($bannedPatterns as $kw) {
            if (str_contains($lower, $kw)) {
                Log::info('Chatbot guest abuse blocked', ['message' => $message, 'keyword' => $kw]);

                return [
                    'blocked' => true,
                    'response' => 'Your message contains content that violates our community guidelines. Please log in to continue chatting.',
                ];
            }
        }

        return null;
    }

    // ────────────────────────────────────────────────
    //  Intent Handlers
    // ────────────────────────────────────────────────

    protected function handleRoomSearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $scored = $this->gemini->searchRoomsHybrid($query, $constraints, 5);

        $priceIntent = $this->detectPriceIntent($query);
        if ($priceIntent) {
            $scored = $this->sortRoomsByPrice($scored, $priceIntent, $constraints['pax'] ?? 2);
        }

        foreach ($scored as &$entry) {
            $entry['check_in_date'] = $constraints['check_in_date'] ?? null;
            $entry['check_out_date'] = $constraints['check_out_date'] ?? null;
            $entry['pax'] = $constraints['pax'] ?? null;
        }
        unset($entry);
        $context = $this->gemini->getRoomContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('rooms')];
        }

        // Pax-aware extra-person hint for queries like "for 3 pax" or "additional per head"
        $pax = $constraints['pax'] ?? null;
        if ($pax) {
            $hint = '';
            foreach ($scored as $entry) {
                /** @var RoomType $room */
                $room = $entry['item'];
                $baseOcc = (int) ($room->base_occupancy ?: 2);
                $maxOcc = (int) ($room->max_occupancy ?: 2);
                $fee = (float) ($room->extra_person_fee ?: 0);
                if ($pax > $baseOcc && $maxOcc > $baseOcc && $fee > 0) {
                    $extraCount = min($pax, $maxOcc) - $baseOcc;
                    $totalNightly = $room->calculateNightlyRate($pax);
                    $hint .= "• {$room->room_name} at {$room->hotel->hotel_name}: base ₱".number_format($room->base_price, 2)." for {$baseOcc} pax + ₱".number_format($fee, 2)."/head × {$extraCount} extra = ₱".number_format($totalNightly, 2)."/night (max {$maxOcc} pax)\n";
                } elseif ($pax > $maxOcc) {
                    $hint .= "• {$room->room_name} at {$room->hotel->hotel_name}: No extra guests allowed — maximum {$maxOcc} guests (requested {$pax} pax)\n";
                }
            }
            if ($hint !== '') {
                $context .= "\n\n--- PAX-AWARE PRICING (trust these totals) ---\n".$hint."---\n";
            }
        }

        $context = $this->gemini->extractPricingContext($context, $query);
        $prompt = $this->buildPrompt('room-search', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        return [
            'reply' => $reply,
            'retrieved_rooms' => $this->formatRoomResults($scored),
        ];
    }

    protected function handleAddOnSearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $scored = $this->gemini->searchAddOns($query, 5);
        $context = $this->gemini->getAddOnContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('add-ons')];
        }

        $context = $this->gemini->extractPricingContext($context, $query);
        $prompt = $this->buildPrompt('addon-search', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        return [
            'reply' => $reply,
            'retrieved_addons' => array_map(fn ($e) => [
                'id' => $e['item']->id,
                'name' => $e['item']->name,
                'type' => $e['item']->type,
                'destination' => $e['item']->destination?->name ?? null,
                'similarity_score' => round($e['score'], 4),
            ], $scored),
        ];
    }

    protected function handleHotelSearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $limit = (int) ($constraints['limit'] ?? 0);
        if ($limit < 3 || $limit > 10) {
            $limit = 5;
            if (preg_match('/\btop\s*(\d+)\b/i', $query, $m)) {
                $limit = max(3, min(10, (int) $m[1]));
            }
        }
        $scored = $this->gemini->searchHotels($query, $limit, $constraints['hotel_id'] ?? null, $constraints['destination_id'] ?? null);

        $priceIntent = $this->detectPriceIntent($query);
        if ($priceIntent) {
            $scored = $this->sortHotelsByPrice($scored, $priceIntent);
        }

        $context = $this->gemini->getHotelContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('hotels')];
        }

        $prompt = $this->buildPrompt('hotel-search', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        return [
            'reply' => $reply,
            'retrieved_hotels' => $this->formatHotelResults($scored),
        ];
    }

    /**
     * Detect whether a hotel query asks for price-based ranking.
     *
     * @return string|null 'expensive' | 'cheap' | null
     */
    protected function detectPriceIntent(string $query): ?string
    {
        $lower = mb_strtolower($query);

        if (preg_match('/\b(most\s+expensive|expensive|pricey|costliest|premium|luxurious|luxury|high-end|high\s*end|mahal)\b/', $lower)) {
            return 'expensive';
        }

        if (preg_match('/\b(cheapest|cheap|affordable|budget|lowest|least\s+expensive|mura)\b/', $lower)) {
            return 'cheap';
        }

        return null;
    }

    /**
     * Sort scored hotels by their cheapest shown room rate. Ties fall back to
     * the original similarity score.
     */
    protected function sortHotelsByPrice(array $scored, string $direction): array
    {
        $priceMap = [];
        foreach ($scored as $entry) {
            $priceMap[$entry['item']->id] = $this->hotelPriceFrom($entry['item']) ?? PHP_FLOAT_MAX;
        }

        usort($scored, function ($a, $b) use ($priceMap, $direction) {
            $pa = $priceMap[$a['item']->id];
            $pb = $priceMap[$b['item']->id];

            if ($pa === $pb) {
                return $b['score'] <=> $a['score'];
            }

            return $direction === 'expensive' ? $pb <=> $pa : $pa <=> $pb;
        });

        return array_values($scored);
    }

    /**
     * Sort scored rooms by total nightly rate for the requested pax so
     * cheapest/most expensive rooms surface first for budget/luxury queries.
     * Ties fall back to similarity.
     */
    protected function sortRoomsByPrice(array $scored, string $direction, int $pax = 2): array
    {
        usort($scored, function ($a, $b) use ($direction, $pax) {
            $pa = (float) $a['item']->calculateNightlyRate($pax);
            $pb = (float) $b['item']->calculateNightlyRate($pax);

            if ($pa === $pb) {
                return $b['score'] <=> $a['score'];
            }

            return $direction === 'expensive' ? $pb <=> $pa : $pa <=> $pb;
        });

        return array_values($scored);
    }

    /**
     * Cheapest shown room rate for a hotel (₱/night), or null when unknown.
     */
    protected function hotelPriceFrom(HotelModel $hotel): ?float
    {
        $min = $hotel->rooms()->where('is_shown', true)->min('base_price');

        return $min !== null ? (float) $min : null;
    }

    protected function handleActivitySearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $scored = $this->gemini->searchActivities($query, 3);
        $context = $this->gemini->getActivityContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('activities')];
        }

        $prompt = $this->buildPrompt('activity-search', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        return [
            'reply' => $reply,
            'retrieved_activities' => $this->formatActivityResults($scored),
        ];
    }

    protected function handlePackageSearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $destinationId = $constraints['destination_id'] ?? null;
        $scored = $this->gemini->searchPackages($query, 5, $destinationId);

        $today = Carbon::now()->startOfDay();
        $scored = array_filter($scored, function ($entry) use ($today) {
            /** @var Package $pkg */
            $pkg = $entry['item'];
            $validFrom = $pkg->valid_from ? Carbon::parse($pkg->valid_from)->startOfDay() : null;
            $validTo = $pkg->valid_to ? Carbon::parse($pkg->valid_to)->startOfDay() : null;
            if ($validFrom && $today->lt($validFrom)) {
                return false;
            }
            if ($validTo && $today->gt($validTo)) {
                return false;
            }

            return true;
        });
        $scored = array_values($scored);

        $context = $this->gemini->getPackageContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('packages')];
        }

        $context = $this->gemini->extractPricingContext($context, $query);
        $prompt = $this->buildPrompt('package-search', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        return [
            'reply' => $reply,
            'retrieved_packages' => $this->formatPackageResults($scored),
        ];
    }

    protected function handleItineraryQuery(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $destinationId = $constraints['destination_id'] ?? null;
        $pax = $constraints['pax'] ?? 2;
        $nights = $constraints['nights'] ?? 2;
        $days = $nights + 1;
        $maxBudget = $constraints['max_price'] ?? 20000;
        $checkIn = $constraints['check_in_date']
            ? Carbon::parse($constraints['check_in_date'])
            : Carbon::now()->addDays(7);

        $itinerary = $this->gemini->buildItineraryContext($query, $constraints, $pax, $nights, $maxBudget);

        if (! $itinerary['success']) {
            return ['reply' => $itinerary['message'] ?? $this->noResultsReply('itinerary items')];
        }

        $context = $itinerary['context'];
        $prompt = $this->buildPrompt('itinerary', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        return [
            'reply' => $reply,
            'itinerary' => $itinerary['data'],
        ];
    }

    protected function handleAvailabilityQuery(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        // Merge previous hotel/destination when follow-up has no explicit hotel (e.g., "yes check availabilith" after Lazy Dog)
        if (empty($constraints['hotel_id']) && empty($constraints['room_id'])) {
            $lastBot = $session->messages()->where('sender', 'bot')->latest()->first();
            $data = $lastBot?->context_data ?: [];
            $prevHotelId = $data['retrieved_hotels'][0]['id'] ?? $data['retrieved_rooms'][0]['hotel_id'] ?? null;
            if ($prevHotelId) {
                $constraints['hotel_id'] = (int) $prevHotelId;
                $hotel = HotelModel::find($prevHotelId);
                if ($hotel) {
                    $constraints['hotel_name'] = $hotel->hotel_name;
                    if (empty($constraints['destination_id'])) {
                        $constraints['destination_id'] = $hotel->destination_id;
                        $constraints['destination_name'] = $hotel->destination?->name ?? $constraints['destination_name'] ?? null;
                    }
                }
            } elseif (empty($constraints['destination_id'])) {
                $prevDest = $data['retrieved_hotels'][0]['destination'] ?? $data['retrieved_rooms'][0]['destination'] ?? null;
                if ($prevDest) {
                    $destId = DestinationModel::where('name', 'ILIKE', $prevDest)->value('id');
                    if ($destId) {
                        $constraints['destination_id'] = $destId;
                        $constraints['destination_name'] = $prevDest;
                    }
                }
            }
        }

        $pax = $constraints['pax'] ?? 2;
        $checkIn = $constraints['check_in_date']
            ? Carbon::parse($constraints['check_in_date'])->startOfDay()
            : Carbon::now()->addDays(7)->startOfDay();
        $checkOut = $constraints['check_out_date']
            ? Carbon::parse($constraints['check_out_date'])->startOfDay()
            : $checkIn->copy()->addDays(2);
        $nights = max(1, $checkIn->diffInDays($checkOut));

        $rooms = $this->gemini->searchRoomsHybrid($query, $constraints, 8);

        if (empty($rooms)) {
            $destName = $constraints['destination_name'] ?? 'your request';

            return [
                'reply' => "I could not find any rooms matching \"{$destName}\" for {$nights} night(s) from {$checkIn->format('M d')} to {$checkOut->format('M d')}. Try a different destination or date range.",
            ];
        }

        $exactAvailable = [];
        foreach ($rooms as $entry) {
            /** @var RoomType $room */
            $room = $entry['item'];
            $avail = $this->availability->check($room, $checkIn, $checkOut);
            $unitRate = $room->calculateNightlyRate($pax);
            $total = round($unitRate * $nights, 2);
            $entry['availability'] = $avail;
            $entry['total_stay'] = $total;
            $entry['formatted_total'] = '₱'.number_format($total, 2);
            if ($avail['available']) {
                $exactAvailable[] = $entry;
            }
        }

        if (empty($exactAvailable)) {
            $destName2 = $constraints['destination_name'] ?? 'your request';

            return [
                'reply' => "All rooms matching \"{$destName2}\" are fully booked from {$checkIn->format('M d')} to {$checkOut->format('M d')}. Would you like me to check different dates?",
            ];
        }

        // Two-phase: if exact room was requested but <3 available, supplement with close alternatives (same hotel/destination)
        $available = $exactAvailable;
        $hasExactRoom = ! empty($constraints['room_name']) || ! empty($constraints['room_id']);
        if ($hasExactRoom && count($available) < 3) {
            $altConstraints = $constraints;
            unset($altConstraints['room_name'], $altConstraints['room_id']);
            // Keep hotel_id/destination_id/pax, drop room-specific filter
            $altRooms = $this->gemini->searchRoomsHybrid($query, $altConstraints, 8);
            $exactIds = array_map(fn ($e) => $e['item']->id, $available);
            $altAvailable = [];
            foreach ($altRooms as $entry) {
                /** @var RoomType $room */
                $room = $entry['item'];
                if (in_array($room->id, $exactIds, true)) {
                    continue;
                }
                $avail = $this->availability->check($room, $checkIn, $checkOut);
                $unitRate = $room->calculateNightlyRate($pax);
                $total = round($unitRate * $nights, 2);
                $entry['availability'] = $avail;
                $entry['total_stay'] = $total;
                $entry['formatted_total'] = '₱'.number_format($total, 2);
                if ($avail['available']) {
                    $altAvailable[] = $entry;
                }
                if (count($available) + count($altAvailable) >= 5) {
                    break;
                }
            }
            if (! empty($altAvailable)) {
                usort($altAvailable, fn ($a, $b) => $a['total_stay'] <=> $b['total_stay']);
                $available = array_merge($available, array_slice($altAvailable, 0, 5 - count($available)));
            }
            // Second fallback: destination-wide alternatives (same destination, other hotels) when hotel has only one room type
            if (count($available) < 3) {
                $destId = $constraints['destination_id'] ?? null;
                if (! $destId && ! empty($constraints['hotel_id'])) {
                    $destId = HotelModel::where('id', $constraints['hotel_id'])->value('destination_id');
                }
                if ($destId) {
                    $destAltConstraints = [
                        'destination_id' => $destId,
                        'pax' => $constraints['pax'] ?? $pax,
                    ];
                    // Keep max_price if present, drop hotel/room specifics
                    if (! empty($constraints['max_price'])) {
                        $destAltConstraints['max_price'] = $constraints['max_price'];
                    }
                    $destAltRooms = $this->gemini->searchRoomsHybrid($query, $destAltConstraints, 8);
                    $existingIds = array_map(fn ($e) => $e['item']->id, $available);
                    $destAltAvailable = [];
                    foreach ($destAltRooms as $entry) {
                        /** @var RoomType $room */
                        $room = $entry['item'];
                        if (in_array($room->id, $existingIds, true)) {
                            continue;
                        }
                        $avail = $this->availability->check($room, $checkIn, $checkOut);
                        $unitRate = $room->calculateNightlyRate($pax);
                        $total = round($unitRate * $nights, 2);
                        $entry['availability'] = $avail;
                        $entry['total_stay'] = $total;
                        $entry['formatted_total'] = '₱'.number_format($total, 2);
                        if ($avail['available']) {
                            $destAltAvailable[] = $entry;
                        }
                        if (count($available) + count($destAltAvailable) >= 5) {
                            break;
                        }
                    }
                    if (! empty($destAltAvailable)) {
                        usort($destAltAvailable, fn ($a, $b) => $a['total_stay'] <=> $b['total_stay']);
                        $available = array_merge($available, array_slice($destAltAvailable, 0, 5 - count($available)));
                    }
                }
            }
        }

        if (! $hasExactRoom) {
            usort($available, fn ($a, $b) => $a['total_stay'] <=> $b['total_stay']);
        }

        $context = $this->gemini->extractPricingContext(
            $this->buildAvailabilityContext($available, $pax, $nights),
            $query
        );

        $prompt = $this->buildPrompt('availability', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        // Formatted rooms for widget cards (so alternatives also render as cards with Best Match badge)
        $formattedRooms = array_map(function ($e) use ($checkIn, $checkOut, $pax) {
            /** @var RoomType $room */
            $room = $e['item'];

            return [
                'id' => $room->id,
                'room_name' => $room->room_name,
                'hotel_name' => $room->hotel?->hotel_name ?? 'Unknown Hotel',
                'hotel_id' => $room->hotel_id ?? $room->hotel?->id ?? null,
                'destination' => $room->hotel?->destination?->name ?? null,
                'base_price' => (float) $room->base_price,
                'occupancy' => $room->occupancy,
                'base_occupancy' => (int) ($room->base_occupancy ?: 2),
                'max_occupancy' => (int) ($room->max_occupancy ?: ($room->occupancy ?: 2)),
                'extra_person_fee' => (float) ($room->extra_person_fee ?: 0),
                'image' => $this->firstImage($room->images),
                'similarity_score' => round((float) ($e['score'] ?? 0), 4),
                'check_in_date' => $checkIn->format('Y-m-d'),
                'check_out_date' => $checkOut->format('Y-m-d'),
                'pax' => $pax,
                'remaining' => $e['availability']['remaining'] ?? null,
                'total_rooms' => $e['availability']['total_rooms'] ?? null,
            ];
        }, array_slice($available, 0, 5));

        return [
            'reply' => $reply,
            'availability' => array_values(array_slice($available, 0, 5)),
            'retrieved_rooms' => $formattedRooms,
        ];
    }

    protected function handleMapQuery(string $query, array $constraints, ChatSession $session, ?float $userLat = null, ?float $userLng = null): array
    {
        $places = $constraints['place_names'] ?? [];

        if (count($places) >= 2) {
            [['name' => $a], ['name' => $b]] = [$places[0], $places[1]];
            $da = DestinationModel::where('name', 'ILIKE', $a)->first();
            $db = DestinationModel::where('name', 'ILIKE', $b)->first();

            if ($da && $db && $da->latitude && $da->longitude && $db->latitude && $db->longitude) {
                $km = $this->distance->haversine(
                    (float) $da->latitude,
                    (float) $da->longitude,
                    (float) $db->latitude,
                    (float) $db->longitude
                );
                $label = $this->distance->format($km);

                return [
                    'reply' => "{$da->name} is approximately {$label} from {$db->name}.",
                    'map' => [
                        'from' => ['name' => $da->name, 'lat' => $da->latitude, 'lng' => $da->longitude],
                        'to' => ['name' => $db->name, 'lat' => $db->latitude, 'lng' => $db->longitude],
                        'distance_km' => round($km, 2),
                        'distance_label' => $label,
                    ],
                ];
            }
        }

        if (! empty($places)) {
            ['name' => $placeName] = $places[0];
            $dest = DestinationModel::where('name', 'ILIKE', $placeName)->first();
            if ($dest && $dest->latitude && $dest->longitude) {
                $isUserDistance = preg_match('/\b(how far am i|from me|from my location|from here|am i from|distance from me)\b/i', $query)
                    || (preg_match('/\bhow far\b/i', $query) && preg_match('/\b(i|me|my)\b/i', $query) && count($places) === 1);

                if ($isUserDistance) {
                    if ($userLat !== null && $userLng !== null) {
                        $km = $this->distance->haversine($userLat, $userLng, (float) $dest->latitude, (float) $dest->longitude);
                        $label = $this->distance->format($km);

                        return [
                            'reply' => "You are approximately {$label} from {$dest->name}.",
                            'map' => [
                                'from' => ['lat' => $userLat, 'lng' => $userLng, 'label' => 'You'],
                                'to' => ['name' => $dest->name, 'lat' => $dest->latitude, 'lng' => $dest->longitude],
                                'distance_km' => round($km, 2),
                                'distance_label' => $label,
                            ],
                        ];
                    }

                    return [
                        'reply' => "To calculate how far you are from {$dest->name}, I'll need your current location.",
                        'location_request' => true,
                        'location_target' => $dest->name,
                        'map' => [
                            'target' => ['name' => $dest->name, 'lat' => $dest->latitude, 'lng' => $dest->longitude],
                        ],
                    ];
                }

                return [
                    'reply' => "{$dest->name} is located at latitude {$dest->latitude}, longitude {$dest->longitude}.",
                    'map' => [
                        'name' => $dest->name,
                        'lat' => $dest->latitude,
                        'lng' => $dest->longitude,
                    ],
                ];
            }
        }

        $examples = $this->exampleDestinations(2);

        return [
            'reply' => 'I could not find the location you mentioned. Try naming a specific destination like "'.$examples.'".',
        ];
    }

    protected function handleWeatherQuery(string $query, array $constraints, ChatSession $session): array
    {
        $destinationName = $constraints['destination_name'] ?? null;

        if (! $destinationName) {
            $example = $this->exampleDestinations(1);

            return ['reply' => 'Which destination would you like the weather for? For example, "What\'s the weather in '.$example.' this weekend?"'];
        }

        $dest = DestinationModel::where('name', 'ILIKE', $destinationName)->first();

        if (! $dest) {
            return ['reply' => "I could not find \"{$destinationName}\" in our destinations. Could you check the spelling?"];
        }

        $forecast = $this->weather->forecastForDestination($dest);

        if (! $forecast) {
            return ['reply' => "I'm sorry, weather data for {$dest->name} is currently unavailable. Please try again later."];
        }

        $current = $forecast['list'][0] ?? [];
        $weatherMain = $current['weather'][0] ?? [];
        $main = $current['main'] ?? [];
        $temp = round($main['temp'] ?? 0);
        $desc = $weatherMain['description'] ?? 'unknown';
        $advice = $this->weather->advice($this->weather->normalize($forecast));

        $adviceText = ! empty($advice) ? ' Travel tip: '.implode(' ', $advice) : '';
        $outlook = $this->buildFiveDayOutlook($forecast);

        $reply = "The current weather in {$dest->name} is {$desc} at {$temp}°C.{$adviceText}";
        if ($outlook !== '') {
            $reply .= "\n\n".$outlook;
        }

        return [
            'reply' => $reply,
            'weather' => [
                'destination' => $dest->name,
                'temp' => $temp,
                'description' => $desc,
                'feels_like' => round($main['feels_like'] ?? $temp),
                'humidity' => $main['humidity'] ?? null,
                'advice' => $advice,
            ],
        ];
    }

    protected function buildFiveDayOutlook(array $forecast): string
    {
        $list = $forecast['list'] ?? [];
        if (empty($list) || ! is_array($list)) {
            return '';
        }

        $byDate = [];
        foreach ($list as $entry) {
            $dtTxt = $entry['dt_txt'] ?? null;
            if (! $dtTxt) {
                continue;
            }
            $date = substr($dtTxt, 0, 10);
            $byDate[$date][] = $entry;
        }

        if (empty($byDate)) {
            return '';
        }

        ksort($byDate);
        $lines = [];
        $taken = 0;

        foreach ($byDate as $date => $entries) {
            if ($taken >= 5) {
                break;
            }

            $descs = [];
            $temps = [];
            $pops = [];
            $middayEntry = $entries[intdiv(count($entries), 2)] ?? $entries[0];

            foreach ($entries as $e) {
                $d = strtolower(trim($e['weather'][0]['description'] ?? ''));
                if ($d !== '') {
                    $descs[] = $d;
                }
                $m = $e['main'] ?? [];
                if (isset($m['temp'])) {
                    $temps[] = (float) $m['temp'];
                }
                if (isset($m['temp_min'])) {
                    $temps[] = (float) $m['temp_min'];
                }
                if (isset($m['temp_max'])) {
                    $temps[] = (float) $m['temp_max'];
                }
                if (isset($e['pop'])) {
                    $pops[] = (float) $e['pop'];
                }
            }

            if (empty($temps)) {
                continue;
            }

            $low = (int) round(min($temps));
            $high = (int) round(max($temps));
            $range = $low === $high ? "{$low}°C" : "{$low}–{$high}°C";

            $counts = array_count_values($descs);
            arsort($counts);
            $desc = $descs ? (string) array_key_first($counts) : strtolower(trim($middayEntry['weather'][0]['description'] ?? 'unknown'));

            $pop = $pops ? max($pops) : 0.0;
            $rain = $pop > 0.05 ? ', '.(int) round($pop * 100).'% chance of rain' : '';

            try {
                $label = (new \DateTimeImmutable($date))->format('D M j');
            } catch (\Throwable) {
                $label = $date;
            }

            $lines[] = "- **{$label}**: ".ucfirst($desc).", {$range}{$rain}";
            $taken++;
        }

        if (empty($lines)) {
            return '';
        }

        return "**5-day outlook:**\n".implode("\n", $lines);
    }

    protected function handleDestinationsOverview(ChatSession $session): array
    {
        $names = DestinationModel::orderBy('name')->pluck('name')->all();

        if (empty($names)) {
            return ['reply' => "We don't have any destinations in our database yet. Please check back soon!"];
        }

        $formatted = count($names) === 1
            ? '**'.$names[0].'**'
            : implode(', ', array_map(fn ($n) => '**'.$n.'**', array_slice($names, 0, -1))).' and **'.end($names).'**';

        $count = count($names);
        $label = $count === 1 ? 'destination' : 'destinations';

        return [
            'reply' => "SunnyTrips focuses on Philippine destinations — currently **{$count} {$label}** powered by our AI recommendations: {$formatted}. Each destination page highlights curated stays and experiences. Ask me about a specific one like \"Tell me about {$names[0]}\" to see hotels, rooms, and things to do!",
        ];
    }

    protected function exampleDestinations(int $count = 2): string
    {
        $names = DestinationModel::orderBy('name')->pluck('name')->take($count)->all();

        if (empty($names)) {
            return 'a destination';
        }

        if (count($names) === 1) {
            return $names[0];
        }

        return implode(' or ', $names);
    }

    protected function handleGeneralChat(string $query, ChatSession $session): array
    {
        $destNames = DestinationModel::orderBy('name')->pluck('name')->all();
        $grounding = '';

        if (! empty($destNames)) {
            $list = implode(', ', $destNames);
            $grounding = "KNOWN DESTINATIONS IN OUR DATABASE: {$list}. Only reference these destinations. If the user asks about a destination not in this list, say it is not in our database. Do not invent other destinations.\n\n";
        }

        $prompt = $grounding.'USER QUERY: '.$query;
        $reply = $this->geminiChatResponse($prompt, $session);

        return ['reply' => $reply];
    }

    // ────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────

    protected function buildPrompt(string $stage, string $context, string $query, ?User $user): string
    {
        $header = match ($stage) {
            'room-search' => 'TASK: Recommend rooms based on the database results below.',
            'hotel-search' => 'TASK: Recommend hotels based on the database results below.',
            'activity-search' => 'TASK: Recommend activities and tours based on the database results below.',
            'package-search' => 'TASK: Recommend travel packages and promos based on the database results below.',
            'addon-search' => 'TASK: Recommend add-ons and transfer services based on the database results below.',
            'availability' => 'TASK: Report real-time room availability, prices, and remaining inventory.',
            'itinerary' => 'TASK: Present a day-by-day itinerary plan using the provided items.',
            default => "TASK: Answer the user's travel question.",
        };

        $rules = [
            'Answer ONLY using the provided database results and explicitly supplied live data.',
            'Treat the current DATABASE RESULTS section as the source of truth. Do not carry unsupported facts from earlier conversation turns into the answer.',
            'Never invent prices, availability, names, durations, or other factual details.',
            'Never present "Total Physical Rooms" as live availability. If the context says "not live availability", tell the user to provide check-in/check-out dates for a live check (e.g., "check Aug 30-31 for 2 pax").',
            'In DATABASE RESULTS, Rank #1 is the system\'s best AI match (highest relevance score) for the query; Rank #2+ are next-best alternatives. You must list every Rank provided (up to 5 hotels/rooms/activities/packages where provided, e.g., top 5) — Rank #1 under ### Best Match with one sentence why #1 is top (use Vibe/Category/Featured Amenities/Price Range/Guest Rating from that block), and Rank #2+ under ### Other Options each one bullet (name — Price Range — one key amenity). Do not omit alternatives to stay concise; this ranked-list rule overrides the concise 3-paragraph limit. If 2 or more Ranks were provided, then add one short line "Ranked by system: #1 is best match, #2+ are close alternatives." If only Rank #1 was provided, do NOT add any ranked/“best match” line and do not mention alternatives. Do not show raw relevance numbers unless helpful.',
            'Never add airports, ferry terminals, boats, vans, transfers, beaches, landmarks, restaurants, shops, fees, or food and drink estimates unless the exact fact appears in the database results.',
            'If information is unavailable, say it is not in our database instead of filling the gap with general travel knowledge.',
            'Use **bold** for short labels, ### for section headings, and - for bullet lists. Do not output HTML.',
            'You may reply in English or Taglish depending on the user\'s language.',
            'Keep responses friendly, concise, and helpful.',
        ];

        if ($stage === 'itinerary') {
            $rules[] = 'Build this itinerary ONLY from the listed destination, hotel, room, and activities.';
            $rules[] = 'Use the provided Day labels and activity assignments; do not invent new day-specific details.';
            $rules[] = 'Do not introduce any other locations, attractions, activities, venues, services, logistics, fees, or expenses.';
            $rules[] = 'Do not create airport arrival or departure plans, transfer details, meal plans, or extra budget estimates. Only use the listed room and activity prices and the pre-computed total.';
            $rules[] = 'If a part of the trip is not covered by the provided results, say that it is not included in the database results.';
        }

        $header .= "\n\nRULES:\n- ".implode("\n- ", $rules);

        if ($user) {
            $header .= "\n- The user is logged in as {$user->name}.";
        }

        return "{$header}\n\n=== DATABASE RESULTS ===\n{$context}\n=== END DATABASE RESULTS ===\n\nUSER QUERY: {$query}";
    }

    protected function buildSystemPrompt(string $type, bool $grounded): string
    {
        return $this->geminiChatSystemPrompt();
    }

    protected function geminiChatResponse(string $prompt, ChatSession $session): string
    {
        $history = $this->conversation->history($session, 6);

        return $this->gemini->generateChatResponse(
            $this->geminiChatSystemPrompt(),
            $history,
            $prompt
        ) ?? 'I apologize, but I could not generate a response at this moment. Please try again.';
    }

    protected function geminiChatSystemPrompt(): string
    {
        return $this->gemini->loadChatbotSystemPrompt();
    }

    protected function noResultsReply(string $type): string
    {
        $examples = $this->exampleDestinations(2);

        return "I could not find any {$type} matching your request. Try broadening your search, or ask me about a specific destination like {$examples}!";
    }

    protected function buildAvailabilityContext(array $available, int $pax, int $nights): string
    {
        $blocks = [];
        foreach ($available as $index => $entry) {
            /** @var RoomType $room */
            $room = $entry['item'];
            $avail = $entry['availability'];
            $hotel = $room->hotel;
            $baseOcc = (int) ($room->base_occupancy ?: 2);
            $maxOcc = (int) ($room->max_occupancy ?: 2);
            $fee = (float) ($room->extra_person_fee ?: 0);
            $extraLine = $fee > 0 && $maxOcc > $baseOcc
                ? 'Extra Person Fee: ₱'.number_format($fee, 2)." per extra head per night beyond {$baseOcc} pax"
                : "No extra guests allowed — maximum {$maxOcc} guests";
            $rank = $index + 1;
            $score = isset($entry['score']) ? round((float) $entry['score'], 4) : null;
            $rankLabel = $rank === 1
                ? '--- Available Room Rank #1 — BEST MATCH'.($score !== null ? " (relevance: {$score})" : '').' ---'
                : "--- Available Room Rank #{$rank} — Alternative".($score !== null ? " (relevance: {$score})" : '').' ---';
            $blocks[] = implode("\n", [
                $rankLabel,
                "Room: {$room->room_name} at {$hotel->hotel_name}",
                'Price per night: ₱'.number_format($room->calculateNightlyRate($pax), 2),
                "Total for {$nights} nights: {$entry['formatted_total']}",
                "Remaining: {$avail['remaining']} of {$avail['total_rooms']} rooms",
                "Base Occupancy: {$baseOcc} pax | Max Occupancy: {$maxOcc} pax",
                $extraLine,
                "Bed: {$room->bed_configuration}",
            ]);
        }

        $header = count($available) > 1
            ? "Ranked by AI semantic relevance + availability: Rank #1 = requested room/best match, Rank #2+ = close alternatives. Tell the user this.\n\n=== AVAILABLE ROOMS ({$pax} pax, {$nights} nights) ==="
            : "=== AVAILABLE ROOMS ({$pax} pax, {$nights} nights) ===";

        return $header."\n\n".implode("\n\n", $blocks);
    }

    protected function formatRoomResults(array $scored): array
    {
        return array_map(fn ($e) => [
            'id' => $e['item']->id,
            'room_name' => $e['item']->room_name,
            'hotel_name' => $e['item']->hotel?->hotel_name ?? 'Unknown Hotel',
            'hotel_id' => $e['item']->hotel_id ?? $e['item']->hotel?->id ?? null,
            'destination' => $e['item']->hotel?->destination?->name ?? null,
            'base_price' => (float) $e['item']->base_price,
            'occupancy' => $e['item']->occupancy,
            'base_occupancy' => (int) ($e['item']->base_occupancy ?: 2),
            'max_occupancy' => (int) ($e['item']->max_occupancy ?: ($e['item']->occupancy ?: 2)),
            'extra_person_fee' => (float) ($e['item']->extra_person_fee ?: 0),
            'ideal_guest' => $e['item']->ideal_guest ?? $e['item']->ideal_for,
            'image' => $this->firstImage($e['item']->images),
            'similarity_score' => round($e['score'], 4),
            'check_in_date' => $e['check_in_date'] ?? null,
            'check_out_date' => $e['check_out_date'] ?? null,
            'pax' => $e['pax'] ?? null,
        ], $scored);
    }

    protected function formatHotelResults(array $scored): array
    {
        return array_map(fn ($e) => [
            'id' => $e['item']->id,
            'hotel_name' => $e['item']->hotel_name,
            'destination' => $e['item']->destination?->name ?? null,
            'destination_id' => $e['item']->destination_id ?? $e['item']->destination?->id ?? null,
            'type' => $e['item']->type,
            'price_from' => $this->hotelPriceFrom($e['item']),
            'image' => $this->firstImage($e['item']->images),
            'similarity_score' => round($e['score'], 4),
        ], $scored);
    }

    protected function formatActivityResults(array $scored): array
    {
        return array_map(fn ($e) => [
            'id' => $e['item']->id,
            'activity_name' => $e['item']->activity_name,
            'destination' => $e['item']->destination?->name ?? null,
            'category' => $e['item']->category,
            'rate' => $e['item']->rate,
            'description' => strip_tags($e['item']->description ?? ''),
            'image' => $this->firstImage($e['item']->images),
            'similarity_score' => round($e['score'], 4),
        ], $scored);
    }

    protected function formatPackageResults(array $scored): array
    {
        return array_map(fn ($e) => [
            'id' => $e['item']->id,
            'name' => $e['item']->name,
            'destination' => $e['item']->destination?->name ?? null,
            'type' => $e['item']->type,
            'price' => (float) $e['item']->price,
            'days' => $e['item']->days,
            'nights' => $e['item']->nights,
            'min_pax' => $e['item']->min_pax,
            'description' => $e['item']->description ?? null,
            'inclusions' => $e['item']->generic_inclusions,
            'image' => $this->firstImage($e['item']->images),
            'similarity_score' => round($e['score'], 4),
        ], $scored);
    }

    protected function firstImage($images): ?string
    {
        if (empty($images)) {
            return null;
        }
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [];
        }
        if (is_array($images) && count($images) > 0) {
            return $images[0];
        }

        return null;
    }
}
