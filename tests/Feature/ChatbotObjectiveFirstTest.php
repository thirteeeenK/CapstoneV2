<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\Chat\IntentRouter;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => [
                'values' => array_fill(0, 3072, 0.01),
            ],
        ]),
        '*generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Here is a recommendation for you!'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->elNido = DestinationModel::factory()->create(['name' => 'El Nido']);
    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'El Nido Beach Resort',
        'destination_id' => $this->elNido->id,
    ]);

    $this->router = new IntentRouter;
});

function uniformEmbedding(float $v = 0.01): string
{
    return '['.implode(',', array_fill(0, 3072, (string) $v)).']';
}

function orthogonalEmbedding(): string
{
    // Zero cosine similarity against any uniform-positive vector, so the
    // cheapest item ranks strictly last on semantic relevance.
    return '['.implode(',', array_merge(array_fill(0, 1536, '0.05'), array_fill(0, 1536, '-0.05'))).']';
}

test('classifies scuba trainer inclusions query as activity search', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Discover Scuba Diving (DSD)',
        'destination_id' => $this->elNido->id,
    ]);

    expect($this->router->classify('scuba trainer are included?'))->toBe(IntentRouter::ACTIVITY_SEARCH);
});

test('extracts activity name from alias variants', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Discover Scuba Diving (DSD)',
        'destination_id' => $this->elNido->id,
    ]);

    expect($this->router->extractActivityName('scuba trainer are included?'))->toBe('Discover Scuba Diving (DSD)');
    expect($this->router->extractActivityName('is scuba training included?'))->toBe('Discover Scuba Diving (DSD)');
    expect($this->router->extractActivityName('what are the DSD inclusions?'))->toBe('Discover Scuba Diving (DSD)');
});

test('detects field inquiry intent', function () {
    expect($this->router->detectFieldIntent('scuba trainer are included?'))->toBe('inclusions');
    expect($this->router->detectFieldIntent('what are the exclusions?'))->toBe('exclusions');
    expect($this->router->detectFieldIntent('how much is the cheapest room?'))->toBe('price');
    expect($this->router->detectFieldIntent('how long does the tour last?'))->toBe('duration');
    expect($this->router->detectFieldIntent('show me beachfront rooms in El Nido'))->toBeNull();
});

test('cheapest rooms query surfaces true cheapest for logged-in user', function () {
    $prices = [7500, 6000, 5000, 4200, 3500, 2800, 1500];
    foreach ($prices as $i => $price) {
        RoomType::factory()->create([
            'hotel_id' => $this->hotel->id,
            'room_name' => 'Room Tier '.$i,
            'base_price' => $price,
            'embedding' => $price === 1500 ? orthogonalEmbedding() : uniformEmbedding(),
        ]);
    }

    $user = onboardedUser();

    $response = $this->actingAs($user)->postJson('/chat', [
        'message' => 'cheapest place in El Nido',
    ]);

    $response->assertOk();
    $rooms = $response->json('retrieved_rooms');
    expect($rooms)->not->toBeEmpty();
    expect((float) $rooms[0]['base_price'])->toBe(1500.0);
});

test('cheapest activities query surfaces true cheapest for logged-in user', function () {
    $rates = [2500, 1800, 1200, 900, 600, 350];
    foreach ($rates as $i => $rate) {
        ActivityModel::factory()->create([
            'activity_name' => 'El Nido Fun Activity '.$i,
            'destination_id' => $this->elNido->id,
            'rate' => '₱'.$rate.'/person',
            'embedding' => $rate === 350 ? orthogonalEmbedding() : uniformEmbedding(),
        ]);
    }

    $user = onboardedUser();

    $response = $this->actingAs($user)->postJson('/chat', [
        'message' => 'cheapest activity in El Nido',
    ]);

    $response->assertOk();
    $activities = $response->json('retrieved_activities');
    expect($activities)->not->toBeEmpty();
    expect($activities[0]['activity_name'])->toBe('El Nido Fun Activity 5');
});

test('scuba trainer inclusions query resolves the exact activity end to end', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Discover Scuba Diving (DSD)',
        'destination_id' => $this->elNido->id,
        'rate' => '₱2500/person',
        'inclusions' => ['Scuba trainer', 'Gear rental', 'Boat transfer'],
    ]);

    $response = $this->postJson('/chat', [
        'message' => 'scuba trainer are included?',
    ]);

    $response->assertOk();
    $activities = $response->json('retrieved_activities');
    expect($activities)->not->toBeEmpty();
    expect($activities[0]['activity_name'])->toBe('Discover Scuba Diving (DSD)');
});

test('price-ordered contexts label rank one as lowest price', function () {
    $gemini = app(GeminiService::class);
    $activity = ActivityModel::factory()->create([
        'activity_name' => 'Cheap Reef Tour',
        'destination_id' => $this->elNido->id,
        'rate' => '₱350/person',
    ]);

    $context = $gemini->getActivityContext([['item' => $activity, 'score' => 1.0]], 'price-asc');

    expect($context)->toContain('LOWEST PRICE')
        ->and($context)->not->toContain('BEST MATCH');
});
