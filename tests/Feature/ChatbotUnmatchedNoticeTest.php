<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;

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
