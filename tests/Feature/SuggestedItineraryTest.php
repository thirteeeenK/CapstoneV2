<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\UserPreference;
use App\Services\Recommendations\SuggestedItineraryBuilder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.gemini.api_key' => null,
        'services.openweather.api_key' => 'test-key',
        'services.openweather.base_url' => 'https://weather.test',
        'services.routing.provider' => 'valhalla',
        'services.routing.base_url' => 'https://routing.test',
    ]);

    $slot = fn (string $time, float $pop) => ['dt_txt' => $time, 'main' => ['temp' => 29, 'temp_min' => 27, 'temp_max' => 31, 'humidity' => 70], 'weather' => [['description' => 'scattered clouds', 'icon' => '03d']], 'pop' => $pop];
    Http::fake([
        'weather.test/*' => Http::response(['list' => [$slot('2026-12-01 03:00:00', 0.1), $slot('2026-12-02 03:00:00', 0.2)]]),
        'routing.test/sources_to_targets*' => Http::response(['sources_to_targets' => [[
            ['distance' => 1.25, 'time' => 900], ['distance' => 3.4, 'time' => 2400], ['distance' => 0.8, 'time' => 600],
        ]]]),
    ]);
});

function suggestionFixture(): array
{
    $destination = DestinationModel::factory()->create();
    $hotel = HotelModel::factory()->create(['destination_id' => $destination->id, 'latitude' => 11.95, 'longitude' => 121.92]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'room_name' => 'Garden Twin', 'base_price' => 3000, 'max_occupancy' => 2]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'room_name' => 'Solo Pod', 'base_price' => 1200, 'max_occupancy' => 1]);
    $activities = collect([800, 1500, 600])->map(fn (int $rate) => ActivityModel::factory()->create([
        'destination_id' => $destination->id, 'rate' => "₱{$rate}/person", 'latitude' => 11.96, 'longitude' => 121.93,
    ]));

    return [$destination, $hotel->load('rooms'), $activities];
}

it('builds a priced 3-day itinerary with weather and hotel-to-activity road distances', function () {
    [$destination, $hotel, $activities] = suggestionFixture();
    $preference = new UserPreference(['traveler_type' => 'Solo Traveler']);

    $suggestion = app(SuggestedItineraryBuilder::class)->build($destination, collect([$hotel]), $activities, $preference, true);

    expect($suggestion['pax'])->toBe(1)
        ->and($suggestion['room']->room_name)->toBe('Solo Pod')
        ->and($suggestion['days'])->toHaveCount(3)
        ->and($suggestion['prices'])->toBe(['stay' => 2400.0, 'activities' => 2900.0, 'total' => 5300.0])
        ->and($suggestion['weather']['level'])->toBe('good')
        ->and($suggestion['weather']['days'])->toHaveCount(2)
        ->and($suggestion['days'][0]['activities'][0]['leg'])->toMatchArray(['distance_km' => 1.25, 'duration_minutes' => 15, 'estimated' => false]);
});

it('sizes the party from the traveler type', function (?string $type, int $pax) {
    expect(app(SuggestedItineraryBuilder::class)->paxFor($type))->toBe($pax);
})->with([
    ['Solo Traveler', 1],
    ['Couple / Honeymoon', 2],
    ['Family with Kids', 4],
    ['Friends Group', 4],
    [null, 2],
]);

it('shows the suggested itinerary and the Lucky entry point on the dashboard', function () {
    [$destination, $hotel] = suggestionFixture();
    $user = onboardedUser();
    $user->userPreference()->create(['destination' => $destination->name, 'traveler_type' => 'Couple / Honeymoon']);
    $hotel->update(['embedding' => '['.implode(',', array_fill(0, 3072, '0.01')).']']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Suggested itinerary for you')
        ->assertSee('3 days in '.$destination->name.' for 2 travelers')
        ->assertSee($hotel->hotel_name, false)
        ->assertSee('Estimated cost')
        ->assertSee(route('lucky.index'));
});
