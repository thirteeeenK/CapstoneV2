<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\CartItem;
use App\Models\Package;
use App\Models\RoomType;

beforeEach(function () {
    $this->user = onboardedUser();
    $this->actingAs($this->user);
    $this->activity = ActivityModel::factory()->create(['is_shown' => true]);
});

function basketLine(int $userId, string $type, int $itemId, int $quantity, int $pax): CartItem
{
    return CartItem::create(['user_id' => $userId, 'item_type' => $type, 'item_id' => $itemId, 'quantity' => $quantity, 'selected_pax' => $pax, 'is_selected' => true]);
}

it('leaves basket rows untouched when the checkout page is viewed', function () {
    $line = basketLine($this->user->id, 'activity', $this->activity->id, 1, 3);

    $this->get(route('checkout.index'))->assertOk();

    expect($line->fresh()->only(['quantity', 'selected_pax']))->toBe(['quantity' => 1, 'selected_pax' => 3]);
});

it('repairs legacy per-traveler basket rows once without touching rooms', function () {
    $activity = basketLine($this->user->id, 'activity', $this->activity->id, 1, 3);
    $addon = basketLine($this->user->id, 'addon', AddOnModel::factory()->create()->id, 4, 2);
    $package = basketLine($this->user->id, 'package', Package::factory()->create()->id, 0, 0);
    $room = basketLine($this->user->id, 'room', RoomType::factory()->create()->id, 1, 3);

    (require database_path('migrations/2026_10_22_123425_normalize_legacy_cart_item_traveler_counts.php'))->up();

    expect($activity->fresh()->only(['quantity', 'selected_pax']))->toBe(['quantity' => 3, 'selected_pax' => 3])
        ->and($addon->fresh()->only(['quantity', 'selected_pax']))->toBe(['quantity' => 4, 'selected_pax' => 4])
        ->and($package->fresh()->only(['quantity', 'selected_pax']))->toBe(['quantity' => 1, 'selected_pax' => 1])
        ->and($room->fresh()->only(['quantity', 'selected_pax']))->toBe(['quantity' => 1, 'selected_pax' => 3]);
});

it('limits how quickly basket lines can be added', function () {
    foreach (range(1, 30) as $attempt) {
        $this->postJson(route('cart.add'), [])->assertUnprocessable();
    }

    $this->postJson(route('cart.add'), [])->assertTooManyRequests();
});

it('rejects basket notes longer than 1,000 characters', function () {
    $this->postJson(route('cart.add'), ['item_type' => 'activity', 'item_id' => $this->activity->id, 'notes' => str_repeat('a', 1001)])
        ->assertUnprocessable()->assertJsonValidationErrors('notes');

    expect(CartItem::count())->toBe(0);
});
