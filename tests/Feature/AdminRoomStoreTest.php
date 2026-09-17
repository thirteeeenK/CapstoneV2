<?php

use App\Models\AdminModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\GeminiService;

beforeEach(function () {
    $this->partialMock(GeminiService::class, function ($mock) {
        $mock->shouldReceive('generateEmbedding')->andReturnNull();
    });

    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@sunnytripstest.com',
        'password' => 'password',
    ]);

    $this->destination = DestinationModel::firstOrCreate(
        ['name' => 'Boracay'],
        ['description' => 'Island destination', 'is_shown' => true]
    );

    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'Occupancy Test Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
    ]);
});

function validRoomPayload(array $overrides = []): array
{
    return array_merge([
        'room_name' => 'Deluxe Room',
        'total_rooms' => 5,
        'base_occupancy' => 2,
        'max_occupancy' => 3,
        'bed_configuration' => '1 King Bed',
        'base_price' => 6000.00,
    ], $overrides);
}

it('stores a room when occupancy is omitted by mirroring max capacity', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('store-room', $this->hotel->id), validRoomPayload())
        ->assertRedirect(route('manage-rooms', $this->hotel->id));

    $room = RoomType::where('hotel_id', $this->hotel->id)->first();

    expect($room)->not->toBeNull()
        ->and($room->max_occupancy)->toBe(3)
        ->and($room->occupancy)->toBe(3);
});

it('persists an explicit occupancy value when provided', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('store-room', $this->hotel->id), validRoomPayload([
            'occupancy' => 2,
            'max_occupancy' => 4,
        ]))
        ->assertRedirect(route('manage-rooms', $this->hotel->id));

    $room = RoomType::where('hotel_id', $this->hotel->id)->first();

    expect($room->occupancy)->toBe(2)
        ->and($room->max_occupancy)->toBe(4);
});

it('rejects a room without max capacity', function () {
    $payload = validRoomPayload();
    unset($payload['max_occupancy']);

    $this->actingAs($this->admin, 'admin')
        ->post(route('store-room', $this->hotel->id), $payload)
        ->assertSessionHasErrors('max_occupancy');
});

it('renders a single max capacity input synced to the hidden occupancy field', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('create-room', $this->hotel->id))
        ->assertOk();

    $html = $response->getContent();

    expect(substr_count($html, 'name="max_occupancy"'))->toBe(1)
        ->and($html)->toContain('id="occupancyHidden"')
        ->and($html)->not->toContain('name="occupancy" name=')
        ->and($html)->not->toContain('name="max_occupancy" name=');
});
