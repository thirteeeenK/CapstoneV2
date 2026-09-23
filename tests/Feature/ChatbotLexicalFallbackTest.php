<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->boracay = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->cebu = DestinationModel::factory()->create(['name' => 'Cebu']);
});

test('hotel search with embedding failure returns visible destination hotels and reports lexical retrieval mode', function () {
    Http::fake([
        '*embedContent*' => Http::response(['error' => 'embedding down'], 500),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Here are hotels.']]]]]]),
    ]);

    HotelModel::factory()->create([
        'hotel_name' => 'Visible Boracay Resort',
        'destination_id' => $this->boracay->id,
        'is_shown' => true,
    ]);
    HotelModel::factory()->create([
        'hotel_name' => 'Hidden Boracay Resort',
        'destination_id' => $this->boracay->id,
        'is_shown' => false,
    ]);
    HotelModel::factory()->create([
        'hotel_name' => 'Cebu Only Suites',
        'destination_id' => $this->cebu->id,
        'is_shown' => true,
    ]);

    $response = $this->postJson('/chat', [
        'message' => 'Show me hotels in Boracay',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');

    $names = collect($response->json('retrieved_hotels') ?? [])->pluck('hotel_name');
    expect($names)
        ->toContain('Visible Boracay Resort')
        ->not->toContain('Hidden Boracay Resort')
        ->not->toContain('Cebu Only Suites');

    expect($response->json('trace.retrieval_mode'))->toBe('lexical');
});

test('successful semantic hotel search reports semantic retrieval mode', function () {
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => array_fill(0, 3072, 0.01)]]),
    ]);

    HotelModel::factory()->create([
        'destination_id' => $this->boracay->id,
        'embedding' => '['.implode(',', array_fill(0, 3072, '0.01')).']',
    ]);

    $service = app(GeminiService::class);
    $results = $service->searchHotels('beach resort', 5, null, $this->boracay->id);

    expect($results)->not->toBeEmpty()
        ->and($service->lastRetrievalMode)->toBe('semantic');
});

test('activity search falls back to visible destination activities when embedding fails', function () {
    Http::fake([
        '*embedContent*' => Http::response(['error' => 'embedding down'], 500),
    ]);

    ActivityModel::factory()->create([
        'activity_name' => 'Visible Boracay Island Hopping',
        'destination_id' => $this->boracay->id,
        'is_shown' => true,
    ]);
    ActivityModel::factory()->create([
        'activity_name' => 'Hidden Boracay Snorkel',
        'destination_id' => $this->boracay->id,
        'is_shown' => false,
    ]);
    ActivityModel::factory()->create([
        'activity_name' => 'Cebu Canyon Trek',
        'destination_id' => $this->cebu->id,
        'is_shown' => true,
    ]);

    $service = app(GeminiService::class);
    $results = $service->searchActivities('fun things to do', 5, $this->boracay->id);

    $names = collect($results)->map(fn ($e) => $e['item']->activity_name);
    expect($names)
        ->toContain('Visible Boracay Island Hopping')
        ->not->toContain('Hidden Boracay Snorkel')
        ->not->toContain('Cebu Canyon Trek');
    expect($service->lastRetrievalMode)->toBe('lexical');
});

test('package search falls back to active destination packages when embedding fails', function () {
    Http::fake([
        '*embedContent*' => Http::response(['error' => 'embedding down'], 500),
    ]);

    Package::factory()->create([
        'name' => 'Boracay Island Escape Deal',
        'destination_id' => $this->boracay->id,
        'is_active' => true,
    ]);
    Package::factory()->create([
        'name' => 'Boracay Retired Deal',
        'destination_id' => $this->boracay->id,
        'is_active' => false,
    ]);
    Package::factory()->create([
        'name' => 'Cebu City Deal',
        'destination_id' => $this->cebu->id,
        'is_active' => true,
    ]);

    $service = app(GeminiService::class);
    $results = $service->searchPackages('nice getaway deal', 5, $this->boracay->id);

    $names = collect($results)->map(fn ($e) => $e['item']->name);
    expect($names)
        ->toContain('Boracay Island Escape Deal')
        ->not->toContain('Boracay Retired Deal')
        ->not->toContain('Cebu City Deal');
    expect($service->lastRetrievalMode)->toBe('lexical');
});

test('add-on search falls back to visible destination add-ons when embedding fails', function () {
    Http::fake([
        '*embedContent*' => Http::response(['error' => 'embedding down'], 500),
    ]);

    AddOnModel::factory()->create([
        'name' => 'Boracay Airport Transfer',
        'destination_id' => $this->boracay->id,
        'is_shown' => true,
    ]);
    AddOnModel::factory()->create([
        'name' => 'Hidden Boracay Yacht',
        'destination_id' => $this->boracay->id,
        'is_shown' => false,
    ]);
    AddOnModel::factory()->create([
        'name' => 'Cebu Ferry Pickup',
        'destination_id' => $this->cebu->id,
        'is_shown' => true,
    ]);

    $service = app(GeminiService::class);
    $results = $service->searchAddOns('transfer please', 5, $this->boracay->id);

    $names = collect($results)->map(fn ($e) => $e['item']->name);
    expect($names)
        ->toContain('Boracay Airport Transfer')
        ->not->toContain('Hidden Boracay Yacht')
        ->not->toContain('Cebu Ferry Pickup');
    expect($service->lastRetrievalMode)->toBe('lexical');
});

test('room hybrid search reports lexical retrieval mode when embedding fails', function () {
    Http::fake([
        '*embedContent*' => Http::response(['error' => 'embedding down'], 500),
    ]);

    $hotel = HotelModel::factory()->create([
        'destination_id' => $this->boracay->id,
        'is_shown' => true,
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Lexical Fallback Room',
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);

    $service = app(GeminiService::class);
    $results = $service->searchRoomsHybrid('cheap room in Boracay', ['destination_id' => $this->boracay->id], 5);

    expect($results)->not->toBeEmpty()
        ->and($service->lastRetrievalMode)->toBe('lexical');
});
