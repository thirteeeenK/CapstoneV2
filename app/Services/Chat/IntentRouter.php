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

    protected array $travelKeywords = [
        'hotel',
        'hotels',
        'room',
        'rooms',
        'resort',
        'resorts',
        'stay',
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
        'travel',
        'vacation',
        'holiday',
        'itinerary',
        'plan',
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
        'budget',
        'pesos',
        'php',
        '₱',
        'rate',
        'rates',
        'how much',
        'magkano',
        'presyo',
        'pax',
        'guests',
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
    ];

    public function isTravelQuery(string $query): bool
    {
        $lower = mb_strtolower($query);
        foreach ($this->travelKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function classify(string $query): string
    {
        $lower = mb_strtolower($query);

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

        return self::GENERAL_TALK;
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
            'place_names' => [],
        ];

        if (preg_match('/(\d+)\s*(?:pax|persons?|people|tao|katao|miyembro|guests?)/i', $query, $m)) {
            $constraints['pax'] = (int) $m[1];
        }
        if (preg_match('/couple|couples|2\s*pax/i', $query) && ! $constraints['pax']) {
            $constraints['pax'] = 2;
        }
        if (preg_match('/(?:solo|alone|1\s*pax|myself)/i', $query) && ! $constraints['pax']) {
            $constraints['pax'] = 1;
        }

        if (preg_match('/(?:(?:under|below|less\s*than|max|maximum|budget\s*(?:of|is)?))\s*(?:₱|php|peso)?\s*(\d[\d,]{0,8})/i', $query, $m)) {
            $constraints['max_price'] = (int) str_replace(',', '', $m[1]);
        }
        if (preg_match('/(?:₱|php|peso)?\s*(\d[\d,]{1,8})\s*(?:budget|max|pesos)/i', $query, $m)) {
            $constraints['max_price'] = (int) str_replace(',', '', $m[1]);
        }

        if (preg_match('/(\d+)\s*(?:nights?|gabi)\b/i', $query, $m)) {
            $constraints['nights'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*(?:days?|araw)\b/i', $query, $m) && ! $constraints['nights']) {
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
            $constraints['addon_id'] = AddOnModel::where('name', 'ILIKE', $constraints['addon_name'])->value('id');
        }

        $constraints['package_name'] = $this->extractPackageName($query);
        $constraints['activity_name'] = $this->extractActivityName($query);

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

        return null;
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
        if (preg_match('/(\d+)\s*(?:day|araw)\s*(?:itinerary|plan|trip|itiniraryo)/i', $lower)) {
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
        if (preg_match('/\d+\s*(?:nights?|gabi|days?|araw)\b/i', $query)) {
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
        if (preg_match('/(\d+)\s*(?:day|araw)\s*(?:itinerary|plan|trip|itiniraryo)/i', $lower)) {
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

    protected function hasActivityIntent(string $lower): bool
    {
        $act = [
            'activity',
            'activities',
            'tour',
            'tours',
            'island hopping',
            'diving',
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

        if (preg_match('/\bdestinations?\b/', $lower) && preg_match('/\b(you|your|sunnytrips|sunny|trips|we|our)\b/', $lower)) {
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
