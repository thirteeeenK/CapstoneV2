<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CartItem;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Models\User;
use App\Notifications\BookingApproved;
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

function createPendingBooking(User $user, RoomType $room): Booking
{
    CartItem::create([
        'user_id' => $user->id,
        'item_type' => 'room',
        'item_id' => $room->id,
        'quantity' => 1,
        'check_in_date' => '2026-12-01',
        'check_out_date' => '2026-12-04',
        'selected_pax' => 2,
        'is_selected' => true,
    ]);

    test()->actingAs($user)->post(route('checkout.process'), [
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => $user->email,
        'contact_phone' => '09171234567',
        'guest_manifest' => null,
    ])->assertOk();

    return Booking::first();
}

function approveBooking(\App\Models\AdminModel $admin, Booking $booking, array $adjustment = []): \Illuminate\Testing\TestResponse
{
    $payload = array_merge([
        'items' => [
            $booking->items->first()->id => ['include' => '1', 'quantity' => 1],
        ],
    ], $adjustment);

    return test()->actingAs($admin, 'admin')->post(route('admin.bookings.approve', $booking->id), $payload);
}

it('applies an admin discount with a reason and reduces net_amount', function () {
    $booking = createPendingBooking($this->user, $this->room);
    expect($booking->net_amount)->toBe('6000.00');

    approveBooking($this->admin, $booking, [
        'admin_discount_amount' => '2000.00',
        'price_adjustment_reason' => 'Room was listed with outdated photos, sorry for the inconvenience.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();
    expect($booking->status)->toBe('approved')
        ->and($booking->net_amount)->toBe('4000.00')
        ->and($booking->admin_discount_amount)->toBe('2000.00')
        ->and($booking->admin_surcharge_amount)->toBe('0.00')
        ->and($booking->price_adjustment_reason)->toBe('Room was listed with outdated photos, sorry for the inconvenience.')
        ->and($booking->price_adjusted_at)->not->toBeNull();
});

it('applies an additional amount with a reason and increases net_amount', function () {
    $booking = createPendingBooking($this->user, $this->room);

    approveBooking($this->admin, $booking, [
        'admin_surcharge_amount' => '500.00',
        'price_adjustment_reason' => 'Extra guest not declared in the original request.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();
    expect($booking->status)->toBe('approved')
        ->and($booking->net_amount)->toBe('6500.00')
        ->and($booking->admin_surcharge_amount)->toBe('500.00')
        ->and($booking->admin_discount_amount)->toBe('0.00')
        ->and($booking->price_adjusted_at)->not->toBeNull();
});

it('rejects approval when an amount is set without a reason', function () {
    $booking = createPendingBooking($this->user, $this->room);

    approveBooking($this->admin, $booking, [
        'admin_discount_amount' => '1000.00',
    ])->assertSessionHas('error', 'Please provide a reason for the price adjustment.');

    $booking->refresh();
    expect($booking->status)->toBe('pending')
        ->and($booking->admin_discount_amount)->toBe('0.00')
        ->and($booking->price_adjusted_at)->toBeNull();
});

it('clamps net_amount to zero when the discount exceeds the total', function () {
    $booking = createPendingBooking($this->user, $this->room);

    approveBooking($this->admin, $booking, [
        'admin_discount_amount' => '10000.00',
        'price_adjustment_reason' => 'Full courtesy discount.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();
    expect($booking->status)->toBe('approved')
        ->and($booking->net_amount)->toBe('0.00')
        ->and($booking->admin_discount_amount)->toBe('10000.00');
});

it('leaves net_amount unchanged when no adjustment is submitted', function () {
    $booking = createPendingBooking($this->user, $this->room);

    approveBooking($this->admin, $booking)->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();
    expect($booking->status)->toBe('approved')
        ->and($booking->net_amount)->toBe('6000.00')
        ->and($booking->admin_discount_amount)->toBe('0.00')
        ->and($booking->admin_surcharge_amount)->toBe('0.00')
        ->and($booking->price_adjustment_reason)->toBeNull()
        ->and($booking->price_adjusted_at)->toBeNull();
});

it('carries the admin adjustment in the database notification payload', function () {
    $booking = createPendingBooking($this->user, $this->room);

    approveBooking($this->admin, $booking, [
        'admin_discount_amount' => '1500.00',
        'admin_surcharge_amount' => '250.00',
        'price_adjustment_reason' => 'Combined courtesy and extra guest charge.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();

    Notification::assertSentTo($this->user, BookingApproved::class, function ($notification, $channels) use ($booking) {
        $data = $notification->toArray($this->user);

        return ($data['admin_adjustment']['discount'] ?? null) === 1500.0
            && ($data['admin_adjustment']['surcharge'] ?? null) === 250.0
            && ($data['admin_adjustment']['reason'] ?? null) === 'Combined courtesy and extra guest charge.'
            && $data['booking_code'] === $booking->booking_code;
    });
});

it('renders the adjustment section in the BookingApproved email', function () {
    $booking = createPendingBooking($this->user, $this->room);

    approveBooking($this->admin, $booking, [
        'admin_discount_amount' => '2000.00',
        'price_adjustment_reason' => 'Room was listed with outdated photos, sorry for the inconvenience.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();

    Notification::assertSentTo($this->user, BookingApproved::class, function ($notification, $channels) {
        $mail = $notification->toMail($this->user);
        $text = implode("\n", array_merge($mail->introLines, [$mail->actionText ?? '']));

        return str_contains($text, 'discount of') && str_contains($text, '2,000.00')
            && str_contains($text, 'Room was listed with outdated photos, sorry for the inconvenience.');
    });
});

it('still emails guests without an account via the contact fallback path', function () {
    $booking = Booking::create([
        'booking_code' => 'ST-' . date('Y') . '-' . strtoupper(\Illuminate\Support\Str::random(5)),
        'user_id' => null,
        'status' => Booking::STATUS_PENDING,
        'total_amount' => 3000.00,
        'discount_amount' => 0.00,
        'tax_amount' => 0.00,
        'net_amount' => 3000.00,
        'payment_status' => Booking::PAYMENT_UNPAID,
        'contact_name' => 'Guest Person',
        'contact_email' => 'guest@example.com',
        'contact_phone' => '09170000000',
        'guest_manifest' => null,
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'item_type' => 'activity',
        'item_id' => 1,
        'item_title' => 'Island Hopping Tour',
        'item_subtitle' => 'Test tour',
        'unit_price' => 3000.00,
        'quantity' => 1,
        'selected_pax' => 1,
        'subtotal' => 3000.00,
        'availability_status' => 'pending',
        'item_snapshot' => [],
    ]);

    approveBooking($this->admin, $booking, [
        'admin_discount_amount' => '500.00',
        'price_adjustment_reason' => 'Loyalty courtesy discount.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();
    expect($booking->net_amount)->toBe('2500.00');

    Notification::assertSentOnDemand(BookingApproved::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'guest@example.com';
    });
});

it('keeps the admin adjustment columns when the booking is rebooked', function () {
    $booking = createPendingBooking($this->user, $this->room);

    approveBooking($this->admin, $booking, [
        'admin_discount_amount' => '1000.00',
        'price_adjustment_reason' => 'Noise during the first night.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();
    expect($booking->admin_discount_amount)->toBe('1000.00')
        ->and($booking->price_adjusted_at)->not->toBeNull();

    $booking->status = 'expired';
    $booking->expired_at = now();
    $booking->save();

    $this->actingAs($this->user)->post(route('booking.rebook', $booking->booking_code))
        ->assertRedirect(route('cart.index'));

    expect(CartItem::count())->toBe(1)
        ->and(CartItem::first()->item_id)->toBe($this->room->id);

    $booking->refresh();
    expect($booking->admin_discount_amount)->toBe('1000.00')
        ->and($booking->admin_surcharge_amount)->toBe('0.00')
        ->and($booking->net_amount)->toBe('5000.00');
});

it('renders the adjustment rows and reason card on the user booking page', function () {
    $booking = createPendingBooking($this->user, $this->room);

    approveBooking($this->admin, $booking, [
        'admin_discount_amount' => '2000.00',
        'admin_surcharge_amount' => '500.00',
        'price_adjustment_reason' => 'Combined courtesy discount and extra guest charge.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id));

    $booking->refresh();

    $response = $this->actingAs($this->user)->get(route('booking.show', $booking->booking_code));
    $response->assertOk()
        ->assertSee('Admin discount')
        ->assertSee('Additional amount')
        ->assertSee('Note from SunnyTrips')
        ->assertSee('Combined courtesy discount and extra guest charge.')
        ->assertSee('4,500.00');
});
