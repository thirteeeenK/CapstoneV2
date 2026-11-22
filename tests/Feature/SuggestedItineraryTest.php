<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\TripPlan;
use App\Models\UserPreference;
use App\Services\Recommendations\SuggestedItineraryBuilder;
use App\Services\TripBookingDatePolicy;
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
        ->assertSee(route('lucky.index'))
        ->assertSee(route('trip-planner.suggested'))
        ->assertSee('Open this itinerary in Trip Planner');
});

it('opens the suggested itinerary as a dated trip plan with its room and activities already chosen', function () {
    [$destination, $hotel, $activities] = suggestionFixture();
    $user = onboardedUser();
    $room = $hotel->rooms->firstWhere('room_name', 'Garden Twin');
    // Strings, exactly as the dashboard's HTML form submits them.
    $payload = ['destination_id' => (string) $destination->id, 'room_id' => (string) $room->id,
        'activity_ids' => $activities->pluck('id')->map(fn ($id) => (string) $id)->all(), 'pax' => '2', 'nights' => '2'];

    $response = $this->actingAs($user)->post(route('trip-planner.suggested'), $payload);

    $plan = TripPlan::query()->where('user_id', $user->id)->sole();
    $response->assertRedirect(route('trip-planner.index', ['plan' => $plan->id, 'generate' => 1]));
    expect($plan)->source->toBe('suggested')->status->toBe('dated')->pax->toBe(2)->duration_days->toBe(3)
        ->and($plan->start_date->toDateString())->toBe(app(TripBookingDatePolicy::class)->minimumArrivalDate())
        ->and($plan->items->where('item_type', 'room')->pluck('item_id')->all())->toBe([$room->id])
        ->and($plan->items->where('item_type', 'activity')->pluck('item_id')->sort()->values()->all())->toBe($activities->pluck('id')->sort()->values()->all());

    $this->post(route('trip-planner.suggested'), $payload)->assertRedirect(route('trip-planner.index', ['plan' => $plan->id, 'generate' => 1]));
    expect(TripPlan::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('does not create a plan from a room outside the suggested destination', function () {
    [$destination] = suggestionFixture();
    $otherRoom = RoomType::factory()->create();

    $this->actingAs(onboardedUser())->post(route('trip-planner.suggested'), [
        'destination_id' => $destination->id, 'room_id' => $otherRoom->id, 'pax' => 2, 'nights' => 2,
    ])->assertSessionHasErrors('item_id');

    expect(TripPlan::query()->count())->toBe(0);
});

it('auto-builds the schedule when the planner opens a suggested plan', function () {
    $this->actingAs(onboardedUser())->get(route('trip-planner.index', ['plan' => 5, 'generate' => 1]))
        ->assertOk()->assertSee('autoGenerate: true', false);
});

it('sends a GET to the suggested itinerary URL back to the dashboard instead of failing', function () {
    $this->actingAs(onboardedUser())->get(route('trip-planner.suggested.show'))
        ->assertRedirect(route('dashboard'))->assertSessionHas('info');
});

it('books enough rooms when no single room sleeps the whole party', function () {
    [$destination, $hotel, $activities] = suggestionFixture();
    $preference = new UserPreference(['traveler_type' => 'Friends Group']);

    $suggestion = app(SuggestedItineraryBuilder::class)->build($destination, collect([$hotel]), $activities, $preference, true);

    // Four travelers: four Solo Pods (₱4,800/night) cost less than two Garden Twins (₱6,000/night).
    expect($suggestion)->pax->toBe(4)->room_quantity->toBe(4)->room_pax->toBe(1)
        ->and($suggestion['room']->room_name)->toBe('Solo Pod')
        ->and($suggestion['prices']['stay'])->toBe(9600.0);

    $user = onboardedUser();
    $this->actingAs($user)->post(route('trip-planner.suggested'), [
        'destination_id' => (string) $destination->id, 'room_id' => (string) $suggestion['room']->id, 'room_quantity' => (string) $suggestion['room_quantity'], 'pax' => '4', 'nights' => '2',
        'activity_ids' => $activities->pluck('id')->map(fn ($id) => (string) $id)->all(),
    ])->assertRedirect();

    $room = TripPlan::query()->where('user_id', $user->id)->sole()->items->firstWhere('item_type', 'room');
    expect($room->options)->toMatchArray(['quantity' => 4, 'pax' => 1]);
});

it('returns to the suggestion card with the reason when the plan cannot be created', function () {
    [$destination, $hotel] = suggestionFixture();
    $tooSmall = $hotel->rooms->firstWhere('room_name', 'Solo Pod');

    $this->actingAs(onboardedUser())->post(route('trip-planner.suggested'), [
        'destination_id' => (string) $destination->id, 'room_id' => (string) $tooSmall->id, 'room_quantity' => '1', 'pax' => '4', 'nights' => '2',
    ])->assertRedirect(route('dashboard').'#suggested-itinerary')->assertSessionHasErrors('room');

    expect(TripPlan::query()->count())->toBe(0);
});
