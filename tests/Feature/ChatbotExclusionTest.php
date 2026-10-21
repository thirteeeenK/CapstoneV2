<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\Chat\IntentRouter;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.chatbot.planner_enabled' => false]);
    Http::fake([
        '*embedContent*' => Http::response(['embedding' => ['values' => unitVector(0)]]),
        '*generateContent*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Here are some options.']]]]]]),
    ]);

    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'Palm Beach Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_name' => 'Palm Deluxe Suite',
        'base_price' => 2500,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);
    $this->router = app(IntentRouter::class);
});

test('a negated catalog name is excluded, never turned into a request', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Party Boat Cruise',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);

    $message = 'activities in Boracay, without the Party Boat Cruise';

    expect($this->router->extractExclusions($message)['names'])->toContain('party boat cruise');
    expect($this->router->extractActivityName($message))->toBeNull();
});

test('multiword, multiple and taglish exclusions all parse', function (string $message, array $expected) {
    expect($this->router->extractExclusions($message)['terms'])->toEqualCanonicalizing($expected);
})->with([
    'multiword' => ['activities but no island hopping', ['island hopping']],
    'multiple' => ['activities, no diving and no hiking', ['diving', 'hiking']],
    'taglish ayoko' => ['ayoko ng diving', ['diving']],
    'taglish walang' => ['hotels na walang pool', ['pool']],
    'inline' => ['find me a hotel in Boracay without wifi', ['wifi']],
]);

test('a budget ceiling is never read as an excluded name', function () {
    $exclusions = $this->router->extractExclusions('hotels in Boracay, not more than 3000 per night');

    expect($exclusions['terms'])->toBeEmpty()
        ->and($exclusions['names'])->toBeEmpty()
        ->and($exclusions['unclear'])->toBeEmpty();
});

test('qualified absence wording keeps pool-free hotels without hiding positive pools', function (string $description, bool $included) {
    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Garden Lodge', 'hotel_description' => $description,
        'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0),
    ]);
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay, no pool']);
    $response->assertOk();
    expect(collect($response->json('retrieved_hotels'))->contains('id', $hotel->id))->toBe($included);
})->with([
    'reported wording' => ["There's no swimming pool at this property.", true],
    'several qualifiers' => ['There is no private outdoor swimming pool here.', true],
    'positive swimming pool' => ['An outdoor swimming pool is available.', false],
    'sentence boundary' => ['No breakfast. An outdoor swimming pool is available.', false],
    'contrast boundary' => ['No breakfast but an outdoor swimming pool is available.', false],
]);

test('an excluded amenity is not also treated as a requested positive feature', function () {
    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Pool Free Inn', 'hotel_description' => 'A simple inn with no pool.',
        'destination_id' => $this->destination->id, 'is_shown' => true, 'embedding' => unitVectorString(0),
    ]);
    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay without pool']);
    $response->assertOk();
    expect(collect($response->json('retrieved_hotels'))->firstWhere('id', $hotel->id)['match_status'] ?? null)->not->toBe('alternative');
    expect($response->json('reply'))->not->toContain('requested features (pool)');
});

test('an unresolvable exclusion is asked about instead of guessed', function () {
    $response = $this->postJson('/chat', ['message' => 'activities in Boracay, no bungee jumping']);

    $response->assertOk();
    expect($response->json('reply'))->toContain('bungee jumping');
});

test('exclusions do not match unrelated card metadata', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Sunset Viewpoint Walk',
        'description' => 'A quiet walk to the viewpoint.',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);

    $response = $this->postJson('/chat', ['message' => 'activities in Boracay, no boat']);

    $response->assertOk();
    expect($response->json('retrieved_activities.0.activity_name'))->toBe('Sunset Viewpoint Walk');
});

test('a card that states a feature is absent is not excluded by that feature', function () {
    HotelModel::factory()->create([
        'hotel_name' => 'Quiet Hillside Inn',
        'hotel_description' => 'A small inn with no pool and no bar, just quiet gardens.',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);

    $response = $this->postJson('/chat', ['message' => 'hotels in Boracay with no pool']);

    $response->assertOk();
    // "no pool" in the description is not an affirmative pool amenity, so the
    // inn survives an exclusion of pools.
    expect(collect($response->json('retrieved_hotels'))->pluck('hotel_name'))
        ->toContain('Quiet Hillside Inn');
});

test('exclusion filtering backfills the display cap from eligible options', function () {
    foreach (['Diving Lesson One', 'Diving Lesson Two', 'Diving Lesson Three'] as $name) {
        ActivityModel::factory()->create([
            'activity_name' => $name,
            'description' => 'Scuba diving session.',
            'destination_id' => $this->destination->id,
            'is_shown' => true,
            'embedding' => unitVectorString(0),
        ]);
    }
    foreach (['Beach Yoga', 'Island Picnic'] as $name) {
        ActivityModel::factory()->create([
            'activity_name' => $name,
            'description' => 'A relaxed shoreline session.',
            'destination_id' => $this->destination->id,
            'is_shown' => true,
            'embedding' => unitVectorString(0),
        ]);
    }

    $response = $this->postJson('/chat', ['message' => 'activities in Boracay, no diving']);

    $response->assertOk();
    $names = collect($response->json('retrieved_activities'))->pluck('activity_name');

    // The three diving options are filtered out and the eligible ones behind
    // them fill the results instead of the section coming back short.
    expect($names)->not->toContain('Diving Lesson One');
    expect($names->count())->toBe(2);
});

test('an add-on with no confirmed match reports alternatives, not a verified match', function () {
    AddOnModel::factory()->create([
        'name' => 'Airport Van Transfer',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);

    $response = $this->postJson('/chat', ['message' => 'do you have a helicopter transfer add-on in Boracay?']);

    $response->assertOk();

    $addons = $response->json('retrieved_addons') ?? [];
    if ($addons === []) {
        expect($response->json('retrieval_outcome.status'))->not->toBe('matched');

        return;
    }

    // Cards exist, but they are not the requested offering: the copy, the card
    // markers and the outcome must all say so.
    expect($response->json('retrieval_outcome.reason'))->not->toBe('verified_result_found');
    expect($response->json('reply'))->toContain('not a confirmed match');
    expect($addons[0]['match_status'])->toBe('alternative');
});
