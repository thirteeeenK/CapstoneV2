<?php

use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Support\Facades\Http;

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
                            ['text' => 'Here are my picks for you!'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);
});

test('personalized room search still matches null max_occupancy rooms via COALESCE and keeps the floor', function () {
    $user = onboardedUser();
    $this->actingAs($user);

    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Personalization Test Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(1500),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Target Family Suite',
        'base_price' => 3000,
        'base_occupancy' => 4,
        'max_occupancy' => null,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Decoy Solo Pod',
        'base_price' => 1500,
        'base_occupancy' => 2,
        'max_occupancy' => 10,
        'is_shown' => true,
        'embedding' => unitVectorString(1500),
    ]);

    $response = $this->postJson('/chat', ['message' => 'find rooms for 3 pax']);

    $response->assertOk()->assertJsonPath('status', 'success');
    $names = collect($response->json('retrieved_rooms') ?? [])->pluck('room_name');
    expect($names)->toContain('Target Family Suite')
        ->and($names)->not->toContain('Decoy Solo Pod');
});

test('personalized hotel search reranks the floored canonical pool instead of bypassing the floor', function () {
    $user = onboardedUser();
    $this->actingAs($user);

    HotelModel::factory()->create([
        'hotel_name' => 'Target Palm Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);
    HotelModel::factory()->create([
        'hotel_name' => 'Decoy Mountain Lodge',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(1500),
    ]);

    $response = $this->postJson('/chat', ['message' => 'find hotels']);

    $response->assertOk()->assertJsonPath('status', 'success');
    $names = collect($response->json('retrieved_hotels') ?? [])->pluck('hotel_name');
    expect($names)->toContain('Target Palm Resort')
        ->and($names)->not->toContain('Decoy Mountain Lodge');
});
