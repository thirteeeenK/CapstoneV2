<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;

beforeEach(function () {
    $this->boracay = DestinationModel::factory()->create([
        'name' => 'Boracay',
        'description' => 'White beach',
        'latitude' => 11.9674,
        'longitude' => 121.9251,
    ]);
    $this->elnido = DestinationModel::factory()->create([
        'name' => 'El Nido',
        'description' => 'Limestone cliffs',
        'latitude' => 11.1782,
        'longitude' => 119.2950,
    ]);

    // The only Jet Ski in the catalog lives in Boracay.
    ActivityModel::factory()->create([
        'destination_id' => $this->boracay->id,
        'activity_name' => 'Jet Ski (30 mins)',
        'rate' => '₱1500/person',
        'embedding' => unitVectorString(0),
    ]);

    // El Nido alternatives live on the same embedding axis so vector
    // searches return them for an e1 query embedding.
    ActivityModel::factory()->create([
        'destination_id' => $this->elnido->id,
        'activity_name' => 'Clear Kayak',
        'rate' => '₱800/person',
        'embedding' => unitVectorString(0),
    ]);
    ActivityModel::factory()->create([
        'destination_id' => $this->elnido->id,
        'activity_name' => 'Stand-Up Paddleboard',
        'rate' => '₱600/person',
        'embedding' => unitVectorString(0),
    ]);
});

test('jetski price query in El Nido discloses the Boracay-only Jet Ski', function () {
    mockGemini(unitVector(0));

    $response = $this->postJson('/chat', [
        'message' => 'jetski price El Nido',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $reply = $response->json('reply');
    expect($reply)->toContain('Just so you know');
    expect($reply)->toContain('Boracay');
    expect($response->json('retrieved_activities') ?? [])->not->toBeEmpty();
});

test('named activity with wrong destination returns scoped alternatives with disclosure', function () {
    mockGemini(unitVector(0));

    // The Jet Ski lives only in Boracay; destination-scoped exact match
    // must not return it. Semantic search yields El Nido alternatives
    // and the cross-scope disclosure fires (same as the sibling test).
    $response = $this->postJson('/chat', [
        'message' => 'jetski in El Nido',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $activities = $response->json('retrieved_activities') ?? [];
    expect($activities)->not->toBeEmpty();
    expect(collect($activities)->pluck('activity_name')->implode(' '))->toContain('Kayak');
    expect($response->json('reply'))->toContain('Just so you know');
    expect($response->json('reply'))->toContain('Boracay');
});

test('unknown thing reports not in catalog with alternatives', function () {
    mockGemini(unitVector(0));

    $response = $this->postJson('/chat', [
        'message' => 'skydive in Boracay',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->toContain("couldn't find");
    expect($response->json('retrieved_activities') ?? [])->not->toBeEmpty();
});

test('traveler profiles do not produce a contradictory hotel absence notice', function () {
    mockGemini(unitVector(0));

    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Spin Designer Hostel',
        'destination_id' => $this->elnido->id,
        'embedding' => unitVectorString(0),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Shared Dorm Bed',
        'base_price' => 1900,
        'embedding' => unitVectorString(0),
    ]);

    $response = $this->postJson('/chat', [
        'message' => 'hotels good for backpackers',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->toContain('Spin Designer Hostel')
        ->and($response->json('reply'))->not->toContain("couldn't find backpackers");
});

test('misspelled stopword shows no notice', function () {
    mockGemini(unitVector(0));

    // "recommendaition" is a typo of a request verb — fuzzy stopword
    // matching must eat it instead of firing a not-in-catalog notice.
    $response = $this->postJson('/chat', [
        'message' => 'activity recommendaition for El Nido',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->not->toContain('catalog');
});

test('generic activity query shows no notice', function () {
    mockGemini(unitVector(0));

    $response = $this->postJson('/chat', [
        'message' => 'activities in El Nido',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->not->toContain('Heads up');
    expect($response->json('retrieved_activities') ?? [])->not->toBeEmpty();
});

test('matching name query shows no notice', function () {
    mockGemini(unitVector(0));

    $response = $this->postJson('/chat', [
        'message' => 'Jet Ski in Boracay price',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->not->toContain('Heads up');
});

test('category-word query shows no notice', function () {
    mockGemini(unitVector(0));

    // "water" is a category/medium word, not a nameable thing — it must
    // never trigger the not-in-catalog notice.
    $response = $this->postJson('/chat', [
        'message' => 'What water activities do you have in Boracay?',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->not->toContain('Heads up');
    expect($response->json('retrieved_activities') ?? [])->not->toBeEmpty();
});

test('request-verb query shows no notice', function () {
    mockGemini(unitVector(0));

    // "recommendation" is a request verb, not a catalog thing.
    $response = $this->postJson('/chat', [
        'message' => 'water activities recommendation in Boracay',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->not->toContain('Heads up');
});

test('cross-scope notice names the scope plainly', function () {
    mockGemini(unitVector(0));

    $response = $this->postJson('/chat', [
        'message' => 'jetski price El Nido',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->toContain('closest El Nido alternatives');
});

test('Taglish price questions disclose alternatives without treating request words as offerings', function (string $query) {
    ActivityModel::where('activity_name', 'Jet Ski (30 mins)')->update(['is_shown' => false]);
    mockGemini(unitVector(0), "I can't seem to find jet ski rentals in our database. Try asking about hotels instead.");

    $response = $this->postJson('/chat', ['message' => $query]);

    $response->assertOk();
    expect($response->json('retrieved_activities'))->not->toBeEmpty();
    expect($response->json('reply'))
        ->toContain("I couldn't find a confirmed match")
        ->toContain('Clear Kayak')
        ->not->toContain('find magkano')
        ->not->toContain('find presyo')
        ->not->toContain('Prices are shown on each card')
        ->not->toContain("I can't seem")
        ->not->toContain('Try asking about hotels');
})->with(['magkano ang jet ski', 'ano po ang presyo ng jet ski sa El Nido']);

test('a partial compound offering match does not count as a confirmed match', function () {
    ActivityModel::factory()->create([
        'destination_id' => $this->elnido->id,
        'activity_name' => 'Jet Boat',
        'description' => 'A powered boat excursion.',
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0));

    $response = $this->postJson('/chat', ['message' => 'magkano ang jet ski sa El Nido']);

    $response->assertOk();
    expect($response->json('reply'))->toContain('Just so you know')
        ->toContain('Jet Ski (30 mins)')
        ->toContain('Boracay');
});

test('an existing jet ski answers a Taglish price question without an absence notice', function () {
    mockGemini(unitVector(0));

    $response = $this->postJson('/chat', ['message' => 'magkano ang jet ski sa Boracay']);

    $response->assertOk();
    expect($response->json('reply'))->toContain('Jet Ski (30 mins)')
        ->not->toContain("couldn't find")
        ->not->toContain('alternatives instead');
});

test('add-on mismatches use the same single fallback answer', function () {
    AddOnModel::factory()->create([
        'destination_id' => $this->elnido->id,
        'name' => 'Shared Airport Transfer',
        'description' => 'Shared van transfer.',
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0), 'Unverified helicopter transfer is available.');

    $response = $this->postJson('/chat', ['message' => 'magkano helicopter add-on sa El Nido']);

    $response->assertOk();
    expect($response->json('retrieved_addons'))->not->toBeEmpty();
    expect($response->json('reply'))->toContain("I couldn't find a confirmed match")
        ->toContain('Shared Airport Transfer')
        ->not->toContain('find magkano')
        ->not->toContain('Unverified helicopter');
});

test('traveler preferences do not produce an activity absence notice', function () {
    mockGemini(unitVector(0), 'These are the available activities.');

    $response = $this->postJson('/chat', ['message' => 'activities good for backpackers in El Nido']);

    $response->assertOk();
    expect($response->json('retrieved_activities'))->not->toBeEmpty();
    expect($response->json('reply'))->not->toContain("couldn't find")
        ->not->toContain('not a confirmed match');
});

test('resolved activity detail questions retain the field answer', function () {
    ActivityModel::where('activity_name', 'Jet Ski (30 mins)')->update(['duration' => '30 minutes']);
    mockGemini(unitVector(0), 'The activity lasts 30 minutes.');

    $response = $this->postJson('/chat', ['message' => 'Jet Ski (30 mins) duration in Boracay']);

    $response->assertOk();
    expect($response->json('reply'))->toContain('30 minutes')
        ->not->toContain("couldn't find")
        ->not->toContain('Other options:');
});
