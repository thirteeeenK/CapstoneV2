<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Services\DistanceService;
use App\Services\RoutingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function routingFixture(): array
{
    return ['trip' => ['summary' => ['has_ferry' => false]], 'code' => 'Ok', 'routes' => [[
        'distance' => 1250, 'duration' => 930,
        'geometry' => ['type' => 'LineString', 'coordinates' => [[121.9257, 11.9564], [121.927, 11.96], [121.9255, 11.9658]]],
    ]]];
}

it('returns route geometry and normalized road distance through the authenticated endpoint', function () {
    config(['services.routing.base_url' => 'https://routing.test']);
    Http::fake(['routing.test/route*' => Http::response(routingFixture())]);
    $this->actingAs(onboardedUser())->getJson(route('api.dss.route', [
        'from_lat' => 11.9564, 'from_lng' => 121.9257, 'to_lat' => 11.9658, 'to_lng' => 121.9255,
    ]))->assertOk()->assertJsonPath('data.distance_km', 1.25)
        ->assertJsonPath('data.duration_minutes', 16)->assertJsonPath('data.geometry.type', 'LineString');
    Http::assertSent(fn (Request $request) => json_decode($request['json'], true)['costing'] === 'pedestrian');
});

it('rejects invalid coordinates and modes before querying Valhalla', function () {
    $this->actingAs(onboardedUser())->getJson(route('api.dss.route', [
        'from_lat' => 100, 'from_lng' => 121, 'to_lat' => 12, 'to_lng' => 122, 'mode' => 'flying',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['from_lat', 'mode']);
    Http::assertNothingSent();
});

it('requires authentication for the routing endpoint', function () {
    $this->getJson(route('api.dss.route'))->assertUnauthorized();
});

it('keeps unavailable routes unknown without a distance fallback', function () {
    config(['services.routing.base_url' => 'https://routing.test']);
    Http::fake(['routing.test/route*' => Http::response(['error_code' => 442], 400)]);
    $result = app(RoutingService::class)->route(['lat' => 11.9, 'lng' => 121.9], ['lat' => 12, 'lng' => 122]);
    expect($result['status'])->toBe('no_route')->and($result['distance_km'])->toBeNull()->and($result['geometry'])->toBeNull();
});

it('handles a failed provider connection without inventing travel time', function () {
    config(['services.routing.base_url' => 'https://routing.test']);
    Http::fake(['routing.test/route*' => Http::failedConnection()]);
    expect(app(RoutingService::class)->route(['lat' => 11.9, 'lng' => 121.9], ['lat' => 12, 'lng' => 122])['duration_seconds'])->toBeNull();
});

it('stops hammering a disconnected provider across matrix batches', function () {
    config(['services.routing.base_url' => 'https://routing.test', 'services.routing.matrix_batch_size' => 1]);
    Http::fake(['routing.test/sources_to_targets*' => Http::failedConnection()]);
    $points = [['lat' => 11.9, 'lng' => 121.9], ['lat' => 11.95, 'lng' => 121.95]];
    $matrix = app(RoutingService::class)->matrix($points, $points);
    expect($matrix['status'])->toBe('unavailable')->and($matrix['distances'])->toBe([[null, null], [null, null]]);
    Http::assertSentCount(1);
});

it('does not query any provider when routing is unconfigured', function () {
    config(['services.routing.base_url' => null]);
    expect(app(RoutingService::class)->route(['lat' => 11.9, 'lng' => 121.9], ['lat' => 12, 'lng' => 122])['status'])->toBe('not_configured');
    Http::assertNothingSent();
});

it('separates route cache entries by direction mode and graph version', function () {
    config(['services.routing.base_url' => 'https://routing.test', 'services.routing.graph_version' => 'test-v1']);
    Cache::flush();
    Http::fake(['routing.test/route*' => Http::response(routingFixture())]);
    $service = app(RoutingService::class);
    $from = ['lat' => 11.9564, 'lng' => 121.9257];
    $to = ['lat' => 11.9658, 'lng' => 121.9255];
    $service->route($from, $to);
    $service->route($from, $to);
    $service->route($to, $from);
    $service->route($from, $to, 'driving');
    config(['services.routing.graph_version' => 'test-v2']);
    $service->route($from, $to);
    Http::assertSentCount(8);
});

it('preserves directed matrix values and null unreachable cells', function () {
    config(['services.routing.base_url' => 'https://routing.test']);
    Http::fake(['routing.test/sources_to_targets*' => Http::response(['sources_to_targets' => [
        [['distance' => 0, 'time' => 0], ['distance' => 1.8, 'time' => 400]],
        [['distance' => null, 'time' => null], ['distance' => 0, 'time' => 0]],
    ]])]);
    $points = [['lat' => 11.9, 'lng' => 121.9], ['lat' => 12, 'lng' => 122]];
    $result = app(RoutingService::class)->matrix($points, $points);
    expect($result['distances'])->toBe([[0.0, 1.8], [null, 0.0]])->and($result['durations'][0][1])->toBe(400.0);
});

it('does not label ferry travel as an ordinary road or walking route', function () {
    config(['services.routing.base_url' => 'https://routing.test']);
    $fixture = routingFixture();
    $fixture['trip']['summary']['has_ferry'] = true;
    Http::fake(['routing.test/route*' => Http::response($fixture)]);
    $route = app(RoutingService::class)->route(['lat' => 11.96, 'lng' => 121.92], ['lat' => 11.92, 'lng' => 121.94]);
    expect($route['status'])->toBe('unsupported_transfer')->and($route['distance_km'])->toBeNull();
    Http::assertSentCount(1);
});

it('batches matrices and retains missing location positions', function () {
    config(['services.routing.base_url' => 'https://routing.test', 'services.routing.matrix_batch_size' => 2]);
    Http::fake(['routing.test/sources_to_targets*' => function (Request $request) {
        $payload = json_decode($request['json'], true);

        return Http::response(['sources_to_targets' => [array_map(fn ($point) => ['distance' => $point['lat'], 'time' => 60], $payload['targets'])]]);
    }]);
    $result = app(RoutingService::class)->matrix([['lat' => 11, 'lng' => 121]], [
        ['lat' => 12, 'lng' => 121], ['lat' => null, 'lng' => null], ['lat' => 13, 'lng' => 122],
    ]);
    expect($result['distances'][0])->toBe([12.0, null, 13.0]);
    Http::assertSentCount(2);
});

it('never substitutes a destination centre for an unknown activity location', function () {
    $destination = DestinationModel::factory()->create(['latitude' => 11.9, 'longitude' => 121.9]);
    $activity = ActivityModel::factory()->create(['destination_id' => $destination->id, 'latitude' => null, 'longitude' => null]);
    expect(app(DistanceService::class)->coordsOf($activity))->toBe([null, null]);
});

it('isolates a target outside graph coverage without losing other matrix distances', function () {
    config(['services.routing.base_url' => 'https://routing.test']);
    Http::fake(['routing.test/sources_to_targets*' => function (Request $request) {
        $payload = json_decode($request['json'], true);
        if (count($payload['targets']) > 1 || (float) $payload['targets'][0]['lon'] === 120.0) {
            return Http::response(['error_code' => 171], 400);
        }

        return Http::response(['sources_to_targets' => [[['distance' => 1.25, 'time' => 600]]]]);
    }]);
    $result = app(RoutingService::class)->matrix([['lat' => 11.9, 'lng' => 121.9]], [
        ['lat' => 11.95, 'lng' => 121.95], ['lat' => 11, 'lng' => 120],
    ]);
    expect($result['distances'][0])->toBe([1.25, null]);
    Http::assertSentCount(3);
});

it('rejects a provider geometry that is not a coordinate array', function () {
    config(['services.routing.base_url' => 'https://routing.test']);
    $fixture = routingFixture();
    $fixture['routes'][0]['geometry']['coordinates'] = 'invalid';
    Http::fake(['routing.test/route*' => Http::response($fixture)]);
    expect(app(RoutingService::class)->route(['lat' => 11.9, 'lng' => 121.9], ['lat' => 12, 'lng' => 122])['status'])->toBe('invalid_response');
});
