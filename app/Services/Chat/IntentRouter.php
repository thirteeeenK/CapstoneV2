<?php

namespace App\Services\Chat;

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use Carbon\Carbon;
use Carbon\Constants\UnitValue;
use Illuminate\Support\Facades\Log;

class IntentRouter
{
    public const GENERAL_TALK = 'GENERAL_TALK';

    public const PACKAGE_SEARCH = 'PACKAGE_SEARCH';

    public const ROOM_SEARCH = 'ROOM_SEARCH';

    public const HOTEL_SEARCH = 'HOTEL_SEARCH';

    public const ACTIVITY_SEARCH = 'ACTIVITY_SEARCH';

    public const ITINERARY_QUERY = 'ITINERARY_QUERY';

    public const AVAILABILITY_QUERY = 'AVAILABILITY_QUERY';

    public const MAP_QUERY = 'MAP_QUERY';

    public const WEATHER_QUERY = 'WEATHER_QUERY';

    public const DESTINATIONS_OVERVIEW = 'DESTINATIONS_OVERVIEW';

    public const ADDON_SEARCH = 'ADDON_SEARCH';

    public const DISCOUNT_QUERY = 'DISCOUNT_QUERY';

    public const BOOKING_STATUS = 'BOOKING_STATUS';

    public const SUPPORT_AGENT = 'SUPPORT_AGENT';

    public const LEGAL_QUERY = 'LEGAL_QUERY';

    protected array $travelKeywords = [
        'hotel',
        'hotels',
        'room',
        'rooms',
        'resort',
        'resorts',
        'stay',
        'stays',
        'accommodation',
        'book',
        'booking',
        'check in',
        'check-in',
        'check out',
        'check-out',
        'activity',
        'activities',
        'tour',
        'tours',
        'island hopping',
        'diving',
        'snorkeling',
        'beach',
        'beaches',
        'trip',
        'trips',
        'travel',
        'travels',
        'vacation',
        'holiday',
        'itinerary',
        'itineraries',
        'plan',
        'plans',
        'planning',
        'trip plan',
        'travel plan',
        'available',
        'availability',
        'open dates',
        'weather',
        'forecast',
        'rain',
        'sunny',
        'temperature',
        'climate',
        'where is',
        'how far',
        'nearby',
        'distance',
        'map',
        'location',
        'locate',
        'recommend',
        'recommendation',
        'suggest',
        'suggestion',
        'best',
        'top',
        'destinasyon',
        'bakasyon',
        'pasyalan',
        'pasyal',
        'byahe',
        'price',
        'prices',
        'cost',
        'costs',
        'budget',
        'budgets',
        'pesos',
        'php',
        '₱',
        'rate',
        'rates',
        'how much',
        'magkano',
        'presyo',
        'pax',
        'guest',
        'guests',
        'person',
        'persons',
        'people',
        'couple',
        'family',
        'family-friendly',
        'group',
        'solo',
        'night',
        'nights',
        'days',
        'day',
        'weekend',
        'week',
        'package',
        'packages',
        'promo',
        'deal',
        'deals',
        'bundle',
        'tipid',
        'all-in',
        'all inclusive',
        'addon',
        'add-on',
        'add ons',
        'transfer',
        'pickup',
        'surcharge',
        'pricing tier',
        'extra person',
        'extra pax',
        'additional pax',
        'per head',
        'per person',
        'per night',
        'max guests',
        'max occupants',
        'base occupancy',
        'additional charge',
        'valid until',
        'valid from',
        'promo period',
        // amenity/vibe refinements — bare filters like "luxury quiet pool" must route to room/hotel, not GENERAL_TALK
        'luxury',
        'luxurious',
        'premium',
        'quiet',
        'pool',
        'pools',
        'pool access',
        'private pool',
        'beachfront',
        'secluded',
        'relaxing',
        'lively',
        'find me',
        'something in',
        // safe broad travel signals (added, DB-verified)
        'honeymoon',
        'ocean view',
        'sea view',
        'mountain view',
        'garden view',
        'sunset cruise',
        'island tour',
        'guided tour',
        'sunnytrips',
        // missing-from-keyword-gap: water/land activities that should still travel-route
        'banana boat',
        'parasailing',
        'parasail',
        'paraw',
        'jet ski',
        'jetski',
        'helmet diving',
        'crystal kayak',
        'paddleboard',
        'canopy walk',
        'nacpan',
        'puka',
    ];

    public function isTravelQuery(string $query): bool
    {
        $lower = mb_strtolower($query);
        foreach ($this->travelKeywords as $keyword) {
            $kw = mb_strtolower(trim($keyword));
            if ($kw === '') {
                continue;
            }
            // Word-boundary match: short keywords like "rain" must not fire
            // inside other words ("trainer" → "rain" misrouted to rooms).
            if (preg_match('/(?<!\p{L})'.preg_quote($kw, '/').'(?!\p{L})/iu', $lower)) {
                return true;
            }
        }

        return false;
    }

    public function classify(string $query): string
    {
        $lower = mb_strtolower($query);

        if ($this->hasSupportAgentIntent($lower)) {
            return self::SUPPORT_AGENT;
        }

        if ($this->hasLegalIntent($lower)) {
            return self::LEGAL_QUERY;
        }

        if ($this->hasBookingStatusIntent($lower)) {
            return self::BOOKING_STATUS;
        }

        if ($this->hasDestinationsOverviewIntent($lower)) {
            return self::DESTINATIONS_OVERVIEW;
        }

        if ($this->hasWeatherIntent($lower)) {
            return self::WEATHER_QUERY;
        }

        if ($this->hasMapIntent($lower)) {
            return self::MAP_QUERY;
        }

        if ($this->hasAvailabilityIntent($lower)) {
            return self::AVAILABILITY_QUERY;
        }

        if ($this->hasItineraryIntent($lower)) {
            $activityName = $this->extractActivityName($query);
            if ($activityName !== null && ! $this->hasItineraryPlanningSignals($lower, $query)) {
                return self::ACTIVITY_SEARCH;
            }

            return self::ITINERARY_QUERY;
        }

        if ($this->hasDiscountIntent($lower)) {
            return self::DISCOUNT_QUERY;
        }

        if ($this->hasPackageIntent($lower)) {
            return self::PACKAGE_SEARCH;
        }

        if ($this->hasAddOnIntent($lower)) {
            return self::ADDON_SEARCH;
        }

        if ($this->hasRoomIntent($lower)) {
            return self::ROOM_SEARCH;
        }

        if ($this->hasHotelIntent($lower)) {
            return self::HOTEL_SEARCH;
        }

        if ($this->hasActivityIntent($lower)) {
            return self::ACTIVITY_SEARCH;
        }

        // Bare hotel/room name without explicit keyword (e.g., "how about for happiness?") — treat as room/hotel search
        if ($this->extractHotelName($query) || $this->extractRoomName($query)) {
            if (preg_match('/\b(max|occupancy|occupants|pax|guests|extra|per head|base|price|how many)\b/i', $lower)) {
                return self::ROOM_SEARCH;
            }

            return self::HOTEL_SEARCH;
        }

        if ($this->isTravelQuery($lower)) {
            return self::ROOM_SEARCH;
        }

        // Bare price-ranked destination query ("cheapest place in El Nido"):
        // no catalog keyword, so default to rooms (stay) deterministically
        // instead of semantic-routing roulette.
        if ($this->hasPriceRankingIntent($lower) && $this->extractDestinationName($query)) {
            return self::ROOM_SEARCH;
        }

        return self::GENERAL_TALK;
    }

    /**
     * True when the query asks for price-based ranking (cheapest / most
     * expensive). Mirrors ChatbotService::detectPriceIntent wording.
     */
    protected function hasPriceRankingIntent(string $lower): bool
    {
        return (bool) preg_match('/\b(most\s+expensive|expensive|pricey|costliest|premium|luxurious|luxury|high-end|high\s*end|mahal|cheapest|cheap|affordable|budget|lowest|least\s+expensive|mura)\b/', $lower);
    }

    /**
     * Normalize user text for matching: lowercase, strip emoji/punctuation,
     * collapse whitespace. Ponytailed typo tolerance starts here — every
     * extractor below should match against this, not raw input.
     */
    public function normalizeText(string $query): string
    {
        $text = mb_strtolower($query);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Fuzzy token match: true when $needle matches $haystack exactly or
     * within $maxDistance levenshtein edits per token (typos like
     * "borakay" → "boracay"). Bounded: tokens < 4 chars require exact.
     */
    protected function fuzzyContains(string $haystack, string $needle, int $maxDistance = 2, int $minimumHaystackTokenLength = 4): bool
    {
        $needle = $this->normalizeText($needle);
        $haystack = $this->normalizeText($haystack);
        if ($needle === '' || $haystack === '') {
            return false;
        }
        if (str_contains($haystack, $needle)) {
            return true;
        }
        // Concatenated typo ("elnedo" → "el nido"): whole-string edit
        // distance, only when the query is about as short as the name.
        $flatHay = str_replace(' ', '', $haystack);
        $flatNeedle = str_replace(' ', '', $needle);
        if ($flatNeedle !== '' && strlen($flatHay) <= strlen($flatNeedle) + 2
            && abs(strlen($flatHay) - strlen($flatNeedle)) <= 2
            && levenshtein($flatHay, $flatNeedle) <= 2) {
            return true;
        }
        $hayTokens = preg_split('/\s+/', $haystack) ?: [];
        // Concatenated name typed as one typo'd token ("elnedo" in a longer
        // query): compare the spaceless name against each query token.
        if (str_contains($needle, ' ')) {
            foreach ($hayTokens as $ht) {
                if (abs(strlen($ht) - strlen($flatNeedle)) <= 2 && levenshtein($ht, $flatNeedle) <= 2) {
                    return true;
                }
            }
        }
        $hayTokens = preg_split('/\s+/', $haystack) ?: [];
        $needleTokens = preg_split('/\s+/', $needle) ?: [];
        $checked = 0;
        foreach ($needleTokens as $nt) {
            if (strlen($nt) < 4) {
                continue;
            }
            $checked++;
            $matched = false;
            foreach ($hayTokens as $ht) {
                // Mirror the needle guard: 1-3 char query words ("in",
                // "sea", "to") otherwise align to any short name fragment
                // ("mins", "seda") and pin entities from thin air.
                if (strlen($ht) < $minimumHaystackTokenLength || abs(strlen($ht) - strlen($nt)) > $maxDistance) {
                    continue;
                }
                if (levenshtein($ht, $nt) <= $maxDistance) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        return $checked > 0;
    }

    public function extractConstraints(string $query): array
    {
        $lower = mb_strtolower($query);
        $constraints = [
            'pax' => null,
            'max_price' => null,
            'destination_id' => null,
            'destination_name' => null,
            'hotel_id' => null,
            'hotel_name' => null,
            'room_name' => null,
            'room_id' => null,
            'addon_name' => null,
            'addon_id' => null,
            'package_name' => null,
            'activity_name' => null,
            'check_in_date' => null,
            'check_out_date' => null,
            'nights' => null,
            'days' => null,
            'limit' => null,
            'field_intent' => null,
            'place_names' => [],
        ];

        if (preg_match('/(\d+)\s*(?:pax|persons?|people|tao|katao|miyembro|guests?|adults?|children|kids|heads?)\b/i', $query, $m)) {
            $constraints['pax'] = (int) $m[1];
        }
        if (preg_match('/\bfor\s+(\d+)\s*(?:pax|persons?|people|guests?)?\b(?!\s*(?:nights?|days?|gabi|araw))/i', $query, $m) && ! $constraints['pax']) {
            $constraints['pax'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*kami\b/i', $query, $m) && ! $constraints['pax']) {
            $constraints['pax'] = (int) $m[1];
        }
        if (preg_match('/couple|couples|2\s*pax/i', $query) && ! $constraints['pax']) {
            $constraints['pax'] = 2;
        }
        if (preg_match('/(?:solo|alone|1\s*pax|myself)/i', $query) && ! $constraints['pax']) {
            $constraints['pax'] = 1;
        }

        if (preg_match('/(?:(?:under|below|less\s*than|max|maximum|budget\s*(?:of|is)?))\s*(?:₱|php|peso)?\s*(\d[\d,]{0,8})\b(?!\s*k\b)/i', $query, $m)) {
            $constraints['max_price'] = (int) str_replace(',', '', $m[1]);
        }
        if (preg_match('/(?:₱|php|peso)?\s*(\d[\d,]{1,8})(?!\s*k\b)\s*(?:budget|max|pesos)/i', $query, $m)) {
            $constraints['max_price'] = (int) str_replace(',', '', $m[1]);
        }
        if (! $constraints['max_price'] && preg_match('/(?:under|below|less\s*than|max|maximum|budget|around|approx(?:imate(?:ly)?)?)\s*(?:of\s*)?(?:₱|php|peso)?\s*(\d+(?:\.\d+)?)\s*k\b/i', $query, $m)) {
            $constraints['max_price'] = (int) ((float) $m[1] * 1000);
        }
        if (! $constraints['max_price'] && preg_match('/(\d+(?:\.\d+)?)\s*k\s*(?:budget|max|per\s*(?:head|person|pax))?\b/i', $query, $m)) {
            $constraints['max_price'] = (int) ((float) $m[1] * 1000);
        }

        if (preg_match('/(\d+)[-\s]*(?:nights?|gabi)\b/i', $query, $m)) {
            $constraints['nights'] = (int) $m[1];
        }
        if (preg_match('/(\d+)[-\s]*(?:days?|araw)\b/i', $query, $m) && ! $constraints['nights']) {
            $constraints['days'] = (int) $m[1];
            $constraints['nights'] = max(0, $constraints['days'] - 1);
        }

        $dateRange = $this->extractDateRange($query);
        if ($dateRange) {
            $constraints['check_in_date'] = $dateRange[0];
            $constraints['check_out_date'] = $dateRange[1];
            if (! $constraints['nights'] && $dateRange[0] && $dateRange[1]) {
                $constraints['nights'] = max(1, Carbon::parse($dateRange[0])->diffInDays(Carbon::parse($dateRange[1])));
            }
        }

        $constraints['destination_name'] = $this->extractDestinationName($query);
        if ($constraints['destination_name']) {
            $constraints['destination_id'] = $this->resolveDestinationId($constraints['destination_name']);
        }

        $constraints['hotel_name'] = $this->extractHotelName($query);
        if ($constraints['hotel_name']) {
            $constraints['hotel_id'] = HotelModel::where('hotel_name', 'ILIKE', $constraints['hotel_name'])->value('id');
        }

        // Room name — supports both hotel-scoped and global search (per answer 5)
        $constraints['room_name'] = $this->extractRoomName($query);
        if ($constraints['room_name']) {
            $roomQuery = RoomType::where('room_name', 'ILIKE', $constraints['room_name']);
            if ($constraints['hotel_id']) {
                $roomQuery->where('hotel_id', $constraints['hotel_id']);
            }
            $room = $roomQuery->first();
            if ($room) {
                $constraints['room_id'] = $room->id;
                // Co-set hotel if not already set (global room-name-only query)
                if (! $constraints['hotel_id']) {
                    $constraints['hotel_id'] = $room->hotel_id;
                    if (! $constraints['hotel_name']) {
                        $constraints['hotel_name'] = HotelModel::where('id', $room->hotel_id)->value('hotel_name');
                    }
                }
            }
        }

        $constraints['addon_name'] = $this->extractAddOnName($query);
        if ($constraints['addon_name']) {
            $constraints['addon_id'] = AddOnModel::where('name', 'ILIKE', $constraints['addon_name'])
                ->where('is_shown', true)
                ->when($constraints['destination_id'] ?? null, fn ($q) => $q->where('destination_id', $constraints['destination_id']))
                ->value('id');
        }

        $constraints['package_name'] = $this->extractPackageName($query);
        $constraints['activity_name'] = $this->extractActivityName($query);
        $constraints['field_intent'] = $this->detectFieldIntent($query);

        // Dynamic top N (e.g., "top 5 hotel")
        if (preg_match('/\btop\s*(\d+)\b/i', $query, $m)) {
            $n = (int) $m[1];
            $constraints['limit'] = max(3, min(10, $n));
        }

        $constraints['place_names'] = $this->extractPlaceNames($query);

        return $constraints;
    }

    public function extractDestinationName(string $query): ?string
    {
        $destinations = DestinationModel::pluck('name')->sortByDesc(fn ($n) => mb_strlen($n));
        $lower = mb_strtolower($query);

        foreach ($destinations as $name) {
            if (str_contains($lower, mb_strtolower($name))) {
                return $name;
            }
        }

        // Concatenated / misspelled forms ("elnido" for "El Nido") — compare
        // both sides with all non-letters stripped so spacing is ignored.
        $flattened = (string) preg_replace('/[^\p{L}\p{N}]/u', '', $lower);
        if ($flattened !== '') {
            foreach ($destinations as $name) {
                $flatName = (string) preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower((string) $name));
                if ($flatName !== '' && str_contains($flattened, $flatName)) {
                    return $name;
                }
            }
        }

        // Typo tolerance ("borakay" → "Boracay", "elnedo" → "El Nido").
        foreach ($destinations as $name) {
            if ($this->fuzzyContains($query, (string) $name)) {
                return $name;
            }
        }

        return null;
    }

    protected function resolveDestinationId(string $name): ?int
    {
        return DestinationModel::where('name', 'ILIKE', $name)->value('id');
    }

    public function extractHotelName(string $query): ?string
    {
        $hotels = HotelModel::pluck('hotel_name')->sortByDesc(fn ($n) => mb_strlen($n));
        $lower = mb_strtolower($query);

        foreach ($hotels as $name) {
            if (str_contains($lower, mb_strtolower($name))) {
                return $name;
            }
        }

        $genericWords = [
            'resort',
            'resorts',
            'hotel',
            'hotels',
            'hostel',
            'hostels',
            'inn',
            'inns',
            'lodge',
            'lodges',
            'suites',
            'suite',
            'beach',
            'beaches',
            'island',
            'islands',
            'bay',
            'bays',
            'villa',
            'villas',
            'residences',
            'vacation',
            'holiday',
            'guest',
            'house',
            'homes',
            'home',
            // Scenery words that name no hotel by themselves ("fly above the
            // sea" must not pin a hotel carrying a "sea" token).
            'sea',
            'seas',
            'ocean',
            'oceans',
            'sand',
            'sands',
            'sun',
            'sunset',
            'lagoon',
            'lagoons',
            'cove',
            'coves',
            'the',
            'and',
            'de',
            'del',
            'la',
            'of',
        ];
        $destinationTokens = [];
        foreach (DestinationModel::pluck('name')->all() as $destName) {
            foreach (preg_split('/\s+/', mb_strtolower((string) $destName)) as $t) {
                $t = trim($t, " \t\n\r\0\x0B-");
                if ($t !== '') {
                    $destinationTokens[] = $t;
                }
            }
        }
        $destinationTokens = array_unique($destinationTokens);

        foreach ($hotels as $name) {
            $tokens = preg_split('/\s+/', mb_strtolower((string) $name));
            foreach ($tokens as $token) {
                $token = trim($token, " \t\n\r\0\x0B-");
                if (strlen($token) < 3 || in_array($token, $genericWords, true) || in_array($token, $destinationTokens, true)) {
                    continue;
                }
                if (preg_match('/\b'.preg_quote($token, '/').'\b/', $lower)) {
                    return (string) $name;
                }
            }
        }

        // Typo tolerance ("happines" → "Happiness Beach Resort").
        // Skip names whose only distinctive tokens are generic/destination words.
        foreach ($hotels as $name) {
            $tokens = preg_split('/\s+/', mb_strtolower((string) $name));
            $meaningful = array_filter($tokens, fn ($t) => strlen($t) >= 3 && ! in_array($t, $genericWords, true) && ! in_array($t, $destinationTokens, true));
            if ($meaningful === []) {
                continue;
            }
            if ($this->fuzzyContains($query, (string) $name)) {
                return (string) $name;
            }
        }

        return null;
    }

    public function extractRoomName(string $query): ?string
    {
        $rooms = RoomType::pluck('room_name')->sortByDesc(fn ($n) => mb_strlen($n));
        $lower = mb_strtolower($query);

        foreach ($rooms as $name) {
            if (str_contains($lower, mb_strtolower($name))) {
                return (string) $name;
            }
        }

        return null;
    }

    public function extractAddOnName(string $query): ?string
    {
        try {
            $addons = AddOnModel::pluck('name')->sortByDesc(fn ($n) => mb_strlen($n));
            $lower = mb_strtolower($query);
            foreach ($addons as $name) {
                if (str_contains($lower, mb_strtolower($name))) {
                    return (string) $name;
                }
            }
        } catch (\Throwable $e) {
            // table may not exist in tests
        }

        return null;
    }

    public function extractPackageName(string $query): ?string
    {
        $packages = Package::pluck('name')->sortByDesc(fn ($n) => mb_strlen($n));
        $lower = mb_strtolower($query);
        foreach ($packages as $name) {
            if (str_contains($lower, mb_strtolower($name))) {
                return (string) $name;
            }
        }

        return null;
    }

    public function extractActivityName(string $query): ?string
    {
        $activities = ActivityModel::pluck('activity_name')->sortByDesc(fn ($n) => mb_strlen($n));
        $lower = mb_strtolower($query);
        foreach ($activities as $name) {
            if (str_contains($lower, mb_strtolower($name))) {
                return (string) $name;
            }
        }

        // Partial-name fallback ("el nido tour b itinerary" → "El Nido Tour B
        // (Caves & Coves)"): every distinctive name token must appear in the
        // query is too strict when the query identifies the item another way
        // (e.g. the "Tour B" letter). Instead require ≥1 distinctive hit AND
        // full coverage of the query's significant tokens by the name.
        [$genericWords, $destinationTokens] = $this->activityTokenFilters();

        $queryTokens = [];
        foreach (preg_split('/\s+/', $lower) as $t) {
            $t = $this->cleanToken($t);
            if ($t !== '' && ! in_array($t, $genericWords, true) && ! in_array($t, $destinationTokens, true)) {
                $queryTokens[] = $t;
            }
        }
        $queryTokens = array_values(array_unique($queryTokens));

        $best = $this->matchActivityByTokens($activities, $lower, $queryTokens, $genericWords, $destinationTokens);

        // Alias fallback ("scuba trainer" → "Discover Scuba Diving (DSD)").
        if ($best === null) {
            $expanded = array_values(array_unique(array_map(
                fn ($t) => self::ACTIVITY_TOKEN_ALIASES[$t] ?? $t,
                $queryTokens
            )));
            if ($expanded !== $queryTokens) {
                $best = $this->matchActivityByTokens($activities, $lower, $expanded, $genericWords, $destinationTokens);
            }
        }

        // Typo tolerance ("parawsailing" → "Parasailing"). Matched against the
        // distinctive tokens only: destination + generic words ("El Nido …
        // Activity") appear in every browse query and must not trigger a
        // false exact hit that hijacks the whole search.
        if ($best === null) {
            foreach ($activities as $name) {
                $distinctive = [];
                foreach (preg_split('/\s+/', mb_strtolower((string) $name)) as $t) {
                    $t = $this->cleanToken($t);
                    if ($t !== '' && ! in_array($t, $genericWords, true) && ! in_array($t, $destinationTokens, true)) {
                        $distinctive[] = $t;
                    }
                }
                if (empty($distinctive)) {
                    continue;
                }
                if ($this->fuzzyContains($query, implode(' ', $distinctive), minimumHaystackTokenLength: 5)) {
                    return (string) $name;
                }
                // Concatenated typing ("jetski" → "Jet Ski (30 mins)"): a
                // single long query token containing (or contained in) the
                // spaceless distinctive core counts as a hit. Threshold 5+
                // so short words ("mins", "tour") can't pin items alone.
                // Skipped for price-seeking queries ("jetski price El Nido"
                // wants a scoped price listing, not the item itself).
                $flatCore = str_replace(' ', '', implode(' ', $distinctive));
                if ($flatCore !== '' && $this->detectFieldIntent($query) !== 'price') {
                    foreach (preg_split('/\s+/', mb_strtolower($query)) as $qt) {
                        $qt = $this->cleanToken($qt);
                        if (strlen($qt) >= 5 && (str_contains($flatCore, $qt) || str_contains($qt, $flatCore))) {
                            return (string) $name;
                        }
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Score catalog activities against significant query tokens: ≥1
     * distinctive name token must hit the raw query AND every significant
     * query token must be covered by the name. Best hits, then best
     * hit-ratio, wins.
     */
    protected function matchActivityByTokens($activities, string $lower, array $queryTokens, array $genericWords, array $destinationTokens): ?string
    {
        $best = null;
        $bestHits = 0;
        $bestRatio = 0.0;
        foreach ($activities as $name) {
            $nameTokens = [];
            foreach (preg_split('/\s+/', mb_strtolower((string) $name)) as $t) {
                $t = $this->cleanToken($t);
                if ($t !== '' && ! in_array($t, $genericWords, true) && ! in_array($t, $destinationTokens, true)) {
                    $nameTokens[] = $t;
                }
            }
            $nameTokens = array_unique($nameTokens);
            if (empty($nameTokens)) {
                continue;
            }
            $hits = 0;
            foreach ($nameTokens as $token) {
                if (preg_match('/\b'.preg_quote($token, '/').'\b/', $lower)) {
                    $hits++;
                }
            }
            if ($hits === 0) {
                continue;
            }
            // Every significant query token must be covered by the name.
            $covered = true;
            foreach ($queryTokens as $qt) {
                if (! in_array($qt, $nameTokens, true)) {
                    $covered = false;
                    break;
                }
            }
            if (! $covered) {
                continue;
            }
            $ratio = $hits / count($nameTokens);
            if ($hits > $bestHits || ($hits === $bestHits && $ratio > $bestRatio)) {
                $best = (string) $name;
                $bestHits = $hits;
                $bestRatio = $ratio;
            }
        }

        return $best;
    }

    /**
     * Shorthand/role tokens users type that never appear in catalog names.
     * Trainer-family words imply the beginner intro product ("Discover …"),
     * not the generic certified activity: mapping them to "discover" lets
     * "scuba trainer" resolve Discover Scuba Diving (DSD) even though the
     * shorter "Scuba Diving" would otherwise win on hit-ratio. Hits are
     * still counted against the raw query and every expanded token must be
     * covered by the name, so the alias only relaxes coverage, never
     * invents a match from thin air.
     */
    protected const ACTIVITY_TOKEN_ALIASES = [
        'trainer' => 'discover',
        'trainor' => 'discover',
        'training' => 'discover',
        'instructor' => 'discover',
        'scuba' => 'scuba',
        'snorkel' => 'snorkeling',
    ];

    /**
     * Deterministic field-inquiry mode: what the user asks ABOUT a named
     * entity (its inclusions, price, duration...), not which entity to find.
     * Checked exclusions-first since "not included" contains "included".
     */
    public function detectFieldIntent(string $query): ?string
    {
        $lower = mb_strtolower($query);
        if (preg_match('/\bnot\s+included\b|\bexclu|\bhindi\s+kasama\b|\bnot\s+part\s+of\b/i', $lower)) {
            return 'exclusions';
        }
        if (preg_match('/\binclud|\binclusion|\bkasama\b|\bcomes?\s+with\b|\bwhat\s+do\s+(i|we)\s+get\b/i', $lower)) {
            return 'inclusions';
        }
        if (preg_match('/\bhow\s+much\b|\bmagkano\b|\bpresyo\b|\bprices?\b|\bcosts?\b|\brates?\b/i', $lower)) {
            return 'price';
        }
        if (preg_match('/\bhow\s+long\b|\bduration\b|\blong\s+does\b|\bgaano\s+katagal\b/i', $lower)) {
            return 'duration';
        }
        if (preg_match('/\bcapacity\b|\bmax\s+pax\b|\bmaximum\s+(pax|guests|occupancy)\b|\bkasya\b/i', $lower)) {
            return 'capacity';
        }
        if (preg_match('/\brequirements?\b|\bbring\b|\bdadalhin\b|\brestrictions?\b|\bage\s+limit\b|\bbawal\b/i', $lower)) {
            return 'requirements';
        }
        if (preg_match('/\bamenit|\bfacilit|\bswimming\s+pool\b|\bpool\b|\bwifi\b|\bgym\b|\bspa\b|\bpasilidad\b/i', $lower)) {
            return 'amenities';
        }
        if (preg_match('/\bwhere\s+is\b/i', $lower)) {
            return 'location';
        }
        if (preg_match('/\bitinerary\b/i', $lower)) {
            return 'itinerary';
        }

        return null;
    }

    protected function cleanToken(string $token): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower(trim($token)));
    }

    /**
     * Shared word filters used when matching catalog names against a query.
     *
     * @return array{0: string[], 1: string[]} [genericWords, destinationTokens]
     */
    protected function activityTokenFilters(): array
    {
        $genericWords = [
            'tour', 'tours', 'activity', 'activities', 'island', 'islands',
            'ride', 'rides', 'experience', 'package', 'packages',
            'the', 'a', 'an', 'and', 'or', 'of', 'in', 'on', 'at', 'for', 'to', 'with',
            'what', 'how', 'is', 'are', 'was', 'were', 'do', 'does', 'did', 'it', 'its',
            'much', 'many', 'about', 'vs', 'versus', 'between', 'compare', 'comparison',
            'difference', 'differences', 'different',
            'itinerary', 'itineraries', 'inclusion', 'inclusions', 'include', 'includes', 'included', 'including',
            'exclusion', 'exclusions', 'exclude', 'excludes', 'excluded', 'excluding',
            'requirement', 'requirements', 'price', 'prices', 'cost', 'costs', 'rate', 'rates',
            'duration', 'location', 'details', 'detail', 'info', 'information',
            'offered', 'offer', 'offering', 'available', 'availability', 'best', 'top',
            'boracay', 'elnido', 'el', 'nido', 'palawan', 'cebu', 'philippines',
        ];

        $destinationTokens = [];
        foreach (DestinationModel::pluck('name')->all() as $destName) {
            foreach (preg_split('/\s+/', mb_strtolower((string) $destName)) as $t) {
                $t = $this->cleanToken($t);
                if ($t !== '') {
                    $destinationTokens[] = $t;
                }
            }
        }

        return [$genericWords, array_values(array_unique($destinationTokens))];
    }

    /**
     * Every activity whose full significant name is mentioned in the query.
     * Unlike extractActivityName() this is not limited to a single best match,
     * so comparisons ("atv and zipline", "banana boat vs parasailing") can
     * resolve both sides.
     *
     * @return string[]
     */
    public function extractActivityNames(string $query): array
    {
        $activities = ActivityModel::pluck('activity_name');
        $lower = mb_strtolower($query);
        [$genericWords, $destinationTokens] = $this->activityTokenFilters();

        $matches = [];
        foreach ($activities as $name) {
            $lowerName = mb_strtolower((string) $name);
            if (str_contains($lower, $lowerName)) {
                $matches[] = (string) $name;

                continue;
            }

            $nameTokens = [];
            foreach (preg_split('/\s+/', $lowerName) as $t) {
                $t = $this->cleanToken($t);
                if ($t !== '' && ! in_array($t, $genericWords, true) && ! in_array($t, $destinationTokens, true)) {
                    $nameTokens[] = $t;
                }
            }
            $nameTokens = array_unique($nameTokens);
            if (empty($nameTokens)) {
                continue;
            }

            $allPresent = true;
            foreach ($nameTokens as $token) {
                if (! preg_match('/\b'.preg_quote($token, '/').'\b/', $lower)) {
                    $allPresent = false;
                    break;
                }
            }
            if ($allPresent) {
                $matches[] = (string) $name;
            }
        }

        return $matches;
    }

    protected function extractPlaceNames(string $query): array
    {
        $places = [];
        $destinations = DestinationModel::pluck('name')->all();

        foreach ($destinations as $name) {
            if (mb_stripos($query, $name) !== false) {
                $places[] = ['type' => 'destination', 'name' => $name];
            }
        }

        $hotelName = $this->extractHotelName($query);
        if ($hotelName) {
            $hotel = HotelModel::where('hotel_name', 'ILIKE', $hotelName)->first();
            if ($hotel && $hotel->latitude && $hotel->longitude) {
                $places[] = ['type' => 'hotel', 'name' => $hotelName, 'model' => $hotel];
            }
        }

        $activityName = $this->extractActivityName($query);
        if ($activityName) {
            $activity = ActivityModel::where('activity_name', 'ILIKE', $activityName)->first();
            if ($activity && $activity->latitude_with_fallback !== null) {
                $places[] = ['type' => 'activity', 'name' => $activityName, 'model' => $activity];
            }
        }

        return $places;
    }

    protected function extractDateRange(string $query): ?array
    {
        $lower = mb_strtolower($query);

        if (preg_match('/this\s*weekend/i', $lower)) {
            $sat = Carbon::now()->next(UnitValue::SATURDAY);

            return [$sat->format('Y-m-d'), $sat->copy()->addDays(2)->format('Y-m-d')];
        }

        if (preg_match('/next\s*week/i', $lower)) {
            $mon = Carbon::now()->next(UnitValue::MONDAY);

            return [$mon->format('Y-m-d'), $mon->copy()->addDays(7)->format('Y-m-d')];
        }

        if (preg_match('/(?:tonight|today)/i', $lower) && ! preg_match('/this\s*weekend/i', $lower)) {
            return [Carbon::now()->format('Y-m-d'), Carbon::now()->addDay()->format('Y-m-d')];
        }

        $months = implode('|', [
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'september',
            'october',
            'november',
            'december',
            'jan',
            'feb',
            'mar',
            'apr',
            'may',
            'jun',
            'jul',
            'aug',
            'sep',
            'oct',
            'nov',
            'dec',
        ]);

        $datePattern = "/(($months)\s*\d{1,2})\s*(?:to|-|–)\s*(($months)?\s*\d{1,2})/i";
        $datePattern2 = "/($months)\s*(\d{1,2})\s*(?:to|-|–)\s*(\d{1,2})/i";

        if (preg_match($datePattern, $query, $m)) {
            try {
                $d1 = Carbon::parse($m[1]);
                $d2 = Carbon::parse($m[3]);
                if (mb_check_encoding($m[4] ?? '', 'UTF-8') && trim($m[4] ?? '') !== '' && $d2->lt($d1)) {
                    $d2->addYear();
                }

                return [$d1->format('Y-m-d'), $d2->format('Y-m-d')];
            } catch (\Throwable $e) {
                Log::debug('IntentRouter date parse failed (pattern1): '.$e->getMessage());
            }
        }

        if (preg_match($datePattern2, $query, $m)) {
            try {
                $dateStr = $m[1].' '.$m[2];
                $d1 = Carbon::parse($dateStr);
                $d2 = Carbon::parse($m[1].' '.$m[3]);
                if ($d2->lt($d1)) {
                    $d2->addMonth();
                }

                return [$d1->format('Y-m-d'), $d2->format('Y-m-d')];
            } catch (\Throwable $e) {
                Log::debug('IntentRouter date parse failed (pattern2): '.$e->getMessage());
            }
        }

        return null;
    }

    protected function hasWeatherIntent(string $lower): bool
    {
        $weather = [
            'weather',
            'forecast',
            'rain',
            'rainy',
            'sunny',
            'temperature',
            'climate',
            'hot',
            'cold',
            'humid',
            'storm',
            'typhoon',
            'bagyo',
            'ulan',
            'araw',
            'init',
            'lamig',
            'panahon',
            // safe broad weather signals (excluded travel advisory)
            'cloudy',
            'overcast',
            'windy',
            'monsoon',
            'habagat',
            'amihan',
            'thunderstorm',
            'flood',
            'baha',
            'clear skies',
            'sea condition',
            'swell',
            'good weather',
            'bad weather',
        ];
        foreach ($weather as $w) {
            if (preg_match('/\b'.preg_quote($w, '/').'\b/i', $lower)) {
                return true;
            }
        }

        return false;
    }

    protected function hasMapIntent(string $lower): bool
    {
        $map = [
            'where is',
            'how far',
            'nearby',
            'distance',
            'map',
            'location',
            'locate',
            'direction',
            'directions',
            'navigate',
            'nasaan',
            'saan',
            'gaano kalayo',
            'malapit',
            'kalapit',
            // safe broad map signals
            'how to get to',
            'papaano pumunta',
            'paano pumunta',
            'ilang minuto',
            'ilang oras',
            'walking distance',
            'driving distance',
        ];
        foreach ($map as $m) {
            if (str_contains($lower, $m)) {
                return true;
            }
        }
        // Tagalog variants with flexible spacing
        if (preg_match('/\b(gaano\s*(?:ako|ka|ko|mo)?\s*kalayo|kalayo\s*(?:ko|mo|niya)?|layo\s*(?:ko|mo|niya)?|distansya\s*(?:ko|mo)?|nasaan\s*ako|kinalalagyan\s*(?:ko|mo)?)\b/i', $lower)) {
            return true;
        }
        // address/coordinates/landmark need word boundaries to avoid false positives
        foreach (['address', 'coordinates', 'landmark'] as $w) {
            if (preg_match('/\b'.preg_quote($w, '/').'\b/i', $lower)) {
                return true;
            }
        }
        if (preg_match('/saan\s+(ang|yung)\s+/i', $lower)) {
            return true;
        }

        return false;
    }

    protected function hasAvailabilityIntent(string $lower): bool
    {
        $months = '(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|january|february|march|april|may|june|july|august|september|october|november|december)';
        $hasDate = preg_match(
            '/(\d{1,2}\s*(?:to|-|–)\s*\d{1,2}|'.$months.'\s*\d{1,2}\s*(?:to|-|–)\s*(?:'.$months.'\s*)?\d{1,2}|check\s*(?:in|out)|check-in|check-out|stay\s*dates)/i',
            $lower
        );

        // Typo-tolerant avail (availabilith, availab, availble) + "check avail..." works without date (covers "yes check availabilith" follow-up)
        $hasAvailTypo = preg_match('/\bavaila\w*\b/i', $lower) || str_contains($lower, 'bakante');
        if ($hasAvailTypo) {
            // Require date OR explicit "check" to avoid catching "what tours are available" as availability (should be ACTIVITY_SEARCH)
            if ($hasDate || str_contains($lower, 'check')) {
                return true;
            }
            // If avail phrase appears with tour/activity words but no check/hotel/date, don't treat as availability
            if (preg_match('/\b(tour|tours|activity|activities)\b/i', $lower)) {
                return false;
            }
        }

        if (! $hasDate) {
            return false;
        }

        $avail = ['available', 'availability', 'open dates', 'still open', 'may available', 'check availability', 'may bakante', 'bakante'];
        foreach ($avail as $a) {
            if (str_contains($lower, $a)) {
                return true;
            }
        }

        return preg_match('/(?:available|open|free|vacant)/i', $lower);
    }

    protected function hasItineraryIntent(string $lower): bool
    {
        $itin = [
            'itinerary',
            'itenerary',
            'plan my trip',
            'trip plan',
            'travel plan',
            'plan a trip',
            'plan for',
            'day itinerary',
            'day trip',
            'sample itinerary',
            'itiniraryo',
            // safe broad itinerary signals (excluded first day/last day/what to do first)
            'suggest an itinerary',
            'build an itinerary',
            'travel schedule',
            'daily schedule',
            'plano ng byahe',
            'balak',
        ];
        foreach ($itin as $i) {
            if (str_contains($lower, $i)) {
                return true;
            }
        }
        if (preg_match('/(\d+)[-\s]*(?:day|araw)\s*(?:itinerary|plan|trip|itiniraryo)/i', $lower)) {
            return true;
        }

        return preg_match('/plan\s+(?:a|my|our|an?)\s+(?:trip|vacation|holiday|bakasyon)/i', $lower);
    }

    protected function hasItineraryPlanningSignals(string $lower, string $query): bool
    {
        if (preg_match('/\d+\s*(?:pax|persons?|people|tao|katao|miyembro|guests?)/i', $query)) {
            return true;
        }
        if (preg_match('/\b(couple|couples|solo|alone)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\d+[-\s]*(?:nights?|gabi|days?|araw)\b/i', $query)) {
            return true;
        }
        if (preg_match('/(?:₱|php|peso|budget)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(?:under|below|less\s*than|max|maximum)\s*(?:₱|php|peso)?\s*\d/i', $lower)) {
            return true;
        }
        if ($this->extractDateRange($query) !== null) {
            return true;
        }
        if (preg_match('/\b(plan\s+(a|my|our|an?)\s+(trip|vacation|holiday|bakasyon)|trip\s*plan|travel\s*plan|suggest an itinerary|build an itinerary|travel schedule|daily schedule)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/(\d+)[-\s]*(?:day|araw)\s*(?:itinerary|plan|trip|itiniraryo)/i', $lower)) {
            return true;
        }

        return false;
    }

    protected function hasDiscountIntent(string $lower): bool
    {
        // Direct discount queries — must be before PACKAGE fallback to avoid ROOM_SEARCH via travelKeywords
        // Covers bare "discount", Taglish "meron bang discount", "magkano discount", foreigner surcharge
        if (preg_match('/\b(discount|discounts|discounted)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(foreigner.*surcharge|surcharge.*foreigner)\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(meron|may|magkano|how much).*discount\b/i', $lower)) {
            return true;
        }
        if (preg_match('/\b(senior|student|pwd|child|infant).*\bdiscount\b/i', $lower)) {
            return true;
        }

        return false;
    }

    protected function hasPackageIntent(string $lower): bool
    {
        $pkg = [
            'package',
            'packages',
            'promo',
            'deal',
            'deals',
            'bundle',
            'tipid',
            'all-in',
            'all inclusive',
            // safe broad package signals (excluded voucher/gift certificate)
            'promo code',
            'discount code',
            'group package',
            'honeymoon package',
            'early bird',
            'flash sale',
            'limited offer',
            'seasonal promo',
        ];
        foreach ($pkg as $p) {
            if (str_contains($lower, $p)) {
                return true;
            }
        }
        if (preg_match('/promos?|(?:travel|tour)\s*(?:package|deal)/i', $lower)) {
            return true;
        }

        return false;
    }

    protected function hasAddOnIntent(string $lower): bool
    {
        $keywords = ['add-on', 'addon', 'add ons', 'transfer', 'pickup', 'surcharge', 'pricing tier', 'per pax', 'per head pricing'];
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        if (preg_match('/\b(airport\s*transfer|roundtrip\s*transfer|van\s*transfer|boat\s*transfer)\b/i', $lower)) {
            return true;
        }

        return false;
    }

    protected function hasRoomIntent(string $lower): bool
    {
        $room = ['room', 'rooms', 'suite', 'villa', 'bed', 'beds', 'occupancy', 'bedroom', 'accommodation', 'stay in', 'matulog', 'tulugan', 'kuwarto', 'kwarto', 'extra person', 'extra pax', 'additional pax', 'per head', 'per person', 'max guests', 'max occupants', 'base occupancy', 'additional charge'];
        foreach ($room as $r) {
            if (preg_match('/\b'.preg_quote($r, '/').'\b/i', $lower)) {
                return true;
            }
        }
        if (preg_match('/(?:ocean\s*view|beachfront|pool\s*view|garden\s*view|balcony|terrace)/i', $lower)) {
            return true;
        }
        // bare amenity/vibe filters after a hotel/room context (e.g., "luxury quiet pool", "family-friendly")
        if (preg_match('/\b(pool|luxury|luxurious|premium|quiet|family-friendly|family|budget-friendly|secluded|private)\b/i', $lower)) {
            return true;
        }
        // "find me something in <destination>" without explicit hotel/room word should still be searchable
        if (preg_match('/\bfind\s+me\b.*\b(in|near|at)\b/i', $lower)) {
            return true;
        }

        return false;
    }

    protected function hasHotelIntent(string $lower): bool
    {
        $hotel = ['hotel', 'hotels', 'resort', 'resorts', 'inn', 'lodge', 'hostel', 'stay at', 'stay in a', 'tuluyan', 'otol'];
        foreach ($hotel as $h) {
            if (preg_match('/\b'.preg_quote($h, '/').'\b/i', $lower)) {
                return true;
            }
        }
        if (preg_match('/best\s+(?:hotel|resort|place\s*to\s*stay)/i', $lower)) {
            return true;
        }
        // amenity-only queries also imply hotel search when no room keyword but amenity present
        if (preg_match('/\b(pool|luxury|quiet|family-friendly)\b/i', $lower) && preg_match('/\b(in|near|at|for)\b/i', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * The catalog a message explicitly names, in classify() priority order.
     * Null when the message carries no catalog keyword.
     */
    public function explicitCatalogIntent(string $query): ?string
    {
        $lower = mb_strtolower($query);
        if ($this->hasPackageIntent($lower)) {
            return self::PACKAGE_SEARCH;
        }
        if ($this->hasAddOnIntent($lower)) {
            return self::ADDON_SEARCH;
        }
        if ($this->hasRoomIntent($lower)) {
            return self::ROOM_SEARCH;
        }
        if ($this->hasHotelIntent($lower)) {
            return self::HOTEL_SEARCH;
        }
        if ($this->hasActivityIntent($lower)) {
            return self::ACTIVITY_SEARCH;
        }

        return null;
    }

    /**
     * True when the query carries an explicit keyword for any searchable
     * catalog. Used to gate the semantic-routing fallback: keyword hits always
     * win, embeddings only decide genuinely ambiguous queries.
     */
    public function hasExplicitCatalogIntent(string $query): bool
    {
        $lower = mb_strtolower($query);

        return $this->hasRoomIntent($lower)
            || $this->hasHotelIntent($lower)
            || $this->hasActivityIntent($lower)
            || $this->hasPackageIntent($lower)
            || $this->hasAddOnIntent($lower);
    }

    /**
     * True for noun-catalog keywords (activity/package/add-on) whose hit is
     * rarely incidental. Room/hotel intent is excluded: amenity words like
     * "family" or "pool" appear in genuine follow-ups ("is it family
     * friendly?") and must not force a fresh search.
     */
    public function hasNounCatalogIntent(string $query): bool
    {
        $lower = mb_strtolower($query);

        return $this->hasActivityIntent($lower)
            || $this->hasPackageIntent($lower)
            || $this->hasAddOnIntent($lower);
    }

    protected function hasActivityIntent(string $lower): bool
    {
        $act = [
            'activity',
            'activities',
            'tour',
            'tours',
            'island hopping',
            'diving',
            'scuba',
            'snorkeling',
            'hiking',
            'trek',
            'surfing',
            'kayak',
            'zipline',
            'atv',
            'thing to do',
            'things to do',
            'attraction',
            'attractions',
            'adventure',
            'gawain',
            'pasyalan',
            'libangan',
            // safe broad activity signals (excluded sup/zoo/waterfall etc. — DB-missing, sup substring risky)
            'paddleboarding',
            'cliff jumping',
            'cave exploring',
            'spelunking',
            'cultural tour',
            'sunset cruise',
            // missing-from-keyword-gap: DB has these names but they were never routed to ACTIVITY_SEARCH
            'banana boat',
            'parasailing',
            'parasail',
            'paraw',
            'paraw sailing',
            'jet ski',
            'jetski',
            'ufo',
            'ufo ride',
            'helmet diving',
            'crystal kayak',
            'paddleboard',
            'canopy walk',
            'nacpan',
            'puka',
            'cliff dive',
        ];
        foreach ($act as $a) {
            if (str_contains($lower, $a)) {
                return true;
            }
        }

        return false;
    }

    protected function hasDestinationsOverviewIntent(string $lower): bool
    {
        if (str_contains($lower, 'about sunnytrips') || str_contains($lower, 'what is sunnytrips') || str_contains($lower, 'tell me about sunny')) {
            return true;
        }

        if (preg_match('/\bdestinations?\b/', $lower) && preg_match('/\b(you|your|sunnytrips|sunny|trips|we|our|offer|offers|offered|offering|provide|provides|provided|available|have|has|listing|list)\b/', $lower)) {
            return true;
        }

        if (preg_match('/\b(what|which)\b.*\bdestinations?\b/', $lower)) {
            return true;
        }

        if (preg_match('/\bwhere\b.*\b(you operate|you cover|can i go|can we go|do you have)\b/', $lower)) {
            return true;
        }

        if (preg_match('/\bwhat places\b|\bwhich places\b|\bplaces do you\b/', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * User-scoped booking lookup ("status of my booking"), not a catalog
     * search ("book a room"). Possessive adjacency or status words required.
     */
    protected function hasBookingStatusIntent(string $lower): bool
    {
        if (preg_match('/\b(my|our)\s+(booking|reservation)\b/', $lower)) {
            return true;
        }

        if (preg_match('/\b(booking|reservation)\s+(status|code|reference|details?|number|approved|confirmed|rejected|cancelled|paid)\b/', $lower)) {
            return true;
        }

        if (preg_match("/\bstatus\s+of\s+(my|the|this)\s+(booking|reservation)\b/", $lower)) {
            return true;
        }

        return (bool) preg_match("/\bwhere(?:'s|\s+is)\s+my\s+(booking|reservation)\b/", $lower);
    }

    /**
     * Deterministic legal/contact lookup (privacy, terms, AI disclosure,
     * contact info). Checked right after the human-agent intent so
     * "talk to an agent" still wins over "contact info".
     */
    protected function hasLegalIntent(string $lower): bool
    {
        // "in terms of X" is comparative phrasing, not the legal document.
        $hasLegalAnchor = (bool) preg_match('/\b(privacy|terms|condition|disclosure|contact|hotline|email|e-mail|telephone|cellphone|address|located|location)\b/', $lower);
        if (str_contains($lower, 'in terms of') && ! $hasLegalAnchor) {
            return false;
        }
        if (str_contains($lower, 'in terms of') && preg_match('/\b(privacy|disclosure|contact|hotline|email|address)\b/', $lower) === 0
            && preg_match('/\bterms\s+(and\s+conditions|of\s+(service|use))\b/', $lower) === 0) {
            return false;
        }

        // Weather "conditions" are not legal terms.
        if (preg_match('/\bweather\b/', $lower) && preg_match('/\b(privacy|terms|contact|email|hotline|address|disclosure)\b/', $lower) === 0) {
            return false;
        }

        if (preg_match('/\bprivacy(\s+polic\w*)?\b/', $lower)) {
            return true;
        }

        if (preg_match('/\bterms\s+(and\s+conditions|of\s+(service|use))\b/', $lower)) {
            return true;
        }

        if (preg_match('/\bterms\b/', $lower) && preg_match('/\b(conditions?|service|use|polic\w*|agree)\b/', $lower)) {
            return true;
        }

        if (preg_match('/\b(ai(\s+usage)?\s+disclosure|artificial\s+intelligence(\s+disclosure)?)\b/', $lower)) {
            return true;
        }

        if (preg_match('/\bcontact(\s+(us|info|information|number|details?|email|address))?\b/', $lower)) {
            return true;
        }

        if (preg_match('/\b(e-?mail|hotline|telephone|cellphone|contact\s+number|phone\s+number)\b/', $lower)) {
            return true;
        }

        if (preg_match('/\bhow\s+(do|can)\s+(i|we)\s+(contact|reach)\b/', $lower)) {
            return true;
        }

        return (bool) preg_match('/\b(where\s+are\s+you\s+located|your\s+(address|location|office|contact))\b/', $lower);
    }

    /**
     * User is explicitly asking for a human/agent rather than the bot.
     */
    protected function hasSupportAgentIntent(string $lower): bool
    {
        if (preg_match('/\b(talk|speak|chat|connect|message)\s+(to|with)\s+(a\s+|the\s+|an\s+)?(human|person|people|agent|someone|somebody|staff|representative|rep|support|admin|team)\b/', $lower)) {
            return true;
        }

        if (preg_match('/\b(live|real|actual|human|customer)\s+(agent|support|person|representative|rep|service)\b/', $lower)) {
            return true;
        }

        return (bool) preg_match('/\b(totoong\s+tao|kausapin\s+ang\s+tao|makipag-?usap\s+sa\s+tao)\b/u', $lower);
    }

    /**
     * Extract a booking code mentioned in the query (e.g., "ST-2026-ABCDE").
     * The handler validates the extracted token against the user's bookings.
     */
    public function extractBookingCode(string $query): ?string
    {
        if (preg_match('/\b(?:booking|reservation|confirmation)\s*(?:code|number|no\.?|#|id|ref(?:erence)?)?\s*(?:is\s+|:|=)?\s*([A-Za-z0-9][A-Za-z0-9\-]{3,24})/i', $query, $m)) {
            $token = strtoupper(trim($m[1]));
            // Real booking codes always contain a digit or hyphen — rejects bare words like "status"
            if (str_contains($token, '-') || preg_match('/\d/', $token)) {
                return $token;
            }
        }

        return null;
    }
}
