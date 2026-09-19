<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;

function chatUnitVectorString(): string
{
    $v = 1.0 / sqrt(3072);

    return '['.implode(',', array_fill(0, 3072, (string) $v)).']';
}

function chatOrthVectorString(): string
{
    $v = 1.0 / sqrt(3072);
    $vals = [];
    for ($i = 0; $i < 3072; $i++) {
        $vals[] = (string) (($i % 2 === 0 ? $v : -$v));
    }

    return '['.implode(',', $vals).']';
}

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

    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $this->gemini = app(GeminiService::class);
});

function chatMakeHotel(object $ctx, string $name, float $roomPrice, string $embedding): HotelModel
{
    $hotel = HotelModel::factory()->create([
        'hotel_name' => $name,
        'destination_id' => $ctx->destination->id,
        'embedding' => $embedding,
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => $name.' Room',
        'base_price' => $roomPrice,
        'max_occupancy' => 4,
        'embedding' => $embedding,
    ]);

    return $hotel;
}

test('hotel search keeps in-budget, flags slight overflow, drops far over-budget and irrelevant', function () {
    chatMakeHotel($this, 'Budget Stay', 2500, chatUnitVectorString());
    chatMakeHotel($this, 'Slightly Over Stay', 3200, chatUnitVectorString());
    chatMakeHotel($this, 'Luxury Stay', 9000, chatUnitVectorString());
    chatMakeHotel($this, 'Irrelevant Stay', 2000, chatOrthVectorString());

    $scored = $this->gemini->searchHotels('beach resort', 5, null, $this->destination->id, ['max_price' => 3000]);

    $names = array_map(fn ($e) => $e['item']->hotel_name, $scored);
    expect($names)->toContain('Budget Stay')
        ->and($names)->toContain('Slightly Over Stay')
        ->and($names)->not->toContain('Luxury Stay')
        ->and($names)->not->toContain('Irrelevant Stay');

    $over = array_values(array_filter($scored, fn ($e) => $e['item']->hotel_name === 'Slightly Over Stay'))[0];
    expect($over['over_budget'] ?? false)->toBeTrue()
        ->and($over['over_by'] ?? 0)->toEqual(200.0);

    $inBudget = array_values(array_filter($scored, fn ($e) => $e['item']->hotel_name === 'Budget Stay'))[0];
    expect($inBudget['over_budget'] ?? false)->toBeFalse();
});

test('hotel search falls back to cheapest when everything is way over budget', function () {
    chatMakeHotel($this, 'Luxury Only', 9000, chatUnitVectorString());

    $scored = $this->gemini->searchHotels('beach resort', 5, null, $this->destination->id, ['max_price' => 3000]);

    expect($scored)->toHaveCount(1)
        ->and($scored[0]['fallback'] ?? false)->toBeTrue()
        ->and($scored[0]['item']->hotel_name)->toBe('Luxury Only');
});

test('activity search enforces budget overflow and relevance floor', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Cheap Paddle',
        'destination_id' => $this->destination->id,
        'rate' => '₱300/person',
        'capacity' => 'Up to 10 guests',
        'embedding' => chatUnitVectorString(),
    ]);
    ActivityModel::factory()->create([
        'activity_name' => 'Pricey Paddle',
        'destination_id' => $this->destination->id,
        'rate' => '₱580/person',
        'capacity' => 'Up to 10 guests',
        'embedding' => chatUnitVectorString(),
    ]);
    ActivityModel::factory()->create([
        'activity_name' => 'Unrelated Trek',
        'destination_id' => $this->destination->id,
        'rate' => '₱300/person',
        'capacity' => 'Up to 10 guests',
        'embedding' => chatOrthVectorString(),
    ]);

    $scored = $this->gemini->searchActivities('paddle', 5, $this->destination->id, ['max_price' => 550, 'pax' => 2]);

    $names = array_map(fn ($e) => $e['item']->activity_name, $scored);
    expect($names)->toContain('Cheap Paddle')
        ->and($names)->toContain('Pricey Paddle')
        ->and($names)->not->toContain('Unrelated Trek');

    $over = array_values(array_filter($scored, fn ($e) => $e['item']->activity_name === 'Pricey Paddle'))[0];
    expect($over['over_budget'] ?? false)->toBeTrue()
        ->and($over['over_by'] ?? 0)->toEqual(30.0);
});

test('activity search filters out over-capacity options', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Tiny Boat',
        'destination_id' => $this->destination->id,
        'rate' => '₱300/person',
        'capacity' => 'Up to 4 guests',
        'embedding' => chatUnitVectorString(),
    ]);

    $scored = $this->gemini->searchActivities('boat', 5, $this->destination->id, ['pax' => 10]);

    expect($scored)->toBeEmpty();
});

test('package search flags slight overflow and drops irrelevant', function () {
    Package::factory()->create([
        'name' => 'Boracay Deal',
        'destination_id' => $this->destination->id,
        'price' => 5000,
        'min_pax' => 2,
        'embedding' => chatUnitVectorString(),
    ]);
    Package::factory()->create([
        'name' => 'Boracay Plus',
        'destination_id' => $this->destination->id,
        'price' => 6000,
        'min_pax' => 2,
        'embedding' => chatUnitVectorString(),
    ]);
    Package::factory()->create([
        'name' => 'Unrelated Deal',
        'destination_id' => $this->destination->id,
        'price' => 4000,
        'min_pax' => 2,
        'embedding' => chatOrthVectorString(),
    ]);

    $scored = $this->gemini->searchPackages('boracay deal', 5, $this->destination->id, ['max_price' => 5500]);

    $names = array_map(fn ($e) => $e['item']->name, $scored);
    expect($names)->toContain('Boracay Deal')
        ->and($names)->toContain('Boracay Plus')
        ->and($names)->not->toContain('Unrelated Deal');

    $over = array_values(array_filter($scored, fn ($e) => $e['item']->name === 'Boracay Plus'))[0];
    expect($over['over_budget'] ?? false)->toBeTrue()
        ->and($over['over_by'] ?? 0)->toEqual(500.0);
});

test('addon search flags slight overflow and drops irrelevant', function () {
    AddOnModel::factory()->create([
        'name' => 'Cheap Transfer',
        'pricing_tiers' => [['min_pax' => 1, 'max_pax' => 10, 'rate' => 400]],
        'embedding' => chatUnitVectorString(),
    ]);
    AddOnModel::factory()->create([
        'name' => 'Pricey Transfer',
        'pricing_tiers' => [['min_pax' => 1, 'max_pax' => 10, 'rate' => 580]],
        'embedding' => chatUnitVectorString(),
    ]);
    AddOnModel::factory()->create([
        'name' => 'Unrelated Sim',
        'pricing_tiers' => [['min_pax' => 1, 'max_pax' => 10, 'rate' => 300]],
        'embedding' => chatOrthVectorString(),
    ]);

    $scored = $this->gemini->searchAddOns('transfer', 5, null, ['max_price' => 550, 'pax' => 2]);

    $names = array_map(fn ($e) => $e['item']->name, $scored);
    expect($names)->toContain('Cheap Transfer')
        ->and($names)->toContain('Pricey Transfer')
        ->and($names)->not->toContain('Unrelated Sim');

    $over = array_values(array_filter($scored, fn ($e) => $e['item']->name === 'Pricey Transfer'))[0];
    expect($over['over_budget'] ?? false)->toBeTrue();
});

test('room search enforces budget overflow and relevance floor', function () {
    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Room Test Hotel',
        'destination_id' => $this->destination->id,
        'embedding' => chatUnitVectorString(),
    ]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'room_name' => 'Standard Room', 'base_price' => 2500, 'max_occupancy' => 4, 'embedding' => chatUnitVectorString()]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'room_name' => 'Deluxe Room', 'base_price' => 3200, 'max_occupancy' => 4, 'embedding' => chatUnitVectorString()]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'room_name' => 'Suite Room', 'base_price' => 9000, 'max_occupancy' => 4, 'embedding' => chatUnitVectorString()]);
    RoomType::factory()->create(['hotel_id' => $hotel->id, 'room_name' => 'Unrelated Room', 'base_price' => 2000, 'max_occupancy' => 4, 'embedding' => chatOrthVectorString()]);

    $scored = $this->gemini->searchRoomsHybrid('ocean view room', ['destination_id' => $this->destination->id, 'max_price' => 3000], 5);

    $names = array_map(fn ($e) => $e['item']->room_name, $scored);
    expect($names)->toContain('Standard Room')
        ->and($names)->toContain('Deluxe Room')
        ->and($names)->not->toContain('Suite Room')
        ->and($names)->not->toContain('Unrelated Room');

    $over = array_values(array_filter($scored, fn ($e) => $e['item']->room_name === 'Deluxe Room'))[0];
    expect($over['over_budget'] ?? false)->toBeTrue();
});

test('guest hotel chat with impossible budget shows closest-options notice', function () {
    chatMakeHotel($this, 'Luxury Only', 9000, chatUnitVectorString());

    $response = $this->postJson('/chat', [
        'message' => 'hotels in Boracay under 3000',
    ]);

    $response->assertOk();
    expect($response->json('reply'))->toContain('closest options');
});

test('guest activity chat over capacity names the pax conflict', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Tiny Boat',
        'destination_id' => $this->destination->id,
        'rate' => '₱300/person',
        'capacity' => 'Up to 4 guests',
        'embedding' => chatUnitVectorString(),
    ]);

    $response = $this->postJson('/chat', [
        'message' => 'activities in Boracay for 10 people',
    ]);

    $response->assertOk();
    expect($response->json('reply'))->toContain('10 pax');
});
