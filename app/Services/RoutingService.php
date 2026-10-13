<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RoutingService
{
    public function configured(): bool
    {
        return $this->provider() !== 'none';
    }

    /**
     * Resolve the active routing backend from config.
     */
    private function provider(): string
    {
        $configured = (string) config('services.routing.provider', 'auto');
        $hasOrs = filled(config('services.routing.ors_api_key'));
        $hasValhalla = filled(config('services.routing.base_url'));

        return match ($configured) {
            'ors' => $hasOrs ? 'ors' : 'none',
            'valhalla' => $hasValhalla ? 'valhalla' : 'none',
            default => $hasOrs ? 'ors' : ($hasValhalla ? 'valhalla' : 'none'),
        };
    }

    /** @param array{lat: float, lng: float} $from
     * @param  array{lat: float, lng: float}  $to
     */
    public function route(array $from, array $to, ?string $mode = null): array
    {
        $mode ??= config('services.routing.default_mode', 'walking');
        if (! $this->validPoint($from) || ! $this->validPoint($to) || ! $this->validMode($mode)) {
            return $this->unavailable('invalid_location', $mode);
        }

        return $this->provider() === 'ors'
            ? $this->routeOrs($from, $to, $mode)
            : $this->routeValhalla($from, $to, $mode);
    }

    private function routeValhalla(array $from, array $to, string $mode): array
    {
        $costing = $mode === 'walking' ? 'pedestrian' : 'auto';
        $payload = [
            'locations' => [$this->location($from), $this->location($to)],
            'costing' => $costing,
            'costing_options' => [$costing => ['use_ferry' => 0]],
            'directions_options' => ['units' => 'kilometers'],
        ];
        $native = $this->request('route', $payload + ['format' => 'json']);
        if (isset($native['_error'])) {
            return $this->unavailable($native['_error'], $mode);
        }
        if (! is_bool($native['trip']['summary']['has_ferry'] ?? null)) {
            return $this->unavailable('invalid_response', $mode);
        }
        if ($native['trip']['summary']['has_ferry']) {
            return $this->unavailable('unsupported_transfer', $mode);
        }
        $response = $this->request('route', $payload + ['format' => 'osrm', 'shape_format' => 'geojson']);
        if (isset($response['_error'])) {
            return $this->unavailable($response['_error'], $mode);
        }

        $route = $response['routes'][0] ?? [];
        if (! is_array($route)) {
            return $this->unavailable('invalid_response', $mode);
        }
        $geometry = $route['geometry'] ?? [];
        if (! $this->validNumber($route['distance'] ?? null) || ! $this->validNumber($route['duration'] ?? null)
            || ! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString' || ! is_array($geometry['coordinates'] ?? null) || count($geometry['coordinates']) < 2) {
            return $this->unavailable('invalid_response', $mode);
        }
        foreach ($geometry['coordinates'] as $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) < 2 || ! $this->validPoint(['lng' => $point[0], 'lat' => $point[1]])) {
                return $this->unavailable('invalid_response', $mode);
            }
        }

        return [
            'available' => true,
            'status' => 'ok',
            'provider' => 'valhalla',
            'mode' => $mode,
            'distance_km' => (float) $route['distance'] / 1000,
            'duration_seconds' => (float) $route['duration'],
            'duration_minutes' => (int) ceil($route['duration'] / 60),
            'geometry' => $geometry,
            'snapped_waypoints' => $response['waypoints'] ?? [],
            'graph_version' => config('services.routing.graph_version'),
        ];
    }

    private function routeOrs(array $from, array $to, string $mode): array
    {
        $response = $this->orsRequest('directions/'.$this->orsProfile($mode).'/geojson', [
            'coordinates' => [[(float) $from['lng'], (float) $from['lat']], [(float) $to['lng'], (float) $to['lat']]],
        ]);
        if (isset($response['_error'])) {
            return $this->unavailable($response['_error'], $mode);
        }

        $feature = $response['features'][0] ?? [];
        $geometry = $feature['geometry'] ?? [];
        $summary = $feature['properties']['summary'] ?? [];
        if (! $this->validNumber($summary['distance'] ?? null) || ! $this->validNumber($summary['duration'] ?? null)
            || ! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString' || ! is_array($geometry['coordinates'] ?? null) || count($geometry['coordinates']) < 2) {
            return $this->unavailable('invalid_response', $mode);
        }
        foreach ($geometry['coordinates'] as $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) < 2 || ! $this->validPoint(['lng' => $point[0], 'lat' => $point[1]])) {
                return $this->unavailable('invalid_response', $mode);
            }
        }

        return [
            'available' => true,
            'status' => 'ok',
            'provider' => 'ors',
            'mode' => $mode,
            'distance_km' => (float) $summary['distance'] / 1000,
            'duration_seconds' => (float) $summary['duration'],
            'duration_minutes' => (int) ceil($summary['duration'] / 60),
            'geometry' => $geometry,
            'snapped_waypoints' => [],
            'graph_version' => 'openrouteservice',
        ];
    }

    /**
     * One ORS matrix call: locations hold the sources then the targets,
     * addressed back by their original index so unreachable pairs stay null.
     */
    private function matrixOrs(array $sources, array $targets, string $mode): array
    {
        $distances = array_fill(0, count($sources), array_fill(0, count($targets), null));
        $durations = $distances;
        $validSources = array_filter($sources, $this->validPoint(...));
        $validTargets = array_filter($targets, $this->validPoint(...));
        $status = count($validSources) !== count($sources) || count($validTargets) !== count($targets) ? 'partial' : 'ok';
        if (empty($validSources) || empty($validTargets)) {
            return ['available' => false, 'status' => 'partial', 'distances' => $distances, 'durations' => $durations, 'mode' => $mode, 'provider' => 'ors'];
        }

        $locations = [];
        foreach ([...array_values($validSources), ...array_values($validTargets)] as $point) {
            $locations[] = [(float) $point['lng'], (float) $point['lat']];
        }
        if (count($locations) > 50) {
            return ['available' => false, 'status' => 'invalid_request', 'distances' => $distances, 'durations' => $durations, 'mode' => $mode, 'provider' => 'ors'];
        }
        $offset = count($validSources);
        $response = $this->orsRequest('matrix/'.$this->orsProfile($mode), [
            'locations' => $locations,
            'sources' => range(0, $offset - 1),
            'destinations' => range($offset, count($locations) - 1),
            'metrics' => ['distance', 'duration'],
            'units' => 'km',
        ]);
        if (isset($response['_error']) || ! is_array($response['distances'] ?? null) || ! is_array($response['durations'] ?? null)) {
            return ['available' => false, 'status' => $response['_error'] ?? 'invalid_response', 'distances' => $distances, 'durations' => $durations, 'mode' => $mode, 'provider' => 'ors'];
        }

        foreach (array_keys($validSources) as $row => $sourceIndex) {
            foreach (array_keys($validTargets) as $column => $targetIndex) {
                $distance = $response['distances'][$row][$column] ?? null;
                $duration = $response['durations'][$row][$column] ?? null;
                if ($this->validNumber($distance) && $this->validNumber($duration)) {
                    $distances[$sourceIndex][$targetIndex] = (float) $distance;
                    $durations[$sourceIndex][$targetIndex] = (float) $duration;
                } else {
                    $status = 'partial';
                }
            }
        }

        return ['available' => $status === 'ok', 'status' => $status, 'distances' => $distances, 'durations' => $durations, 'mode' => $mode, 'provider' => 'ors'];
    }

    private function orsProfile(string $mode): string
    {
        return $mode === 'driving' ? 'driving-car' : 'foot-walking';
    }

    /**
     * Directed matrix; unknown/unreachable entries remain null.
     *
     * @param  list<array{lat: float, lng: float}>  $sources
     * @param  list<array{lat: float, lng: float}>  $targets
     */
    public function matrix(array $sources, array $targets, ?string $mode = null): array
    {
        $mode ??= config('services.routing.default_mode', 'walking');
        if (count($sources) > 200 || count($targets) > 200) {
            return ['available' => false, 'status' => 'invalid_request', 'distances' => [], 'durations' => [], 'mode' => $mode];
        }
        $distances = array_fill(0, count($sources), array_fill(0, count($targets), null));
        $durations = $distances;
        if (! $this->validMode($mode) || empty($sources) || empty($targets)) {
            return ['available' => false, 'status' => 'invalid_request', 'distances' => $distances, 'durations' => $durations, 'mode' => $mode];
        }
        if ($this->provider() === 'ors') {
            return $this->matrixOrs($sources, $targets, $mode);
        }
        $batchSize = max(1, min(25, (int) config('services.routing.matrix_batch_size', 25)));
        $status = 'ok';
        $pairFallbacks = 0;
        foreach (array_chunk($sources, $batchSize, true) as $sourceChunk) {
            foreach (array_chunk($targets, $batchSize, true) as $targetChunk) {
                $validSources = array_filter($sourceChunk, $this->validPoint(...));
                $validTargets = array_filter($targetChunk, $this->validPoint(...));
                if (count($validSources) !== count($sourceChunk) || count($validTargets) !== count($targetChunk)) {
                    $status = 'partial';
                }
                if (empty($validSources) || empty($validTargets)) {
                    $status = 'partial';

                    continue;
                }
                $response = $this->request('sources_to_targets', [
                    'sources' => array_values(array_map($this->location(...), $validSources)),
                    'targets' => array_values(array_map($this->location(...), $validTargets)),
                    'costing' => $mode === 'walking' ? 'pedestrian' : 'auto',
                    'costing_options' => [($mode === 'walking' ? 'pedestrian' : 'auto') => ['use_ferry' => 0]],
                    'units' => 'kilometers',
                    'verbose' => true,
                ]);
                if (($response['_error'] ?? null) === 'no_route' && (count($validSources) > 1 || count($validTargets) > 1)) {
                    $status = 'partial';
                    foreach ($validSources as $sourceIndex => $source) {
                        foreach ($validTargets as $targetIndex => $target) {
                            if ($pairFallbacks++ >= 100) {
                                continue;
                            }
                            $pair = $this->matrix([$source], [$target], $mode);
                            $distances[$sourceIndex][$targetIndex] = $pair['distances'][0][0];
                            $durations[$sourceIndex][$targetIndex] = $pair['durations'][0][0];
                        }
                    }

                    continue;
                }
                $rows = $response['sources_to_targets'] ?? [];
                if (isset($response['_error']) || ! is_array($rows) || count($rows) !== count($validSources)) {
                    $status = $response['_error'] ?? 'invalid_response';

                    continue;
                }
                foreach (array_keys($validSources) as $rowIndex => $sourceIndex) {
                    foreach (array_keys($validTargets) as $colIndex => $targetIndex) {
                        $cell = $rows[$rowIndex][$colIndex] ?? [];
                        if ($this->validNumber($cell['distance'] ?? null) && $this->validNumber($cell['time'] ?? null)) {
                            $distances[$sourceIndex][$targetIndex] = (float) $cell['distance'];
                            $durations[$sourceIndex][$targetIndex] = (float) $cell['time'];
                        } else {
                            $status = 'partial';
                        }
                    }
                }
            }
        }

        return ['available' => $status === 'ok', 'status' => $status, 'distances' => $distances, 'durations' => $durations, 'mode' => $mode, 'provider' => 'valhalla'];
    }

    public function health(): bool
    {
        if ($this->provider() === 'ors') {
            return filled(config('services.routing.ors_api_key'));
        }
        $response = $this->request('status', [], false);

        return ! isset($response['_error']) && isset($response['version']);
    }

    public function validPoint(array $point): bool
    {
        return isset($point['lat'], $point['lng']) && is_numeric($point['lat']) && is_numeric($point['lng'])
            && is_finite((float) $point['lat']) && is_finite((float) $point['lng'])
            && abs((float) $point['lat']) <= 90 && abs((float) $point['lng']) <= 180;
    }

    private function validMode(string $mode): bool
    {
        return in_array($mode, ['walking', 'driving'], true);
    }

    private function validNumber(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0;
    }

    private function location(array $point): array
    {
        return ['lat' => (float) $point['lat'], 'lon' => (float) $point['lng'], 'search_cutoff' => max(1, (int) config('services.routing.snap_radius_meters', 150))];
    }

    private function unavailable(string $status, string $mode): array
    {
        return ['available' => false, 'status' => $status, 'provider' => $this->provider(), 'mode' => $mode, 'distance_km' => null, 'duration_seconds' => null, 'duration_minutes' => null, 'geometry' => null];
    }

    /**
     * POST to the OpenRouteService v2 API, with the same cache + cooldown
     * behaviour as the Valhalla transport so a free-tier quota goes further.
     */
    private function orsRequest(string $endpoint, array $payload): array
    {
        $apiKey = (string) config('services.routing.ors_api_key');
        if ($apiKey === '') {
            return ['_error' => 'not_configured'];
        }
        $baseUrl = rtrim((string) config('services.routing.ors_base_url', 'https://api.openrouteservice.org'), '/');
        $cooldownKey = 'routing:cooldown:ors';
        if (Cache::has($cooldownKey)) {
            return ['_error' => 'unavailable'];
        }
        $key = 'routing:ors:'.hash('sha256', json_encode([$endpoint, $payload], JSON_THROW_ON_ERROR));
        if (($result = Cache::get($key)) !== null) {
            return $result;
        }
        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
                // The /geojson directions endpoint rejects a plain application/json Accept.
                'Accept' => str_ends_with($endpoint, '/geojson') ? 'application/geo+json' : 'application/json',
            ])->connectTimeout(3)->timeout(max(1, (int) config('services.routing.timeout', 10)))
                ->post($baseUrl.'/v2/'.$endpoint, $payload);
            if (! $response->successful()) {
                if ($response->serverError()) {
                    Cache::put($cooldownKey, true, now()->addSeconds(15));
                }
                $code = (int) $response->json('error.code');
                $result = ['_error' => in_array($code, [2009, 2010], true) || $response->status() === 404 ? 'no_route' : 'unavailable'];
                if ($result['_error'] === 'no_route') {
                    Cache::put($key, $result, now()->addMinutes(5));
                }

                return $result;
            }
            $result = $response->json();
            if (! is_array($result)) {
                return ['_error' => 'invalid_response'];
            }
            Cache::put($key, $result, now()->addMinutes(max(1, (int) config('services.routing.cache_minutes', 1440))));
            Cache::forget($cooldownKey);

            return $result;
        } catch (ConnectionException) {
            Cache::put($cooldownKey, true, now()->addSeconds(15));

            return ['_error' => 'unavailable'];
        }
    }

    private function request(string $endpoint, array $payload, bool $cached = true): array
    {
        $baseUrl = rtrim((string) config('services.routing.base_url'), '/');
        if ($baseUrl === '') {
            return ['_error' => 'not_configured'];
        }
        $cooldownKey = 'routing:cooldown:'.hash('sha256', $baseUrl);
        if ($cached && Cache::has($cooldownKey)) {
            return ['_error' => 'unavailable'];
        }
        $key = 'routing:'.hash('sha256', json_encode([$baseUrl, config('services.routing.graph_version'), $endpoint, $payload], JSON_THROW_ON_ERROR));
        if ($cached && ($result = Cache::get($key)) !== null) {
            return $result;
        }
        try {
            $response = Http::connectTimeout(3)->timeout(max(1, (int) config('services.routing.timeout', 10)))
                ->acceptJson()->withOptions(['allow_redirects' => false])
                ->get($baseUrl.'/'.$endpoint, $payload === [] ? [] : ['json' => json_encode($payload, JSON_THROW_ON_ERROR)]);
            if (! $response->successful()) {
                $code = $response->json('error_code');
                if ($response->serverError()) {
                    Cache::put($cooldownKey, true, now()->addSeconds(15));
                }

                $result = ['_error' => in_array($code, [170, 171, 441, 442], true) || in_array($response->json('code'), ['NoSegment', 'NoRoute'], true) ? 'no_route' : 'unavailable'];
                if ($cached && $result['_error'] === 'no_route') {
                    Cache::put($key, $result, now()->addMinutes(5));
                }

                return $result;
            }
            $result = $response->json();
            if (! is_array($result)) {
                return ['_error' => 'invalid_response'];
            }
            if ($cached) {
                Cache::put($key, $result, now()->addMinutes(max(1, (int) config('services.routing.cache_minutes', 1440))));
            }
            Cache::forget($cooldownKey);

            return $result;
        } catch (ConnectionException) {
            Cache::put($cooldownKey, true, now()->addSeconds(15));

            return ['_error' => 'unavailable'];
        }
    }
}
