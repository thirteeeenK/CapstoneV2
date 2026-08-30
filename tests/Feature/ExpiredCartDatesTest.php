<?php

use App\Models\CartItem;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->room = RoomType::factory()->create([
        'room_name' => 'Deluxe Ocean View',
        'base_price' => 2000.00,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500.00,
        'total_rooms' => 5,
    ]);
    $this->user = onboardedUser();
});

it('rejects adding a room with a past check-in date via cart API', function () {
    Carbon::setTestNow('2026-08-31 10:00:00');

    $response = $this->actingAs($this->user)->postJson(route('cart.add'), [
        'item_type' => 'room',
        'item_id' => $this->room->id,
        'quantity' => 1,
        'selected_pax' => 2,
        'check_in_date' => '2026-08-26',
        'check_out_date' => '2026-08-27',
    ]);

    $response->assertStatus(422);
    // Validation returns standard Laravel errors structure after we added after_or_equal:today
    $response->assertJsonValidationErrors(['check_in_date']);

    expect(CartItem::count())->toBe(0);

    Carbon::setTestNow();
});

it('allows adding a room with check_in equal to today', function () {
    Carbon::setTestNow('2026-08-31 10:00:00');

    $response = $this->actingAs($this->user)->postJson(route('cart.add'), [
        'item_type' => 'room',
        'item_id' => $this->room->id,
        'quantity' => 1,
        'selected_pax' => 2,
        'check_in_date' => '2026-08-31',
        'check_out_date' => '2026-09-01',
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    expect(CartItem::count())->toBe(1);

    Carbon::setTestNow();
});

it('rejects updating a cart item to a past check-in date', function () {
    Carbon::setTestNow('2026-08-31 10:00:00');

    $cartItem = CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $this->room->id,
        'quantity' => 1,
        'check_in_date' => '2026-09-01',
        'check_out_date' => '2026-09-02',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);

    $response = $this->actingAs($this->user)->patchJson(route('cart.update', $cartItem->id), [
        'check_in_date' => '2026-08-26',
        'check_out_date' => '2026-08-27',
    ]);

    $response->assertStatus(422);

    Carbon::setTestNow();
});

it('blocks checkout page when selected cart item has past dates and redirects to cart with error', function () {
    // Simulate scenario: item added Aug 26-27, today Aug 31
    Carbon::setTestNow('2026-08-31 10:00:00');

    CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $this->room->id,
        'quantity' => 1,
        'check_in_date' => '2026-08-26',
        'check_out_date' => '2026-08-27',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('checkout.index'));

    $response->assertRedirect(route('cart.index'))
        ->assertSessionHas('error');

    expect(session('error') ?? $response->getSession()->get('error'))
        ->toContain('already passed');

    Carbon::setTestNow();
});

it('allows checkout page when expired item is deselected', function () {
    Carbon::setTestNow('2026-08-31 10:00:00');

    CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $this->room->id,
        'quantity' => 1,
        'check_in_date' => '2026-08-26',
        'check_out_date' => '2026-08-27',
        'selected_pax' => 2,
        'is_selected' => false,
    ]);

    // Add a valid selected item so cart not empty
    $validRoom = RoomType::factory()->create(['base_price' => 1500]);
    CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $validRoom->id,
        'quantity' => 1,
        'check_in_date' => '2026-09-01',
        'check_out_date' => '2026-09-02',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('checkout.index'));

    $response->assertOk();

    Carbon::setTestNow();
});

it('blocks checkout process API when selected item is expired', function () {
    Carbon::setTestNow('2026-08-31 10:00:00');

    CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $this->room->id,
        'quantity' => 1,
        'check_in_date' => '2026-08-26',
        'check_out_date' => '2026-08-27',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => json_encode([['full_name' => 'Juan Dela Cruz', 'category' => 'Adult']]),
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);

    expect($response->json('message'))->toContain('already passed');

    Carbon::setTestNow();
});

it('allows checkout process when expired item is deselected', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');

    // Past but deselected
    CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $this->room->id,
        'quantity' => 1,
        'check_in_date' => '2026-08-26',
        'check_out_date' => '2026-08-27',
        'selected_pax' => 2,
        'is_selected' => false,
    ]);

    $futureRoom = RoomType::factory()->create(['base_price' => 2000]);
    CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $futureRoom->id,
        'quantity' => 1,
        'check_in_date' => '2026-09-20',
        'check_out_date' => '2026-09-22',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => json_encode([['full_name' => 'Juan Dela Cruz', 'category' => 'Adult']]),
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    Carbon::setTestNow();
});

it('exposes is_expired flag in cart data endpoint', function () {
    Carbon::setTestNow('2026-08-31 10:00:00');

    CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $this->room->id,
        'quantity' => 1,
        'check_in_date' => '2026-08-26',
        'check_out_date' => '2026-08-27',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);

    $futureRoom = RoomType::factory()->create(['base_price' => 1500]);
    CartItem::create([
        'user_id' => $this->user->id,
        'item_type' => 'room',
        'item_id' => $futureRoom->id,
        'quantity' => 1,
        'check_in_date' => '2026-09-01',
        'check_out_date' => '2026-09-02',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('cart.data'));
    $response->assertOk()->assertJson(['success' => true]);

    $items = collect($response->json('items'));
    expect($items->firstWhere('check_in_date', '2026-08-26')['is_expired'])->toBeTrue()
        ->and($items->firstWhere('check_in_date', '2026-09-01')['is_expired'])->toBeFalse();

    Carbon::setTestNow();
});

it('CartItem isExpired returns true only for past room check_in', function () {
    Carbon::setTestNow('2026-08-31');

    $expired = CartItem::factory()->make([
        'item_type' => 'room',
        'check_in_date' => '2026-08-26',
        'check_out_date' => '2026-08-27',
    ]);
    expect($expired->isExpired())->toBeTrue();

    $today = CartItem::factory()->make([
        'item_type' => 'room',
        'check_in_date' => '2026-08-31',
        'check_out_date' => '2026-09-01',
    ]);
    expect($today->isExpired())->toBeFalse();

    $future = CartItem::factory()->make([
        'item_type' => 'room',
        'check_in_date' => '2026-09-10',
        'check_out_date' => '2026-09-12',
    ]);
    expect($future->isExpired())->toBeFalse();

    $activity = CartItem::factory()->make([
        'item_type' => 'activity',
        'check_in_date' => null,
        'check_out_date' => null,
    ]);
    expect($activity->isExpired())->toBeFalse();

    Carbon::setTestNow();
});
