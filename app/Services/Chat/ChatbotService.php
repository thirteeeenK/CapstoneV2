<?php

namespace App\Services\Chat;

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\ChatbotAbuseReport;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\Faq;
use App\Models\HotelModel;
use App\Models\IpBan;
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
    /**
     * Guest login nudge appended by ChatbotController. Canonical home is here
     * so Gemini-bound history can strip it (else the model mimics it and the
     * reply ends up with the nudge twice).
     */
    public const GUEST_NUDGE = "You're chatting as a guest — log in or register for the full experience, or contact SUNNYTRIPS TRAVEL SERVICES at 09682447153 for more inquiries.";

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

        // Handoff state machine: a PENDING ticket means "AI + user conversation
        // while waiting for assignment" — messages flow through the normal
        // chatbot path (and are persisted for the future admin). Only a
        // HUMAN_ACTIVE ticket (admin claimed) disables the AI.
        $pendingHandoff = $inquiry && $inquiry->status === SupportInquiry::STATUS_PENDING;
        $aiBase = ['status' => 'success', 'control' => 'ai', 'session_token' => $session->session_token];
        if ($pendingHandoff) {
            $aiBase['handoff_status'] = SupportInquiry::STATUS_PENDING;
        }

        if ($inquiry && $inquiry->status === SupportInquiry::STATUS_HUMAN_ACTIVE) {
            return [
                'status' => 'human_support_active',
                'control' => 'admin',
                'session_token' => $session->session_token,
            ];
        }

        $lastBot = $session->messages()->where('sender', 'bot')->latest('id')->first();

        // DSS: "is it okay to book in that weather" → scored advisory using previous forecast (not repeat outlook)
        if ($lastBot && $this->isWeatherAdvisoryFollowUp($message, $lastBot)) {
            $reply = $this->handleWeatherAdvisory($message, $session, $user);
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge($aiBase, ['reply' => $text], $reply);
        }

        // Availability follow-up over a prior exact room: short queries like
        // "is the room available?" / "is it still open?" / "yes check it" must stay
        // scoped to the exact room instead of broadening back to the whole hotel/destination.
        if ($lastBot && $this->isExactRoomAvailabilityFollowUp($message, $lastBot)) {
            $constraints = $this->intentRouter->extractConstraints($message);
            $constraints = $this->inheritRoomContext($constraints, $session);
            $reply = $this->handleAvailabilityQuery($message, $constraints, $user, $session);
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge($aiBase, ['reply' => $text], $reply);
        }

        // "Check alternatives" pill/typed follow-up after an unavailable exact
        // room → drop room-specific filter and surface same-hotel/destination
        // alternatives via the regular availability path.
        if ($lastBot && $this->isCheckAlternativesFollowUp($message, $lastBot)) {
            $constraints = $this->intentRouter->extractConstraints($message);
            $constraints = $this->inheritRoomContext($constraints, $session);
            unset($constraints['room_id'], $constraints['room_name']);
            $reply = $this->handleAvailabilityQuery($message, $constraints, $user, $session);
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge($aiBase, ['reply' => $text], $reply);
        }

        // Filter refinements like "luxury quiet pool" should re-search with inherited destination, not Q&A over old cards
        if ($lastBot && $this->isFilterRefinementQuery($message, $lastBot) && ! $this->startsNewSearch(mb_strtolower(trim($message)))) {
            // fall through to fresh search with conversational destination inheritance
        } elseif ($this->isFollowUpQuery($message, $lastBot)) {
            $reply = $this->handleFollowUp($message, $session);
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge($aiBase, ['reply' => $text], $reply);
        }

        // Affirmative like "yes search" after bot offered to search → repeat the
        // OFFERED search (activity/package/hotel/room), not hardcoded rooms
        if ($lastBot && $this->isAffirmativeSearchQuery($message, $lastBot)) {
            // The current user message is already persisted, so the latest
            // *previous* user message is the second-latest user row.
            $previousUser = $session->messages()->where('sender', 'user')->latest('id')->skip(1)->first();
            $intent = $this->resolveAffirmativeIntent($lastBot, $previousUser?->message);
            // Reuse the prior user message as the search query so the handler
            // embeds something meaningful ("how about banana boat") instead of
            // the affirmation text ("yes search") — unless the affirmation
            // itself carries searchable content ("yes, search El Nido hotels").
            $searchQuery = $previousUser?->message ?: $message;
            if ($searchQuery !== $message && $this->affirmationCarriesSearch($message)) {
                $searchQuery = $message;
            }
            $constraints = $this->intentRouter->extractConstraints($searchQuery);
            $constraints = $this->resolveConversationalDestination($constraints, $session);
            $constraints = $this->resolveDefaultDestination($constraints, $user);
            $constraints = $this->inheritConversationalConstraints($searchQuery, $constraints, $session);
            $reply = match ($intent) {
                IntentRouter::ACTIVITY_SEARCH => $this->handleActivitySearch($searchQuery, $constraints, $user, $session),
                IntentRouter::HOTEL_SEARCH => $this->handleHotelSearch($searchQuery, $constraints, $user, $session),
                IntentRouter::PACKAGE_SEARCH => $this->handlePackageSearch($searchQuery, $constraints, $user, $session),
                IntentRouter::ADDON_SEARCH => $this->handleAddOnSearch($searchQuery, $constraints, $user, $session),
                default => $this->handleRoomSearch($searchQuery, $constraints, $user, $session),
            };
            // Fallback to hotel search if room search yields nothing but hotels exist
            if ($intent === IntentRouter::ROOM_SEARCH && empty($reply['retrieved_rooms']) && ! empty($constraints['destination_id'])) {
                $hotelReply = $this->handleHotelSearch($searchQuery, $constraints, $user, $session);
                if (! empty($hotelReply['retrieved_hotels'])) {
                    $reply = $hotelReply;
                }
            }
            $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
            $this->conversation->persist($session, 'bot', $text, $reply);

            return array_merge($aiBase, ['reply' => $text], $reply);
        }

        $faq = $this->faq->findBestMatch($message);
        // Don't hijack amenity refinements or explicit Boracay hotel/room queries with FAQ
        // Legal/contact queries have their own deterministic handler — never FAQ.
        $lowerForFaq = mb_strtolower(trim($message));
        $isLegalQuery = $this->intentRouter->classify($message) === IntentRouter::LEGAL_QUERY;
        $isBareFilter = $lastBot && $this->isFilterRefinementQuery($message, $lastBot);
        $hasExplicitDest = (bool) $this->intentRouter->extractDestinationName($message);
        if ($faq && ! $isLegalQuery && ! $isBareFilter && ! $hasExplicitDest) {
            $reply = ['reply' => $faq->answer, 'faq' => ['id' => $faq->id, 'question' => $faq->question, 'answer' => $faq->answer]];
            $this->conversation->persist($session, 'bot', $reply['reply'], $reply);

            return array_merge($aiBase, $reply);
        }

        $intent = $this->intentRouter->classify($message);
        $constraints = in_array($intent, [IntentRouter::GENERAL_TALK, IntentRouter::DESTINATIONS_OVERVIEW, IntentRouter::BOOKING_STATUS, IntentRouter::LEGAL_QUERY], true)
            ? []
            : $this->intentRouter->extractConstraints($message);
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);
        $constraints = $this->inheritConversationalConstraints($message, $constraints, $session);

        // Semantic catalog routing: keyword misses (e.g. a new offering with no
        // keyword yet) fall back to embeddings instead of defaulting to rooms.
        // Keyword hits and named hotels/rooms always keep their intent.
        // GENERAL_TALK gets one embedding-based chance at a catalog search
        // (typos like "parawsailing", novel phrasings) before general chat.
        if (
            ($intent === IntentRouter::ROOM_SEARCH
            && ! $this->intentRouter->hasExplicitCatalogIntent($message)
            && empty($constraints['hotel_id']) && empty($constraints['hotel_name'])
            && empty($constraints['room_id']) && empty($constraints['room_name']))
            || ($intent === IntentRouter::GENERAL_TALK && ! $this->isGeneralKnowledgeQuery($message))
        ) {
            if ($intent === IntentRouter::GENERAL_TALK) {
                $constraints = $this->intentRouter->extractConstraints($message);
                $constraints = $this->resolveConversationalDestination($constraints, $session);
                $constraints = $this->resolveDefaultDestination($constraints, $user);
                $constraints = $this->inheritConversationalConstraints($message, $constraints, $session);
            }
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
            IntentRouter::SUPPORT_AGENT => $this->handleSupportAgentRequest(),
            IntentRouter::LEGAL_QUERY => $this->handleLegalQuery($message),
            default => $this->handleGeneralChat($message, $session),
        };

        $text = $reply['reply'] ?? 'Sorry, I could not process that request. Please try again.';
        $this->conversation->persist($session, 'bot', $text, $reply);

        return array_merge($aiBase, ['reply' => $text], $reply);
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

        // A message naming a catalog entity absent from the previous cards
        // ("how about banana boat" after Scuba Diving) is a fresh search,
        // not Q&A over old cards — even with follow-up phrasing.
        if ($this->namesNewEntity($query, $lastBot)) {
            return false;
        }

        $referential = '/\b(it|its|they|them|their|his|her|those|these|this one|that one|the one|which one|the first|the second|the other)\b/';
        $continuation = '/^(how much|how many|how about|what about|what is|what are|what\'s|and what|what else|and how|tell me more|more info|more details|more options|why|is it|are they|does it|do they|can you|give me the|whose|price of|prices of|cost of)/';

        return (bool) (preg_match($referential, $lower) || preg_match($continuation, $lower));
    }

    /**
     * True when the message names a catalog entity (activity/package/hotel)
     * absent from the previous turn's cards. Such messages start a fresh
     * search instead of follow-up Q&A over stale cards.
     */
    protected function namesNewEntity(string $query, ChatMessage $lastBot): bool
    {
        $data = $lastBot->context_data ?: [];
        $prev = [];
        foreach (($data['retrieved_activities'] ?? []) as $a) {
            $prev[] = mb_strtolower(trim((string) ($a['activity_name'] ?? '')));
        }
        foreach (($data['retrieved_packages'] ?? []) as $p) {
            $prev[] = mb_strtolower(trim((string) ($p['name'] ?? '')));
        }
        foreach (($data['retrieved_hotels'] ?? []) as $h) {
            $prev[] = mb_strtolower(trim((string) ($h['hotel_name'] ?? '')));
        }
        foreach (($data['retrieved_rooms'] ?? []) as $r) {
            $prev[] = mb_strtolower(trim((string) ($r['room_name'] ?? '')));
            $prev[] = mb_strtolower(trim((string) ($r['hotel_name'] ?? '')));
        }
        $prev = array_filter($prev);

        foreach ([
            $this->intentRouter->extractActivityName($query),
            $this->intentRouter->extractPackageName($query),
            $this->intentRouter->extractHotelName($query),
        ] as $named) {
            if ($named && ! in_array(mb_strtolower(trim($named)), $prev, true)) {
                return true;
            }
        }

        // Combo/new-topic queries ("atv and zipline combo, is it offered?")
        // name no single entity but carry a catalog keyword with zero overlap
        // with the previous cards — also a fresh search, not Q&A over them.
        if ($this->intentRouter->hasNounCatalogIntent($query)) {
            $lower = mb_strtolower($query);
            foreach ($prev as $name) {
                foreach (preg_split('/\s+/', $name) as $t) {
                    $t = trim((string) $t);
                    if (strlen($t) >= 4 && str_contains($lower, $t)) {
                        return false;
                    }
                }
            }

            return true;
        }

        return false;
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
        $lastBot = $session->messages()->where('sender', 'bot')->latest('id')->first();
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
                $blocks[] = "- Room: {$r['room_name']} at {$r['hotel_name']} — {$price}/night — {$occupancyText} (inventory count, not live availability)";
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

    public const REPETITION_THRESHOLD = 5;

    public const REPETITION_WINDOW = 10;

    /**
     * RSC action: title-defense repetition guard. The same normalized question
     * REPETITION_THRESHOLD times inside the trailing user-message window means
     * a bot hammering one query: guests get a temporary IP ban (enforced
     * globally by CheckIpBanned), authed users get a logged abuse report.
     * Kill-switch env CHATBOT_REPETITION_BAN_ENABLED (default off); localhost
     * is exempt unless CHATBOT_REPETITION_BAN_ALLOW_LOCAL is true (demo use).
     * Duration via CHATBOT_REPETITION_BAN_SECONDS (default 600). Active
     * human-support sessions are never punished.
     */
    public function checkRepetition(ChatSession $session, ?User $user, string $message, ?string $ip): ?array
    {
        if (! env('CHATBOT_REPETITION_BAN_ENABLED', false)) {
            return null;
        }
        if ($ip && in_array($ip, ['127.0.0.1', '::1'], true) && ! env('CHATBOT_REPETITION_BAN_ALLOW_LOCAL', false)) {
            return null;
        }

        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $message)));
        if ($normalized === '') {
            return null;
        }

        $handoffActive = SupportInquiry::where('chat_session_id', $session->id)
            ->whereIn('status', [SupportInquiry::STATUS_PENDING, SupportInquiry::STATUS_HUMAN_ACTIVE])
            ->exists();
        if ($handoffActive) {
            return null;
        }

        $priorMatches = $session->messages()
            ->where('sender', 'user')
            ->latest('id')
            ->limit(self::REPETITION_WINDOW)
            ->pluck('message')
            ->filter(fn ($m) => mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $m))) === $normalized)
            ->count();

        if ($priorMatches < self::REPETITION_THRESHOLD - 1) {
            return null;
        }

        if ($user) {
            ChatbotAbuseReport::create([
                'user_id' => $user->id,
                'message' => $message,
                'category' => 'Spam/Repetition',
                'reason' => 'Same question repeated '.self::REPETITION_THRESHOLD.'+ times in one session.',
                'status' => 'pending',
            ]);
            $user->increment('chatbot_flag_count');

            return [
                'blocked' => true,
                'response' => 'Your message contains content that violates our community guidelines. This incident has been logged for administrator review.',
            ];
        }

        if (! $ip) {
            return null;
        }
        if (! IpBan::isBanned($ip)) {
            IpBan::create([
                'ip_address' => $ip,
                'ban_level' => 'temporary',
                'reason' => 'Repeated identical chatbot messages (spam protection).',
                'banned_at' => now(),
                'expires_at' => now()->addSeconds((int) env('CHATBOT_REPETITION_BAN_SECONDS', 600)),
            ]);
        }
        Log::warning('Chatbot repetition IP ban', ['ip' => $ip, 'session_id' => $session->id]);

        return [
            'blocked' => true,
            'response' => 'You have been temporarily blocked from chatting due to repeated messages. Please try again later or contact SUNNYTRIPS TRAVEL SERVICES at 09682447153.',
        ];
    }

    // ────────────────────────────────────────────────
    //  Intent Handlers
    // ────────────────────────────────────────────────

    protected function handleRoomSearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);
        $scored = null;
        // Objective-first: an explicit cheapest/most-expensive ask is answered
        // from price-ordered DB rows, never from a top-5 semantic slice.
        $priceIntent = $this->detectPriceIntent($query);
        if ($priceIntent) {
            $scored = $this->cheapestRoomsFirst($constraints, $priceIntent);
        }
        if ($scored === null && $this->isPersonalized($user)) {
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
                    $roomsQuery->where('base_price', '<=', (float) $constraints['max_price'] * 1.10);
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

        if ($priceIntent) {
            $scored = $this->sortRoomsByPrice($scored, $priceIntent, $constraints['pax'] ?? 2);
        }

        foreach ($scored as &$entry) {
            $entry['check_in_date'] = $constraints['check_in_date'] ?? null;
            $entry['check_out_date'] = $constraints['check_out_date'] ?? null;
            $entry['pax'] = $constraints['pax'] ?? null;
        }
        unset($entry);
        $ordering = $this->resultOrdering($priceIntent, $scored, $constraints, 'room_name', 'room_id');
        $fieldIntent = $constraints['field_intent'] ?? $this->intentRouter->detectFieldIntent($query);
        $context = $this->gemini->getRoomContext($scored, $ordering);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('rooms', $constraints)];
        }

        // Pax-aware extra-person hint for queries like "for 3 pax" or "additional per head"
        $pax = $constraints['pax'] ?? null;
        $comboGroups = [];
        foreach ($scored as $entry) {
            if (! empty($entry['combo_group'])) {
                $comboGroups[$entry['combo_group']]['rooms'][] = $entry;
                $comboGroups[$entry['combo_group']]['total'] = $entry['combo_total'] ?? null;
            }
        }
        if (! empty($comboGroups)) {
            $comboLines = [];
            foreach ($comboGroups as $gi => $group) {
                $parts = [];
                foreach ($group['rooms'] as $entry) {
                    /** @var RoomType $room */
                    $room = $entry['item'];
                    $roomPax = (int) ($entry['combo_pax'] ?? 0);
                    $parts[] = "{$room->room_name} at {$room->hotel->hotel_name} ({$roomPax} pax, ₱".number_format($room->calculateNightlyRate($roomPax), 2).'/night)';
                }
                $comboLines[] = 'Option '.$gi.': '.implode(' + ', $parts).' = ₱'.number_format((float) ($group['total'] ?? 0), 2).'/night combined';
            }
            $context .= "\n\n--- GROUP SPLIT (no single room fits {$pax} pax — trust these pairings and totals) ---\n".implode("\n", $comboLines)."\n---\n";
        }
        if ($pax) {
            $hint = '';
            foreach ($scored as $entry) {
                if (! empty($entry['combo_group'])) {
                    continue;
                }
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
        $prompt = $this->buildPrompt('room-search', $context, $query, $user, $this->promptOptions($ordering, $scored, $constraints, $fieldIntent, 'room_name', 'room_id'));
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);
        if ($lead = $this->fieldLead($fieldIntent, $scored)) {
            $reply = $lead."\n\n".$reply;
        }
        if ($notice = $this->budgetNotice($scored, $constraints)) {
            $reply = $notice."\n\n".$reply;
        }
        // Visible filter badge when destination was inherited for a bare amenity refinement
        $hadExplicitDest = (bool) $this->intentRouter->extractDestinationName($query);
        if (! $hadExplicitDest && ! empty($constraints['destination_name'])) {
            $lastBotBadge = $session->messages()->where('sender', 'bot')->latest('id')->first();
            if ($lastBotBadge && $this->isFilterRefinementQuery($query, $lastBotBadge)) {
                $reply = "Filtered for **{$constraints['destination_name']}**: {$query}\n\n".$reply;
            }
        }

        return [
            'reply' => $reply,
            'retrieved_rooms' => $this->formatRoomResults($scored),
            'result_ordering' => $ordering,
        ];
    }

    protected function handleAddOnSearch(string $query, array $constraints, ?User $user, ChatSession $session): array
    {
        $constraints = $this->resolveConversationalDestination($constraints, $session);
        $constraints = $this->resolveDefaultDestination($constraints, $user);
        $scored = $this->gemini->searchAddOns($query, 5, $constraints['destination_id'] ?? null, $constraints);
        $context = $this->gemini->getAddOnContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('add-ons', $constraints)];
        }

        $context = $this->gemini->extractPricingContext($context, $query);
        $prompt = $this->buildPrompt('addon-search', $context, $query, $user);
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);
        if ($notice = $this->budgetNotice($scored, $constraints)) {
            $reply = $notice."\n\n".$reply;
        }

        return [
            'reply' => $reply,
            'retrieved_addons' => array_map(fn ($e) => [
                'id' => $e['item']->id,
                'name' => $e['item']->name,
                'type' => $e['item']->type,
                'destination' => $e['item']->destination?->name ?? null,
                'similarity_score' => round($e['score'], 4),
                'over_budget' => ! empty($e['over_budget']),
                'over_by' => isset($e['over_by']) ? round((float) $e['over_by'], 2) : null,
                'fallback' => ! empty($e['fallback']),
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
        $priceIntent = $this->detectPriceIntent($query);

        // Exact-match shortcut: if a specific hotel name is extracted, return just that hotel.
        if (! empty($constraints['hotel_name'])) {
            $hotel = HotelModel::with('destination')
                ->where('hotel_name', 'ILIKE', $constraints['hotel_name'])
                ->first();
            if ($hotel) {
                $constraints['hotel_id'] = $hotel->id;
                $scored = [['item' => $hotel, 'score' => 1.0]];
            }
        }

        if ($scored === null) {
            if ($priceIntent) {
                $scored = $this->cheapestHotelsFirst($constraints, $priceIntent, $limit);
            } elseif ($this->isPersonalized($user)) {
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
                $scored = $this->gemini->searchHotels($query, $limit, $constraints['hotel_id'] ?? null, $constraints['destination_id'] ?? null, $constraints);
            }
        }

        $priceIntent = $this->detectPriceIntent($query);
        if ($priceIntent) {
            $scored = $this->sortHotelsByPrice($scored, $priceIntent);
        }

        $ordering = $this->resultOrdering($priceIntent, $scored, $constraints, 'hotel_name', 'hotel_id');
        $fieldIntent = $constraints['field_intent'] ?? $this->intentRouter->detectFieldIntent($query);
        $context = $this->gemini->getHotelContext($scored, $ordering);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('hotels', $constraints)];
        }

        $prompt = $this->buildPrompt('hotel-search', $context, $query, $user, $this->promptOptions($ordering, $scored, $constraints, $fieldIntent, 'hotel_name', 'hotel_id'));
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);
        if ($lead = $this->fieldLead($fieldIntent, $scored)) {
            $reply = $lead."\n\n".$reply;
        }
        if ($notice = $this->budgetNotice($scored, $constraints)) {
            $reply = $notice."\n\n".$reply;
        }
        $hadExplicitDest = (bool) $this->intentRouter->extractDestinationName($query);
        if (! $hadExplicitDest && ! empty($constraints['destination_name'])) {
            $lastBotBadge = $session->messages()->where('sender', 'bot')->latest('id')->first();
            if ($lastBotBadge && $this->isFilterRefinementQuery($query, $lastBotBadge)) {
                $reply = "Filtered for **{$constraints['destination_name']}**: {$query}\n\n".$reply;
            }
        }

        return [
            'reply' => $reply,
            'retrieved_hotels' => $this->formatHotelResults($scored),
            'result_ordering' => $ordering,
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

    /**
     * Objective-first room retrieval for explicit cheapest/most-expensive
     * asks: price-ordered DB rows over the full scoped pool (no semantic
     * top-N truncation, no relevance floor, no personalization blend).
     * Vectors survive only as the price-tie breaker inside sortRoomsByPrice.
     */
    protected function cheapestRoomsFirst(array $constraints, string $direction, int $limit = 5): array
    {
        $pax = ! empty($constraints['pax']) ? (int) $constraints['pax'] : null;
        $maxPrice = ! empty($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $rooms = RoomType::with('hotel.destination')
            ->where('is_shown', true)
            ->when(! empty($constraints['destination_id']), fn ($q) => $q->whereHas('hotel', fn ($qq) => $qq->where('destination_id', $constraints['destination_id'])))
            ->when(! empty($constraints['hotel_id']), fn ($q) => $q->where('hotel_id', $constraints['hotel_id']))
            ->when($pax, fn ($q) => $q->whereRaw('COALESCE(max_occupancy, base_occupancy, 2) >= ?', [$pax]))
            ->when(! empty($constraints['room_id']), fn ($q) => $q->where('id', $constraints['room_id']))
            ->when(empty($constraints['room_id']) && ! empty($constraints['room_name']), fn ($q) => $q->where('room_name', 'ILIKE', $constraints['room_name']))
            ->orderBy('base_price', $direction === 'expensive' ? 'desc' : 'asc')
            ->limit(max($limit * 10, 50))
            ->get();
        $scored = $rooms->map(fn ($r) => ['item' => $r, 'score' => 0.0])->all();
        if ($maxPrice !== null && ! empty($scored)) {
            $scored = $this->applyBudgetOrCheapest($scored, fn ($r) => $this->gemini->roomPriceForPax($r, $pax), $maxPrice, $limit);
        }
        $scored = $this->sortRoomsByPrice($scored, $direction, $constraints['pax'] ?? 2);

        return array_slice(array_values($scored), 0, $limit);
    }

    /**
     * Objective-first hotel retrieval: full scoped pool sorted by each
     * hotel's cheapest shown room rate.
     */
    protected function cheapestHotelsFirst(array $constraints, string $direction, int $limit): array
    {
        $maxPrice = ! empty($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $hotels = HotelModel::with('destination')
            ->where('is_shown', true)
            ->when(! empty($constraints['hotel_id']), fn ($q) => $q->where('id', $constraints['hotel_id']))
            ->when(! empty($constraints['destination_id']), fn ($q) => $q->where('destination_id', $constraints['destination_id']))
            ->limit(max($limit * 10, 50))
            ->get();
        $scored = $hotels->map(fn ($h) => ['item' => $h, 'score' => 0.0])->all();
        if ($maxPrice !== null && ! empty($scored)) {
            $scored = $this->applyBudgetOrCheapest($scored, fn ($h) => $this->gemini->hotelMinPrice($h) ?? 0, $maxPrice, $limit);
        }
        $scored = $this->sortHotelsByPrice($scored, $direction);

        return array_slice(array_values($scored), 0, $limit);
    }

    /**
     * Objective-first activity retrieval: full scoped pool sorted by numeric
     * rate (caller slices to display size).
     */
    protected function cheapestActivitiesFirst(array $constraints, string $direction): array
    {
        $maxPrice = ! empty($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $pax = ! empty($constraints['pax']) ? (int) $constraints['pax'] : null;
        $activities = ActivityModel::with('destination')
            ->where('is_shown', true)
            ->when(! empty($constraints['destination_id']), fn ($q) => $q->where('destination_id', $constraints['destination_id']))
            ->limit(50)
            ->get();
        $scored = $activities->map(fn ($a) => ['item' => $a, 'score' => 0.0])->all();
        if ($maxPrice !== null && ! empty($scored)) {
            $paxForPrice = $pax ?? 1;
            $scored = $this->applyBudgetOrCheapest($scored, fn ($a) => $this->gemini->activityPriceForPax($a, $paxForPrice), $maxPrice, 3);
        }

        return $this->sortActivitiesByPrice($scored, $direction);
    }

    /**
     * Budget split shared by the objective-first paths: in-budget first,
     * then up-to-2 slight-overflow options; cheapest overall when nothing
     * fits (flagged as fallback downstream by budgetNotice).
     */
    protected function applyBudgetOrCheapest(array $scored, callable $priceFor, float $maxPrice, int $limit): array
    {
        [$inBudget, $overflow] = $this->gemini->splitBudgetOverflow($scored, $priceFor, $maxPrice, GeminiService::OVER_BUDGET_MAX);
        $merged = array_merge($inBudget, $overflow);
        if (empty($merged) && ! empty($scored)) {
            return $this->gemini->cheapestFallback($scored, $priceFor, min(3, $limit));
        }

        return $merged;
    }

    /**
     * Objective-accurate ordering tag for contexts and prompts:
     * price-asc/price-desc for explicit price ranking, exact for a single
     * named-entity hit, null (semantic) otherwise.
     */
    protected function resultOrdering(?string $priceIntent, array $scored, array $constraints, string $nameKey, string $idKey): ?string
    {
        if ($priceIntent) {
            return $priceIntent === 'expensive' ? 'price-desc' : 'price-asc';
        }
        if (count($scored) === 1 && (! empty($constraints[$nameKey]) || ! empty($constraints[$idKey]))) {
            return 'exact';
        }

        return null;
    }

    /**
     * Prompt options shared by the search handlers: ordering-aware rank
     * rules, field-first answering, and a deterministic INTERPRETATION
     * block the LLM must not reinterpret.
     */
    protected function promptOptions(?string $ordering, array $scored, array $constraints, ?string $fieldIntent, string $nameKey, string $idKey): array
    {
        $options = [
            'ordering' => $ordering,
            'result_count' => count($scored),
            'field' => $fieldIntent,
        ];
        if ($fieldIntent && count($scored) === 1) {
            $entity = $constraints[$nameKey] ?? null;
            if (! $entity && ! empty($constraints[$idKey])) {
                $entity = '#'.$constraints[$idKey];
            }
            if ($entity) {
                $options['interpretation'] = "entity: {$entity} (exact match) | question: {$fieldIntent} | answerable: yes — quote the {$fieldIntent} field first";
            }
        } elseif ($fieldIntent && count($scored) > 1) {
            // Ambiguous field question over several results: name every
            // entity so the LLM answers each one's field, not a generic list.
            $names = [];
            foreach (array_slice($scored, 0, 3) as $entry) {
                $names[] = $this->fieldItemName($entry['item']);
            }
            $options['interpretation'] = 'entities: '.implode(', ', $names)." | question: {$fieldIntent} | answerable: yes — state each entity's {$fieldIntent} first";
        }

        return $options;
    }

    /**
     * Deterministic field-first lead built verbatim from the database, so an
     * exact field question is answered from stored values even when the LLM
     * ignores the quote-the-field instruction. Null when the model has no
     * value for the field — then the prompt-only behavior stands.
     */
    protected function fieldLead(?string $field, array $scored): ?string
    {
        if (! $field || $scored === []) {
            return null;
        }
        if (count($scored) === 1) {
            $value = $this->fieldValue($field, $scored[0]['item']);
            if ($value === null) {
                return null;
            }

            return $this->fieldItemName($scored[0]['item']).' '.$this->fieldVerb($field).': '.$value;
        }
        $lines = [];
        foreach ($scored as $entry) {
            $value = $this->fieldValue($field, $entry['item']);
            if ($value === null) {
                continue;
            }
            $lines[] = '- '.$this->fieldItemName($entry['item']).': '.$value;
        }
        if ($lines === []) {
            return null;
        }
        $noun = $field === 'price' ? 'prices' : $field;

        return "Here are the {$noun} for each matching result:\n".implode("\n", $lines);
    }

    protected function fieldVerb(string $field): string
    {
        return match ($field) {
            'inclusions' => 'includes',
            'exclusions' => 'excludes',
            'price' => 'costs',
            'duration' => 'lasts',
            'capacity' => 'fits',
            'requirements' => 'requires',
            'location' => 'is located in',
            'itinerary' => 'itinerary:',
            default => 'details:',
        };
    }

    protected function fieldValue(string $field, mixed $item): ?string
    {
        $value = match (true) {
            $item instanceof ActivityModel => match ($field) {
                'inclusions' => $this->joinList($item->inclusions),
                'exclusions' => $this->joinList($item->exclusions),
                'price' => $this->clean($item->rate),
                'duration' => $this->clean($item->duration),
                'capacity' => $this->clean($item->capacity),
                'requirements' => $this->clean($item->requirements),
                'location' => $this->clean($item->destination?->name),
                'itinerary' => $this->joinList($item->itinerary, '; '),
                default => null,
            },
            $item instanceof RoomType => match ($field) {
                'price' => $item->base_price !== null ? '₱'.number_format((float) $item->base_price, 2).' per night' : null,
                'capacity' => $item->max_occupancy ? ((int) $item->max_occupancy).' pax max' : null,
                'location' => $this->clean($item->hotel?->hotel_name),
                default => null,
            },
            $item instanceof HotelModel => match ($field) {
                'location' => $this->clean($item->destination?->name),
                default => null,
            },
            default => null,
        };

        return $value !== null && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    protected function fieldItemName(mixed $item): string
    {
        return match (true) {
            $item instanceof ActivityModel => (string) $item->activity_name,
            $item instanceof RoomType => (string) $item->room_name,
            $item instanceof HotelModel => (string) $item->hotel_name,
            default => 'This result',
        };
    }

    protected function joinList(mixed $value, string $separator = ', '): ?string
    {
        if (is_string($value)) {
            return $this->clean($value);
        }
        if (! is_array($value)) {
            return null;
        }
        $parts = array_filter(array_map(fn ($v) => trim((string) $v), $value));

        return $parts === [] ? null : implode($separator, $parts);
    }

    protected function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
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
            $state = $session->metadata['retrieval_state'] ?? [];
            if (! empty($state['destination_id'])) {
                $constraints['destination_id'] = (int) $state['destination_id'];
                $constraints['destination_name'] = $state['destination_name'] ?? null;

                return $constraints;
            }

            // Last explicit destination from prior bot cards or user messages (last 6 turns)
            $lastBot = $session->messages()->where('sender', 'bot')->latest('id')->first();
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

    /**
     * Detect a short availability follow-up that refers to a specific room
     * from the prior turn (e.g. "is the room available?", "is it still open?",
     * "available pa rin ba?"). Only matches when the last bot turn had exactly
     * one room card so we never steal broad "what rooms are available" queries.
     */
    protected function isExactRoomAvailabilityFollowUp(string $message, ChatMessage $lastBot): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }
        if (count(preg_split('/\s+/', $lower)) > 10) {
            return false;
        }

        $data = $lastBot->context_data ?: [];
        $rooms = $data['retrieved_rooms'] ?? [];
        if (count($rooms) !== 1) {
            return false;
        }

        $patterns = [
            '/\b(is|are)\b.*\b(the|this|that|it)\b.*\b(room|rooms)\b.*\b(available|open|free|vacant|book|booked)\b/i',
            '/\b(available|open|free|vacant)\b.*\b(pa rin|pang|ngayon|pa)\b/i',
            '/\b(availa\w*|bakante)\b/i',
            '/\b(still open|still available|can (we|i) (book|reserve))\b/i',
            // Date-bearing follow-up after an exact room turn (e.g. "check sep 8-9 for 2 pax")
            '/\b(check|verify|confirm)\b.*\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|sept|month|tomorrow|next|this)\b/i',
            '/\b\d{1,2}\s*(?:-|to|–)\s*\d{1,2}\b/i',
            '/\bcheck\s+it\b/i',
            '/\byes\b.*\bcheck\b/i',
            '/^\s*check\s*\.?$/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect a "check alternatives" follow-up after an unavailable exact room
     * turn. Triggered by the suggested pill click or a typed message like
     * "check alternatives", "show me other rooms", "any other room for [name]?".
     */
    protected function isCheckAlternativesFollowUp(string $message, ChatMessage $lastBot): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }
        if (count(preg_split('/\s+/', $lower)) > 12) {
            return false;
        }

        $data = $lastBot->context_data ?: [];
        if (empty($data['retrieved_rooms'])) {
            return false;
        }
        $hasUnavailableExactRoom = false;
        foreach ($data['retrieved_rooms'] as $r) {
            $remaining = $r['remaining'] ?? null;
            $total = $r['total_rooms'] ?? null;
            if ($remaining !== null && $total !== null && (int) $remaining <= 0) {
                $hasUnavailableExactRoom = true;
                break;
            }
        }
        if (! $hasUnavailableExactRoom) {
            return false;
        }

        $patterns = [
            '/\bcheck\s+alternatives?\b/i',
            '/\bshow\s+(me\s+)?(other|alternative)\b/i',
            '/\b(any|other|different)\s+(rooms?|options?)\b/i',
            '/\balternatives?\b/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inherit the exact room (and its dates/pax) from the last bot turn when the
     * user follows up on a specific room without re-naming it. This keeps
     * availability questions scoped to that room instead of broadening back to
     * the whole hotel or destination.
     */
    protected function inheritRoomContext(array $constraints, ChatSession $session, ?int $roomId = null): array
    {
        $lastBot = $session->messages()->where('sender', 'bot')->latest('id')->first();
        $data = $lastBot?->context_data ?: [];
        $prior = null;
        foreach ($data['retrieved_rooms'] ?? [] as $r) {
            if ($roomId === null || (int) ($r['id'] ?? 0) === (int) $roomId) {
                $prior = $r;
                break;
            }
        }
        if (! $prior) {
            return $constraints;
        }

        if (empty($constraints['room_id'])) {
            $constraints['room_id'] = (int) ($prior['id'] ?? 0) ?: null;
        }
        if (empty($constraints['room_name'])) {
            $constraints['room_name'] = $prior['room_name'] ?? null;
        }
        if (! empty($prior['hotel_id']) && empty($constraints['hotel_id'])) {
            $constraints['hotel_id'] = (int) $prior['hotel_id'];
        }
        if (! empty($prior['hotel_name']) && empty($constraints['hotel_name'])) {
            $constraints['hotel_name'] = $prior['hotel_name'];
        }
        if (! empty($prior['destination']) && empty($constraints['destination_id'])) {
            $destId = DestinationModel::where('name', 'ILIKE', $prior['destination'])->value('id');
            if ($destId) {
                $constraints['destination_id'] = (int) $destId;
                $constraints['destination_name'] = $prior['destination'];
            }
        }
        if (empty($constraints['check_in_date']) && ! empty($prior['check_in_date'])) {
            $constraints['check_in_date'] = $prior['check_in_date'];
        }
        if (empty($constraints['check_out_date']) && ! empty($prior['check_out_date'])) {
            $constraints['check_out_date'] = $prior['check_out_date'];
        }
        if (empty($constraints['pax']) && ! empty($prior['pax'])) {
            $constraints['pax'] = (int) $prior['pax'];
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
     * Carry pax/budget/dates/destination across follow-up turns ("may pool
     * ba yun?" after "Boracay under 5000 for 5 pax") so refinements re-search
     * with the prior scope instead of dropping it. Explicit values in the
     * current message always win; a fresh topic resets stored state so
     * nothing leaks across conversations (e.g. Boracay → El Nido switch).
     */
    protected function inheritConversationalConstraints(string $message, array $constraints, ChatSession $session): array
    {
        $keys = ['pax', 'max_price', 'destination_id', 'destination_name', 'check_in_date', 'check_out_date', 'nights'];
        try {
            $lastBot = $session->messages()->where('sender', 'bot')->latest('id')->first();
            $isContinuation = $lastBot && ($this->isFollowUpQuery($message, $lastBot)
                || $this->isFilterRefinementQuery($message, $lastBot)
                || $this->isExactRoomAvailabilityFollowUp($message, $lastBot)
                || $this->isCheckAlternativesFollowUp($message, $lastBot)
                || $this->isAffirmativeSearchQuery($message, $lastBot));

            $metadata = $session->metadata ?? [];
            $state = $metadata['constraint_state'] ?? [];

            if ($isContinuation) {
                foreach ($keys as $key) {
                    if (empty($constraints[$key]) && ! empty($state[$key])) {
                        $constraints[$key] = $state[$key];
                    }
                }
            }

            $newState = [];
            foreach ($keys as $key) {
                if (! empty($constraints[$key])) {
                    $newState[$key] = $constraints[$key];
                }
            }
            $metadata['constraint_state'] = $newState;
            $session->update(['metadata' => $metadata]);
        } catch (\Throwable $e) {
            Log::debug('inheritConversationalConstraints failed: '.$e->getMessage());
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
        // A bare "yes"/"ok"/"search" after a results turn repeats that turn's
        // search (intent resolved by resolveAffirmativeIntent from the previous
        // user message and stored cards), whatever the catalog was.
        $hadResults = ! empty($data['retrieved_activities']) || ! empty($data['retrieved_packages'])
            || ! empty($data['retrieved_hotels']) || ! empty($data['retrieved_rooms'])
            || ! empty($data['retrieved_addons']);
        if ($hadResults && preg_match('/\b(yes|yep|ok|search)\b/i', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * True when an affirmative message carries its own searchable content
     * (a destination or named entity), so it must be searched as-is instead
     * of inheriting the previous user message.
     */
    protected function affirmationCarriesSearch(string $message): bool
    {
        if ($this->intentRouter->extractDestinationName($message)) {
            return true;
        }

        return (bool) ($this->intentRouter->extractActivityName($message)
            || $this->intentRouter->extractPackageName($message)
            || $this->intentRouter->extractRoomName($message)
            || $this->intentRouter->extractHotelName($message)
            || $this->intentRouter->extractAddOnName($message));
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

        // Named-entity fallback: "how about banana boat" carries no catalog
        // keyword, but names a DB entity — honor that over carried cards.
        if ($previousUserMessage) {
            if ($this->intentRouter->extractActivityName($previousUserMessage)) {
                return IntentRouter::ACTIVITY_SEARCH;
            }
            if ($this->intentRouter->extractPackageName($previousUserMessage)) {
                return IntentRouter::PACKAGE_SEARCH;
            }
            if ($this->intentRouter->extractRoomName($previousUserMessage)) {
                return IntentRouter::ROOM_SEARCH;
            }
            if ($this->intentRouter->extractHotelName($previousUserMessage)) {
                return IntentRouter::HOTEL_SEARCH;
            }
            if ($this->intentRouter->extractAddOnName($previousUserMessage)) {
                return IntentRouter::ADDON_SEARCH;
            }
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

        // Side-by-side comparison when the user names two activities.
        $comparisonNames = $this->activityComparisonNames($query);
        if (! empty($comparisonNames)) {
            return $this->handleActivityComparison($comparisonNames, $query, $user, $session);
        }

        $maxPrice = ! empty($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        $priceIntent = $this->detectPriceIntent($query);
        // Widen the vector window so budget filters and price sorts see the
        // whole candidate pool, not just the top 3 semantic hits.
        $window = ($maxPrice !== null || $priceIntent !== null) ? 50 : 3;
        $scored = null;

        // Exact-match shortcut: if a specific activity name is extracted, return just that activity.
        if (! empty($constraints['activity_name'])) {
            $activity = ActivityModel::with('destination')
                ->where('activity_name', 'ILIKE', $constraints['activity_name'])
                ->first();
            if ($activity) {
                $constraints['activity_id'] = $activity->id;
                $scored = [['item' => $activity, 'score' => 1.0]];
            }
        }

        if ($scored === null) {
            if ($priceIntent) {
                $scored = $this->cheapestActivitiesFirst($constraints, $priceIntent);
            } elseif ($this->isPersonalized($user)) {
                $blended = $this->blendedVector($user, $query);
                if ($blended) {
                    $candidates = ActivityModel::with('destination')
                        ->where('is_shown', true)
                        ->whereNotNull('embedding')
                        ->when(! empty($constraints['destination_id']), fn ($q) => $q->where('destination_id', $constraints['destination_id']))
                        ->get();
                    if ($candidates->isNotEmpty()) {
                        $scored = $this->gemini->rankRecommendations($blended, $candidates, $window);
                    }
                }
            }
            if ($scored === null) {
                $scored = $this->gemini->searchActivities($query, $window, $constraints['destination_id'] ?? null, $constraints);
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
                            $scored = $this->gemini->rankRecommendations($blended, $destCandidates, $window);
                        }
                    }
                }
            }
        }

        if ($priceIntent) {
            $scored = $this->sortActivitiesByPrice($scored, $priceIntent);
        }
        $scored = array_slice($scored, 0, 3);
        $priceNotice = $this->budgetNotice($scored, $constraints);

        $ordering = $this->resultOrdering($priceIntent, $scored, $constraints, 'activity_name', 'activity_id');
        $fieldIntent = $constraints['field_intent'] ?? $this->intentRouter->detectFieldIntent($query);
        $context = $this->gemini->getActivityContext($scored, $ordering);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('activities', $constraints)];
        }

        $prompt = $this->buildPrompt('activity-search', $context, $query, $user, $this->promptOptions($ordering, $scored, $constraints, $fieldIntent, 'activity_name', 'activity_id'));
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);
        if ($lead = $this->fieldLead($fieldIntent, $scored)) {
            $reply = $lead."\n\n".$reply;
        }
        if ($priceNotice) {
            $reply = $priceNotice."\n\n".$reply;
        }

        return [
            'reply' => $reply,
            'retrieved_activities' => $this->formatActivityResults($scored),
            'result_ordering' => $ordering,
        ];
    }

    /**
     * Names of two or more activities the user is explicitly comparing.
     *
     * @return string[]
     */
    protected function activityComparisonNames(string $query): array
    {
        if (! preg_match('/\b(compare|comparison|vs\.?|versus|difference|differences|better)\b/i', $query)) {
            return [];
        }

        $names = array_values(array_unique($this->intentRouter->extractActivityNames($query)));

        return count($names) >= 2 ? $names : [];
    }

    /**
     * Compare two named activities side by side using real DB fields.
     *
     * @param  string[]  $names
     */
    protected function handleActivityComparison(array $names, string $query, ?User $user, ChatSession $session): array
    {
        $activities = ActivityModel::with('destination')
            ->whereIn('activity_name', $names)
            ->get()
            ->keyBy('activity_name');

        $scored = [];
        foreach ($names as $name) {
            $activity = $activities->get($name);
            if ($activity) {
                $scored[] = ['item' => $activity, 'score' => 1.0];
            }
        }

        if (count($scored) < 2) {
            $scored = $this->gemini->searchActivities($query, 3, null);
        }

        $context = $this->gemini->getActivityContext($scored);

        if (empty(trim($context))) {
            return ['reply' => $this->noResultsReply('activities')];
        }

        $prompt = $this->buildPrompt('activity-compare', $context, $query, $user);
        $reply = $this->geminiChatResponse($prompt, $session);

        return [
            'reply' => $reply,
            'retrieved_activities' => $this->formatActivityResults($scored),
        ];
    }

    /**
     * Numeric rate for an activity (min of a range, per-person/unit basis).
     */
    protected function activityPrice(ActivityModel $activity): float
    {
        return $activity->calculateRateForPax(1);
    }

    /**
     * Sort scored activities by numeric price.
     */
    protected function sortActivitiesByPrice(array $scored, string $direction): array
    {
        usort($scored, function ($a, $b) use ($direction) {
            $pa = $this->activityPrice($a['item']);
            $pb = $this->activityPrice($b['item']);

            if ($pa === $pb) {
                return $b['score'] <=> $a['score'];
            }

            return $direction === 'expensive' ? $pb <=> $pa : $pa <=> $pb;
        });

        return array_values($scored);
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
                $scored = $this->gemini->searchPackages($query, 5, $destinationId, $constraints);
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
            return ['reply' => $this->noResultsReply('packages', $constraints)];
        }

        $context = $this->gemini->extractPricingContext($context, $query);
        $prompt = $this->buildPrompt('package-search', $context, $query, $user);
        $reply = $this->stripRankLineIfSingle($this->geminiChatResponse($prompt, $session), $scored);
        if ($notice = $this->budgetNotice($scored, $constraints)) {
            $reply = $notice."\n\n".$reply;
        }

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
        // Merge previous hotel/destination/room when follow-up has no explicit hotel (e.g., "yes check availabilith" after Lazy Dog)
        $lastBot = $session->messages()->where('sender', 'bot')->latest('id')->first();
        $data = $lastBot?->context_data ?: [];
        if (empty($constraints['hotel_id']) && empty($constraints['room_id']) && empty($constraints['room_name'])) {
            $prevRoomId = $data['retrieved_rooms'][0]['id'] ?? null;
            if ($prevRoomId) {
                $constraints = $this->inheritRoomContext($constraints, $session, (int) $prevRoomId);
            }
        }
        if (empty($constraints['hotel_id']) && empty($constraints['room_id'])) {
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

        $hasExactRoom = ! empty($constraints['room_name']) || ! empty($constraints['room_id']);

        if (empty($exactAvailable)) {
            // Exact-room follow-up: scope the reply to just that room and offer alternatives via pill (no auto-list).
            if ($hasExactRoom) {
                $exactEntry = null;
                foreach ($rooms as $entry) {
                    /** @var RoomType $room */
                    $room = $entry['item'];
                    $matches = false;
                    if (! empty($constraints['room_id']) && (int) $room->id === (int) $constraints['room_id']) {
                        $matches = true;
                    } elseif (! empty($constraints['room_name']) && strcasecmp((string) $room->room_name, (string) $constraints['room_name']) === 0) {
                        $matches = true;
                    }
                    if ($matches) {
                        $exactEntry = $entry;
                        $exactEntry['availability'] = $this->availability->check($room, $checkIn, $checkOut);
                        $unitRate = $room->calculateNightlyRate($pax);
                        $exactEntry['total_stay'] = round($unitRate * $nights, 2);
                        $exactEntry['formatted_total'] = '₱'.number_format($exactEntry['total_stay'], 2);
                        break;
                    }
                }

                if ($exactEntry) {
                    $room = $exactEntry['item'];
                    $reply = "{$room->room_name} at {$room->hotel?->hotel_name} is not available from {$checkIn->format('M d')} to {$checkOut->format('M d')}. Would you like me to check alternative rooms for those dates?";
                    $formatted = [[
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
                        'check_in_date' => $checkIn->format('Y-m-d'),
                        'check_out_date' => $checkOut->format('Y-m-d'),
                        'pax' => $pax,
                        'remaining' => $exactEntry['availability']['remaining'] ?? null,
                        'total_rooms' => $exactEntry['availability']['total_rooms'] ?? null,
                    ]];

                    return [
                        'reply' => $reply,
                        'retrieved_rooms' => $formatted,
                        'suggested_actions' => [[
                            'id' => 'check-alternatives',
                            'label' => 'Check alternatives',
                            'prompt' => "Check alternatives for {$room->room_name} at {$room->hotel?->hotel_name}",
                        ]],
                    ];
                }
            }

            $destName2 = $constraints['destination_name'] ?? 'your request';

            return [
                'reply' => "All rooms matching \"{$destName2}\" are fully booked from {$checkIn->format('M d')} to {$checkOut->format('M d')}. Would you like me to check different dates?",
            ];
        }

        // Two-phase: only when the exact room was requested but yielded NO
        // available match, supplement with same-hotel/destination alternatives.
        // When the exact room was found and is available, stay scoped to it.
        $available = $exactAvailable;
        if (! $hasExactRoom && count($available) < 5) {
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
                    'reply' => $place['type'] === 'activity'
                        ? $this->activityLocationReply($place, $coords)
                        : $this->hotelLocationReply($place, $coords),
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
        } elseif ($place['type'] === 'activity') {
            $activity = $place['model'] ?? ActivityModel::where('activity_name', 'ILIKE', $place['name'])->first();
            $lat = $activity?->latitude_with_fallback;
            $lng = $activity?->longitude_with_fallback;
            if ($lat !== null && $lng !== null) {
                return ['lat' => (float) $lat, 'lng' => (float) $lng];
            }
        }

        return null;
    }

    /**
     * Human reply for an activity location query, naming the host destination
     * when the coordinates come from the destination fallback.
     *
     * @param  array{name: string, type: string, model?: ActivityModel}  $place
     * @param  array{lat: float, lng: float}  $coords
     */
    protected function activityLocationReply(array $place, array $coords): string
    {
        $coordsLabel = "latitude {$coords['lat']}, longitude {$coords['lng']}";
        $activity = $place['model'] ?? null;
        $destination = $activity?->destination?->name;

        if ($activity?->specific_address) {
            return "{$place['name']} is located at {$activity->specific_address}.";
        }

        if ($activity && $activity->latitude === null && $destination) {
            return "{$place['name']} is located in {$destination} (around {$coordsLabel}).";
        }

        return $destination
            ? "{$place['name']} is located at {$coordsLabel}, in {$destination}."
            : "{$place['name']} is located at {$coordsLabel}.";
    }

    /**
     * Human reply for a hotel location query. Prefers the admin-typed
     * specific address; falls back to raw coordinates.
     *
     * @param  array{name: string, type: string, model?: HotelModel}  $place
     * @param  array{lat: float, lng: float}  $coords
     */
    protected function hotelLocationReply(array $place, array $coords): string
    {
        $hotel = $place['model'] ?? null;

        if ($hotel?->specific_address) {
            return "{$place['name']} is located at {$hotel->specific_address}.";
        }

        return "{$place['name']} is located at latitude {$coords['lat']}, longitude {$coords['lng']}.";
    }

    protected function handleWeatherQuery(string $query, array $constraints, ChatSession $session): array
    {
        $destinationName = $constraints['destination_name'] ?? null;

        if (! $destinationName) {
            $example = $this->exampleDestinations(1);

            return ['reply' => 'Which destination would you like the weather for? For example, "What\'s the weather in '.$example.' this weekend?"'];
        }

        /** @var DestinationModel|null $dest */
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
        $lastBot = $session->messages()->where('sender', 'bot')->latest('id')->first();
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

    /**
     * Off-topic, world-knowledge questions that must never be force-routed
     * into a catalog search just because they score above the semantic floor.
     */
    protected function isGeneralKnowledgeQuery(string $message): bool
    {
        return (bool) preg_match(
            '/\b(who|whom|whose|capital|president|prime\s+minister|history|historical|invented|invention|meaning|definition|translate|synonym|antonym|recipe|science|physics|chemistry|math|mathematics|calculate)\b/i',
            $message
        );
    }

    /**
     * The user asked for a human — point them at the handoff control and offer
     * a one-tap action that triggers it from inside the chat.
     */
    protected function handleSupportAgentRequest(): array
    {
        return [
            'reply' => "Of course — tap the button below and I'll connect you to a human agent from our team. You can also use **Talk to Admin** at the top of this chat at any time.\n\nWhile you wait, feel free to type your question here so the agent has the context when they join.",
            'suggested_actions' => [
                ['id' => 'talk-to-agent', 'label' => 'Talk to a human agent', 'handoff' => true],
            ],
        ];
    }

    /**
     * Deterministic legal/contact answers — canned text plus links to the
     * legal pages. No Gemini call, so nothing can be hallucinated.
     */
    protected function handleLegalQuery(string $message): array
    {
        $lower = mb_strtolower($message);

        $wantsPrivacy = (bool) preg_match('/\bprivacy\b/', $lower);
        $wantsTerms = (bool) preg_match('/\bterms\b|\bconditions\b/', $lower);
        $wantsAi = (bool) preg_match('/\bai\b|\bartificial\s+intelligence\b|\bdisclosure\b/', $lower);
        $wantsContact = (bool) preg_match('/\bcontact\b|\be-?mail\b|\bhotline\b|\btelephone\b|\bcellphone\b|\bphone\s+number\b|\bcontact\s+number\b|\baddress\b|\blocated\b|\blocation\b|\boffice\b|\breach\b/', $lower);

        if ($wantsContact) {
            return [
                'reply' => "You can reach SUNNYTRIPS TRAVEL SERVICES at sunnytrips01@gmail.com or 09682447153. Address: Pili, Camarines Sur.\n\nFor data requests, see our [Privacy Policy](/privacy-policy).",
                'legal' => ['topic' => 'contact'],
            ];
        }

        $docs = array_filter([$wantsPrivacy ? 'privacy' : null, $wantsTerms ? 'terms' : null, $wantsAi ? 'ai' : null]);

        if (count($docs) === 1) {
            return match (reset($docs)) {
                'privacy' => [
                    'reply' => 'We handle your bookings and inquiries under our Privacy Policy — what we collect (name, contact, ID/travel docs, payment for processing), how we use it (bookings, updates, compliance), and your rights (access, correct, delete where allowed). Read the full policy here: [Privacy Policy](/privacy-policy)',
                    'legal' => ['topic' => 'privacy'],
                ],
                'terms' => [
                    'reply' => 'Our Terms cover eligibility (18+), your account, AI-assisted recommendations, supplier bookings, payments/refunds, and conduct. Read them here: [Terms and Conditions](/terms-and-conditions)',
                    'legal' => ['topic' => 'terms'],
                ],
                default => [
                    'reply' => 'Our AI Disclosure explains what the assistant can do, what data it uses, and its limits — always verify prices and availability before booking. Read it here: [AI Usage Disclosure](/ai-disclosure)',
                    'legal' => ['topic' => 'ai_disclosure'],
                ],
            };
        }

        return [
            'reply' => 'I can share our [Terms and Conditions](/terms-and-conditions), [Privacy Policy](/privacy-policy), or [AI Usage Disclosure](/ai-disclosure) — which one would you like? For anything else, reach us at sunnytrips01@gmail.com / 09682447153.',
            'legal' => ['topic' => 'menu'],
        ];
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

    /**
     * Ordering-aware ranked-list rule: price-ordered sets must never be
     * described as AI best-match, and single exact hits get no rank
     * language at all.
     */
    protected function rankRule(array $options): string
    {
        $ordering = $options['ordering'] ?? null;
        $count = (int) ($options['result_count'] ?? 0);
        if ($ordering === 'price-asc' || $ordering === 'price-desc') {
            $asc = $ordering === 'price-asc';
            $extreme = $asc ? 'lowest price' : 'highest price';
            $heading = $asc ? 'Lowest Price' : 'Highest Price';
            $footnote = $count >= 2 ? " If 2 or more Ranks were provided, add one short line \"Ordered by price: #1 is the {$extreme}.\" as the FINAL line of your response (bottom footnote, not at the top). If only Rank #1 was provided, do NOT add any ordered/ranked line and do not mention alternatives." : ' Do NOT add any ordered/ranked line and do not mention alternatives.';

            return "In DATABASE RESULTS, results are ordered by price, NOT by AI relevance. Rank #1 is the {$extreme} option and Rank #2+ follow in price order. You must list every Rank provided — Rank #1 under ### {$heading} with one sentence why it costs least (use the rate/price fields from that block), and Rank #2+ under ### Other Options each one bullet (name — price — one key amenity). Do not omit alternatives to stay concise; this ranked-list rule overrides the concise 3-paragraph limit.{$footnote} Do not show raw relevance numbers.";
        }
        if ($ordering === 'exact') {
            return 'A single exact database match was provided (EXACT MATCH). Do NOT use ranked-list language ("best match", "Rank #1", alternatives, footnotes). Answer the user\'s question directly from that block.';
        }

        return 'In DATABASE RESULTS, Rank #1 is the system\'s best AI match (highest relevance score) for the query; Rank #2+ are next-best alternatives. You must list every Rank provided (up to 5 hotels/rooms/activities/packages where provided, e.g., top 5) — Rank #1 under ### Best Match with one sentence why #1 is top (use Vibe/Category/Featured Amenities/Price Range/Guest Rating from that block), and Rank #2+ under ### Other Options each one bullet (name — Price Range — one key amenity). Do not omit alternatives to stay concise; this ranked-list rule overrides the concise 3-paragraph limit. If 2 or more Ranks were provided, add one short line "Ranked by system: #1 is best match, #2+ are close alternatives." as the FINAL line of your response (bottom footnote, not at the top). If only Rank #1 was provided, do NOT add any ranked/“best match” line and do not mention alternatives. Do not show raw relevance numbers unless helpful.';
    }

    protected function buildPrompt(string $stage, string $context, string $query, ?User $user, array $options = []): string
    {
        $header = match ($stage) {
            'room-search' => 'TASK: Recommend rooms based on the database results below.',
            'hotel-search' => 'TASK: Recommend hotels based on the database results below.',
            'activity-search' => 'TASK: Recommend activities and tours based on the database results below.',
            'activity-compare' => 'TASK: Compare the named activities/tours below side by side.',
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
            'Never present "Total Physical Rooms" as live availability.',
            $this->rankRule($options),
            'Never add airports, ferry terminals, boats, vans, transfers, beaches, landmarks, restaurants, shops, fees, or food and drink estimates unless the exact fact appears in the database results.',
            'If information is unavailable, say it is not in our database instead of filling the gap with general travel knowledge.',
            'Use **bold** for short labels, ### for section headings, and - for bullet lists. Do not output HTML.',
            'You may reply in English or Taglish depending on the user\'s language.',
            'Keep responses friendly, concise, and helpful.',
        ];

        if ($stage === 'activity-compare') {
            $rules[] = 'Compare the listed activities ONLY against each other, using the provided fields.';
            $rules[] = 'Do NOT rank them, do NOT add a "best match" or "ranked by system" line.';
            $rules[] = 'Give one short block per activity (name — price — duration — activity level — key inclusions), then a brief "Which to pick" line naming the practical difference.';
            $rules[] = 'If a field is missing for one item, say it is not in our database instead of guessing.';
        }

        if ($stage === 'itinerary') {
            $rules[] = 'Build this itinerary ONLY from the listed destination, hotel, room, and activities.';
            $rules[] = 'Use the provided Day labels and activity assignments; do not invent new day-specific details.';
            $rules[] = 'Do not introduce any other locations, attractions, activities, venues, services, logistics, fees, or expenses.';
            $rules[] = 'Do not create airport arrival or departure plans, transfer details, meal plans, or extra budget estimates. Only use the listed room and activity prices and the pre-computed total.';
            $rules[] = 'If a part of the trip is not covered by the provided results, say that it is not included in the database results.';
            $rules[] = 'When the context gives a pre-computed total, quote that exact figure verbatim and never recompute or re-sum prices yourself. If no total is provided, list individual prices instead of stating a combined total.';
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

        // Field-first answering: the user asked ABOUT a named entity, so the
        // requested field leads instead of a generic recommendation.
        if (! empty($options['field'])) {
            $header .= "\n- The user asked specifically about {$options['field']}. Answer that FIRST by quoting the matching database field verbatim, then add at most one short follow-up line.";
        }

        if ($user) {
            $header .= "\n- The user is logged in as {$user->name}.";
            if ($this->isPersonalized($user)) {
                $profile = $this->buildUserProfileText($user);
                if ($profile) {
                    $header .= "\n- Personalized for this user (from onboarding quiz). Use it to tailor why #1 is best:\n".$profile;
                }
            }
        }

        $tail = '';
        if (! empty($options['interpretation'])) {
            $tail = "\n\nINTERPRETATION (deterministic — do not reinterpret):\n".$options['interpretation'];
        }

        return "{$header}\n\n=== DATABASE RESULTS ===\n{$context}\n=== END DATABASE RESULTS ==={$tail}\n\nUSER QUERY: {$query}";
    }

    protected function buildSystemPrompt(string $type, bool $grounded): string
    {
        return $this->geminiChatSystemPrompt();
    }

    protected function geminiChatResponse(string $prompt, ChatSession $session): string
    {
        // The current user message has already been persisted. Exclude it from
        // history because generateChatResponse appends $prompt as the one current turn.
        $history = $this->conversation->history($session, 6, true);

        // Strip the guest nudge from prior bot turns: it is appended at
        // response time and persisted, so without this Gemini sees its own
        // sign-off in history and echoes it (doubled nudge).
        $suffix = "\n\n".self::GUEST_NUDGE;
        foreach ($history as &$turn) {
            $text = $turn['parts'][0]['text'] ?? null;
            if (is_string($text) && str_ends_with($text, $suffix)) {
                $turn['parts'][0]['text'] = substr($text, 0, -strlen($suffix));
            }
        }
        unset($turn);

        return $this->gemini->generateChatResponse(
            $this->geminiChatSystemPrompt(),
            $history,
            $prompt
        ) ?? 'I apologize, but I could not generate a response at this moment. Please try again.';
    }

    protected function geminiChatSystemPrompt(): string
    {
        $base = $this->gemini->loadChatbotSystemPrompt();

        // Ground destination examples to reality: Gemini free-associates
        // Philippine destinations (Palawan, Cebu, ...) unless told otherwise.
        $names = DestinationModel::orderBy('name')->pluck('name')->all();
        if (empty($names)) {
            return $base;
        }

        $list = implode(', ', $names);

        return $base."\n\n## Supported Destinations (EXHAUSTIVE — from the live database)\n\nSunnyTrips currently serves ONLY these destinations: {$list}. When asking the user where they want to go, or giving destination examples, reference ONLY these. NEVER offer Palawan, Cebu, Siargao, Bohol, or any other destination as an option or example. If the user asks about a destination not in this list, say it is not in our database yet.";
    }

    protected function noResultsReply(string $type, array $constraints = []): string
    {
        $examples = $this->exampleDestinations(2);
        $bits = [];
        if (! empty($constraints['destination_name'])) {
            $bits[] = 'in '.$constraints['destination_name'];
        }
        if (! empty($constraints['max_price'])) {
            $bits[] = 'under ₱'.number_format((float) $constraints['max_price']);
        }
        if (! empty($constraints['pax'])) {
            $bits[] = 'for '.(int) $constraints['pax'].' pax';
        }
        $scope = $bits ? ' '.implode(' ', $bits) : '';

        // Name the real blocker instead of blaming budget: when the group does
        // not fit any single room, say so with the biggest-room fact and the
        // cheapest 2-room split (or why even a split cannot cover them).
        if ($type === 'rooms' && ! empty($constraints['pax'])) {
            $pax = (int) $constraints['pax'];
            $destId = $constraints['destination_id'] ?? null;
            $destName = $constraints['destination_name'] ?? 'this destination';
            $scopeMax = $this->gemini->maxRoomOccupancy($destId, $constraints['hotel_id'] ?? null);
            if ($scopeMax > 0 && $pax > $scopeMax) {
                $biggest = $this->gemini->largestRoom($destId, $constraints['hotel_id'] ?? null);
                $biggestFact = $biggest
                    ? "our biggest, {$biggest->room_name} at {$biggest->hotel?->hotel_name}, fits {$scopeMax}"
                    : "our biggest room fits {$scopeMax}";
                $splits = $this->gemini->groupSplitOptions($destId, $constraints['hotel_id'] ?? null, $pax, null, 1);
                if (! empty($splits)) {
                    $cheapest = '₱'.number_format($splits[0]['total'], 2).'/night';
                    $budgetBit = ! empty($constraints['max_price']) ? ' (above your ₱'.number_format((float) $constraints['max_price']).' budget)' : '';

                    return "No single room in {$destName} fits {$pax} — {$biggestFact}. The cheapest 2-room split covering {$pax} is {$cheapest}{$budgetBit}. Raise your budget to that, or shrink the group size.";
                }

                return "No single room in {$destName} fits {$pax} — {$biggestFact}, and even 2 rooms cannot cover {$pax} in one hotel. Try 3 rooms, or shrink the group size.";
            }
        }

        return "I could not find any {$type} matching your request{$scope}. Try raising your budget or lowering the group size, or ask me about a specific destination like {$examples}!";
    }

    /**
     * Budget notice for overflow/fallback flags set by GeminiService search.
     * Null when everything shown is in budget (or no budget was given).
     */
    protected function budgetNotice(array $scored, array $constraints): ?string
    {
        $maxPrice = ! empty($constraints['max_price']) ? (float) $constraints['max_price'] : null;
        if ($maxPrice === null || empty($scored)) {
            return null;
        }
        $fallbackCount = count(array_filter($scored, fn ($e) => ! empty($e['fallback'])));
        if ($fallbackCount > 0 && $fallbackCount === count($scored)) {
            return 'Nothing matched your **₱'.number_format($maxPrice).'** budget — here are the closest options above it.';
        }
        $overCount = count(array_filter($scored, fn ($e) => ! empty($e['over_budget'])));
        if ($overCount > 0) {
            $plural = $overCount > 1;

            return 'Heads up: '.($plural ? "{$overCount} marked options run" : '1 marked option runs').' slightly over your **₱'.number_format($maxPrice).'** budget (within ~10%).';
        }

        return null;
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

        $cleaned = (string) preg_replace('/^[^\n]*(Ranked by (system|AI semantic relevance)|Ordered by price)[^\n]*\n?/mi', '', $reply);

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
            ? "Ranked by AI semantic relevance + availability: Rank #1 = requested room/best match, Rank #2+ = close alternatives.\n\n=== AVAILABLE ROOMS ({$pax} pax, {$nights} nights) ==="
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
            'destination_id' => $e['item']->hotel?->destination_id ?? $e['item']->hotel?->destination?->id ?? null,
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
            'over_budget' => ! empty($e['over_budget']),
            'over_by' => isset($e['over_by']) ? round((float) $e['over_by'], 2) : null,
            'fallback' => ! empty($e['fallback']),
            'combo_group' => $e['combo_group'] ?? null,
            'combo_total' => isset($e['combo_total']) ? round((float) $e['combo_total'], 2) : null,
            'combo_with' => $e['combo_with'] ?? null,
            'combo_pax' => isset($e['combo_pax']) ? (int) $e['combo_pax'] : null,
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
            'over_budget' => ! empty($e['over_budget']),
            'over_by' => isset($e['over_by']) ? round((float) $e['over_by'], 2) : null,
            'fallback' => ! empty($e['fallback']),
        ], $scored);
    }

    protected function formatActivityResults(array $scored): array
    {
        return array_map(fn ($e) => [
            'id' => $e['item']->id,
            'activity_name' => $e['item']->activity_name,
            'destination' => $e['item']->destination?->name ?? null,
            'destination_id' => $e['item']->destination_id ?? $e['item']->destination?->id ?? null,
            'category' => $e['item']->category,
            'rate' => $e['item']->rate,
            'description' => strip_tags($e['item']->description ?? ''),
            'image' => $this->firstImage($e['item']->images),
            'similarity_score' => round($e['score'], 4),
            'over_budget' => ! empty($e['over_budget']),
            'over_by' => isset($e['over_by']) ? round((float) $e['over_by'], 2) : null,
            'fallback' => ! empty($e['fallback']),
        ], $scored);
    }

    protected function formatPackageResults(array $scored): array
    {
        return array_map(fn ($e) => [
            'id' => $e['item']->id,
            'name' => $e['item']->name,
            'destination' => $e['item']->destination?->name ?? null,
            'destination_id' => $e['item']->destination_id ?? $e['item']->destination?->id ?? null,
            'type' => $e['item']->type,
            'price' => (float) $e['item']->price,
            'days' => $e['item']->days,
            'nights' => $e['item']->nights,
            'min_pax' => $e['item']->min_pax,
            'description' => $e['item']->description ?? null,
            'inclusions' => $e['item']->generic_inclusions,
            'image' => $this->firstImage($e['item']->images),
            'similarity_score' => round($e['score'], 4),
            'over_budget' => ! empty($e['over_budget']),
            'over_by' => isset($e['over_by']) ? round((float) $e['over_by'], 2) : null,
            'fallback' => ! empty($e['fallback']),
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
