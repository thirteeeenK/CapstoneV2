<?php

namespace App\Services\Chat;

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Models\SupportInquiry;
use App\Models\User;
use App\Models\UserPreference;
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

        // DSS: "is it okay to book in that weather" → scored advisory using previous forecast (not repeat outlook)
        if ($lastBot && $this->isWeatherAdvisoryFollowUp($message, $lastBot)) {
            $reply = $this->handleWeatherAdvisory($message, $session, $user);
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge(['status' => 'success', 'control' => 'ai', 'session_token' => $session->session_token, 'reply' => $text], $reply);
        }

        // Filter refinements like "luxury quiet pool" should re-search with inherited destination, not Q&A over old cards
        if ($lastBot && $this->isFilterRefinementQuery($message, $lastBot) && ! $this->startsNewSearch(mb_strtolower(trim($message)))) {
            // fall through to fresh search with conversational destination inheritance
        } elseif ($this->isFollowUpQuery($message, $lastBot)) {
            $reply = $this->handleFollowUp($message, $session);
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge(['status' => 'success', 'control' => 'ai', 'session_token' => $session->session_token, 'reply' => $text], $reply);
        }

        // Affirmative like "yes search" after bot offered to search → repeat the
        // OFFERED search (activity/package/hotel/room), not hardcoded rooms
        if ($lastBot && $this->isAffirmativeSearchQuery($message, $lastBot)) {
            // The current user message is already persisted, so the latest
            // *previous* user message is the second-latest user row.
            $previousUser = $session->messages()->where('sender', 'user')->latest()->skip(1)->first();
            $intent = $this->resolveAffirmativeIntent($lastBot, $previousUser?->message);
            $constraints = $this->intentRouter->extractConstraints($message);
            $constraints = $this->resolveConversationalDestination($constraints, $session);
            $constraints = $this->resolveDefaultDestination($constraints, $user);
            $reply = match ($intent) {
                IntentRouter::ACTIVITY_SEARCH => $this->handleActivitySearch($message, $constraints, $user, $session),
                IntentRouter::HOTEL_SEARCH => $this->handleHotelSearch($message, $constraints, $user, $session),
                IntentRouter::PACKAGE_SEARCH => $this->handlePackageSearch($message, $constraints, $user, $session),
                IntentRouter::ADDON_SEARCH => $this->handleAddOnSearch($message, $constraints, $user, $session),
                default => $this->handleRoomSearch($message, $constraints, $user, $session),
            };
            // Fallback to hotel search if room search yields nothing but hotels exist
            if ($intent === IntentRouter::ROOM_SEARCH && empty($reply['retrieved_rooms']) && ! empty($constraints['destination_id'])) {
                $hotelReply = $this->handleHotelSearch($message, $constraints, $user, $session);
                if (! empty($hotelReply['retrieved_hotels'])) {
                    $reply = $hotelReply;
                }
            }
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge(['status' => 'success', 'control' => 'ai', 'session_token' => $session->session_token, 'reply' => $text], $reply);
        }

        $faq = $this->faq->findBestMatch($message);
        // Don't hijack amenity refinements or explicit Boracay hotel/room queries with FAQ
        $lowerForFaq = mb_strtolower(trim($message));
        $isBareFilter = $lastBot && $this->isFilterRefinementQuery($message, $lastBot);
        $hasExplicitDest = (bool) $this->intentRouter->extractDestinationName($message);
        if ($faq && ! $isBareFilter && ! $hasExplicitDest) {
            $reply = ['reply' => $faq->answer, 'faq' => ['id' => $faq->id, 'question' => $faq->question, 'answer' => $faq->answer]];
            $this->conversation->persist($session, 'bot', $reply['reply'], $reply);

            return array_merge(['status' => 'success', 'control' => 'ai', 'session_token' => $session->session_token], $reply);
        }

        $intent = $this->intentRouter->classify($message);
        $constraints = in_array($intent, [IntentRouter::GENERAL_TALK, IntentRouter::DESTINATIONS_OVERVIEW, IntentRouter::BOOKING_STATUS], true)
            ? []
            : $this->intentRouter->extractConstraints($message);
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);

        // Semantic catalog routing: keyword misses (e.g. a new offering with no
        // keyword yet) fall back to embeddings instead of defaulting to rooms.
        // Keyword hits and named hotels/rooms always keep their intent.
        if (
            $intent === IntentRouter::ROOM_SEARCH
            && ! $this->intentRouter->hasExplicitCatalogIntent($message)
            && empty($constraints['hotel_id']) && empty($constraints['hotel_name'])
            && empty($constraints['room_id']) && empty($constraints['room_name'])
        ) {
            $catalog = $this->gemini->resolveSemanticCatalog($message, $constraints['destination_id'] ?? null);
            $intent = match ($catalog) {
                'activities' => IntentRouter::ACTIVITY_SEARCH,
                'hotels' => IntentRouter::HOTEL_SEARCH,
                'packages' => IntentRouter::PACKAGE_SEARCH,
                'addons' => IntentRouter::ADDON_SEARCH,
                default => $intent,
            };
        }

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
            IntentRouter::DISCOUNT_QUERY => $this->handleDiscountQuery($message, $session),
            IntentRouter::BOOKING_STATUS => $this->handleBookingStatus($message, $user, $session),
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

        // Destination switch like "actually ... in El Nido" when previous was Boracay should be fresh search, not refinement
        $normalizedLower = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower);
        $normalizedLower = trim(preg_replace('/\s+/', ' ', $normalizedLower));
        foreach (DestinationModel::pluck('name') as $name) {
            $nameLower = mb_strtolower((string) $name);
            if ($nameLower !== '' && str_contains($normalizedLower, $nameLower)) {
                // If this destination is NOT in previous context, it's a switch → not a refinement
                $data = $lastBot->context_data ?: [];
                $prevDests = [];
                foreach (['retrieved_hotels', 'retrieved_rooms', 'retrieved_activities'] as $k) {
                    foreach ($data[$k] ?? [] as $it) {
                        $prevDests[] = mb_strtolower(trim((string) ($it['destination'] ?? '')));
                    }
                }
                // also check recent user dests? keep simple: if prev had any dest and new dest differs, treat as switch
                if (! empty($prevDests) && ! in_array($nameLower, $prevDests, true)) {
                    return false;
                }

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
        // normalize punctuation so "boracay)" or "boracay?" still matches, and "pertaining to boracay" counts
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);
        foreach (DestinationModel::pluck('name') as $name) {
            $name = mb_strtolower((string) $name);
            if ($name !== '' && str_contains($normalized, $name)) {
                return true;
            }
        }

        // If query names a specific room or add-on, treat as fresh search regardless of follow-up heuristic
        if ($this->intentRouter->extractRoomName($lower) || $this->intentRouter->extractAddOnName($lower)) {
            return true;
        }

        // Also treat "pertaining to", "something in <destination>" as fresh search triggers
        if (preg_match('/\b(pertaining to|something in|find me.*in|hotels and rooms)\b/i', $lower)) {
            return true;
        }

        return (bool) preg_match('/\b(hotel|hotels|room|rooms|resort|resorts|activity|activities|tour|tours|package|packages|itinerary|availability|weather|where is|how far)\b.*\b(in|near|at|for|with|pertaining)\b/i', $lower)
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
                'weather',
                'weather_forecast_raw',
                'weather_normalized',
                'destination_id',
                'destination_name',
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

        if (! empty($data['weather'])) {
            $w = $data['weather'];
            $suit = $w['suitability'] ?? null;
            $suitLine = $suit ? " — Booking score {$suit['score']}/100 ({$suit['label']})" : '';
            $blocks[] = "- Weather: {$w['destination']} — {$w['description']} at {$w['temp']}°C{$suitLine}";
            if (! empty($suit['reasons'])) {
                foreach ($suit['reasons'] as $r) {
                    $blocks[] = "  - {$r}";
                }
            }
            if (! empty($suit['driestDate'])) {
                $blocks[] = "  - Driest date in 5-day: {$suit['driestDate']}";
            }
        } elseif (! empty($data['destination_name']) && ! empty($data['weather_normalized'])) {
            $wn = $data['weather_normalized'];
            $cur = $wn['current'] ?? null;
            if ($cur) {
                $blocks[] = "- Weather: {$data['destination_name']} — {$cur['description']} at {$cur['temp']}°C";
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
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);
        $scored = null;
        if ($this->isPersonalized($user)) {
            $blended = $this->blendedVector($user, $query);
            if ($blended) {
                $roomsQuery = RoomType::with('hotel.destination')
                    ->where('is_shown', true)
                    ->whereNotNull('embedding');
                if (! empty($constraints['destination_id'])) {
                    $roomsQuery->whereHas('hotel', fn ($q) => $q->where('destination_id', $constraints['destination_id']));
                }
                if (! empty($constraints['hotel_id'])) {
                    $roomsQuery->where('hotel_id', $constraints['hotel_id']);
                }
                if (! empty($constraints['pax'])) {
                    $roomsQuery->where('max_occupancy', '>=', $constraints['pax']);
                }
                if (! empty($constraints['max_price'])) {
                    $roomsQuery->where('base_price', '<=', $constraints['max_price']);
                }
                if (! empty($constraints['room_id'])) {
                    $roomsQuery->where('id', $constraints['room_id']);
                } elseif (! empty($constraints['room_name'])) {
                    $roomsQuery->where('room_name', 'ILIKE', $constraints['room_name']);
                }
                $rooms = $roomsQuery->get();
                if ($rooms->isEmpty() && ! empty($constraints['room_name']) && ! empty($constraints['hotel_id'])) {
                    $rooms = RoomType::with('hotel.destination')
                        ->where('is_shown', true)
                        ->whereNotNull('embedding')
                        ->where('room_name', 'ILIKE', $constraints['room_name'])
                        ->get();
                }
                if ($rooms->isEmpty() && empty($constraints['hotel_id']) && empty($constraints['room_id']) && empty($constraints['room_name'])) {
                    $rooms = RoomType::with('hotel.destination')
                        ->where('is_shown', true)
                        ->whereNotNull('embedding')
                        ->get();
                }
                if ($rooms->isNotEmpty()) {
                    $scored = $this->gemini->rankRecommendations($blended, $rooms, 5);
                }
            }
        }
        if ($scored === null) {
            $scored = $this->gemini->searchRoomsHybrid($query, $constraints, 5);
        }

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
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);
        // Visible filter badge when destination was inherited for a bare amenity refinement
        $hadExplicitDest = (bool) $this->intentRouter->extractDestinationName($query);
        if (! $hadExplicitDest && ! empty($constraints['destination_name'])) {
            $lastBotBadge = $session->messages()->where('sender', 'bot')->latest()->first();
            if ($lastBotBadge && $this->isFilterRefinementQuery($query, $lastBotBadge)) {
                $reply = "Filtered for **{$constraints['destination_name']}**: {$query}\n\n".$reply;
            }
        }

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
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);

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

    protected function handleDiscountQuery(string $query, ChatSession $session): array
    {
        // FAQ path gives verbatim bullets (zero LLM cost) and is idempotent; live context ensures ₱50/₱150 stays correct if DB changes
        $faq = Faq::where('question', 'Does SunnyTrips offer discounts?')->where('is_active', true)->first();
        if ($faq) {
            return [
                'reply' => $faq->answer,
                'faq' => ['id' => $faq->id, 'question' => $faq->question, 'answer' => $faq->answer],
                'discount_rules' => $this->gemini->getPassengerDiscountContext(),
            ];
        }
        $context = $this->gemini->getPassengerDiscountContext();
        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('discounts')];
        }
        $prompt = $this->buildPrompt('discount', $context, $query, null);
        $reply = $this->geminiChatResponse($prompt, $session);

        return ['reply' => $reply, 'discount_rules' => $context];
    }

    protected function handleBookingStatus(string $query, ?User $user, ChatSession $session): array
    {
        // Booking data is identity-scoped: guests must log in, no code-based lookup
        if (! $user) {
            return [
                'reply' => 'Please [log in]('.route('login').') to your SunnyTrips account and ask again — I\'ll pull up your bookings and their latest status.',
            ];
        }

        $code = $this->intentRouter->extractBookingCode($query);
        $bookingsQuery = Booking::where('user_id', $user->id)->with('items')->latest('created_at');
        if ($code) {
            $bookingsQuery->where('booking_code', $code);
        }
        $bookings = $bookingsQuery->limit(5)->get();

        if ($bookings->isEmpty()) {
            if ($code) {
                return [
                    'reply' => "I couldn't find a booking with code **{$code}** in your account. Double-check the code, or just ask me for \"my bookings\" to see all of them.",
                ];
            }

            return [
                'reply' => "You don't have any bookings yet. Ask me to find a room, hotel, or package and I'll help you plan your trip — once you book, I can track the status here.",
            ];
        }

        $context = $this->buildBookingContext($bookings);
        $prompt = $this->buildPrompt('booking-status', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        return ['reply' => $this->linkifyBookingReferences($reply, $bookings)];
    }

    /**
     * Deterministically repair booking links Gemini flattened to plain text.
     * The model often drops the (url) half of [label](url); reattach it here
     * so every "View full details for CODE" always renders clickable.
     */
    protected function linkifyBookingReferences(string $reply, $bookings): string
    {
        foreach ($bookings as $booking) {
            $url = route('booking.show', $booking->booking_code);
            $label = 'View full details for '.$booking->booking_code;
            $markdown = "[{$label}]({$url})";
            if (str_contains($reply, $markdown)) {
                continue;
            }
            $reply = str_replace($label, $markdown, $reply);
        }

        return $reply;
    }

    protected function buildBookingContext($bookings): string
    {
        $blocks = [];
        foreach ($bookings as $booking) {
            /** @var Booking $booking */
            $lines = [
                "Booking Code: {$booking->booking_code}",
                'Status: '.str_replace('_', ' ', (string) $booking->status),
                'Payment Status: '.str_replace('_', ' ', (string) $booking->payment_status),
                'Total: ₱'.number_format((float) $booking->net_amount, 2),
            ];
            if ($booking->status === Booking::STATUS_APPROVED && $booking->payment_deadline) {
                $lines[] = 'Payment Deadline: '.$booking->payment_deadline->format('M j, Y g:i A').' (48-hour window)';
            }
            foreach ($booking->items as $item) {
                $dates = $item->check_in_date
                    ? " ({$item->check_in_date} to {$item->check_out_date}, {$item->nights} night(s))"
                    : '';
                $lines[] = "- {$item->item_title}{$dates}";
            }
            if ($booking->status === Booking::STATUS_REJECTED && $booking->rejection_reason) {
                $lines[] = 'Rejection Reason: '.$booking->rejection_reason;
            }
            if ($booking->status === Booking::STATUS_CANCELLED && $booking->cancellation_reason) {
                $lines[] = 'Cancellation Reason: '.$booking->cancellation_reason;
            }
            $lines[] = 'View full details: [View full details for '.$booking->booking_code.']('.route('booking.show', $booking->booking_code).')';

            $blocks[] = implode("\n", $lines);
        }

        return "=== USER BOOKINGS (live records for the logged-in user) ===\n\n".implode("\n\n", $blocks);
    }

    protected function handleHotelSearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);
        $limit = (int) ($constraints['limit'] ?? 0);
        if ($limit < 3 || $limit > 10) {
            $limit = 5;
            if (preg_match('/\btop\s*(\d+)\b/i', $query, $m)) {
                $limit = max(3, min(10, (int) $m[1]));
            }
        }
        $scored = null;

        // Exact-match shortcut: if a specific hotel name is extracted, return just that hotel.
        if (! empty($constraints['hotel_name'])) {
            $hotel = HotelModel::with('destination')
                ->where('hotel_name', 'ILIKE', $constraints['hotel_name'])
                ->first();
            if ($hotel) {
                $scored = [['item' => $hotel, 'score' => 1.0]];
            }
        }

        if ($scored === null) {
            if ($this->isPersonalized($user)) {
                $blended = $this->blendedVector($user, $query);
                if ($blended) {
                    $candidates = HotelModel::with('destination')
                        ->where('is_shown', true)
                        ->whereNotNull('embedding')
                        ->when(! empty($constraints['hotel_id']), fn ($q) => $q->where('id', $constraints['hotel_id']))
                        ->when(! empty($constraints['destination_id']), fn ($q) => $q->where('destination_id', $constraints['destination_id']))
                        ->get();
                    if ($candidates->isNotEmpty()) {
                        $scored = $this->gemini->rankRecommendations($blended, $candidates, $limit);
                    }
                }
            }
            if ($scored === null) {
                $scored = $this->gemini->searchHotels($query, $limit, $constraints['hotel_id'] ?? null, $constraints['destination_id'] ?? null);
            }
        }

        $priceIntent = $this->detectPriceIntent($query);
        if ($priceIntent) {
            $scored = $this->sortHotelsByPrice($scored, $priceIntent);
        }

        $context = $this->gemini->getHotelContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('hotels')];
        }

        $prompt = $this->buildPrompt('hotel-search', $context, $query, $user);
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);
        $hadExplicitDest = (bool) $this->intentRouter->extractDestinationName($query);
        if (! $hadExplicitDest && ! empty($constraints['destination_name'])) {
            $lastBotBadge = $session->messages()->where('sender', 'bot')->latest()->first();
            if ($lastBotBadge && $this->isFilterRefinementQuery($query, $lastBotBadge)) {
                $reply = "Filtered for **{$constraints['destination_name']}**: {$query}\n\n".$reply;
            }
        }

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

    // ────────────────────────────────────────────────
    //  Personalization helpers (always-on when logged-in)
    // ────────────────────────────────────────────────

    protected function parseUserVector(?User $user): ?array
    {
        if (! $user || empty($user->preferences_embedding)) {
            return null;
        }
        $raw = $user->preferences_embedding;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            $clean = trim($raw, "[] \t\n\r");
            if ($clean === '') {
                return null;
            }

            return array_map('floatval', explode(',', $clean));
        }

        return null;
    }

    protected function isPersonalized(?User $user): bool
    {
        $vec = $this->parseUserVector($user);
        if (empty($vec)) {
            return false;
        }
        if ($user->preferences_embedding === '[0]') {
            return false;
        }
        $sumAbs = array_sum(array_map('abs', $vec));

        return $sumAbs > 0.0001;
    }

    protected function blendedVector(?User $user, string $query): ?array
    {
        $userVector = $this->parseUserVector($user);
        if (empty($userVector)) {
            return null;
        }
        $queryVector = $this->gemini->generateEmbedding($query, 'RETRIEVAL_QUERY');
        if (! $queryVector) {
            return $userVector;
        }
        $len = max(count($userVector), count($queryVector));
        $blended = [];
        for ($i = 0; $i < $len; $i++) {
            $uv = $userVector[$i] ?? 0.0;
            $qv = $queryVector[$i] ?? 0.0;
            $blended[$i] = 0.65 * $uv + 0.35 * $qv;
        }

        return $this->gemini->normalizeVector($blended);
    }

    protected function resolveConversationalDestination(array $constraints, ChatSession $session): array
    {
        if (! empty($constraints['destination_id'])) {
            return $constraints;
        }
        try {
            // Last explicit destination from prior bot cards or user messages (last 6 turns)
            $lastBot = $session->messages()->where('sender', 'bot')->latest()->first();
            $data = $lastBot?->context_data ?: [];

            $candidates = [];
            if (! empty($data['retrieved_hotels'][0]['destination'])) {
                $candidates[] = $data['retrieved_hotels'][0]['destination'];
            }
            if (! empty($data['retrieved_rooms'][0]['destination'])) {
                $candidates[] = $data['retrieved_rooms'][0]['destination'];
            }
            if (! empty($data['retrieved_activities'][0]['destination'])) {
                $candidates[] = $data['retrieved_activities'][0]['destination'];
            }
            if (! empty($data['itinerary']['destination']['name'])) {
                $candidates[] = $data['itinerary']['destination']['name'];
            }
            // Scan recent user messages for explicit destination mention
            $recentUsers = $session->messages()->where('sender', 'user')->latest('created_at')->limit(6)->pluck('message');
            foreach ($recentUsers as $msg) {
                $dest = $this->intentRouter->extractDestinationName($msg);
                if ($dest) {
                    $candidates[] = $dest;
                }
            }
            // Also check recent bot text for destination name (fallback)
            if ($lastBot && $lastBot->message) {
                $dest = $this->intentRouter->extractDestinationName($lastBot->message);
                if ($dest) {
                    $candidates[] = $dest;
                }
            }
            foreach ($candidates as $destName) {
                $destName = trim((string) $destName);
                if ($destName === '') {
                    continue;
                }
                $destId = DestinationModel::where('name', 'ILIKE', $destName)->value('id');
                if ($destId) {
                    $constraints['destination_id'] = (int) $destId;
                    $constraints['destination_name'] = $destName;
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('resolveConversationalDestination failed: '.$e->getMessage());
        }

        return $constraints;
    }

    protected function resolveDefaultDestination(array $constraints, ?User $user): array
    {
        // Conversational destination already resolved in handle(); keep it if present
        if (! empty($constraints['destination_id'])) {
            return $constraints;
        }
        if (! $this->isPersonalized($user)) {
            return $constraints;
        }
        try {
            $pref = UserPreference::where('user_id', $user->id)->first();
            $destName = $pref?->destination;
            if (! $destName) {
                return $constraints;
            }
            $destId = DestinationModel::where('name', 'ILIKE', $destName)->value('id');
            if ($destId) {
                $constraints['destination_id'] = (int) $destId;
                $constraints['destination_name'] = $destName;
            }
        } catch (\Throwable $e) {
            Log::debug('resolveDefaultDestination failed: '.$e->getMessage());
        }

        return $constraints;
    }

    /**
     * Bare amenity/vibe filter after a hotel/room turn (e.g., "luxury quiet pool", "family-friendly")
     * should re-search with inherited destination, not Q&A over old cards.
     */
    protected function isFilterRefinementQuery(string $query, ChatMessage $lastBot): bool
    {
        $lower = mb_strtolower(trim($query));
        if ($lower === '' || count(preg_split('/\s+/', $lower)) > 10) {
            return false;
        }
        // Must be short and contain amenity/vibe or price signal, and no explicit new destination/hotel intent that would be fresh search
        if (! preg_match('/\b(pool|luxury|luxurious|premium|quiet|family-friendly|family|budget-friendly|secluded|private|cheap|cheapest|expensive|budget|under|less than|price)\b/i', $lower)) {
            return false;
        }
        $data = $lastBot->context_data ?: [];
        $hadHotelOrRoom = ! empty($data['retrieved_hotels']) || ! empty($data['retrieved_rooms']);
        $hadActivity = ! empty($data['retrieved_activities']);
        // Only treat as filter refinement if previous turn was hotel/room (or activity for activity filters)
        if (! $hadHotelOrRoom && ! $hadActivity) {
            return false;
        }
        // If query itself names a new destination, let startsNewSearch handle it as fresh
        if ($this->intentRouter->extractDestinationName($query)) {
            return false;
        }

        return true;
    }

    protected function isAffirmativeSearchQuery(string $query, ChatMessage $lastBot): bool
    {
        $lower = trim(mb_strtolower($query));
        // short affirmations that mean "yes, do the search you offered"
        if (! preg_match('/^(yes|yep|yeah|yup|ok|okay|sure|go ahead|please do|search|find it|show me|yes search|yes please)(\.?|!)?$/i', $lower)) {
            // also allow "yes search." with optional punctuation
            if (! preg_match('/^(yes|yep|ok|okay)\b.*\b(search|find|show)\b/i', $lower)) {
                return false;
            }
        }
        $text = mb_strtolower($lastBot->message ?? '');
        // Last bot offered to search / asked for preferences
        if (str_contains($text, 'would you like me to search') || str_contains($text, 'to give you the best recommendations') || str_contains($text, 'let me search')) {
            return true;
        }
        $data = $lastBot->context_data ?: [];
        // If last turn returned activities and user said yes, they likely want
        // to proceed with that activity offer (resolved by resolveAffirmativeIntent)
        if (! empty($data['retrieved_activities']) && preg_match('/\b(yes|yep|ok|search)\b/i', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * Inherit the search intent the bot actually offered. Precedence: explicit
     * offer in the bot text first, then the catalog the user was discussing
     * (their previous message — immune to cards carried over by follow-ups),
     * then stored cards, defaulting to ROOM_SEARCH.
     */
    protected function resolveAffirmativeIntent(ChatMessage $lastBot, ?string $previousUserMessage = null): string
    {
        $text = mb_strtolower($lastBot->message ?? '');
        if (str_contains($text, 'activit')) {
            return IntentRouter::ACTIVITY_SEARCH;
        }
        if (str_contains($text, 'package')) {
            return IntentRouter::PACKAGE_SEARCH;
        }
        if (str_contains($text, 'hotel')) {
            return IntentRouter::HOTEL_SEARCH;
        }
        if (str_contains($text, 'add-on') || str_contains($text, 'addon')) {
            return IntentRouter::ADDON_SEARCH;
        }
        if (str_contains($text, 'room')) {
            return IntentRouter::ROOM_SEARCH;
        }

        if ($previousUserMessage && ($catalog = $this->intentRouter->explicitCatalogIntent($previousUserMessage))) {
            return $catalog;
        }

        $data = $lastBot->context_data ?: [];
        if (! empty($data['retrieved_activities'])) {
            return IntentRouter::ACTIVITY_SEARCH;
        }
        if (! empty($data['retrieved_packages'])) {
            return IntentRouter::PACKAGE_SEARCH;
        }
        if (! empty($data['retrieved_hotels'])) {
            return IntentRouter::HOTEL_SEARCH;
        }
        if (! empty($data['retrieved_addons'])) {
            return IntentRouter::ADDON_SEARCH;
        }

        return IntentRouter::ROOM_SEARCH;
    }

    protected function buildUserProfileText(?User $user): ?string
    {
        if (! $this->isPersonalized($user)) {
            return null;
        }
        try {
            $pref = UserPreference::where('user_id', $user->id)->first();
            if (! $pref) {
                return null;
            }
            $lines = ['Traveler Profile (from onboarding):'];
            if ($pref->destination) {
                $lines[] = "Preferred destination: {$pref->destination}";
            }
            if ($pref->traveler_type) {
                $lines[] = "Traveler type: {$pref->traveler_type}";
            }
            if (! empty($pref->vibes)) {
                $v = is_array($pref->vibes) ? implode(', ', $pref->vibes) : (string) $pref->vibes;
                $lines[] = "Vibes: {$v}";
            }
            if (! empty($pref->amenities)) {
                $a = is_array($pref->amenities) ? implode(', ', $pref->amenities) : (string) $pref->amenities;
                $lines[] = "Amenities: {$a}";
            }
            if (! empty($pref->activities)) {
                $ac = is_array($pref->activities) ? implode(', ', $pref->activities) : (string) $pref->activities;
                $lines[] = "Activities: {$ac}";
            }
            if ($pref->notes) {
                $lines[] = "Notes: {$pref->notes}";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function handleActivitySearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);
        $scored = null;

        // Exact-match shortcut: if a specific activity name is extracted, return just that activity.
        if (! empty($constraints['activity_name'])) {
            $activity = ActivityModel::with('destination')
                ->where('activity_name', 'ILIKE', $constraints['activity_name'])
                ->first();
            if ($activity) {
                $scored = [['item' => $activity, 'score' => 1.0]];
            }
        }

        if ($scored === null) {
            if ($this->isPersonalized($user)) {
                $blended = $this->blendedVector($user, $query);
                if ($blended) {
                    $candidates = ActivityModel::with('destination')
                        ->where('is_shown', true)
                        ->whereNotNull('embedding')
                        ->when(! empty($constraints['destination_id']), fn ($q) => $q->where('destination_id', $constraints['destination_id']))
                        ->get();
                    if ($candidates->isNotEmpty()) {
                        $scored = $this->gemini->rankRecommendations($blended, $candidates, 3);
                    }
                }
            }
            if ($scored === null) {
                $scored = $this->gemini->searchActivities($query, 3, $constraints['destination_id'] ?? null);
                // If personalized default added destination but global search returned broader set, re-rank with blended for that destination
                if ($this->isPersonalized($user) && ! empty($constraints['destination_id']) && ! empty($scored)) {
                    $blended = $this->blendedVector($user, $query);
                    if ($blended) {
                        $destCandidates = ActivityModel::with('destination')
                            ->where('is_shown', true)
                            ->whereNotNull('embedding')
                            ->where('destination_id', $constraints['destination_id'])
                            ->get();
                        if ($destCandidates->isNotEmpty()) {
                            $scored = $this->gemini->rankRecommendations($blended, $destCandidates, 3);
                        }
                    }
                }
            }
        }

        $context = $this->gemini->getActivityContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('activities')];
        }

        $prompt = $this->buildPrompt('activity-search', $context, $query, $user);
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);

        return [
            'reply' => $reply,
            'retrieved_activities' => $this->formatActivityResults($scored),
        ];
    }

    protected function handlePackageSearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);
        $destinationId = $constraints['destination_id'] ?? null;
        $scored = null;

        // Exact-match shortcut: if a specific package name is extracted, return just that package.
        if (! empty($constraints['package_name'])) {
            $pkg = Package::with('destination')
                ->where('name', 'ILIKE', $constraints['package_name'])
                ->first();
            if ($pkg) {
                $scored = [['item' => $pkg, 'score' => 1.0]];
            }
        }

        if ($scored === null) {
            if ($this->isPersonalized($user)) {
                $blended = $this->blendedVector($user, $query);
                if ($blended) {
                    $candidates = Package::with('destination')
                        ->where('is_active', true)
                        ->whereNotNull('embedding')
                        ->when($destinationId, fn ($q) => $q->where('destination_id', $destinationId))
                        ->get();
                    if ($candidates->isNotEmpty()) {
                        $scored = $this->gemini->rankRecommendations($blended, $candidates, 5);
                    }
                }
            }
            if ($scored === null) {
                $scored = $this->gemini->searchPackages($query, 5, $destinationId);
            }
        }

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
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);

        return [
            'reply' => $reply,
            'retrieved_packages' => $this->formatPackageResults($scored),
        ];
    }

    protected function handleItineraryQuery(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);
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
            $p1 = $places[0];
            $p2 = $places[1];
            $coords1 = $this->getPlaceCoords($p1);
            $coords2 = $this->getPlaceCoords($p2);
            if ($coords1 && $coords2) {
                $km = $this->distance->haversine($coords1['lat'], $coords1['lng'], $coords2['lat'], $coords2['lng']);
                $label = $this->distance->format($km);
                $name1 = $p1['name'];
                $name2 = $p2['name'];

                return [
                    'reply' => "{$name1} is approximately {$label} from {$name2}.",
                    'map' => [
                        'from' => ['name' => $name1, 'lat' => $coords1['lat'], 'lng' => $coords1['lng']],
                        'to' => ['name' => $name2, 'lat' => $coords2['lat'], 'lng' => $coords2['lng']],
                        'distance_km' => round($km, 2),
                        'distance_label' => $label,
                    ],
                ];
            }
        }

        if (! empty($places)) {
            $place = $places[0];
            $coords = $this->getPlaceCoords($place);
            if ($coords) {
                $isUserDistance = preg_match('/\b(how far am i|from me|from my location|from here|am i from|distance from me|gaano.*kalayo|layo ko|nasaan ako|kinalalagyan|ako|ko|akin)\b/i', $query)
                    || (preg_match('/\bhow far\b/i', $query) && preg_match('/\b(i|me|my)\b/i', $query) && count($places) === 1)
                    || (preg_match('/\b(gaano|kalayo|layo)\b/i', $query) && preg_match('/\b(ako|ko|akin|kinalalagyan)\b/i', $query))
                    || (preg_match('/\bdistance between\b.*\bmy current location\b/i', $query) && count($places) === 1);

                if ($isUserDistance) {
                    if ($userLat !== null && $userLng !== null) {
                        $km = $this->distance->haversine($userLat, $userLng, $coords['lat'], $coords['lng']);
                        $label = $this->distance->format($km);

                        return [
                            'reply' => "You are approximately {$label} from {$place['name']}.",
                            'map' => [
                                'from' => ['lat' => $userLat, 'lng' => $userLng, 'label' => 'You'],
                                'to' => ['name' => $place['name'], 'lat' => $coords['lat'], 'lng' => $coords['lng']],
                                'distance_km' => round($km, 2),
                                'distance_label' => $label,
                            ],
                        ];
                    }

                    return [
                        'reply' => "To calculate how far you are from {$place['name']}, I'll need your current location.",
                        'location_request' => true,
                        'location_target' => $place['name'],
                        'map' => [
                            'target' => ['name' => $place['name'], 'lat' => $coords['lat'], 'lng' => $coords['lng']],
                        ],
                    ];
                }

                return [
                    'reply' => "{$place['name']} is located at latitude {$coords['lat']}, longitude {$coords['lng']}.",
                    'map' => [
                        'name' => $place['name'],
                        'lat' => $coords['lat'],
                        'lng' => $coords['lng'],
                    ],
                ];
            }
        }

        // Build helpful fallback listing known places
        $knownDestinations = DestinationModel::orderBy('name')->pluck('name')->implode(', ');
        $knownHotels = HotelModel::whereNotNull('latitude')->whereNotNull('longitude')->orderBy('hotel_name')->limit(5)->pluck('hotel_name')->implode(', ');
        $suggestions = [];
        if ($knownDestinations) {
            $suggestions[] = "destinations like {$knownDestinations}";
        }
        if ($knownHotels) {
            $suggestions[] = "hotels like {$knownHotels}";
        }
        $example = ! empty($suggestions) ? 'Try asking about '.implode(' or ', $suggestions).'.' : 'Try "How far is El Nido from Boracay?"';

        return [
            'reply' => 'I could not find the location you mentioned. '.$example,
        ];
    }

    /**
     * Get coordinates for a place array (type destination or hotel).
     */
    protected function getPlaceCoords(array $place): ?array
    {
        if ($place['type'] === 'destination') {
            $dest = DestinationModel::where('name', 'ILIKE', $place['name'])->first();
            if ($dest && $dest->latitude && $dest->longitude) {
                return ['lat' => (float) $dest->latitude, 'lng' => (float) $dest->longitude];
            }
        } elseif ($place['type'] === 'hotel') {
            if (isset($place['model']) && $place['model'] instanceof HotelModel) {
                $hotel = $place['model'];
            } else {
                $hotel = HotelModel::where('hotel_name', 'ILIKE', $place['name'])->first();
            }
            if ($hotel && $hotel->latitude && $hotel->longitude) {
                return ['lat' => (float) $hotel->latitude, 'lng' => (float) $hotel->longitude];
            }
        }

        return null;
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
        $reply .= "\n\n".$this->weatherCoverageNote();

        $normalized = $this->weather->normalize($forecast);
        $suitability = $this->weather->bookingSuitability($forecast);

        return [
            'reply' => $reply,
            'weather' => [
                'destination' => $dest->name,
                'destination_id' => $dest->id,
                'temp' => $temp,
                'description' => $desc,
                'feels_like' => round($main['feels_like'] ?? $temp),
                'humidity' => $main['humidity'] ?? null,
                'advice' => $advice,
                'suitability' => $suitability,
            ],
            // Persist raw forecast + normalized for follow-up advisory reasoning (not shown to user directly).
            'weather_forecast_raw' => $forecast,
            'weather_normalized' => $normalized,
            'destination_id' => $dest->id,
            'destination_name' => $dest->name,
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

    protected function isWeatherAdvisoryFollowUp(string $query, ?ChatMessage $lastBot): bool
    {
        if (! $lastBot) {
            return false;
        }

        $data = $lastBot->context_data ?: [];
        $hasWeather = ! empty($data['weather']) || ! empty($data['weather_normalized']) || ! empty($data['weather_forecast_raw']) || ! empty($data['destination_id']);

        if (! $hasWeather) {
            $msg = mb_strtolower($lastBot->message ?? '');
            if (! str_contains($msg, '5-day outlook') && ! str_contains($msg, 'current weather') && ! str_contains($msg, 'weather in')) {
                return false;
            }
            $hasWeather = true;
        }

        $lower = mb_strtolower(trim($query));
        if ($lower === '') {
            return false;
        }

        // Direct advisory phrases in English + Taglish
        $advisoryPattern = '/\b(okay to book|ok to book|worth (it|booking)|should i (book|go|postpone|proceed|cancel)|is it (safe|advisable|good|worth|okay|ok) |advisable|postpone|cancel|reschedule|sulit.*book|tuloy.*(book|biyahe)|maganda.*panahon|pangit.*panahon)\b/i';
        if (preg_match($advisoryPattern, $lower)) {
            return true;
        }

        // booking + weather in same short query (e.g., "is it okay to book in that weather")
        if (preg_match('/\b(book|booking|biyahe|pasyal|reserve)\b/i', $lower) && preg_match('/\b(weather|rain|ulan|bagyo|panahon|forecast|outlook)\b/i', $lower)) {
            return true;
        }

        // Pure "is it okay?" / "sulit ba?" after a weather turn is implicitly about that weather
        if (preg_match('/^(is it (okay|ok|worth|safe|good)|okay ba|sulit ba|tuloy ba|should i)/i', $lower) && $hasWeather) {
            return true;
        }

        // Very short booking question after weather (≤6 words, contains book)
        if (str_word_count($lower) <= 8 && preg_match('/\b(book|booking)\b/i', $lower) && $hasWeather) {
            return true;
        }

        return false;
    }

    protected function handleWeatherAdvisory(string $query, ChatSession $session, ?User $user): array
    {
        $lastBot = $session->messages()->where('sender', 'bot')->latest()->first();
        $data = $lastBot?->context_data ?: [];
        $constraints = $this->intentRouter->extractConstraints($query);
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);

        $destinationId = $constraints['destination_id'] ?? $data['destination_id'] ?? $data['weather']['destination_id'] ?? null;
        $destinationName = $constraints['destination_name'] ?? $data['destination_name'] ?? $data['weather']['destination'] ?? null;

        if (! $destinationId && $destinationName) {
            $destinationId = DestinationModel::where('name', 'ILIKE', $destinationName)->value('id');
        }

        // Fallback: try to extract from last user messages if still missing
        if (! $destinationId) {
            $recentUsers = $session->messages()->where('sender', 'user')->latest('created_at')->limit(3)->pluck('message');
            foreach ($recentUsers as $msg) {
                $name = $this->intentRouter->extractDestinationName($msg);
                if ($name) {
                    $destinationName = $name;
                    $destinationId = DestinationModel::where('name', 'ILIKE', $name)->value('id');
                    if ($destinationId) {
                        break;
                    }
                }
            }
        }

        if (! $destinationId) {
            $example = $this->exampleDestinations(1);

            return ['reply' => 'Which destination would you like booking advice for? For example, "Is it okay to book in Boracay this week?" (Try '.$example.')'];
        }

        $dest = DestinationModel::find($destinationId);
        if (! $dest) {
            return ['reply' => 'I could not find that destination. Could you check the spelling?'];
        }

        // Reuse cached forecast from previous turn if same destination, else fetch
        $forecast = null;
        $reused = false;
        if (! empty($data['weather_forecast_raw']) && (int) ($data['destination_id'] ?? $data['weather']['destination_id'] ?? 0) === (int) $destinationId) {
            $forecast = $data['weather_forecast_raw'];
            $reused = true;
        }

        if (! $forecast) {
            $forecast = $this->weather->forecastForDestination($dest);
        }

        if (! $forecast) {
            return ['reply' => "I'm sorry, weather data for {$dest->name} is currently unavailable. Please try again later — meanwhile I can show you indoor-friendly activities there."];
        }

        $normalized = $data['weather_normalized'] ?? $this->weather->normalize($forecast);
        if ($reused && empty($normalized['current'])) {
            $normalized = $this->weather->normalize($forecast);
        }

        $suitability = $data['weather']['suitability'] ?? $this->weather->bookingSuitability($forecast);
        if (empty($suitability) || ! isset($suitability['score'])) {
            $suitability = $this->weather->bookingSuitability($forecast);
        }

        // Driest date for current dest
        $driestDate = $suitability['driestDate'] ?? null;
        $driestLabel = 'the driest day in the 5-day';
        $driestPop = null;
        if ($driestDate) {
            try {
                $driestLabel = (new \DateTimeImmutable($driestDate))->format('D M j');
                $driestPop = $suitability['dailyScores'][$driestDate]['pop'] ?? null;
            } catch (\Throwable $e) {
                $driestLabel = $driestDate;
            }
        }

        // Build DSS prompt for Gemini
        $outlook = $this->buildFiveDayOutlook($forecast);
        $dailyLines = [];
        foreach ($suitability['dailyScores'] ?? [] as $date => $d) {
            try {
                $lbl = (new \DateTimeImmutable($date))->format('D M j');
            } catch (\Throwable $e) {
                $lbl = $date;
            }
            $dailyLines[] = "- {$lbl}: score {$d['score']}/100, POP ".(int) round($d['pop'] * 100)."% , rain {$d['rain']}mm, wind {$d['wind']}m/s, temp {$d['temp']}°C";
        }

        $context = "DESTINATION: {$dest->name}\n";
        $context .= "BOOKING SUITABILITY: {$suitability['score']}/100 — {$suitability['label']} (level: {$suitability['level']})\n";
        $context .= 'REASONS: '.implode('; ', $suitability['reasons'] ?? [])."\n";
        $context .= "DAILY BREAKDOWN:\n".implode("\n", $dailyLines)."\n\n";
        $context .= "5-DAY OUTLOOK (from forecast):\n{$outlook}\n\n";
        $context .= "USER QUESTION: {$query}";

        $prompt = $this->buildPrompt('weather-advisory', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        // If Gemini unavailable, deterministic fallback that still uses DSS scoring
        if (! $reply || trim($reply) === '' || str_contains($reply, 'I could not generate a response')) {
            $intro = $suitability['level'] === 'good'
                ? "Based on the weather forecast for {$dest->name}, it's a good time to book."
                : ($suitability['level'] === 'okay'
                    ? "Based on the weather forecast for {$dest->name}, conditions are mixed for booking."
                    : "Based on the weather forecast for {$dest->name}, it's not the best time to book.");
            $fallback = "{$intro}\n\n";
            $fallback .= "Booking Suitability: {$suitability['score']}/100 (".ucfirst($suitability['level']).")\n\n";
            $fallback .= "Reasons:\n".implode("\n", array_map(fn ($r) => "- {$r}", $suitability['reasons'] ?? []))."\n";
            $reply = $fallback;
        }

        $reply = rtrim($reply)."\n\n".$this->weatherCoverageNote();

        return [
            'reply' => $reply,
            'weather_advisory' => [
                'destination' => $dest->name,
                'destination_id' => $dest->id,
                'score' => $suitability['score'],
                'level' => $suitability['level'],
                'label' => $suitability['label'],
                'reasons' => $suitability['reasons'] ?? [],
                'driestDate' => $driestDate,
                'driestLabel' => $driestLabel,
                'dailyScores' => $suitability['dailyScores'] ?? [],
                'alternative' => null,
            ],
            'weather' => [
                'destination' => $dest->name,
                'destination_id' => $dest->id,
                'suitability' => $suitability,
                'advice' => $this->weather->advice($this->weather->normalize($forecast)),
            ],
            'weather_forecast_raw' => $forecast,
            'weather_normalized' => $normalized,
            'destination_id' => $dest->id,
            'destination_name' => $dest->name,
        ];
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

    protected function weatherCoverageNote(): string
    {
        return 'Note: forecasts here cover only the next 5 days; dates beyond that can\'t be generated.';
    }

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
            'discount' => 'TASK: Explain SunnyTrips passenger pricing rules and discounts based on the database results below.',
            'booking-status' => 'TASK: Report the status of the user\'s bookings using the booking records below.',
            'weather-advisory' => 'TASK: Advise on booking given the weather forecast — provide a scored recommendation.',
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

        if ($stage === 'booking-status') {
            $rules[] = 'Report ONLY the booking records provided below; never invent or guess a status, amount, or date.';
            $rules[] = 'Do not mention admin notes, internal price adjustments, payment links, or gateway references.';
            $rules[] = 'Suggest opening the full-details link for actions like payment or cancellation requests.';
            $rules[] = 'Render each booking link EXACTLY in markdown-link format: [View full details for CODE](url) using the URL from that booking record — never output a bare URL.';
        }

        if ($stage === 'weather-advisory') {
            $rules[] = 'Use ONLY the weather forecast numbers provided (POP, rain mm, wind m/s, temp °C) — cite exact values per day; never invent weather.';
            $rules[] = 'Explain the booking score 0-100 and level (Good ≥70, Okay 40-69, Poor <40) with 2-3 short reasons from the provided suitability reasons.';
            $rules[] = 'If score is good, confirm it is a good window; if okay or poor, advise to consider postponing when POP is persistently high. Do NOT mention a driest date, do NOT suggest bringing a rain jacket, and do NOT suggest alternative dates.';
            $rules[] = 'Do NOT suggest alternative destinations, indoor activities, or free cancellation — keep advice to the single destination forecast only.';
            $rules[] = 'Keep Taglish if user used Tagalog, otherwise English; keep tone warm and concise like a Filipino travel buddy.';
            $rules[] = 'Do not add fees, transports, landmarks, restaurants, or activities not in context.';
        }

        $header .= "\n\nRULES:\n- ".implode("\n- ", $rules);

        if ($user) {
            $header .= "\n- The user is logged in as {$user->name}.";
            if ($this->isPersonalized($user)) {
                $profile = $this->buildUserProfileText($user);
                if ($profile) {
                    $header .= "\n- Personalized for this user (from onboarding quiz). Use it to tailor why #1 is best:\n".$profile;
                }
            }
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

    /**
     * Gemini sometimes emits the "Ranked by system: #1 is best match, #2+ are
     * close alternatives" line even when only one result was retrieved.
     * Strip it deterministically when there are no alternatives to mention.
     */
    protected function stripRankLineIfSingle(string $reply, array $scored): string
    {
        if (count($scored) >= 2) {
            return $reply;
        }

        $cleaned = (string) preg_replace('/^[^\n]*Ranked by (system|AI semantic relevance)[^\n]*\n?/mi', '', $reply);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $cleaned));
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
