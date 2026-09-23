<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;

beforeEach(function () {
    $this->destination = DestinationModel::factory()->create([
        'name' => 'Boracay',
        'description' => 'White beach',
        'latitude' => 11.9674,
        'longitude' => 121.9251,
    ]);

    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'Test Beach Resort',
        'destination_id' => $this->destination->id,
        'type' => 'Resort',
        'hotel_description' => 'A test resort.',
        'specific_address' => 'Station 1',
        'latitude' => 11.9674,
        'longitude' => 121.9251,
    ]);

    // Rooms live on axis e2, activities on axis e1 — a query embedding of e1
    // must route to activities, e2 to rooms.
    $this->room = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_name' => 'Deluxe Ocean View',
        'base_price' => 2500.00,
        'embedding' => unitVectorString(1),
    ]);

    $this->activity = ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'ATV Adventure Ride',
        // Pinned above every test price ceiling: the factory default is a
        // random ₱300–2000, which flaked budget assertions whenever it drew low.
        'rate' => '₱850/person',
        'embedding' => unitVectorString(0),
    ]);
});

test('reported ATV query routes to activity search and returns ATV cards', function () {
    mockGemini(unitVector(0));

    $response = $this->postJson('/chat', [
        'message' => 'May atv offering ba dito sa sunnytrips?',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $activities = $response->json('retrieved_activities') ?? [];
    expect($activities)->not->toBeEmpty();
    expect(collect($activities)->pluck('activity_name')->implode(' '))->toContain('ATV Adventure Ride');
});

test('semantic fallback routes a keyword-less offering query to activities', function () {
    mockGemini(unitVector(0));

    // "bago" (new) has no catalog keyword but the query is travel-related.
    $response = $this->postJson('/chat', [
        'message' => 'May bago ba kayo sa Boracay trips?',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('retrieved_activities') ?? [])->not->toBeEmpty();
});

test('embedding failure preserves the old room-search default', function () {
    mockGemini(null);

    $response = $this->postJson('/chat', [
        'message' => 'May bago ba kayo sa Boracay trips?',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('retrieved_rooms') ?? [])->not->toBeEmpty();
});

test('yes search after an activity offer repeats the activity search', function () {
    mockGemini(unitVector(0), 'Would you like me to search for ATV activities in Boracay?');

    $first = $this->postJson('/chat', [
        'message' => 'May atv offering ba kayo in Boracay?',
    ]);
    $first->assertOk();
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'message' => 'yes, search.',
        'session_token' => $token,
    ]);

    $second->assertOk()->assertJsonPath('status', 'success');
    $activities = $second->json('retrieved_activities') ?? [];
    expect($activities)->not->toBeEmpty();
    expect($second->json('retrieved_rooms') ?? [])->toBeEmpty();
});

test('follow-up naming an off-card activity starts a fresh search', function () {
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Banana Boat',
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0));

    $first = $this->postJson('/chat', [
        'message' => 'ATV Adventure Ride',
    ]);
    $first->assertOk();

    $second = $this->postJson('/chat', [
        'message' => 'how about banana boat',
        'session_token' => $first->json('session_token'),
    ]);

    $second->assertOk()->assertJsonPath('status', 'success');
    $activities = $second->json('retrieved_activities') ?? [];
    expect($activities)->toHaveCount(1);
    expect($activities[0]['activity_name'])->toContain('Banana Boat');
});

test('activity combo follow-up with catalog keywords starts a fresh search', function () {
    mockGemini(unitVector(0));

    $first = $this->postJson('/chat', [
        'message' => 'May atv offering ba kayo in Boracay?',
    ]);
    $first->assertOk();
    expect($first->json('retrieved_activities') ?? [])->not->toBeEmpty();

    $second = $this->postJson('/chat', [
        'message' => 'atv and zipline combo, is it offered?',
        'session_token' => $first->json('session_token'),
    ]);

    $second->assertOk()->assertJsonPath('status', 'success');
    expect($second->json('retrieved_activities') ?? [])->not->toBeEmpty();
    expect($second->json('reply'))->not->toContain('previous recommendations');
});

test('yes search reuses the previous user message as the query', function () {
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Banana Boat',
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0), 'Would you like me to search for that banana boat separately?');

    $first = $this->postJson('/chat', [
        'message' => 'how about banana boat',
    ]);
    $first->assertOk();
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'message' => 'yes search',
        'session_token' => $token,
    ]);

    $second->assertOk()->assertJsonPath('status', 'success');
    $activities = $second->json('retrieved_activities') ?? [];
    expect($activities)->toHaveCount(1);
    expect($activities[0]['activity_name'])->toContain('Banana Boat');
});

test('keyword-less typo routes to activity search via general-talk fallback', function () {
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Jet Ski (30 mins)',
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0));

    // "jetski" (no space) matches no keyword, so it classifies GENERAL_TALK —
    // the semantic fallback must still find the Jet Ski activity.
    $response = $this->postJson('/chat', [
        'message' => 'jetski',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $activities = $response->json('retrieved_activities') ?? [];
    expect($activities)->not->toBeEmpty();
    expect(collect($activities)->pluck('activity_name')->implode(' '))->toContain('Jet Ski (30 mins)');
});

test('add-on routing is destination-scoped and routing scores surface in trace', function () {
    $cebu = DestinationModel::factory()->create([
        'name' => 'Cebu',
        'description' => 'Queen of the South',
        'latitude' => 10.3157,
        'longitude' => 123.8854,
    ]);

    // Perfect semantic match on axis e4 — but the add-on lives in Cebu while
    // the conversation is scoped to Boracay, so routing must never pick it.
    AddOnModel::factory()->create([
        'destination_id' => $cebu->id,
        'name' => 'Canyoneering Adventure Fee',
        'embedding' => unitVectorString(4),
    ]);

    mockGemini(unitVector(4), 'Here is what I found.');

    $response = $this->postJson('/chat', [
        'message' => 'May bago ba kayo sa Boracay trips?',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('trace.intent'))->not->toBe('ADDON_SEARCH');
    expect($response->json('retrieved_addons') ?? [])->toBeEmpty();

    $scores = $response->json('trace.routing_scores');
    expect($scores)->toBeArray()
        ->not->toHaveKey('addons')
        ->toHaveKeys(['activities', 'rooms']);
});

test('greeting with no catalog affinity stays in general chat', function () {
    // Orthogonal vector: ~zero similarity to every catalog → below the floor.
    mockGemini(unitVector(5), 'Hello! How can I help with your trip?');

    $response = $this->postJson('/chat', [
        'message' => 'hello',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('retrieved_activities') ?? [])->toBeEmpty();
    expect($response->json('retrieved_rooms') ?? [])->toBeEmpty();
    expect($response->json('retrieved_hotels') ?? [])->toBeEmpty();
    expect($response->json('retrieved_packages') ?? [])->toBeEmpty();
});

test('guest booking status reply links to the login page', function () {
    mockGemini(null, 'unused');

    $this->postJson('/chat', ['message' => 'What is the status of my booking?'])
        ->assertOk()
        ->assertJson(['status' => 'success'])
        ->assertJsonPath('reply', fn ($reply) => str_contains($reply, 'log in') && str_contains($reply, route('login')));
});

test('single package result strips the ranked-by-system line', function () {
    Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Boracay Sulit Deal',
        'embedding' => unitVectorString(0),
        'valid_from' => null,
        'valid_to' => null,
    ]);
    mockGemini(unitVector(0), "Here's the Boracay Sulit Deal!\n\nRanked by system: #1 is best match, #2+ are close alternatives.");

    $response = $this->postJson('/chat', [
        'message' => 'Boracay packages',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('retrieved_packages') ?? [])->toHaveCount(1);
    $reply = $response->json('reply');
    expect($reply)->toContain('Boracay Sulit Deal')
        ->and($reply)->not->toContain('close alternatives');
});

test('multiple package results keep the ranked-by-system line', function () {
    Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Boracay Sulit Deal',
        'embedding' => unitVectorString(0),
        'valid_from' => null,
        'valid_to' => null,
    ]);
    Package::factory()->create([
        'destination_id' => $this->destination->id,
        'name' => 'Boracay Best Deal',
        'embedding' => unitVectorString(0),
        'valid_from' => null,
        'valid_to' => null,
    ]);
    mockGemini(unitVector(0), "Best match first!\n\nRanked by system: #1 is best match, #2+ are close alternatives.");

    $response = $this->postJson('/chat', [
        'message' => 'Boracay packages',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('retrieved_packages') ?? [])->toHaveCount(2);
    expect($response->json('reply'))->toContain('close alternatives');
});

test('activity budget filter keeps only activities within the price ceiling', function () {
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Crystal Kayak',
        'rate' => '₱300/person',
        'embedding' => unitVectorString(0),
    ]);
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Banana Boat',
        'rate' => '₱450/person',
        'embedding' => unitVectorString(0),
    ]);
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'ATV Adventure Ride',
        'rate' => '₱850/person',
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0), 'Here are the activities within your budget.');

    $response = $this->postJson('/chat', [
        'message' => 'show me activities under 500',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $activities = $response->json('retrieved_activities') ?? [];
    expect($activities)->toHaveCount(2);
    expect(collect($activities)->pluck('activity_name'))->not->toContain('ATV Adventure Ride');
    expect($response->json('reply'))->not->toContain('could not find any activities');
});

test('activity budget with no affordable match returns the closest options', function () {
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Crystal Kayak',
        'rate' => '₱300/person',
        'embedding' => unitVectorString(0),
    ]);
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'ATV Adventure Ride',
        'rate' => '₱850/person',
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0), 'Here are the activities.');

    $response = $this->postJson('/chat', [
        'message' => 'show me activities under 100',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->toContain('closest options');
    $activities = $response->json('retrieved_activities') ?? [];
    expect($activities)->not->toBeEmpty();
    expect($activities[0]['activity_name'])->toContain('Crystal Kayak');
});

test('cheapest activity query sorts by real price, not semantic order', function () {
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Crystal Kayak',
        'rate' => '₱300/person',
        'embedding' => unitVectorString(0),
    ]);
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Helmet Diving',
        'rate' => '₱850/person',
        'embedding' => unitVectorString(0),
    ]);
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Zipline',
        'rate' => '₱750/person',
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0), 'Crystal Kayak is the cheapest activity.');

    $response = $this->postJson('/chat', [
        'message' => 'what is the cheapest activity in Boracay',
    ]);

    $response->assertOk();
    $activities = $response->json('retrieved_activities') ?? [];
    expect($activities)->not->toBeEmpty();
    expect($activities[0]['activity_name'])->toContain('Crystal Kayak');
});

test('comparison query returns both named activities', function () {
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Banana Boat',
        'rate' => '₱450/person',
        'embedding' => unitVectorString(0),
    ]);
    ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'activity_name' => 'Parasailing',
        'rate' => '₱1200/person',
        'embedding' => unitVectorString(0),
    ]);
    mockGemini(unitVector(0), 'Here is the comparison.');

    $response = $this->postJson('/chat', [
        'message' => 'compare banana boat and parasailing',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $activities = $response->json('retrieved_activities') ?? [];
    $names = collect($activities)->pluck('activity_name')->implode(' ');
    expect($activities)->toHaveCount(2);
    expect($names)->toContain('Banana Boat')->toContain('Parasailing');
});

test('world-knowledge question does not leak catalog cards', function () {
    // The embedding matches a catalog — the guard must still keep this general.
    mockGemini(unitVector(0), 'Paris is the capital of France.');

    $response = $this->postJson('/chat', [
        'message' => 'what is the capital of france',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply'))->toBe('Paris is the capital of France.');
    expect($response->json('retrieved_activities') ?? [])->toBeEmpty();
    expect($response->json('retrieved_rooms') ?? [])->toBeEmpty();
});

test('human handoff request offers a one-tap handoff action', function () {
    mockGemini(null, 'unused');

    $response = $this->postJson('/chat', [
        'message' => 'i want to talk to a human',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('suggested_actions.0.handoff'))->toBeTrue();
    expect($response->json('suggested_actions.0.label'))->toContain('human');
});

test('weak hotel keyword without entity reroutes to activities on confident vote', function () {
    mockGemini(unitVector(0));

    // "hotel" classifies HOTEL_SEARCH but resolves no hotel entity — the
    // widened fallback gives embeddings one vote, which lands on activities.
    $response = $this->postJson('/chat', [
        'message' => 'hotel for our trip in Boracay',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $activities = $response->json('retrieved_activities') ?? [];
    expect($activities)->not->toBeEmpty();
    expect(collect($activities)->pluck('activity_name')->implode(' '))->toContain('ATV Adventure Ride');
    expect($response->json('retrieved_hotels') ?? [])->toBeEmpty();
});

test('named hotel keeps hotel intent despite activity-affine embedding', function () {
    mockGemini(unitVector(0));

    // Strong hit: hotel name resolves to an entity, so semantic never runs.
    $response = $this->postJson('/chat', [
        'message' => 'Tell me about Test Beach Resort hotel in Boracay',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $hotels = $response->json('retrieved_hotels') ?? [];
    expect($hotels)->not->toBeEmpty();
    expect(collect($hotels)->pluck('hotel_name')->implode(' '))->toContain('Test Beach Resort');
    expect($response->json('retrieved_activities') ?? [])->toBeEmpty();
});

test('weak hotel keyword with embedding failure keeps hotel search', function () {
    mockGemini(null);

    // Exact-match shortcut needs no embeddings: the named hotel is returned
    // even when the embedding backend is down.
    $response = $this->postJson('/chat', [
        'message' => 'Tell me about Test Beach Resort hotel in Boracay',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    $hotels = $response->json('retrieved_hotels') ?? [];
    expect($hotels)->not->toBeEmpty();
    expect(collect($hotels)->pluck('hotel_name')->implode(' '))->toContain('Test Beach Resort');
    expect($response->json('retrieved_activities') ?? [])->toBeEmpty();
});
