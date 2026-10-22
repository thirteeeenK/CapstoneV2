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

    /**
     * A non-travel task SunnyBot declines gracefully without calling the
     * model (writing code, math or homework, essays).
     */
    public const OUT_OF_SCOPE = 'OUT_OF_SCOPE';

    /**
     * Programming vocabulary: always off-topic, even next to travel words
     * ("a python script to scrape hotel prices"). "php" is left out on
     * purpose — it is also the peso — and a bare "hello world" stays a
     * greeting unless it asks for code.
     */
    protected const CODING_PATTERNS = [
        '/(?<![\w+])c\+\+(?![\w+])|(?<![\w#])c#(?![\w#])/u',
        '/\b(?:python|javascript|typescript|html|css|sql|kotlin|golang|laravel|node\.?js|powershell|matlab)\b/u',
        '/\bjava\s+(?:code|program|class|script|function|app)\b/u',
        '/\b(?:source\s+code|programming|coding|leetcode|regex|regular\s+expression|compiler|debug(?:ging)?|syntax\s+error|stack\s+trace)\b/u',
        '/\b(?:write|create|generate|make|build|give)\s+(?:me\s+)?(?:a\s+|an\s+|some\s+|the\s+)?(?:\S+\s+){0,3}?code\b/u',
        '/\b(?:write|create|make|generate|give|print|code)\b.{0,40}\bhello\s+world\b|\bhello\s+world\b.{0,20}\b(?:code|program|script|app)\b/u',
    ];

    /**
     * Travel "codes" that must never read as programming ("promo code",
     * "the code for the wifi").
     */
    protected const NON_PROGRAMMING_CODE = '/\b(?:promo|discount|booking|confirmation|reference|reservation|voucher|coupon|gift|referral|access|verification|otp|qr|zip|postal|area|country|dress|tracking|door|pin|room|wifi|wi-fi|flight|hotel|ticket|transaction|payment|gcash|bank)\s+codes?\b|\bcodes?\s+(?:for|of|to)\s+(?:the\s+|my\s+|our\s+)?(?:wifi|wi-fi|door|room|safe|gate|locker|parking|discount|promo|voucher|booking)\b/u';

    /**
     * Math and homework asks. Only off-topic when the message carries no
     * travel signal ("2+2" yes; "divide the cost for 4 people" no).
     */
    protected const MATH_PATTERNS = [
        '/\b(?:solve|equations?|algebra|calculus|derivatives?|integrals?|trigonometry|geometry|arithmetic|factorial|square\s+root|homework|assignment|quiz|exam)\b/u',
        '/\d+(?:\.\d+)?\s*[\+\*\/×÷^]\s*\d+(?:\.\d+)?/u',
        '/\bwhat(?:\'s|\s+is)\s+\d+(?:\.\d+)?\s*(?:plus|minus|times|divided\s+by|multiplied\s+by)\s*\d+/u',
    ];

    /**
     * Non-travel writing asks. Only off-topic without a travel signal, so
     * "write a caption for my Boracay trip" still reaches the model.
     */
    protected const WRITING_PATTERN = '/\b(?:write|compose|draft|make)\s+(?:me\s+)?(?:a\s+|an\s+|my\s+|the\s+)?(?:\S+\s+){0,2}?(?:essay|poem|story|song|lyrics|letter|speech|report|resume|cv|cover\s+letter|article|research\s+paper|thesis|novel|reaction\s+paper)\b/u';

    /**
     * Health asks that are really travel logistics. Checked before every
     * other health rule so "nearest hospital in Boracay", an accessible
     * room, or insurance paperwork still reaches the normal pipeline.
     */
    protected const HEALTH_LOGISTICS_PATTERNS = [
        '/\b(?:nearest|nearby|closest|any|may|meron|is\s+there)\b[^.?!]{0,60}\b(?:hospital|clinic|pharmacy|pharmacies|drugstore|drug\s*store|doctor|dentist|medical\s+(?:center|centre|facility)|health\s+(?:center|centre)|botika|ospital)\b/iu',
        '/\b(?:hospital|clinic|pharmacy|pharmacies|drugstore|drug\s*store|medical\s+(?:center|centre|facility)|health\s+(?:center|centre)|botika|ospital)s?\s+(?:in|near|nearby|around|at|close\s+to|sa)\b/iu',
        '/\b(?:wheelchair|accessib(?:le|ility)|disab(?:led|ility)|pwd|senior\s+citizen|mobility\s+(?:aid|issue)|ramp|elevator|lift)\b/iu',
        '/\b(?:travel\s+insurance|health\s+declaration|vaccin(?:e|es|ation|ated)|booster\s+shot|covid\s+(?:requirements?|test|protocols?)|medical\s+certificate|fit\s+to\s+(?:fly|travel)|first\s+aid\s+kit|emergency\s+(?:contact|exit|number|hotline))\b/iu',
        '/\b(?:dietary|allerg(?:y|ies)[-\s]?friendly|halal|vegan|vegetarian|gluten[-\s]?free)\b/iu',
    ];

    /**
     * First-person crisis disclosures. These are a health emergency, never
     * abuse: they bypass the moderation keyword list so no report, flag or
     * ban is created.
     */
    protected const HEALTH_CRISIS_PATTERN = '/\b(?:kill(?:ing)?\s+myself|killing\s+my\s*self|end(?:ing)?\s+my\s+(?:own\s+)?life|end\s+it\s+all|take\s+my\s+own\s+life|want(?:ing)?\s+to\s+die|wanna\s+die|suicidal|commit(?:ting)?\s+suicide|self[\s-]?harm|hurt(?:ing)?\s+myself|cut(?:ting)?\s+myself|magpakamatay|ayoko\s+nang?\s+mabuhay|gusto\s+ko\s+nang?\s+mamatay|mamatay\s+na\s+lang\s+ako)\b/iu';

    /**
     * Acute symptoms and emergencies that need help now, not a catalog
     * lookup or a diagnosis.
     */
    protected const HEALTH_URGENT_PATTERN = '/\b(?:chest\s+pains?|can\x27?t\s+breathe|cannot\s+breathe|(?:trouble|difficulty)\s+breathing|shortness\s+of\s+breath|heart\s+attack|having\s+a\s+stroke|seizure|unconscious|passed\s+out|collapsed|severe(?:ly)?\s+bleeding|bleeding\s+(?:a\s+lot|heavily|badly|non[\s-]?stop)|broken\s+(?:bone|arm|leg|ankle|rib)|allergic\s+reaction|anaphyla\w*|snake\s+bite|box\s+jellyfish|drowning|drowned|heat\s*stroke|severe\s+(?:pain|dehydration)|overdose|poisoned|needs?\s+an\s+ambulance|call\s+an\s+ambulance|emergency\s+room|rush(?:ed)?\s+to\s+the\s+hospital|nahimatay|hindi\s+makahinga|sobrang\s+(?:lagnat|sakit)|dinudugo)\b/iu';

    /**
     * Medical questions SunnyBot must decline with a capability boundary:
     * symptoms, medication, dosage, diagnosis and treatment.
     */
    protected const HEALTH_ADVICE_PATTERNS = [
        '/\b(?:what|which|anong?)\s+(?:kind\s+of\s+)?(?:medicine|medication|meds|drug|gamot|pills?|tablets?)\b/iu',
        '/\b(?:dosage|how\s+many\s+(?:mg|tablets?|pills?)|paracetamol|ibuprofen|mefenamic|antibiotics?|antihistamine|loperamide|biogesic)\b/iu',
        '/\b(?:do|might)\s+i\s+have\s+(?:dengue|covid|the\s+flu|an?\s+infection|food\s+poisoning|appendicitis)\b/iu',
        '/\b(?:diagnos(?:e|is|ed)|symptoms?\s+of|treatment\s+for|cure\s+for|home\s+remedy)\b/iu',
        '/\bis\s+(?:it|this|that)\s+(?:safe|okay|ok)\s+to\s+(?:take|mix|drink)\b/iu',
        '/\bwhat\s+should\s+i\s+(?:take|do)\b[^.?!]{0,60}\b(?:pain|pains|ache|aches|fever|headache|sick|ill|nausea|vomit\w*|diarrh\w+|rash)\b/iu',
        '/\b(?:pain|pains|ache|aches|fever|headache|migraine|sick|nausea|vomit\w*|diarrh\w+|rash)\b[^.?!]{0,60}\bwhat\s+should\s+i\s+(?:take|do)\b/iu',
        '/\bi\s+(?:have|got|am\s+having)\s+(?:a\s+|an\s+|bad\s+|severe\s+)*(?:fever|headache|migraine|rash|toothache|stomach\s*ache|diarrhea|diarrhoea|sore\s+throat|infection)\b/iu',
        '/\b(?:stomach|body|back|muscle|joint|tooth|head|ear)\s+(?:pain|pains|ache|aches)\b/iu',
        '/\bi(?:\x27m|\s+am)\s+(?:feeling\s+)?(?:sick|ill|nauseous|dizzy|vomiting)\b/iu',
        '/\b(?:masakit\s+ang|may\s+lagnat|nilalagnat|nagsusuka|nagtatae|gamot\s+(?:sa|para\s+sa))\b/iu',
    ];

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

        if ($this->outOfScopeTopic($query, strongOnly: true) !== null) {
            return self::OUT_OF_SCOPE;
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

        // Catalog names are data, not a fixed keyword list. This keeps newly
        // added activities searchable even when their names use new wording.
        if ($this->extractActivityName($query)) {
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

        if ($this->outOfScopeTopic($query) !== null) {
            return self::OUT_OF_SCOPE;
        }

        return self::GENERAL_TALK;
    }

    /**
     * The kind of non-travel task a message asks for, or null. Coding is
     * off-topic everywhere; math/homework and essay-style writing only when
     * the message has no travel signal, so trip arithmetic and travel
     * writing still reach the normal pipeline. $strongOnly limits the check
     * to coding (used where travel context cannot be trusted to gate).
     *
     * @return 'coding'|'math'|'writing'|null
     */
    public function outOfScopeTopic(string $query, bool $strongOnly = false): ?string
    {
        $lower = mb_strtolower($query);
        $codeText = (string) preg_replace(self::NON_PROGRAMMING_CODE, ' ', $lower);
        foreach (self::CODING_PATTERNS as $pattern) {
            if (preg_match($pattern, $codeText)) {
                return 'coding';
            }
        }

        if ($strongOnly || $this->hasTravelSignal($query)) {
            return null;
        }

        foreach (self::MATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $lower)) {
                return 'math';
            }
        }

        return preg_match(self::WRITING_PATTERN, $lower) ? 'writing' : null;
    }

    /**
     * True when a message mentions anything travel-related: a travel
     * keyword, a catalog keyword, or a known destination.
     */
    protected function hasTravelSignal(string $query): bool
    {
        return $this->isTravelQuery($query)
            || $this->hasExplicitCatalogIntent($query)
            || $this->extractDestinationName($query) !== null;
    }

    /**
     * Feature vocabulary a user can rule out. Multiword entries are matched
     * before single words so "no island hopping" never reduces to "island".
     */
    private const EXCLUDABLE_FEATURES = [
        'water activities', 'water activity', 'land tours', 'land tour',
        'island hopping', 'scuba diving', 'free diving', 'jet ski', 'banana boat',
        'party boat', 'pub crawl', 'night life', 'nightlife', 'early call time',
        'swimming pool', 'infinity pool', 'kids pool', 'shared bathroom',
        'shared room', 'buffet breakfast', 'street food', 'long hikes',
        'water sports', 'extreme sports', 'air conditioning', 'air con',
        'diving', 'snorkelling', 'snorkeling', 'snorkel', 'hiking', 'trekking',
        'surfing', 'swimming', 'kayaking', 'parasailing', 'ziplining', 'zipline',
        'atv', 'massage', 'spa', 'pool', 'wifi', 'wi-fi', 'aircon', 'breakfast',
        'buffet', 'karaoke', 'boat', 'bar', 'club', 'crowds', 'crowded',
        'smoking', 'pets', 'stairs', 'alcohol', 'seafood',
    ];

    /** Plural phrasings normalized to the catalog's category labels. */
    private const FEATURE_ALIASES = ['water activities' => 'water activity', 'land tours' => 'land tour'];

    /**
     * Wording that rules the rest of a span out.
     */
    private const EXCLUSION_NEGATOR = 'no|not|without|wala(?:ng)?|ayaw(?:\s+ko)?(?:\s+ng)?|ayoko\s+ng|huwag(?:\s+ng)?|wag(?:\s+ng)?|skip|exclude|excluding|except|avoid|minus|don\x27t\s+want|do\s+not\s+want|rather\s+not|hindi\s+kasama';

    /**
     * Comparison wording that makes the following span a number, not a thing
     * to exclude ("not more than 3000" is a budget ceiling).
     */
    private const EXCLUSION_NUMERIC_LEAD = 'more|less|over|under|above|below|beyond|exceeding|greater|higher|lower|than|too';

    /**
     * What the user ruled out, parsed before any positive entity match so a
     * negated catalog name can never become a positive detail task.
     *
     * Features resolve to normalized terms, catalog items to their exact
     * names, and anything recognisably excluded but unresolvable is returned
     * as unclear so the caller can ask instead of guessing.
     *
     * @return array{terms: list<string>, names: list<string>, unclear: list<string>}
     */
    public function extractExclusions(string $query): array
    {
        $negator = self::EXCLUSION_NEGATOR;
        $terms = [];
        $names = [];
        $unclear = [];

        // The span may not run across another negator, so "no diving and no
        // hiking" yields two exclusions rather than one long one.
        $spanPattern = '((?:(?!\b(?:'.$negator.')\b)[^,.;?!]){1,60})';

        if (! preg_match_all('/\b(?:'.$negator.')\s+(?:any\s+|some\s+|the\s+|a\s+|an\s+|my\s+|our\s+)?'.$spanPattern.'/iu', $query, $matches)) {
            return ['terms' => [], 'names' => [], 'unclear' => []];
        }

        $catalogNames = $this->excludableCatalogNames();

        foreach ($matches[1] as $span) {
            $span = mb_strtolower(trim($span));
            // Cut the span at the next connector so "no diving but yes to atv"
            // only excludes diving.
            $span = (string) preg_replace('/\s+(?:but|and|or|pero|at|in|sa|for|with|please|lang|na|po)\b.*$/iu', '', $span);
            $span = trim((string) preg_replace('/[^\p{L}\p{N}\s\'&-]/u', ' ', $span));
            $span = trim((string) preg_replace('/\s+/', ' ', $span));

            if ($span === '' || preg_match('/^(?:'.self::EXCLUSION_NUMERIC_LEAD.')\b/iu', $span) || preg_match('/^\d/u', $span)) {
                continue;
            }

            $matchedName = null;
            foreach ($catalogNames as $name) {
                if ($this->containsName($span, $name)) {
                    $matchedName = $name;
                    break;
                }
            }
            if ($matchedName !== null) {
                $names[] = $matchedName;

                continue;
            }

            $matchedFeature = null;
            foreach (self::EXCLUDABLE_FEATURES as $feature) {
                if ($this->containsName($span, $feature)) {
                    $matchedFeature = $feature;
                    break;
                }
            }
            if ($matchedFeature !== null) {
                // Catalog categories are stored singular ("Water Activity").
                $terms[] = self::FEATURE_ALIASES[$matchedFeature] ?? $matchedFeature;

                continue;
            }

            // Category-level exclusions ("no hotels") are handled by the
            // planner's intent filter, not here.
            if (preg_match('/^(?:hotels?|rooms?|activities|activity|tours?|packages?|transfers?|add[\s-]?ons?)\b/iu', $span)) {
                continue;
            }

            $unclear[] = $span;
        }

        return [
            'terms' => array_values(array_unique($terms)),
            'names' => array_values(array_unique($names)),
            'unclear' => array_values(array_unique($unclear)),
        ];
    }

    /**
     * Catalog names long enough to be excluded by name, longest first so a
     * multiword name wins over a shorter one it contains.
     *
     * @return list<string>
     */
    private function excludableCatalogNames(): array
    {
        $names = [];
        foreach ([
            [ActivityModel::class, 'activity_name'],
            [AddOnModel::class, 'name'],
            [HotelModel::class, 'hotel_name'],
            [RoomType::class, 'room_name'],
        ] as [$model, $column]) {
            try {
                foreach ($model::pluck($column)->all() as $name) {
                    $name = trim(mb_strtolower((string) $name));
                    if (mb_strlen($name) >= 3) {
                        $names[] = $name;
                    }
                }
            } catch (\Throwable) {
                // Table may not exist in a minimal test database.
            }
        }

        usort($names, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_values(array_unique($names));
    }

    /**
     * Health and safety asks that must be answered deterministically, before
     * fuzzy catalog matching or the moderation keyword list can act on them.
     *
     * Travel logistics around health (a clinic near a destination, an
     * accessible room, insurance paperwork) are not health concerns and fall
     * through to the normal pipeline.
     *
     * @return 'crisis'|'urgent'|'advice'|null
     */
    public function healthConcern(string $query): ?string
    {
        $normalized = (string) preg_replace('/\s+/', ' ', trim($query));

        if ($normalized === '') {
            return null;
        }

        if (preg_match(self::HEALTH_CRISIS_PATTERN, $normalized)) {
            return 'crisis';
        }

        if (preg_match(self::HEALTH_URGENT_PATTERN, $normalized)) {
            return 'urgent';
        }

        foreach (self::HEALTH_LOGISTICS_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return null;
            }
        }

        foreach (self::HEALTH_ADVICE_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return 'advice';
            }
        }

        return null;
    }

    /**
     * Wording that makes a stated amount a hard ceiling. Shared by the amount
     * parser and detectBudgetMode() so an operator the mode detector knows
     * can never be one the amount parser silently ignores.
     */
    private const BUDGET_CEILING_LEAD = 'under|below|less\s+than|lower\s+than|not\s+more\s+than|no\s+more\s+than|not\s+over|not\s+exceeding|max(?:imum)?|at\s+most|up\s+to|within|hanggang';

    /**
     * Wording that makes a stated amount a target to plan around.
     */
    private const BUDGET_TARGET_LEAD = 'budget|around|about|roughly|approx(?:imate(?:ly)?)?|more\s+or\s+less';

    /**
     * Units that make a following number a count, not money ("max 4 guests",
     * "up to 2 hours", "within 5 km").
     */
    private const NON_PRICE_UNIT = 'pax|persons?|people|guests?|adults?|kids|children|occupants?|tao|nights?|days?|araw|gabi|weeks?|months?|rooms?|beds?|bedrooms?|baths?|hours?|hrs?|mins?|minutes?|km|kms|kilometers?|meters?|stars?|years?|yrs?|pcs?|pieces?';

    /**
     * How a stated budget should be read: a hard ceiling ("under ₱20,000",
     * "max 15k", "20k lang") or a target to plan around ("₱20,000 budget",
     * "budget of 20k", "around 20k").
     *
     * @return 'ceiling'|'target'
     */
    public function detectBudgetMode(string $query): string
    {
        $ceilingBeforeAmount = '/\b(?:'.self::BUDGET_CEILING_LEAD.')\s*(?:of\s+|is\s+|[:=]\s*)?(?:₱|php|peso|p(?=\s*\d))?\s*\d/iu';
        $ceilingAfterAmount = '/\d[\d,.]*\s*k?\s*(?:pesos?|php)?\s+(?:max(?:imum)?|at\s+most|only|lang|tops)\b/iu';
        $ceilingNoun = '/\b(?:max(?:imum)?|limited)\s+budget\b|\bbudget\s+(?:limit|cap|ceiling)\b/iu';
        $ceilingAfterBasis = '/\d[\d,.]*\s*k?\s*(?:per|a|an|each|\/)\s*(?:night|person|pax|head|day)\s+(?:max(?:imum)?|or\s+less|at\s+most|tops|only|lang)\b/iu';

        return preg_match($ceilingBeforeAmount, $query) || preg_match($ceilingAfterAmount, $query) || preg_match($ceilingNoun, $query) || preg_match($ceilingAfterBasis, $query)
            ? 'ceiling'
            : 'target';
    }

    /**
     * The one money parser: amount, currency and operator in a single place.
     *
     * Handles currency markers, comma grouping, decimal-point grouping
     * ("20.000"), "k" notation and every ceiling/target operator
     * detectBudgetMode() recognizes. Counts of travelers, nights, rooms or
     * distances are never read as money.
     *
     * @return array{amount: int, mode: 'ceiling'|'target'}|null
     */
    public function extractBudget(string $query): ?array
    {
        $lead = self::BUDGET_CEILING_LEAD.'|'.self::BUDGET_TARGET_LEAD;
        $unit = self::NON_PRICE_UNIT;
        $currency = '(?:₱|php|peso|p(?=\s*\d))?';
        $digits = '(\d[\d,.]{0,11})';
        $amount = null;

        // "under ₱20,000", "budget of 20k", "within 20000", "hanggang 15k".
        if (preg_match('/\b(?:'.$lead.')\s*(?:of\s*|is\s*|na\s*|[:=]\s*)?'.$currency.'\s*'.$digits.'\s*(k)?\b(?!\s*(?:'.$unit.')\b)/iu', $query, $m)) {
            $amount = $this->normalizeAmount($m[1], ! empty($m[2]));
        }

        // "20,000 budget", "20k max", "15000 pesos", "20000 lang".
        if ($amount === null && preg_match('/'.$currency.'\s*'.$digits.'\s*(k)?\s*(?:budget|max(?:imum)?|pesos?|php|lang|only|tops)\b/iu', $query, $m)) {
            $amount = $this->normalizeAmount($m[1], ! empty($m[2]));
        }

        // A trailing ceiling after the basis: "3000 per night max",
        // "1500 a person or less".
        if ($amount === null && preg_match('/'.$currency.'\s*'.$digits.'\s*(k)?\s*(?:per|a|an|each|\/)\s*(?:night|person|pax|head|day)\s*(?:max(?:imum)?|or\s+less|at\s+most|tops|lang|only)\b/iu', $query, $m)) {
            $amount = $this->normalizeAmount($m[1], ! empty($m[2]));
        }

        // A currency marker on its own is enough: "₱20,000", "P20000".
        if ($amount === null && preg_match('/(?:₱|\bphp\s*|\bpeso\s*|\bp(?=\d))\s*'.$digits.'\s*(k)?\b(?!\s*(?:'.$unit.')\b)/iu', $query, $m)) {
            $amount = $this->normalizeAmount($m[1], ! empty($m[2]));
        }

        // Bare "k" notation: "20k", "15k per head".
        if ($amount === null && preg_match('/\b(\d+(?:\.\d+)?)\s*k\b(?!\s*(?:'.$unit.')\b)/iu', $query, $m)) {
            $amount = (int) round((float) $m[1] * 1000);
        }

        if ($amount === null || $amount <= 0) {
            return null;
        }

        return ['amount' => $amount, 'mode' => $this->detectBudgetMode($query)];
    }

    /**
     * Normalizes a captured amount: comma grouping, dot grouping ("1.250.000")
     * and decimal centavos all resolve to whole pesos.
     */
    private function normalizeAmount(string $raw, bool $thousands): int
    {
        $raw = trim($raw, " \t.,");

        if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $raw)) {
            $raw = str_replace('.', '', $raw);
        }

        return (int) round((float) str_replace(',', '', $raw) * ($thousands ? 1000 : 1));
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
     * Boundary-aware catalog-name match.
     *
     * A plain substring test lets a short catalog name align with the inside
     * of an unrelated word — "ain" inside "mountain" or "pains", "aircon"
     * inside "airconditioned" — which pins an entity from thin air. The name
     * must sit on word boundaries instead, with an optional plural so
     * "ATV rides" still resolves "ATV ride". Short exact names ("ATV",
     * "DSD") keep working because a boundary match needs no minimum length.
     */
    protected function containsName(string $haystack, string $name): bool
    {
        $name = trim(mb_strtolower($name));
        if ($name === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?:e?s)?(?![\p{L}\p{N}])/u';

        return (bool) preg_match($pattern, mb_strtolower($haystack));
    }

    /**
     * The query with every negated span removed.
     *
     * Positive entity extraction runs on this, so a ruled-out catalog item
     * ("without the Party Boat Cruise") can never be resolved as the thing
     * the user is asking for.
     */
    public function withoutNegatedSpans(string $query): string
    {
        $negator = self::EXCLUSION_NEGATOR;
        $span = '(?:(?!\b(?:'.$negator.')\b)[^,.;?!]){1,60}';

        $stripped = (string) preg_replace(
            '/\b(?:'.$negator.')\s+(?:any\s+|some\s+|the\s+|a\s+|an\s+|my\s+|our\s+)?'.$span.'/iu',
            ' ',
            $query
        );

        return trim((string) preg_replace('/\s+/', ' ', $stripped));
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

    /** @return array<string, mixed> */
    public function dialogueConstraints(string $query, ?string $field = null): array
    {
        $words = ['one' => 1, 'isa' => 1, 'two' => 2, 'dalawa' => 2, 'three' => 3, 'tatlo' => 3, 'four' => 4, 'apat' => 4, 'five' => 5, 'lima' => 5, 'six' => 6, 'anim' => 6, 'seven' => 7, 'pito' => 7, 'eight' => 8, 'walo' => 8, 'nine' => 9, 'siyam' => 9, 'ten' => 10, 'sampu' => 10];
        $normalized = preg_replace_callback('/\b('.implode('|', array_keys($words)).')\b(?=\s*(?:kami|pax|people|persons?|guests?|adults?|children|kids|days?|araw|nights?|gabi)\b)/iu', fn (array $m): string => (string) $words[mb_strtolower($m[1])], $query);
        $constraints = $this->extractConstraints($normalized);
        if (empty($constraints['max_price'])) {
            $budget = $this->extractBudget($query);
            if ($budget !== null) {
                $constraints['max_price'] = $budget['amount'];
                $constraints['budget_mode'] = $budget['mode'];
            }
        }
        if (preg_match('/^\s*(\d+|'.implode('|', array_keys($words)).')[.!]?\s*$/iu', $query, $m)) {
            $number = is_numeric($m[1]) ? (int) $m[1] : $words[mb_strtolower($m[1])];
            if (in_array($field, ['pax', 'days', 'nights'], true)) {
                $constraints[$field] = $number;
            }
        }
        if (preg_match_all('/(\d+)\s*(?:adults?|children|kids)\b/iu', $normalized, $counts) && count($counts[1]) > 1) {
            $constraints['pax'] = array_sum(array_map('intval', $counts[1]));
        }
        if (! empty($constraints['days']) && ! preg_match('/\d+\s*(?:nights?|gabi)\b/iu', $normalized)) {
            $constraints['nights'] = null;
        }
        // Narrower dimensions a basis alone does not settle: "per person"
        // for a stay still leaves per night vs for the whole stay open, and a
        // nightly itinerary budget may cover lodging only.
        $constraints['budget_period'] = match (true) {
            (bool) preg_match('/\bper\s*night\b|\/night\b|\ba\s+night\b|\beach\s+night\b|\bbawat\s*gabi\b|\bkada\s*gabi\b/iu', $query) => 'per_night',
            (bool) preg_match('/\b(?:whole|entire|total|buong|all)\s+(?:stay|trip|booking|nights)\b|\bfor\s+the\s+(?:whole|entire|total)\b|\baltogether\b/iu', $query) => 'total',
            default => null,
        };
        $constraints['budget_coverage'] = match (true) {
            (bool) preg_match('/\b(?:hotel|room|stay|lodging|accommodation)s?\s+only\b|\b(?:just|only)\s+the\s+(?:hotel|room|stay|lodging)\b|\bhotel\s+lang\b/iu', $query) => 'lodging',
            (bool) preg_match('/\b(?:everything|all[\s-]?in(?:clusive)?|including\s+(?:the\s+)?activities|with\s+activities|kasama\s+(?:ang\s+)?(?:activities|lahat))\b/iu', $query) => 'all_inclusive',
            default => null,
        };
        $constraints['budget_basis'] = match (true) {
            (bool) preg_match('/per\s*(?:person|pax|head)|\/person|bawat\s*tao/iu', $query) => 'per_person',
            (bool) preg_match('/per\s*night|\/night|bawat\s*gabi/iu', $query) => 'per_night',
            (bool) preg_match('/per\s*activity/iu', $query) => 'per_activity',
            (bool) preg_match('/\b(?:total|overall|combined|whole\s*trip|for\s*both|kabuuan|lahat)\b/iu', $query) => 'trip_total',
            default => null,
        };
        if ($field === 'budget_period' && $constraints['budget_basis'] === 'trip_total') {
            $constraints['budget_basis'] = null;
        }
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\s*(?:to|until|through|–|-)\s*(\d{4}-\d{2}-\d{2})\b/u', $query, $dates)) {
            [$constraints['check_in_date'], $constraints['check_out_date']] = [$dates[1], $dates[2]];
        } elseif (preg_match('/\b([a-z]+)\s+(\d{1,2})\s*(?:to|–|-)\s*(\d{1,2}),?\s+(\d{4})\b/iu', $query, $dates)) {
            try {
                $constraints['check_in_date'] = Carbon::parse($dates[1].' '.$dates[2].' '.$dates[4])->toDateString();
                $constraints['check_out_date'] = Carbon::parse($dates[1].' '.$dates[3].' '.$dates[4])->toDateString();
                if ((int) Carbon::parse($constraints['check_in_date'])->day !== (int) $dates[2]
                    || (int) Carbon::parse($constraints['check_out_date'])->day !== (int) $dates[3]) {
                    $constraints['invalid_dates'] = true;
                }
            } catch (\Throwable) {
                $constraints['invalid_dates'] = true;
            }
        } elseif ($field === 'dates' && preg_match('/\d+[\/-]\d+[\/-]\d+/', $query)) {
            $constraints['invalid_dates'] = true;
        }
        if (! empty($constraints['check_in_date']) && ! empty($constraints['check_out_date'])) {
            try {
                $in = Carbon::parse($constraints['check_in_date'])->startOfDay();
                $out = Carbon::parse($constraints['check_out_date'])->startOfDay();
                if ($in->year !== $out->year && ! preg_match('/\b\d{4}\b/u', $query)) {
                    $constraints['invalid_dates'] = true;
                }
                if ($in->toDateString() !== $constraints['check_in_date'] || $out->toDateString() !== $constraints['check_out_date'] || $in->lt(Carbon::today()) || $out->lte($in)) {
                    $constraints['invalid_dates'] = true;
                } else {
                    $constraints['nights'] = (int) $in->diffInDays($out);
                    if (preg_match('/\b(\d+)\s*days?\b/iu', $query, $duration) && (int) $duration[1] !== $constraints['nights'] + 1) {
                        $constraints['invalid_dates'] = true;
                    }
                    if (preg_match('/\b(\d+)\s*nights?\b/iu', $query, $duration) && (int) $duration[1] !== $constraints['nights']) {
                        $constraints['invalid_dates'] = true;
                    }
                    $constraints['days'] = $constraints['nights'] + 1;
                }
            } catch (\Throwable) {
                $constraints['invalid_dates'] = true;
            }
        }
        if (! empty($constraints['invalid_dates'])) {
            $constraints['check_in_date'] = null;
            $constraints['check_out_date'] = null;
            $constraints['nights'] = null;
        }
        if ($constraints['pax'] !== null && $constraints['pax'] <= 0) {
            $constraints['invalid_pax'] = true;
            $constraints['pax'] = null;
        }
        if (($constraints['days'] !== null && $constraints['days'] <= 0) || ($constraints['nights'] !== null && $constraints['nights'] <= 0)) {
            $constraints['invalid_duration'] = true;
            $constraints['days'] = null;
            $constraints['nights'] = null;
        }

        return $constraints;
    }

    public function extractConstraints(string $query): array
    {
        $lower = mb_strtolower($query);
        $constraints = [
            'pax' => null,
            'max_price' => null,
            'budget_mode' => null,
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

        if (preg_match('/\b(?:activities?[- ]only|only\s+activities|no\s+(?:hotel|room|accommodation)|without\s+(?:a\s+)?(?:hotel|room|accommodation)|already\s+(?:have|booked)\s+(?:a\s+|my\s+)?(?:hotel|room|accommodation))\b/iu', $query)) {
            $constraints['include_accommodation'] = false;
        } elseif (preg_match('/\b(?:include|with|add)\s+(?:a\s+)?(?:hotel|room|accommodation)\b/iu', $query)) {
            $constraints['include_accommodation'] = true;
        }

        if (preg_match('/(\d+)\s*(?:pax|persons?|people|travelers?|travellers?|tao|katao|miyembro|guests?|adults?|children|kids|heads?)\b/i', $query, $m)) {
            $constraints['pax'] = (int) $m[1];
        }
        // "2 adults and 1 child": every traveler counts toward room capacity.
        if (preg_match_all('/(\d+)\s*(?:adults?|children|child|kids?|infants?|seniors?|matanda|bata)\b/iu', $query, $counts) && count($counts[1]) > 1) {
            $constraints['pax'] = array_sum(array_map('intval', $counts[1]));
            $constraints['children'] = array_sum(array_map('intval', array_intersect_key($counts[1], array_filter($counts[0], fn (string $part): bool => (bool) preg_match('/child|kid|infant|bata/iu', $part)))));
        }
        if (preg_match('/\bfor\s+(\d+)\s*(?:pax|persons?|people|guests?)?\b(?!\s*(?:nights?|days?|gabi|araw))/i', $query, $m) && ! $constraints['pax']) {
            $constraints['pax'] = (int) $m[1];
        }
        if (preg_match('/\b(\d+)\s+of\s+us\b/i', $query, $m) && ! $constraints['pax']) {
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

        // One parser for every money phrasing; a count after an operator
        // ("max 4 guests", "up to 2 nights") is never a price.
        $budget = $this->extractBudget($query);
        if ($budget !== null) {
            $constraints['max_price'] = $budget['amount'];
            $constraints['budget_mode'] = $budget['mode'];
        }

        if (preg_match('/(\d+)[-\s]*(?:nights?|gabi)\b/i', $query, $m)) {
            $constraints['nights'] = (int) $m[1];
        }
        if (preg_match('/(\d+)[-\s]*(?:days?|araw)\b/i', $query, $m)) {
            $statedDays = (int) $m[1];
            if (empty($constraints['nights'])) {
                $constraints['days'] = $statedDays;
                $constraints['nights'] = max(0, $statedDays - 1);
            } elseif ($statedDays !== $constraints['nights'] + 1) {
                // A stated day count that cannot follow the stated night
                // count is a self-contradiction: drop both and ask once
                // rather than quietly picking one of the two durations.
                $constraints['invalid_duration'] = true;
                $constraints['days'] = null;
                $constraints['nights'] = null;
            } else {
                $constraints['days'] = $statedDays;
            }
        }

        $dateRange = $this->extractDateRange($query);
        if ($dateRange) {
            $constraints['check_in_date'] = $dateRange[0];
            $constraints['check_out_date'] = $dateRange[1];
            if ($dateRange[0] && $dateRange[1]) {
                $nights = (int) Carbon::parse($dateRange[0])->diffInDays(Carbon::parse($dateRange[1]), false);
                if ($nights < 1 || $nights > 29 || ($constraints['nights'] !== null && $constraints['nights'] !== $nights)) {
                    $constraints['invalid_dates'] = true;
                }
                $constraints['nights'] = max(0, $nights);
                $constraints['days'] = max(1, $nights + 1);
            }
        }

        // "2 nights from 2026-12-10": a start date plus a stated stay length
        // fixes both stay dates.
        if (empty($constraints['check_in_date']) && ! empty($constraints['nights'])
            && preg_match('/\b(?:from|on|starting|simula)\s+(\d{4}-\d{2}-\d{2})\b/iu', $query, $start)) {
            try {
                $in = Carbon::parse($start[1])->startOfDay();
                if ($in->toDateString() === $start[1] && $in->gte(Carbon::today())) {
                    $constraints['check_in_date'] = $in->toDateString();
                    $constraints['check_out_date'] = $in->copy()->addDays($constraints['nights'])->toDateString();
                    $constraints['days'] = $constraints['nights'] + 1;
                }
            } catch (\Throwable) {
                $constraints['invalid_dates'] = true;
            }
        }

        $constraints['destination_name'] = $this->extractDestinationName($query);
        if ($constraints['destination_name']) {
            $constraints['destination_id'] = $this->resolveDestinationId($constraints['destination_name']);
        }

        $constraints['hotel_name'] = $this->extractHotelName($query);
        if ($constraints['hotel_name']) {
            $constraints['hotel_id'] = HotelModel::where('hotel_name', 'ILIKE', $constraints['hotel_name'])->value('id');
            // A partial or fuzzy name match is only trusted inside the named
            // destination: "kwarto para sa 3 tao sa Marikit" must not pin a
            // hotel elsewhere because one of its tokens looked similar.
            if ($constraints['hotel_id'] && $constraints['destination_id'] && ! $this->containsName($lower, $constraints['hotel_name'])
                && (int) HotelModel::whereKey($constraints['hotel_id'])->value('destination_id') !== (int) $constraints['destination_id']) {
                $constraints['hotel_id'] = null;
                $constraints['hotel_name'] = null;
            }
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
            if ($this->containsName($lower, (string) $name)) {
                return $name;
            }
        }

        // Concatenated / misspelled forms ("elnido" for "El Nido") — compare
        // both sides with all non-letters stripped so spacing is ignored.
        $flattened = (string) preg_replace('/[^\p{L}\p{N}]/u', '', $lower);
        if ($flattened !== '') {
            foreach ($destinations as $name) {
                $flatName = (string) preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower((string) $name));
                // Spaceless matching has no word boundaries to lean on, so a
                // short flattened name ("cebu") would align inside unrelated
                // words. Only distinctive names qualify for this fallback.
                if (strlen($flatName) >= 5 && str_contains($flattened, $flatName)) {
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
        // Negation first: a ruled-out name must not resolve as a request.
        $query = $this->withoutNegatedSpans($query);
        $hotels = HotelModel::pluck('hotel_name')->sortByDesc(fn ($n) => mb_strlen($n));
        $lower = mb_strtolower($query);

        foreach ($hotels as $name) {
            if ($this->containsName($lower, (string) $name)) {
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
            // Price words name no hotel on their own ("total budget 4000"
            // must not pin a "Budget Hotel").
            'budget',
            'cheap',
            'affordable',
            'luxury',
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
        // Negation first: a ruled-out name must not resolve as a request.
        $query = $this->withoutNegatedSpans($query);
        $rooms = RoomType::pluck('room_name')->sortByDesc(fn ($n) => mb_strlen($n));
        $lower = mb_strtolower($query);

        foreach ($rooms as $name) {
            if ($this->containsName($lower, (string) $name)) {
                return (string) $name;
            }
        }

        return null;
    }

    public function extractAddOnName(string $query): ?string
    {
        $query = $this->withoutNegatedSpans($query);

        try {
            $addons = AddOnModel::pluck('name')->sortByDesc(fn ($n) => mb_strlen($n));
            $lower = mb_strtolower($query);
            foreach ($addons as $name) {
                if ($this->containsName($lower, (string) $name)) {
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
        // Negation first: a ruled-out name must not resolve as a request.
        $query = $this->withoutNegatedSpans($query);
        $activities = ActivityModel::pluck('activity_name')->sortByDesc(fn ($n) => mb_strlen($n));
        $lower = mb_strtolower($query);
        foreach ($activities as $name) {
            if ($this->containsName($lower, (string) $name)) {
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
            if ($this->isDistinctiveToken($t, $genericWords, $destinationTokens)) {
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
                    if ($this->isDistinctiveToken($t, $genericWords, $destinationTokens)) {
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
                        if (strlen($qt) < 5 || ! (str_contains($flatCore, $qt) || str_contains($qt, $flatCore))) {
                            continue;
                        }
                        // Containment alone lets one word of a multi-word name
                        // ("mountain" of "mountaintrail") pin the item. Require
                        // the two to be nearly the same length, which is what
                        // concatenated typing actually looks like.
                        $shorter = min(strlen($qt), strlen($flatCore));
                        $longer = max(strlen($qt), strlen($flatCore));
                        if ($longer > 0 && $shorter / $longer >= 0.7) {
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
                if ($this->isDistinctiveToken($t, $genericWords, $destinationTokens)) {
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
     * Whether a cleaned token can distinguish one catalog item from another.
     *
     * Generic catalog words, destination names and bare numbers ("30" in
     * "Jet Ski (30 mins)") cannot: counting them as hits lets an unrelated
     * question about a duration or a headcount pin a named entity.
     *
     * @param  string[]  $genericWords
     * @param  string[]  $destinationTokens
     */
    protected function isDistinctiveToken(string $token, array $genericWords, array $destinationTokens): bool
    {
        if ($token === '' || ctype_digit($token)) {
            return false;
        }

        return ! in_array($token, $genericWords, true)
            && ! in_array($token, $destinationTokens, true);
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
            // Duration and headcount wording appears in catalog names
            // ("Jet Ski (30 mins)") and in unrelated questions alike, so it
            // can never distinguish one item from another.
            'min', 'mins', 'minute', 'minutes', 'hr', 'hrs', 'hour', 'hours',
            'day', 'days', 'night', 'nights', 'pax', 'person', 'persons', 'people',
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
    /**
     * Visible hotels or rooms whose full names appear in the query, in the
     * order mentioned; a longer name wins over a shorter one it contains.
     *
     * @param  'hotel'|'room'  $type
     * @return list<array{id: int, name: string}>
     */
    public function mentionedCatalogItems(string $query, string $type): array
    {
        [$model, $column] = $type === 'room' ? [RoomType::class, 'room_name'] : [HotelModel::class, 'hotel_name'];
        $lower = mb_strtolower($this->withoutNegatedSpans($query));
        $found = [];
        $names = $model::where('is_shown', true)->pluck($column, 'id')->all();
        uasort($names, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($names as $id => $name) {
            $needle = mb_strtolower(trim((string) $name));
            if (mb_strlen($needle) < 4 || ! preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $lower, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $found[$match[0][1]] = ['id' => (int) $id, 'name' => (string) $name];
            $lower = substr_replace($lower, str_repeat(' ', strlen($match[0][0])), $match[0][1], strlen($match[0][0]));
        }
        ksort($found);

        return array_values($found);
    }

    public function extractActivityNames(string $query): array
    {
        $query = $this->withoutNegatedSpans($query);
        $activities = ActivityModel::pluck('activity_name');
        $lower = mb_strtolower($query);
        [$genericWords, $destinationTokens] = $this->activityTokenFilters();

        $matches = [];
        foreach ($activities as $name) {
            $lowerName = mb_strtolower((string) $name);
            if ($this->containsName($lower, $lowerName)) {
                $matches[] = (string) $name;

                continue;
            }

            $nameTokens = [];
            foreach (preg_split('/\s+/', $lowerName) as $t) {
                $t = $this->cleanToken($t);
                if ($this->isDistinctiveToken($t, $genericWords, $destinationTokens)) {
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

        // ISO ranges ("2026-12-10 to 2026-12-12"), as the trip-details form
        // and many travelers write them. Impossible dates are left to the
        // caller's range validation rather than guessed.
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\s*(?:to|until|through|hanggang|–|-)\s*(\d{4}-\d{2}-\d{2})\b/u', $query, $iso)) {
            return [$iso[1], $iso[2]];
        }

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

    /**
     * Strict per-category signals used only for multi-intent decomposition.
     * classify() keeps its looser fallbacks (amenity words like "family" or
     * "pool", Tagalog "araw" = day); those must not fire here, or a plain
     * single ask would fan out into extra sections.
     */
    protected const COMPOUND_SIGNAL_PATTERNS = [
        self::LEGAL_QUERY => '/\b(privacy(\s+polic\w*)?|terms\s+(and|&)\s+conditions|terms\s+of\s+(service|use)|contact\s+(us|info|information|number|details?)|hotline)\b/u',
        self::DESTINATIONS_OVERVIEW => '/\b(what|which)\s+destinations?\b|\b(about|what\s+is)\s+sunnytrips\b/u',
        self::WEATHER_QUERY => '/\b(weather|forecast|rain|rainy|typhoon|bagyo|ulan|panahon|temperature|climate|monsoon)\b/u',
        self::MAP_QUERY => '/\b(how\s+far|distance|directions?|how\s+(do\s+i|to|can\s+i)\s+get\s+(to|there)|gaano\s+kalayo|nasaan)\b/u',
        self::ITINERARY_QUERY => '/\b(itinerary|itineraries|itenerary|trip\s+plan|travel\s+plan|plan\s+(a|my|our)\s+(trip|vacation|holiday|bakasyon)|day[-\s]by[-\s]day)\b/u',
        self::DISCOUNT_QUERY => '/\b(discounts?|discounted)\b/u',
        self::PACKAGE_SEARCH => '/\b(packages?|promos?|bundles?|all[-\s]in|all[-\s]inclusive)\b/u',
        self::ADDON_SEARCH => '/\b(add[-\s]?ons?|transfers?|pick[-\s]?ups?)\b/u',
        self::ROOM_SEARCH => '/\b(rooms?|suites?|villas?|kwarto|kuwarto)\b/u',
        self::HOTEL_SEARCH => '/\b(hotels?|resorts?|accommodations?|lodging|hostels?|inns?|tuluyan|matutulugan|(place|places|somewhere)\s+to\s+(stay|sleep)|where\s+to\s+stay|(nice|good|cozy|cheap|affordable|romantic|quiet|relaxing|budget)\s+places?\b(?!\s+to\s+(visit|go|see|explore|eat)))\b/u',
        self::ACTIVITY_SEARCH => '/\b(activit(y|ies)|tours?|things\s+to\s+do|attractions?|adventures?|sightseeing|experiences?|island\s+hopping|diving|scuba|snorkell?ing|hiking|trekking|surfing|kayak(ing)?|zipline|atv|parasail(ing)?|jet\s*ski|banana\s+boat|cliff\s+(jumping|diving)|sunset\s+cruise|pasyalan|gawain|libangan|p(?:u)?wedeng\s+gawin|gagawin)\b/u',
    ];

    /**
     * Every intent a compound message asks for ("a nice place in Boracay and
     * some extreme activities"), in classify() priority order. Empty unless
     * the message splits into 2+ clauses that carry signals for 2+ distinct
     * intents, so single asks keep the regular classify() path. A human-agent
     * request is never decomposed (the handoff is terminal), and intents a
     * richer handler already covers are folded into it: an itinerary includes
     * stays and activities, an availability check includes the rooms. An
     * off-topic clause ("and write me a python script") joins as
     * OUT_OF_SCOPE so it is declined instead of silently dropped; weak
     * off-topic asks ("solve 2+2") are gated by the travel words elsewhere
     * in the message, so they are read clause by clause.
     *
     * @return list<string>
     */
    public function compoundIntents(string $query): array
    {
        if ($this->hasSupportAgentIntent(mb_strtolower($query))) {
            return [];
        }

        $clauseSignals = array_map(fn (string $clause): array => $this->compoundSignals($clause), $this->splitCompoundClauses($query));
        if (count(array_filter($clauseSignals)) < 2) {
            return [];
        }

        $intents = $this->compoundSignals($query);
        if (! in_array(self::OUT_OF_SCOPE, $intents, true) && in_array(self::OUT_OF_SCOPE, array_merge(...$clauseSignals), true)) {
            $intents[] = self::OUT_OF_SCOPE;
        }
        if (in_array(self::ITINERARY_QUERY, $intents, true)) {
            $intents = array_diff($intents, [self::ROOM_SEARCH, self::HOTEL_SEARCH, self::ACTIVITY_SEARCH]);
        }
        if (in_array(self::AVAILABILITY_QUERY, $intents, true)) {
            $intents = array_diff($intents, [self::ROOM_SEARCH, self::HOTEL_SEARCH]);
        }
        $intents = array_values($intents);

        return count($intents) >= 2 ? $intents : [];
    }

    /**
     * One focused sub-query per compound intent: the clauses that ask for it
     * plus the shared clauses that name no intent ("we're 2 pax", "next
     * week"). The destination is re-attached when its clause went elsewhere,
     * so "a hotel in Boracay and some tours" searches tours in Boracay too.
     * Each section's handler and prompt then see only its own ask, never the
     * other half of the message.
     *
     * @param  list<string>  $intents
     * @return array<string, string>
     */
    public function compoundSubQueries(string $query, array $intents): array
    {
        $clauses = $this->splitCompoundClauses($query);
        $destination = $this->extractDestinationName($query);

        $subQueries = [];
        foreach ($intents as $intent) {
            $parts = [];
            foreach ($clauses as $clause) {
                $owners = $this->compoundSignalOwners($this->compoundSignals($clause), $intents);
                if ($owners === [] || in_array($intent, $owners, true)) {
                    $parts[] = $clause;
                }
            }
            $subQuery = $parts !== [] ? implode(', ', $parts) : $query;
            if ($intent !== self::OUT_OF_SCOPE && $destination !== null && $this->extractDestinationName($subQuery) === null) {
                $subQuery .= ' in '.$destination;
            }
            $subQueries[$intent] = $subQuery;
        }

        return $subQueries;
    }

    /**
     * Split a message at the joiners between separate asks. "with" is not a
     * joiner ("room with pool" is one ask), and commas only split when
     * followed by a space so "₱5,000" stays whole.
     *
     * @return list<string>
     */
    public function splitCompoundClauses(string $query): array
    {
        $parts = preg_split(
            '/\s+(?:and|plus|also|as\s+well\s+as|then|tsaka|at\s+saka|saka|pati(?:\s+na)?)\s+|\s*[,;](?=\s)|\s+[&+]\s+|[.?!]+(?=\s|$)/iu',
            $query
        ) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== ''));
    }

    /**
     * Strict intent signals present in a text, in classify() priority order.
     * Room and hotel collapse into one accommodation intent; "tour package"
     * is a package ask, not an activity ask.
     *
     * @return list<string>
     */
    public function compoundSignals(string $text): array
    {
        $lower = mb_strtolower($text);
        $patterns = self::COMPOUND_SIGNAL_PATTERNS;
        $signals = [];

        if (preg_match($patterns[self::LEGAL_QUERY], $lower)) {
            $signals[] = self::LEGAL_QUERY;
        }
        if ($this->hasBookingStatusIntent($lower)) {
            $signals[] = self::BOOKING_STATUS;
        }
        foreach ([self::DESTINATIONS_OVERVIEW, self::WEATHER_QUERY, self::MAP_QUERY] as $intent) {
            if (preg_match($patterns[$intent], $lower)) {
                $signals[] = $intent;
            }
        }
        if ($this->hasAvailabilityIntent($lower)) {
            $signals[] = self::AVAILABILITY_QUERY;
        }
        foreach ([self::ITINERARY_QUERY, self::DISCOUNT_QUERY, self::PACKAGE_SEARCH, self::ADDON_SEARCH] as $intent) {
            if (preg_match($patterns[$intent], $lower)) {
                $signals[] = $intent;
            }
        }
        if (preg_match($patterns[self::ROOM_SEARCH], $lower)) {
            $signals[] = self::ROOM_SEARCH;
        } elseif (preg_match($patterns[self::HOTEL_SEARCH], $lower)) {
            $signals[] = self::HOTEL_SEARCH;
        }
        $activityText = (string) preg_replace('/\b(tour|travel|vacation|holiday)\s+(packages?|promos?|deals?)\b/u', ' ', $lower);
        if (preg_match($patterns[self::ACTIVITY_SEARCH], $activityText)) {
            $signals[] = self::ACTIVITY_SEARCH;
        }
        if ($this->outOfScopeTopic($text) !== null) {
            $signals[] = self::OUT_OF_SCOPE;
        }

        return $signals;
    }

    /**
     * Map a clause's raw signals onto the final compound intents. Folded
     * intents route to the handler that absorbed them, and room/hotel are
     * interchangeable (one accommodation section).
     *
     * @param  list<string>  $signals
     * @param  list<string>  $intents
     * @return list<string>
     */
    protected function compoundSignalOwners(array $signals, array $intents): array
    {
        $accommodation = [self::ROOM_SEARCH, self::HOTEL_SEARCH];
        $owners = [];
        foreach ($signals as $signal) {
            if (in_array($signal, $intents, true)) {
                $owners[] = $signal;

                continue;
            }
            $candidates = in_array($signal, $accommodation, true)
                ? [...$accommodation, self::AVAILABILITY_QUERY, self::ITINERARY_QUERY]
                : ($signal === self::ACTIVITY_SEARCH ? [self::ITINERARY_QUERY] : []);
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $intents, true)) {
                    $owners[] = $candidate;
                    break;
                }
            }
        }

        return array_values(array_unique($owners));
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
            'pwedeng gawin',
            'puwedeng gawin',
            'gagawin',
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
        if (preg_match('/\b(my|our)\s+(bookings?|reservations?)\b|\b(?:bookings?|reservations?)\s+(?:ko|namin)\b/u', $lower)) {
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
