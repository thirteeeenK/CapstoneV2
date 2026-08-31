<?php

namespace App\Services;

use App\Models\DestinationModel;
use App\Models\WeatherCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /**
     * How long a fresh forecast is served before a re-fetch (OpenWeather only
     * refreshes every ~3h). Default 3 hours.
     */
    protected int $freshCacheMinutes = 180;

    /**
     * How long stale-but-cached data can be served when the API is unreachable.
     */
    protected int $staleTtlMinutes = 360;

    /**
     * Cache key prefix used for both Cache::remember and the weather_cache table.
     */
    public function cacheKeyFor(int $destinationId): string
    {
        return "destination:{$destinationId}";
    }

    /**
     * Fetch (and cache) the 5-day / 3-hour forecast for a destination.
     */
    public function forecastForDestination(DestinationModel $destination): ?array
    {
        if (! $destination->latitude || ! $destination->longitude) {
            return null;
        }

        $key = $this->cacheKeyFor($destination->id);

        // Fast path: laravel cache
        if ($cached = cache()->get($key)) {
            return $cached;
        }

        $data = $this->fetchFromApi($destination->latitude, $destination->longitude);

        if ($data) {
            $this->store($key, $data);

            return $data;
        }

        // Slow path: serve stale cache from DB table.
        return $this->staleFallback($key);
    }

    /**
     * Fetch (and cache) forecast by raw coordinates.
     */
    public function forecast(float $lat, float $lng): ?array
    {
        $key = 'coord:'.round($lat, 4).','.round($lng, 4);

        if ($cached = cache()->get($key)) {
            return $cached;
        }

        $data = $this->fetchFromApi($lat, $lng);

        if ($data) {
            $this->store($key, $data);

            return $data;
        }

        return $this->staleFallback($key);
    }

    /**
     * Fetch current-conditions-friendly summary for a destination.
     * Returns a normalized array suited for frontend weather cards.
     */
    public function summaryForDestination(DestinationModel $destination): ?array
    {
        $forecast = $this->forecastForDestination($destination);

        if (! $forecast) {
            return null;
        }

        return $this->normalize($forecast);
    }

    /**
     * Call OpenWeatherMap location-based 5-day endpoint.
     */
    protected function fetchFromApi(float $lat, float $lng): ?array
    {
        $apiKey = config('services.openweather.api_key');

        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->get(config('services.openweather.base_url').'/forecast', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang' => 'en',
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning('OpenWeather fetch failed: '.$e->getMessage());
        }

        // Cache the failure briefly to avoid hammering the API.
        $this->rememberFailure(md5("{$lat},{$lng}"));

        return null;
    }

    private function rememberFailure(string $suffix): void
    {
        cache()->put('weather:fail:'.$suffix, 1, now()->addMinutes(30));
    }

    /**
     * Persist fetched payload to both laravel cache and the weather_cache table.
     */
    protected function store(string $key, array $data): void
    {
        $now = now();
        cache()->put($key, $data, $now->copy()->addMinutes($this->freshCacheMinutes));

        WeatherCache::updateOrCreate(
            ['cache_key' => $key],
            [
                'weather_data' => $data,
                'fetched_at' => $now,
                'expires_at' => $now->copy()->addMinutes($this->freshCacheMinutes),
            ]
        );
    }

    /**
     * Return a previously stored row even if past its freshness window so the
     * UI degrades gracefully when OpenWeather is unreachable.
     */
    protected function staleFallback(string $key): ?array
    {
        $row = WeatherCache::where('cache_key', $key)->first();

        if (! $row) {
            return null;
        }

        // Serve stale data if within the stale TTL.
        if ($row->fetched_at && $row->fetched_at->gt(now()->subMinute($this->staleTtlMinutes))) {
            cache()->put($key, $row->weather_data, now()->addMinutes($this->freshCacheMinutes));

            return $row->weather_data;
        }

        return null;
    }

    /**
     * Normalize the OpenWeather forecast payload into a compact, safe shape
     * for the frontend weather cards.
     */
    public function normalize(array $raw): array
    {
        $lists = $raw['list'] ?? [];

        if (empty($lists)) {
            return [
                'current' => null,
                'daily' => [],
                'updated_at' => now()->toIso8601String(),
            ];
        }

        // Nearest 3-hour entry = "current".
        $current = $this->snapshotFromEntry($lists[0]);

        $daily = [];
        foreach ($lists as $entry) {
            $day = Carbon::parse($entry['dt_txt'] ?? null)->format('Y-m-d');
            if ($day === false) {
                continue;
            }
            $daily[$day] ??= $this->snapshotFromEntry($entry);
        }

        return [
            'current' => $current,
            'daily' => array_values($daily),
            'summary_at' => $current['time'] ?? null,
        ];
    }

    /**
     * Extract the fields we care about from a single 3h forecast entry.
     */
    protected function snapshotFromEntry(array $entry): array
    {
        $weather = $entry['weather'][0] ?? [];
        $main = $entry['main'] ?? [];

        return [
            'time' => $entry['dt_txt'] ?? null,
            'temp' => round($main['temp'] ?? 0),
            'temp_min' => round($main['temp_min'] ?? ($main['temp'] ?? 0)),
            'temp_max' => round($main['temp_max'] ?? ($main['temp'] ?? 0)),
            'feels_like' => round($main['feels_like'] ?? ($main['temp'] ?? 0)),
            'humidity' => $main['humidity'] ?? null,
            'description' => $weather['description'] ?? null,
            'main' => $weather['main'] ?? null,
            'icon' => $weather['icon'] ?? null,
            'wind_speed' => $entry['wind']['speed'] ?? null,
            'rain_1h' => $entry['rain']['1h'] ?? 0,
            'pop' => $entry['pop'] ?? 0,
            'clouds' => $entry['clouds']['all'] ?? 0,
        ];
    }

    /**
     * DSS rule: is it currently too risky for outdoor / water activities?
     */
    public function isOutdoorUnsafe(array $weather): bool
    {
        $current = $weather['current'] ?? null;
        if (! $current) {
            return false;
        }

        $rain = (float) ($current['rain_1h'] ?? 0);
        $wind = (float) ($current['wind_speed'] ?? 0);

        return $rain > 5 || $wind > 20;
    }

    /**
     * Human-friendly DSS advice based on current conditions.
     *
     * @return string[]
     */
    public function advice(array $weather): array
    {
        $current = $weather['current'] ?? null;
        if (! $current) {
            return [];
        }

        $advice = [];
        $rain = (float) ($current['rain_1h'] ?? 0);
        $wind = (float) ($current['wind_speed'] ?? 0);
        $temp = (float) ($current['temp'] ?? 0);

        if ($rain > 5) {
            $advice[] = 'Heavy rain expected — consider rescheduling outdoor activities.';
        } elseif ($rain > 0) {
            $advice[] = 'Light showers possible — bring a light rain jacket for tours.';
        }
        if ($wind > 20) {
            $advice[] = 'Strong winds advisory — water activities may be affected.';
        }
        if ($temp > 35) {
            $advice[] = 'High temperature — stay hydrated on outdoor trips.';
        } elseif ($temp < 22) {
            $advice[] = 'Cooler conditions — a light layer is advisable for evenings.';
        }

        return $advice;
    }

    /**
     * DSS scored booking suitability from a 5-day forecast.
     * Returns score 0-100 + level + reasons + driest date + per-day breakdown.
     *
     * @return array{score:int, level:string, label:string, reasons:string[], driestDate:?string, dailyScores:array}
     */
    public function bookingSuitability(array $forecast): array
    {
        $list = $forecast['list'] ?? [];
        if (empty($list)) {
            $norm = $this->normalize($forecast);
            if (empty($norm['current'])) {
                return ['score' => 50, 'level' => 'unknown', 'label' => 'Unknown — weather data limited', 'reasons' => ['Weather data incomplete for this destination.'], 'driestDate' => null, 'dailyScores' => []];
            }
            $list = [['main' => ['temp' => $norm['current']['temp']], 'weather' => [['description' => $norm['current']['description'] ?? '']], 'wind' => ['speed' => $norm['current']['wind_speed'] ?? 0], 'rain' => ['1h' => $norm['current']['rain_1h'] ?? 0], 'pop' => $norm['current']['pop'] ?? 0, 'dt_txt' => $norm['current']['time']]];
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
            return ['score' => 50, 'level' => 'unknown', 'label' => 'Unknown — weather data limited', 'reasons' => ['Could not parse forecast dates.'], 'driestDate' => null, 'dailyScores' => []];
        }

        ksort($byDate);
        $dailyScores = [];
        $allPops = [];
        $maxRain = 0;
        $maxWind = 0;
        $temps = [];

        foreach ($byDate as $date => $entries) {
            $pops = [];
            $rains = [];
            $winds = [];
            $dayTemps = [];
            foreach ($entries as $e) {
                $pops[] = (float) ($e['pop'] ?? 0);
                $rains[] = (float) ($e['rain']['1h'] ?? $e['rain']['3h'] ?? 0);
                $winds[] = (float) ($e['wind']['speed'] ?? 0);
                if (isset($e['main']['temp'])) {
                    $dayTemps[] = (float) $e['main']['temp'];
                }
            }
            $maxPop = $pops ? max($pops) : 0;
            $dayMaxRain = $rains ? max($rains) : 0;
            $dayMaxWind = $winds ? max($winds) : 0;
            $avgTemp = $dayTemps ? array_sum($dayTemps) / count($dayTemps) : 28;

            // Per-day score: POP dominant, rain/wind penalize, temp comfortable 24-32
            $popScore = 100 - ($maxPop * 100);
            $rainScore = max(0, 100 - ($dayMaxRain * 12)); // rain 5mm → 40, 8mm → 4
            $windScore = max(0, 100 - max(0, $dayMaxWind - 10) * 6); // wind 20 → 40
            $tempScore = $avgTemp >= 24 && $avgTemp <= 32 ? 100 : max(0, 100 - abs($avgTemp - 28) * 5);
            $dayScore = (int) round(0.5 * $popScore + 0.3 * $rainScore + 0.12 * $windScore + 0.08 * $tempScore);

            $dailyScores[$date] = [
                'score' => $dayScore,
                'pop' => $maxPop,
                'rain' => $dayMaxRain,
                'wind' => $dayMaxWind,
                'temp' => round($avgTemp),
            ];
            $allPops[] = $maxPop;
            $maxRain = max($maxRain, $dayMaxRain);
            $maxWind = max($maxWind, $dayMaxWind);
            $temps[] = $avgTemp;
        }

        $avgPop = $allPops ? array_sum($allPops) / count($allPops) : 0;
        $avgTemp = $temps ? array_sum($temps) / count($temps) : 28;

        $popScore = 100 - ($avgPop * 100);
        $rainScore = max(0, 100 - ($maxRain * 12));
        $windScore = max(0, 100 - max(0, $maxWind - 10) * 6);
        $tempScore = $avgTemp >= 24 && $avgTemp <= 32 ? 100 : max(0, 100 - abs($avgTemp - 28) * 5);
        $composite = (int) round(0.5 * $popScore + 0.3 * $rainScore + 0.12 * $windScore + 0.08 * $tempScore);

        if ($composite >= 70) {
            $level = 'good';
            $label = 'Good to book';
        } elseif ($composite >= 40) {
            $level = 'okay';
            $label = 'Okay with indoor backup';
        } else {
            $level = 'poor';
            $label = 'Consider postponing';
        }

        $reasons = [];
        if ($avgPop >= 0.8) {
            $reasons[] = 'Persistent rain expected ('.(int) round($avgPop * 100).'% avg POP across 5 days)';
        } elseif ($avgPop >= 0.5) {
            $reasons[] = 'Frequent showers ('.(int) round($avgPop * 100).'% avg POP)';
        } elseif ($avgPop > 0.2) {
            $reasons[] = 'Occasional showers ('.(int) round($avgPop * 100).'% POP)';
        } else {
            $reasons[] = 'Mostly dry ('.(int) round($avgPop * 100).'% POP)';
        }

        if ($maxRain > 5) {
            $reasons[] = "Heavy rain peaks at {$maxRain}mm/h — outdoor tours may be rescheduled";
        } elseif ($maxRain > 0.5) {
            $reasons[] = "Light rain up to {$maxRain}mm/h — bring a rain jacket";
        }

        if ($maxWind > 20) {
            $reasons[] = "Strong winds up to {$maxWind}m/s — water activities may be suspended";
        } elseif ($maxWind > 12) {
            $reasons[] = "Breezy conditions ({$maxWind}m/s)";
        }

        if ($avgTemp > 33) {
            $reasons[] = 'Hot ('.round($avgTemp).'°C) — stay hydrated';
        } elseif ($avgTemp < 24) {
            $reasons[] = 'Cooler ('.round($avgTemp).'°C) — light layer advised';
        }

        // Driest date = min maxPop, tie-breaker higher score
        $driestDate = null;
        $bestPop = 2;
        $bestScore = -1;
        foreach ($dailyScores as $date => $d) {
            if ($d['pop'] < $bestPop || ($d['pop'] === $bestPop && $d['score'] > $bestScore)) {
                $bestPop = $d['pop'];
                $bestScore = $d['score'];
                $driestDate = $date;
            }
        }

        return [
            'score' => $composite,
            'level' => $level,
            'label' => $label,
            'reasons' => $reasons,
            'driestDate' => $driestDate,
            'dailyScores' => $dailyScores,
        ];
    }
}
