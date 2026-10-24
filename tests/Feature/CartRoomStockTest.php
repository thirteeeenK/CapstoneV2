<?php

use App\Models\CartItem;
use App\Models\RoomType;

beforeEach(function () {
    $this->actingAs(onboardedUser());
    $this->room = RoomType::factory()->create(['is_shown' => true, 'total_rooms' => 2, 'max_occupancy' => 4]);
    $this->stay = ['check_in_date' => now()->addDays(7)->toDateString(), 'check_out_date' => now()->addDays(9)->toDateString()];
});

function addRoomToBasket(object $test, RoomType $room, array $stay, int $quantity = 1, int $pax = 2)
{
    return $test->postJson(route('cart.add'), ['item_type' => 'room', 'item_id' => $room->id, 'quantity' => $quantity, 'selected_pax' => $pax] + $stay);
}

it('rejects a repeat addition that would take more rooms than are left', function () {
    $this->room->update(['total_rooms' => 1]);

    addRoomToBasket($this, $this->room, $this->stay)->assertOk();
    addRoomToBasket($this, $this->room, $this->stay)->assertStatus(422)->assertJsonPath('message', fn ($message) => str_contains($message, 'already holds 1'));

    expect(CartItem::sole()->quantity)->toBe(1);
});

it('counts other basket lines for the same room on overlapping dates', function () {
    addRoomToBasket($this, $this->room, $this->stay, pax: 2)->assertOk();
    $overlapping = ['check_in_date' => now()->addDays(8)->toDateString(), 'check_out_date' => now()->addDays(10)->toDateString()];
    addRoomToBasket($this, $this->room, $overlapping, pax: 3)->assertOk();

    addRoomToBasket($this, $this->room, $this->stay, pax: 4)->assertStatus(422);

    expect(CartItem::sum('quantity'))->toBe(2);
});

it('assumes five rooms when a room type has no stock set', function () {
    $this->room->update(['total_rooms' => 0]);

    addRoomToBasket($this, $this->room, $this->stay, quantity: 5)->assertOk();
    addRoomToBasket($this, $this->room, $this->stay)->assertStatus(422);
});

it('caps a basket quantity change at the rooms left after other lines', function () {
    addRoomToBasket($this, $this->room, $this->stay, pax: 2)->assertOk();
    addRoomToBasket($this, $this->room, $this->stay, pax: 3)->assertOk();
    $line = CartItem::where('selected_pax', 2)->sole();

    $this->patchJson(route('cart.update', $line->id), ['quantity' => 2])->assertOk();

    expect($line->fresh()->quantity)->toBe(1);
});
