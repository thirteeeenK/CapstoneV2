<?php

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function blockRoomFully(RoomType $room, string $in, string $out): void
{
    $booking = Booking::factory()->create(['status' => 'pending']);
    BookingItem::factory()->create([
        'booking_id' => $booking->id,
        'item_type' => 'room',
        'item_id' => $room->id,
        'check_in_date' => $in,
        'check_out_date' => $out,
        'quantity' => 5,
    ]);
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
                            ['text' => 'Here is your itinerary!'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->destination = DestinationModel::factory()->create(['name' => 'Boracay']);

    ActivityModel::factory()->create([
        'activity_name' => 'Cheap Paddle Tour',
        'destination_id' => $this->destination->id,
        'rate' => 500,
        'is_shown' => true,
    ]);
});

function itineraryHotel(int $destinationId, string $name, int $nightly): HotelModel
{
    $hotel = HotelModel::factory()->create([
        'hotel_name' => $name,
        'destination_id' => $destinationId,
        'is_shown' => true,
    ]);
    RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'room_name' => $name.' Room',
        'base_price' => $nightly,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'is_shown' => true,
    ]);

    return $hotel;
}

test('available room wins over a blocked cheaper room', function () {
    $blocked = itineraryHotel($this->destination->id, 'Blocked Budget Inn', 2000);
    itineraryHotel($this->destination->id, 'Open Midway Hotel', 2000);
    // Default window is [today+7, today+9]: span it fully.
    blockRoomFully(
        $blocked->rooms()->first(),
        now()->addDays(5)->toDateString(),
        now()->addDays(12)->toDateString()
    );

    $response = $this->postJson('/chat', [
        'message' => 'Plan a 2-day itinerary in Boracay for 2 pax under 10000',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('itinerary.hotel.name'))->toBe('Open Midway Hotel');
});

test('cheaper total wins when scores tie', function () {
    itineraryHotel($this->destination->id, 'First Pricey Inn', 2500);
    itineraryHotel($this->destination->id, 'Second Budget Inn', 2000);

    $response = $this->postJson('/chat', [
        'message' => 'Plan a 2-day itinerary in Boracay for 2 pax under 10000',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('itinerary.hotel.name'))->toBe('Second Budget Inn');
});

test('over-budget picks cheapest with a deterministic notice', function () {
    itineraryHotel($this->destination->id, 'Cheapest Inn', 2000);
    itineraryHotel($this->destination->id, 'Pricier Inn', 4000);

    $response = $this->postJson('/chat', [
        'message' => 'Plan a 2-day itinerary in Boracay for 2 pax under 1000',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('itinerary.hotel.name'))->toBe('Cheapest Inn')
        ->and($response->json('itinerary.within_budget'))->toBeFalse()
        ->and($response->json('reply'))->toContain('over budget');
});

test('explicit check-in dates reach availability selection', function () {
    $cheap = itineraryHotel($this->destination->id, 'Cheap Blocked Inn', 2000);
    itineraryHotel($this->destination->id, 'Open Expensive Inn', 4000);
    // Block the December window only.
    blockRoomFully(
        $cheap->rooms()->first(),
        now()->copy()->month(12)->day(9)->toDateString(),
        now()->copy()->month(12)->day(13)->toDateString()
    );

    $response = $this->postJson('/chat', [
        'message' => 'Plan a 2-day itinerary in Boracay for 2 pax under 10000 from dec 10 to dec 12',
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');
    expect($response->json('itinerary.hotel.name'))->toBe('Open Expensive Inn');
});
