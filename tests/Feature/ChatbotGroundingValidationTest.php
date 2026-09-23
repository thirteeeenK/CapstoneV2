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
    ]);

    $destination = DestinationModel::factory()->create(['name' => 'Boracay']);
    $hotel = HotelModel::factory()->create([
        'hotel_name' => 'Grand Palm Resort',
        'destination_id' => $destination->id,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Deluxe Suite',
        'base_price' => 5000,
        'base_occupancy' => 2,
        'max_occupancy' => 2,
        'is_shown' => true,
        'embedding' => unitVectorString(0),
    ]);
});

function fakeChatText(string $text): void
{
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
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ]),
    ]);
}

test('a reply with a price outside the context falls back to deterministic cards', function () {
    fakeChatText('I recommend the Grand Palm Resort at only ₱99,999 per night!');

    $response = $this->postJson('/chat', ['message' => 'show me hotels']);

    $response->assertOk()->assertJsonPath('status', 'success');
    $text = $response->json('reply') ?? '';
    expect($text)->not->toContain('99,999')
        ->and($text)->toContain('Grand Palm Resort')
        ->and($response->json('retrieved_hotels') ?? [])->toHaveCount(1);
});

test('a reply with only context prices passes through untouched', function () {
    fakeChatText('The Grand Palm Resort starts at ₱5,000.00 per night.');

    $response = $this->postJson('/chat', ['message' => 'show me hotels']);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('reply') ?? '')->toContain('starts at ₱5,000.00');
});
