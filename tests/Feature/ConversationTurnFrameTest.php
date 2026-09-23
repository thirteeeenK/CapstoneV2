<?php

use App\Models\ChatSession;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Support\Facades\Http;

function gradedVectorString(float $x, int $dims = 3072): string
{
    $v = array_fill(0, $dims, 0.0);
    $v[0] = $x;
    $v[1] = sqrt(max(0.0, 1.0 - $x * $x));

    return '['.implode(',', $v).']';
}

beforeEach(function () {
    Http::fake([
        '*embedContent*' => Http::response([
            'embedding' => [
                'values' => unitVector(0),
            ],
        ]),
        '*generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Here are some great hotels!'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);

    // Graded similarities vs the unit-0 query vector: A=1.0, B=0.8, C=0.65
    // (all above the 0.60 hotel floor, strictly ordered).
    $this->hotelA = HotelModel::factory()->create([
        'hotel_name' => 'Alpha Palm Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => gradedVectorString(1.0),
    ]);
    $this->hotelB = HotelModel::factory()->create([
        'hotel_name' => 'Beta Palm Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => gradedVectorString(0.8),
    ]);
    $this->hotelC = HotelModel::factory()->create([
        'hotel_name' => 'Gamma Palm Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => gradedVectorString(0.65),
    ]);

    $roomHotel = HotelModel::factory()->create([
        'hotel_name' => 'Palm Suites Hotel',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        // Below the 0.60 hotel floor so it never leaks into 'find hotels'.
        'embedding' => gradedVectorString(0.5),
    ]);
    $this->roomA = RoomType::factory()->create([
        'hotel_id' => $roomHotel->id,
        'room_name' => 'Alpha Suite',
        'base_price' => 3000,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'is_shown' => true,
        'embedding' => gradedVectorString(1.0),
    ]);
    $this->roomB = RoomType::factory()->create([
        'hotel_id' => $roomHotel->id,
        'room_name' => 'Beta Suite',
        'base_price' => 3500,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'is_shown' => true,
        'embedding' => gradedVectorString(0.8),
    ]);
    $this->roomC = RoomType::factory()->create([
        'hotel_id' => $roomHotel->id,
        'room_name' => 'Gamma Suite',
        'base_price' => 4000,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'is_shown' => true,
        'embedding' => gradedVectorString(0.65),
    ]);
});

test('turn frame keeps ordered result ids in displayed order', function () {
    $response = $this->postJson('/chat', ['message' => 'find hotels']);

    $response->assertOk()->assertJsonPath('status', 'success');
    $names = collect($response->json('retrieved_hotels') ?? [])->pluck('hotel_name')->all();
    expect($names)->toBe(['Alpha Palm Resort', 'Beta Palm Resort', 'Gamma Palm Resort']);

    $token = $response->json('session_token');
    $frame = ChatSession::where('session_token', $token)->first()->metadata['retrieval_state'] ?? [];
    expect($frame['type'] ?? null)->toBe('hotel')
        ->and($frame['result_ids'] ?? null)->toBe([$this->hotelA->id, $this->hotelB->id, $this->hotelC->id]);
});

test('ordinal follow-up selects the second room on the main search path', function () {
    $first = $this->postJson('/chat', ['message' => 'find rooms']);
    $first->assertOk()->assertJsonPath('status', 'success');
    $names = collect($first->json('retrieved_rooms') ?? [])->pluck('room_name')->all();
    expect($names)->toBe(['Alpha Suite', 'Beta Suite', 'Gamma Suite']);
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'message' => 'does the second one have a pool?',
        'session_token' => $token,
    ]);

    $second->assertOk()->assertJsonPath('status', 'success');
    $rooms = $second->json('retrieved_rooms') ?? [];
    expect($rooms)->toHaveCount(1)
        ->and((int) ($rooms[0]['id'] ?? 0))->toBe($this->roomB->id);
});

test('grounded generation carries the structured dialogue-state block', function () {
    $first = $this->postJson('/chat', ['message' => 'find hotels in Boracay']);
    $first->assertOk()->assertJsonPath('status', 'success');
    expect($first->json('retrieved_hotels') ?? [])->toHaveCount(3);
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'message' => 'tell me more about the first hotel',
        'session_token' => $token,
    ]);
    $second->assertOk()->assertJsonPath('status', 'success');

    $bodies = collect(Http::recorded())
        ->filter(fn ($record) => str_contains($record[0]->url(), 'generateContent'))
        ->map(fn ($record) => $record[0]->body())
        ->values();
    expect($bodies)->not->toBeEmpty();

    // Turn 1 generated with an empty frame: no state block sent.
    expect($bodies->first())->not->toContain('DIALOGUE STATE');

    // Turn 2 inherits the validated frame: destination + result ids present.
    $last = $bodies->last();
    expect($last)->toContain('DIALOGUE STATE')
        ->and($last)->toContain('Boracay');
});

test('ordinal follow-up narrows carried hotel cards to the second result', function () {
    $first = $this->postJson('/chat', ['message' => 'find hotels']);
    $first->assertOk();
    $token = $first->json('session_token');

    $second = $this->postJson('/chat', [
        'message' => 'tell me more about the second hotel',
        'session_token' => $token,
    ]);

    $second->assertOk()->assertJsonPath('status', 'success');
    $hotels = $second->json('retrieved_hotels') ?? [];
    expect($hotels)->toHaveCount(1)
        ->and((int) ($hotels[0]['id'] ?? 0))->toBe($this->hotelB->id);
});
