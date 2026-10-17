<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\Chat\IntentRouter;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 3072, 0.01)],
        ]),
        '*generateContent*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'Here is a recommendation for you!']]],
            ]],
        ]),
    ]);

    $embedding = '['.implode(',', array_fill(0, 3072, '0.01')).']';

    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'Test Beach Resort',
        'destination_id' => $this->destination->id,
        'embedding' => $embedding,
    ]);
    RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_name' => 'Deluxe Ocean View',
        'base_price' => 2500.00,
        'embedding' => $embedding,
    ]);
    $this->embedding = $embedding;
    $this->router = new IntentRouter;
});

test('a compound ask decomposes into every requested intent', function () {
    expect($this->router->compoundIntents('Find me a nice beachfront hotel in Boracay and some extreme activities'))
        ->toBe([IntentRouter::HOTEL_SEARCH, IntentRouter::ACTIVITY_SEARCH])
        ->and($this->router->compoundIntents("What's the weather in Boracay, and any tour packages?"))
        ->toBe([IntentRouter::WEATHER_QUERY, IntentRouter::PACKAGE_SEARCH]);
});

test('single asks and loose keywords never decompose', function (string $message) {
    expect($this->router->compoundIntents($message))->toBe([]);
})->with([
    'plain hotel search' => 'Show me hotels in Boracay',
    'amenity word is not a stay ask' => 'activities in Boracay for family',
    'one clause naming two catalogs' => 'resort with snorkeling in Boracay',
    'itinerary already covers stays' => '3 araw itinerary in Boracay and hotels',
    'human handoff stays terminal' => 'talk to an agent about hotels and tours',
]);

test('each section gets a focused sub-query that keeps the destination', function () {
    $intents = [IntentRouter::HOTEL_SEARCH, IntentRouter::ACTIVITY_SEARCH];

    expect($this->router->compoundSubQueries('Find me a nice beachfront hotel in Boracay and some extreme activities', $intents))
        ->toBe([
            IntentRouter::HOTEL_SEARCH => 'Find me a nice beachfront hotel in Boracay',
            IntentRouter::ACTIVITY_SEARCH => 'some extreme activities in Boracay',
        ]);
});

test('a compound chat message returns stay and activity sections with both card sets', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Parasailing',
        'destination_id' => $this->destination->id,
        'embedding' => $this->embedding,
    ]);

    $response = $this->postJson('/chat', [
        'message' => 'Find me a nice beachfront hotel in Boracay and some extreme activities',
    ]);

    $response->assertOk();
    expect($response->json('reply'))->toContain('### Where to stay')
        ->and($response->json('reply'))->toContain('### Things to do')
        ->and($response->json('retrieved_hotels.0.hotel_name'))->toBe('Test Beach Resort')
        ->and($response->json('retrieved_activities.0.activity_name'))->toBe('Parasailing')
        ->and($response->json('multi_intents'))->toBe([IntentRouter::HOTEL_SEARCH, IntentRouter::ACTIVITY_SEARCH])
        ->and(collect($response->json('suggested_actions'))->pluck('id'))->toContain('build-itinerary');
});

test('a compound section with no matches is stated instead of dropped', function () {
    $response = $this->postJson('/chat', [
        'message' => 'Find me a nice beachfront hotel in Boracay and some extreme activities',
    ]);

    $response->assertOk();
    expect($response->json('reply'))->toContain('### Things to do')
        ->and($response->json('retrieved_hotels.0.hotel_name'))->toBe('Test Beach Resort')
        ->and($response->json('retrieved_activities'))->toBeNull()
        ->and($response->json('retrieval_outcome.status'))->not->toBe('no_match');
});

test('a single-intent chat message keeps the regular unsectioned reply', function () {
    $response = $this->postJson('/chat', [
        'message' => 'Show me hotels in Boracay',
    ]);

    $response->assertOk();
    expect($response->json('reply'))->not->toContain('### Where to stay')
        ->and($response->json('multi_intents'))->toBeNull()
        ->and($response->json('retrieved_hotels.0.hotel_name'))->toBe('Test Beach Resort');
});
