<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $destination = DestinationModel::create(['name' => 'Boracay', 'description' => 'Test', 'image' => null]);
    $hotel = HotelModel::create([
        'hotel_name' => 'Test Beach Resort',
        'destination_id' => $destination->id,
        'type' => 'Resort',
        'hotel_description' => 'A test resort.',
        'specific_address' => 'Station 1, White Beach',
        'latitude' => 11.9674,
        'longitude' => 121.9251,
        'is_shown' => true,
        'images' => [],
    ]);
    $this->room = RoomType::create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Deluxe Ocean View',
        'base_price' => 2000.00,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500.00,
        'total_rooms' => 5,
        'room_amenities' => [],
        'images' => [],
        'is_shown' => true,
    ]);

    $this->user = User::factory()->create();
    $this->user->preferences_embedding = '[' . implode(',', array_fill(0, 3072, '0.1')) . ']';
    $this->user->save();

    $this->admin = \App\Models\AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@sunnytripstest.com',
        'password' => 'password',
    ]);
});

function addRoomToCart(User $user, RoomType $room): CartItem
{
    return CartItem::create([
        'user_id' => $user->id,
        'item_type' => 'room',
        'item_id' => $room->id,
        'quantity' => 1,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-04',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);
}

it('creates a pending booking from the cart via checkout', function () {
    addRoomToCart($this->user, $this->room);

    $response = $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'special_requests' => null,
        'guest_manifest' => json_encode([
            ['full_name' => 'Juan Dela Cruz', 'category' => 'Adult', 'special_notes' => ''],
        ]),
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $booking = Booking::first();
    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe('pending')
        ->and($booking->payment_status)->toBe('unpaid')
        ->and($booking->total_amount)->toBe('6000.00')
        ->and($booking->net_amount)->toBe('6000.00')
        ->and($booking->booking_code)->toMatch('/^ST-\d{4}-[A-Z0-9]{5}$/');

    expect($booking->items)->toHaveCount(1)
        ->and($booking->items->first()->item_title)->toContain('Deluxe Ocean View');

    expect(CartItem::count())->toBe(0);
});

it('admin can approve a pending booking opening a 48-hour payment window', function () {
    $cartItem = addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();

    $this->actingAs($this->admin, 'admin')->post(route('admin.bookings.approve', $booking->id), [
        'items' => [
            $booking->items->first()->id => ['include' => '1', 'quantity' => 1],
        ],
        'admin_notes' => 'Welcome!',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();
    expect($booking->status)->toBe('approved')
        ->and($booking->approved_at)->not->toBeNull()
        ->and($booking->payment_deadline)->not->toBeNull()
        ->and(now()->diffInHours($booking->payment_deadline, false))->toBeLessThanOrEqual(48)
        ->and($booking->reviewed_by_admin_id)->toBe($this->admin->id);

    $item = $booking->items->first();
    expect($item->availability_status)->toBe('available');

    Notification::assertSentTo($this->user, \App\Notifications\BookingApproved::class);
});

it('supports partial approval excluding unavailable items and recomputing totals', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();
    $item = $booking->items->first();

    $this->actingAs($this->admin, 'admin')->post(route('admin.bookings.approve', $booking->id), [
        'items' => [
            $item->id => ['include' => '0', 'quantity' => 1, 'admin_note' => 'Sold out for these dates'],
        ],
    ])->assertRedirect();

    $booking->refresh();
    $item->refresh();

    expect($item->availability_status)->toBe('unavailable')
        ->and($item->admin_note)->toBe('Sold out for these dates')
        ->and((float) $booking->total_amount)->toBe(0.00)
        ->and((float) $booking->net_amount)->toBe(0.00);
});

it('rejects a pending booking with a required reason', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();

    $this->actingAs($this->admin, 'admin')->post(route('admin.bookings.reject', $booking->id), [])
        ->assertSessionHasErrors('rejection_reason');

    $this->actingAs($this->admin, 'admin')->post(route('admin.bookings.reject', $booking->id), ['rejection_reason' => 'Rooms unavailable'])
        ->assertRedirect();

    $booking->refresh();
    expect($booking->status)->toBe('rejected')
        ->and($booking->rejection_reason)->toBe('Rooms unavailable');

    Notification::assertSentTo($this->user, \App\Notifications\BookingRejected::class);
});

it('expires approved bookings after the 48-hour window', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();
    $booking->status = 'approved';
    $booking->payment_deadline = now()->subHour();
    $booking->save();

    $this->artisan('bookings:expire')->assertSuccessful();

    $booking->refresh();
    expect($booking->status)->toBe('expired')
        ->and($booking->expired_at)->not->toBeNull();

    Notification::assertSentTo($this->user, \App\Notifications\BookingExpired::class);
});

it('marks a booking paid through the simulator', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();
    $booking->status = 'approved';
    $booking->payment_deadline = now()->addHours(48);
    $booking->save();

    $this->actingAs($this->user)->post(route('booking.pay.simulator.confirm', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code));

    $booking->refresh();
    expect($booking->status)->toBe('paid')
        ->and($booking->payment_status)->toBe('paid')
        ->and($booking->paid_at)->not->toBeNull();

    Notification::assertSentTo($this->user, \App\Notifications\BookingPaid::class);
});

it('allows the user to cancel a pending booking', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();

    $this->actingAs($this->user)->post(route('booking.cancel', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code));

    $booking->refresh();
    expect($booking->status)->toBe('cancelled')
        ->and($booking->cancelled_at)->not->toBeNull();
});

it('rebooks items back into the cart after expiry', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();
    $booking->status = 'expired';
    $booking->expired_at = now();
    $booking->save();

    $this->actingAs($this->user)->post(route('booking.rebook', $booking->booking_code))
        ->assertRedirect(route('cart.index'));

    expect(CartItem::count())->toBe(1)
        ->and(CartItem::first()->item_id)->toBe($this->room->id);
});

it('holds inventory in availability counts and releases on expiry', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $service = app(\App\Services\RoomAvailabilityService::class);
    $result = $service->check($this->room, now()->parse('2026-12-01'), now()->parse('2026-12-04'));

    expect($result['booked_count'])->toBe(1)
        ->and($result['remaining'])->toBe(4)
        ->and($result['available'])->toBeTrue();

    Booking::first()->update(['status' => 'expired']);

    $released = $service->check($this->room, now()->parse('2026-12-01'), now()->parse('2026-12-04'));
    expect($released['booked_count'])->toBe(0)
        ->and($released['remaining'])->toBe(5);
});

it('redirects unauthenticated visitors away from booking pages', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();

    auth()->logout();

    $this->get(route('booking.show', $booking->booking_code))
        ->assertRedirect(route('login'));
    $this->post(route('booking.pay.simulator.confirm', $booking->booking_code))
        ->assertRedirect(route('login'));
    $this->post(route('booking.cancel', $booking->booking_code))
        ->assertRedirect(route('login'));
});

it('hides another user booking (IDOR) with a 404 for owner-only views', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'special_requests' => 'Contact: 09171234567, Email: victim@mail.com',
        'guest_manifest' => '[{"full_name":"Victim","category":"Adult","special_notes":""}]',
    ]);

    $booking = Booking::first();

    $attacker = User::factory()->create();
    $attacker->preferences_embedding = '[' . implode(',', array_fill(0, 3072, '0.1')) . ']';
    $attacker->save();

    $this->actingAs($attacker)->get(route('booking.show', $booking->booking_code))->assertNotFound();
    $this->actingAs($attacker)->post(route('booking.cancel', $booking->booking_code))->assertNotFound();
    $this->actingAs($attacker)->post(route('booking.pay.simulator.confirm', $booking->booking_code))->assertNotFound();

    $booking->refresh();
    expect($booking->status)->toBe('pending');
});

it('allows the owner but not others to view booking pages', function () {
    addRoomToCart($this->user, $this->room);
    $this->actingAs($this->user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ]);

    $booking = Booking::first();

    $this->actingAs($this->user)->get(route('booking.show', $booking->booking_code))->assertOk();
});
