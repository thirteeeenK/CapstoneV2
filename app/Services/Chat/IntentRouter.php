<?php

namespace App\Services\Chat;

use App\Models\DestinationModel;
use Carbon\Carbon;
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

    protected array $travelKeywords = [
        'hotel', 'hotels', 'room', 'rooms', 'resort', 'resorts', 'stay', 'accommodation',
        'book', 'booking', 'check in', 'check-in', 'check out', 'check-out',
        'activity', 'activities', 'tour', 'tours', 'island hopping', 'diving', 'snorkeling',
        'beach', 'beaches', 'trip', 'travel', 'vacation', 'holiday',
        'itinerary', 'plan', 'planning', 'trip plan', 'travel plan',
        'available', 'availability', 'open dates',
        'weather', 'forecast', 'rain', 'sunny', 'temperature', 'climate',
        'where is', 'how far', 'nearby', 'distance', 'map', 'location', 'locate',
        'recommend', 'recommendation', 'suggest', 'suggestion', 'best', 'top',
        'destinasyon', 'bakasyon', 'pasyalan', 'pasyal', 'byahe',
        'price', 'prices', 'cost', 'budget', 'pesos', 'php', '₱',
        'rate', 'rates', 'how much', 'magkano', 'presyo',
        'pax', 'guests', 'persons', 'people', 'couple', 'family', 'group', 'solo',
        'night', 'nights', 'days', 'day', 'weekend', 'week',
        'package', 'packages', 'promo', 'deal', 'deals', 'bundle', 'tipid', 'all-in', 'all inclusive',
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
            return self::ITINERARY_QUERY;
        }

        if ($this->hasPackageIntent($lower)) {
            return self::PACKAGE_SEARCH;
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
            'check_in_date' => null,
            'check_out_date' => null,
            'nights' => null,
            'days' => null,
            'place_names' => [],
        ];

        if (preg_match('/(\d+)\s*(?:pax|persons?|people|tao|katao|miyembro|guests?)/i', $query, $m)) {
            $constraints['pax'] = (int) $m[1];
        }
        if (preg_match('/couple|couples|2\s*pax/i', $query) && !$constraints['pax']) {
            $constraints['pax'] = 2;
        }
        if (preg_match('/(?:solo|alone|1\s*pax|myself)/i', $query) && !$constraints['pax']) {
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
        if (preg_match('/(\d+)\s*(?:days?|araw)\b/i', $query, $m) && !$constraints['nights']) {
            $constraints['days'] = (int) $m[1];
            $constraints['nights'] = max(0, $constraints['days'] - 1);
        }

        $dateRange = $this->extractDateRange($query);
        if ($dateRange) {
            $constraints['check_in_date'] = $dateRange[0];
            $constraints['check_out_date'] = $dateRange[1];
            if (!$constraints['nights'] && $dateRange[0] && $dateRange[1]) {
                $constraints['nights'] = max(1, Carbon::parse($dateRange[0])->diffInDays(Carbon::parse($dateRange[1])));
            }
        }

        $constraints['destination_name'] = $this->extractDestinationName($query);
        if ($constraints['destination_name']) {
            $constraints['destination_id'] = $this->resolveDestinationId($constraints['destination_name']);
        }

        $constraints['place_names'] = $this->extractPlaceNames($query);

        return $constraints;
    }

    protected function extractDestinationName(string $query): ?string
    {
        $destinations = DestinationModel::pluck('name')->sortByDesc(fn($n) => mb_strlen($n));
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

    protected function extractPlaceNames(string $query): array
    {
        $places = [];
        $destinations = DestinationModel::pluck('name')->all();

        foreach ($destinations as $name) {
            if (mb_stripos($query, $name) !== false) {
                $places[] = ['type' => 'destination', 'name' => $name];
            }
        }

        return $places;
    }

    protected function extractDateRange(string $query): ?array
    {
        $lower = mb_strtolower($query);

        if (preg_match('/this\s*weekend/i', $lower)) {
            $sat = Carbon::now()->next(Carbon::SATURDAY);
            return [$sat->format('Y-m-d'), $sat->copy()->addDays(2)->format('Y-m-d')];
        }

        if (preg_match('/next\s*week/i', $lower)) {
            $mon = Carbon::now()->next(Carbon::MONDAY);
            return [$mon->format('Y-m-d'), $mon->copy()->addDays(7)->format('Y-m-d')];
        }

        if (preg_match('/(?:tonight|today)/i', $lower) && !preg_match('/this\s*weekend/i', $lower)) {
            return [Carbon::now()->format('Y-m-d'), Carbon::now()->addDay()->format('Y-m-d')];
        }

        $months = implode('|', [
            'january', 'february', 'march', 'april', 'may', 'june',
            'july', 'august', 'september', 'october', 'november', 'december',
            'jan', 'feb', 'mar', 'apr', 'may', 'jun',
            'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
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
                Log::debug('IntentRouter date parse failed (pattern1): ' . $e->getMessage());
            }
        }

        if (preg_match($datePattern2, $query, $m)) {
            try {
                $dateStr = $m[1] . ' ' . $m[2];
                $d1 = Carbon::parse($dateStr);
                $d2 = Carbon::parse($m[1] . ' ' . $m[3]);
                if ($d2->lt($d1)) {
                    $d2->addMonth();
                }
                return [$d1->format('Y-m-d'), $d2->format('Y-m-d')];
            } catch (\Throwable $e) {
                Log::debug('IntentRouter date parse failed (pattern2): ' . $e->getMessage());
            }
        }

        return null;
    }

    protected function hasWeatherIntent(string $lower): bool
    {
        $weather = ['weather', 'forecast', 'rain', 'rainy', 'sunny', 'temperature', 'climate', 'hot', 'cold', 'humid', 'storm', 'typhoon', 'bagyo', 'ulan', 'araw', 'init', 'lamig', 'panahon'];
        foreach ($weather as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/i', $lower)) {
                return true;
            }
        }
        return false;
    }

    protected function hasMapIntent(string $lower): bool
    {
        $map = ['where is', 'how far', 'nearby', 'distance', 'map', 'location', 'locate', 'direction', 'directions', 'navigate', 'nasaan', 'saan', 'gaano kalayo', 'malapit', 'kalapit'];
        foreach ($map as $m) {
            if (str_contains($lower, $m)) {
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
            '/(\d{1,2}\s*(?:to|-|–)\s*\d{1,2}|' . $months . '\s*\d{1,2}\s*(?:to|-|–)\s*(?:' . $months . '\s*)?\d{1,2}|check\s*(?:in|out)|check-in|check-out|stay\s*dates)/i',
            $lower
        );
        if (!$hasDate) {
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
        $itin = ['itinerary', 'itenerary', 'plan my trip', 'trip plan', 'travel plan', 'plan a trip', 'plan for', 'day itinerary', 'day trip', 'sample itinerary', 'itiniraryo'];
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

    protected function hasPackageIntent(string $lower): bool
    {
        $pkg = ['package', 'packages', 'promo', 'deal', 'deals', 'bundle', 'tipid', 'all-in', 'all inclusive'];
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

    protected function hasRoomIntent(string $lower): bool
    {
        $room = ['room', 'rooms', 'suite', 'villa', 'bed', 'beds', 'occupancy', 'bedroom', 'accommodation', 'stay in', 'matulog', 'tulugan', 'kuwarto', 'kwarto'];
        foreach ($room as $r) {
            if (preg_match('/\b' . preg_quote($r, '/') . '\b/i', $lower)) {
                return true;
            }
        }
        if (preg_match('/(?:ocean\s*view|beachfront|pool\s*view|garden\s*view|balcony|terrace)/i', $lower)) {
            return true;
        }
        return false;
    }

    protected function hasHotelIntent(string $lower): bool
    {
        $hotel = ['hotel', 'hotels', 'resort', 'resorts', 'inn', 'lodge', 'hostel', 'stay at', 'stay in a', 'tuluyan', 'otol'];
        foreach ($hotel as $h) {
            if (preg_match('/\b' . preg_quote($h, '/') . '\b/i', $lower)) {
                return true;
            }
        }
        if (preg_match('/best\s+(?:hotel|resort|place\s*to\s*stay)/i', $lower)) {
            return true;
        }
        return false;
    }

    protected function hasActivityIntent(string $lower): bool
    {
        $act = ['activity', 'activities', 'tour', 'tours', 'island hopping', 'diving', 'snorkeling', 'hiking', 'trek', 'surfing', 'kayak', 'zipline', 'thing to do', 'things to do', 'attraction', 'attractions', 'adventure', 'gawain', 'pasyalan', 'libangan'];
        foreach ($act as $a) {
            if (str_contains($lower, $a)) {
                return true;
            }
        }
        return false;
    }
}
