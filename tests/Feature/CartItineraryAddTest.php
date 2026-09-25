<?php

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->destination = DestinationModel::factory()->create();
    $hotel = HotelModel::factory()->create([
        'destination_id' => $this->destination->id,
        'is_shown' => true,
    ]);
    $this->room = RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'is_shown' => true,
        'base_price' => 2000,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
    ]);
    $this->activity = ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'is_shown' => true,
        'rate' => 500,
    ]);
});

it('adds room and activities as one group for guests', function () {
    $response = $this->postJson(route('cart.add-itinerary'), [
        'room_id' => $this->room->id,
        'activity_ids' => [$this->activity->id],
        'pax' => 2,
        'check_in_date' => now()->addDays(7)->toDateString(),
        'check_out_date' => now()->addDays(9)->toDateString(),
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $items = DB::table('cart_items')->get();
    expect($items)->toHaveCount(2)
        ->and($items->pluck('lucky_group_id')->unique())->toHaveCount(1)
        ->and($items->first()->lucky_group_id)->not->toBeNull()
        ->and($items->first(function ($i) {
            return $i->item_type === 'room';
        })->check_in_date)->not->toBeNull();
});

it('rejects itinerary add without stay dates', function () {
    $response = $this->postJson(route('cart.add-itinerary'), [
        'room_id' => $this->room->id,
        'activity_ids' => [$this->activity->id],
        'pax' => 2,
        'check_in_date' => null,
        'check_out_date' => null,
    ]);

    $response->assertStatus(422);
    expect(DB::table('cart_items')->count())->toBe(0);
});

it('rejects nonexistent room or activity', function () {
    $this->postJson(route('cart.add-itinerary'), [
        'room_id' => 999999,
        'activity_ids' => [],
        'pax' => 2,
        'check_in_date' => now()->addDays(7)->toDateString(),
        'check_out_date' => now()->addDays(9)->toDateString(),
    ])->assertStatus(422);
});
