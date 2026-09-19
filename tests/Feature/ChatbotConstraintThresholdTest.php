<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use App\Services\Chat\ChatbotService;
use App\Services\Chat\IntentRouter;
use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
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

test('chat system prompt names only real database destinations', function () {
    config(['services.gemini.chat_context_cache' => false]);

    $systemInstruction = null;

    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => ['values' => array_fill(0, 3072, 0.01)],
        ]),
        '*generateContent*' => function (Request $request) use (&$systemInstruction) {
            $systemInstruction = $request->data()['systemInstruction']['parts'][0]['text'] ?? null;

            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Hello!']]],
                ]],
            ]);
        },
    ]);

    $this->postJson('/chat', ['message' => 'Hello there'])->assertOk();

    expect($systemInstruction)->not->toBeNull();

    // Every destination in the DB must be offered...
    foreach (DestinationModel::orderBy('name')->pluck('name')->all() as $name) {
        expect($systemInstruction)->toContain($name);
    }

    // ...and the prompt must forbid freelancing other Philippine spots.
    expect($systemInstruction)
        ->toContain('ONLY these destinations')
        ->not->toContain('e.g., Boracay, El Nido, Palawan');
});

test('typo destination resolves to the real DB name', function () {
    DestinationModel::factory()->create(['name' => 'El Nido']);
    $router = app(IntentRouter::class);

    expect($router->extractDestinationName('hotels in borakay'))->toBe('Boracay')
        ->and($router->extractDestinationName('elnedo tours'))->toBe('El Nido');
});

test('typo activity name resolves', function () {
    ActivityModel::factory()->create([
        'activity_name' => 'Parasailing',
        'destination_id' => $this->destination->id,
        'embedding' => chatUnitVectorString(),
    ]);
    $router = app(IntentRouter::class);

    expect($router->extractActivityName('parawsailing price'))->toBe('Parasailing');
});

test('k-shorthand budget and group pax parse', function () {
    $router = app(IntentRouter::class);

    $c = $router->extractConstraints('rooms under 5k in boracay for 5');
    expect($c['max_price'])->toBe(5000)
        ->and($c['pax'])->toBe(5)
        ->and($c['destination_name'])->toBe('Boracay');

    $c = $router->extractConstraints('hotel 5k budget, 5 kami');
    expect($c['max_price'])->toBe(5000)
        ->and($c['pax'])->toBe(5);

    // "for 2 nights" is a stay length, not a headcount.
    $c = $router->extractConstraints('room for 2 nights under 3000');
    expect($c['pax'])->toBeNull()
        ->and($c['nights'])->toBe(2)
        ->and($c['max_price'])->toBe(3000);
});

test('multi-digit k-shorthand budget does not backtrack to a stray digit', function () {
    $router = app(IntentRouter::class);

    // Regression: "under 20k" parsed as ₱2 (regex backtracked "20" -> "2").
    $c = $router->extractConstraints('hotels in boracay under 20k for 2 pax');
    expect($c['max_price'])->toBe(20000)
        ->and($c['pax'])->toBe(2);

    $c = $router->extractConstraints('rooms below 15k');
    expect($c['max_price'])->toBe(15000);

    // Plain numbers without k still take the non-k path.
    $c = $router->extractConstraints('room under 2000');
    expect($c['max_price'])->toBe(2000);
});

test('follow-up refinement inherits pax and budget from prior turn', function () {
    chatMakeHotel($this, 'Pool Resort', 4000, chatUnitVectorString());
    chatMakeHotel($this, 'Far Luxury', 9000, chatUnitVectorString());

    $first = $this->postJson('/chat', ['message' => 'Find hotels in Boracay under 5k for 4 pax']);
    $first->assertOk()->assertJsonPath('status', 'success');
    $token = $first->json('session_token');
    expect($first->json('retrieved_hotels'))->not->toBeEmpty();

    $session = ChatSession::where('session_token', $token)->firstOrFail();
    expect($session->metadata['constraint_state']['max_price'] ?? null)->toBe(5000)
        ->and($session->metadata['constraint_state']['pax'] ?? null)->toBe(4);

    $second = $this->postJson('/chat', ['message' => 'with pool', 'session_token' => $token]);
    $second->assertOk()->assertJsonPath('status', 'success');

    // Refinement may resolve to rooms or hotels — either way it must stay
    // in Boracay and inside the inherited ₱5k budget (+10% overflow).
    $all = array_merge($second->json('retrieved_hotels') ?? [], $second->json('retrieved_rooms') ?? []);
    expect($all)->not->toBeEmpty();
    $names = [];
    foreach ($all as $it) {
        $names[] = $it['hotel_name'] ?? $it['room_name'] ?? '';
        expect($it['destination'] ?? null)->toBe('Boracay');
        $price = $it['price_from'] ?? $it['base_price'] ?? null;
        if ($price !== null) {
            expect((float) $price)->toBeLessThanOrEqual(5500.0);
        }
    }
    expect(implode(' ', $names))->toContain('Pool Resort')->and(implode(' ', $names))->not->toContain('Far Luxury');
});

function chatMakeGroupHotel(object $ctx): HotelModel
{
    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Group Hotel',
        'destination_id' => $ctx->destination->id,
        'embedding' => chatUnitVectorString(),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Family Room',
        'base_price' => 8400,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 850,
        'embedding' => chatUnitVectorString(),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Dorm Bed',
        'base_price' => 850,
        'base_occupancy' => 1,
        'max_occupancy' => 1,
        'extra_person_fee' => 0,
        'embedding' => chatUnitVectorString(),
    ]);

    return $hotel;
}

test('group of 5 gets cheapest 2-room split within budget', function () {
    chatMakeGroupHotel($this);

    $scored = $this->gemini->searchRoomsHybrid('rooms for 5 pax', [
        'destination_id' => $this->destination->id,
        'destination_name' => 'Boracay',
        'max_price' => 20000,
        'pax' => 5,
    ], 5);

    // Family (4 pax @ 8400+2x850=10100) + Dorm (1 pax @ 850) = 10950.
    expect($scored)->toHaveCount(2);
    $groups = array_unique(array_map(fn ($e) => $e['combo_group'] ?? null, $scored));
    expect($groups)->toHaveCount(1)->and($groups[0])->toBe(1);
    foreach ($scored as $entry) {
        expect((float) ($entry['combo_total'] ?? 0))->toEqual(10950.0)
            ->and($entry['over_budget'] ?? false)->toBeFalse()
            ->and($entry['fallback'] ?? false)->toBeFalse();
    }
});

test('group split above budget is flagged fallback, not silent', function () {
    chatMakeGroupHotel($this);

    $scored = $this->gemini->searchRoomsHybrid('rooms for 5 pax', [
        'destination_id' => $this->destination->id,
        'destination_name' => 'Boracay',
        'max_price' => 5000,
        'pax' => 5,
    ], 5);

    expect($scored)->toHaveCount(2);
    foreach ($scored as $entry) {
        expect($entry['fallback'] ?? false)->toBeTrue()
            ->and((float) ($entry['combo_total'] ?? 0))->toEqual(10950.0);
    }
});

test('chat returns combo cards for 5 pax group', function () {
    chatMakeGroupHotel($this);

    $response = $this->postJson('/chat', ['message' => 'rooms in Boracay for 5 pax under 20000']);
    $response->assertOk()->assertJsonPath('status', 'success');

    $rooms = $response->json('retrieved_rooms') ?? [];
    expect($rooms)->not->toBeEmpty();
    foreach ($rooms as $card) {
        expect((int) ($card['combo_group'] ?? 0))->toBeGreaterThan(0)
            ->and((float) ($card['combo_total'] ?? 0))->toEqual(10950.0);
    }
});

test('no-results reply names pax as the blocker with biggest-room fact', function () {
    chatMakeGroupHotel($this);
    $elnido = DestinationModel::factory()->create(['name' => 'El Nido']);
    $smallHotel = HotelModel::factory()->create([
        'hotel_name' => 'Tiny Inn',
        'destination_id' => $elnido->id,
        'embedding' => chatUnitVectorString(),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $smallHotel->id,
        'room_name' => 'Couple Room',
        'base_price' => 2000,
        'base_occupancy' => 2,
        'max_occupancy' => 2,
        'extra_person_fee' => 0,
        'embedding' => chatUnitVectorString(),
    ]);

    $method = new ReflectionMethod(ChatbotService::class, 'noResultsReply');
    $method->setAccessible(true);
    $svc = app(ChatbotService::class);

    // Boracay: pax exceeds biggest room but a 2-room split exists.
    $boracay = $method->invoke($svc, 'rooms', [
        'destination_id' => $this->destination->id,
        'destination_name' => 'Boracay',
        'max_price' => 5000,
        'pax' => 5,
    ]);
    expect($boracay)->toContain('No single room in Boracay fits 5')
        ->and($boracay)->toContain('fits 4')
        ->and($boracay)->toContain('10,950.00')
        ->and($boracay)->not->toContain('Try raising your budget or lowering the group size');

    // El Nido: biggest fits 2, even two rooms cannot cover 5.
    $elnidoReply = $method->invoke($svc, 'rooms', [
        'destination_id' => $elnido->id,
        'destination_name' => 'El Nido',
        'pax' => 5,
    ]);
    expect($elnidoReply)->toContain('fits 2')
        ->and($elnidoReply)->toContain('even 2 rooms cannot cover 5');
});

test('null max_occupancy rooms stay visible to pax queries', function () {
    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Villa Resort',
        'destination_id' => $this->destination->id,
        'embedding' => chatUnitVectorString(),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Water Villa',
        'base_price' => 38000,
        'base_occupancy' => 2,
        'max_occupancy' => null,
        'embedding' => chatUnitVectorString(),
    ]);

    $scored = $this->gemini->searchRoomsHybrid('villa for 2', [
        'destination_id' => $this->destination->id,
        'pax' => 2,
    ], 5);

    $names = array_map(fn ($e) => $e['item']->room_name, $scored);
    expect($names)->toContain('Water Villa');
});

test('fresh topic resets stored constraints instead of leaking', function () {
    chatMakeHotel($this, 'Pool Resort', 4000, chatUnitVectorString());

    $first = $this->postJson('/chat', ['message' => 'Find hotels in Boracay under 5k for 4 pax']);
    $first->assertOk();
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', ['message' => 'What activities are available?', 'session_token' => $token]);
    $second->assertOk();

    $session = ChatSession::where('session_token', $token)->firstOrFail();
    expect($session->metadata['constraint_state']['max_price'] ?? null)->toBeNull()
        ->and($session->metadata['constraint_state']['pax'] ?? null)->toBeNull();
});
