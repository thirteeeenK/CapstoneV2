<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\GeminiService;

function unitVectorString(int $hotIndex, int $dims = 3072): string
{
    $v = array_fill(0, $dims, 0.0);
    $v[$hotIndex] = 1.0;

    return '['.implode(',', $v).']';
}

function unitVector(int $hotIndex, int $dims = 3072): array
{
    $v = array_fill(0, $dims, 0.0);
    $v[$hotIndex] = 1.0;

    return $v;
}

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
        'embedding' => unitVectorString(0),
    ]);
});

function mockGemini(?array $queryVector, string $chatReply = 'Here are the ATV activities I found in Boracay!'): void
{
    $mock = Mockery::mock(GeminiService::class)->makePartial();
    $mock->shouldReceive('generateEmbedding')->andReturn($queryVector);
    $mock->shouldReceive('generateChatResponse')->andReturn($chatReply);
    app()->instance(GeminiService::class, $mock);
}

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
