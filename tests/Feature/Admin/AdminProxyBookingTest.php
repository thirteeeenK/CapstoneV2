<?php

use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\PassengerCategoryRule;
use App\Models\RoomType;
use App\Models\User;
use App\Notifications\BookingApproved;
use App\Notifications\BookingPaid;
use App\Notifications\BookingRequestReceived;
use App\Notifications\NewGuestAccountCreated;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();

    $this->destination = DestinationModel::firstOrCreate(
        ['name' => 'Boracay'],
        ['description' => 'Island destination', 'is_shown' => true]
    );

    $this->hotel = HotelModel::factory()->create([
        'hotel_name' => 'Paradise Sands Resort',
        'destination_id' => $this->destination->id,
        'is_shown' => true,
    ]);

    $this->room = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_name' => 'Premier Ocean Suite',
        'base_price' => 3000.00,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500.00,
        'total_rooms' => 5,
        'is_shown' => true,
    ]);

    $this->user = onboardedUser([
        'name' => 'Maria Clara',
        'email' => 'maria@example.com',
        'phone_number' => '09171234567',
    ]);

    $this->admin = AdminModel::create([
        'name' => 'Desk Officer Alex',
        'email' => 'alex@sunnytripstest.com',
        'password' => 'password',
    ]);
});

it('renders the create booking form with pre-selected user when user_id is provided', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.bookings.create', ['user_id' => $this->user->id]))
        ->assertOk()
        ->assertSee('Create Booking on Behalf of Customer')
        ->assertSee('Maria Clara')
        ->assertSee('maria@example.com');
});

it('searches registered users via ajax autocomplete', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.bookings.search-users', ['query' => 'Maria']))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $this->user->id,
            'name' => 'Maria Clara',
            'email' => 'maria@example.com',
        ]);
});

it('rejects an admin booking when the phone number is not exactly 11 digits', function () {
    $checkIn = now()->addDays(5)->format('Y-m-d');
    $checkOut = now()->addDays(7)->format('Y-m-d');

    $payload = [
        'is_walk_in' => 0,
        'user_id' => $this->user->id,
        'booking_source' => 'admin_phone',
        'contact_name' => $this->user->name,
        'contact_email' => $this->user->email,
        'initial_status' => 'pending',
        'items' => [
            [
                'item_type' => 'room',
                'item_id' => $this->room->id,
                'quantity' => 1,
                'selected_pax' => 2,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
            ],
        ],
        'guest_manifest' => [
            ['full_name' => 'Maria Clara', 'category' => 'Adult'],
        ],
    ];

    foreach (['0917123456', '091712345678'] as $badPhone) {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.bookings.store'), array_merge($payload, ['contact_phone' => $badPhone]))
            ->assertSessionHasErrors('contact_phone');
    }

    expect(Booking::count())->toBe(0);
});

it('allows an admin to book on behalf of an existing registered user as pending', function () {
    $checkIn = now()->addDays(5)->format('Y-m-d');
    $checkOut = now()->addDays(7)->format('Y-m-d');

    $response = $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 0,
            'user_id' => $this->user->id,
            'booking_source' => 'admin_phone',
            'contact_name' => $this->user->name,
            'contact_email' => $this->user->email,
            'contact_phone' => $this->user->phone_number,
            'initial_status' => 'pending',
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                ],
            ],
            'guest_manifest' => [
                ['full_name' => 'Maria Clara', 'category' => 'Adult'],
                ['full_name' => 'Crisostomo Ibarra', 'category' => 'Adult'],
            ],
        ]);

    $booking = Booking::where('user_id', $this->user->id)->first();
    expect($booking)->not->toBeNull();
    expect($booking->status)->toBe('pending');
    expect($booking->booked_by_admin_id)->toBe($this->admin->id);
    expect($booking->booking_source)->toBe('admin_phone');
    expect($booking->is_walk_in)->toBeFalse();
    expect($booking->isAgentBooked())->toBeTrue();
    expect($booking->items()->count())->toBe(1);

    Notification::assertSentTo($this->user, BookingRequestReceived::class);
});

it('allows an admin to book on behalf of a user and immediately approve it with a payment window', function () {
    $checkIn = now()->addDays(3)->format('Y-m-d');
    $checkOut = now()->addDays(5)->format('Y-m-d');

    $response = $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 0,
            'user_id' => $this->user->id,
            'booking_source' => 'admin_concierge',
            'contact_name' => $this->user->name,
            'contact_email' => $this->user->email,
            'contact_phone' => $this->user->phone_number,
            'initial_status' => 'approved',
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                ],
            ],
        ]);

    $booking = Booking::where('user_id', $this->user->id)->first();
    expect($booking)->not->toBeNull();
    expect($booking->status)->toBe('approved');
    expect($booking->payment_status)->toBe('unpaid');
    expect($booking->approved_at)->not->toBeNull();
    expect($booking->payment_deadline)->not->toBeNull();
    expect($booking->reviewed_by_admin_id)->toBe($this->admin->id);

    Notification::assertSentTo($this->user, BookingApproved::class);
});

it('allows an admin to book a walk-in guest and mark it as paid with cash', function () {
    $checkIn = now()->addDays(2)->format('Y-m-d');
    $checkOut = now()->addDays(4)->format('Y-m-d');

    $response = $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 1,
            'auto_create_account' => 0,
            'booking_source' => 'admin_walk_in',
            'contact_name' => 'Walk-in Traveler John',
            'contact_email' => 'john.walkin@example.com',
            'contact_phone' => '09189998888',
            'initial_status' => 'paid',
            'payment_method' => 'Cash',
            'payment_reference' => 'OR-998877',
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                ],
            ],
        ]);

    $booking = Booking::where('contact_email', 'john.walkin@example.com')->first();
    expect($booking)->not->toBeNull();
    expect($booking->user_id)->toBeNull();
    expect($booking->status)->toBe('paid');
    expect($booking->payment_status)->toBe('paid');
    expect($booking->payment_method)->toBe('Cash');
    expect($booking->payment_reference)->toBe('OR-998877');
    expect($booking->is_walk_in)->toBeTrue();
    expect($booking->paid_at)->not->toBeNull();

    Notification::assertSentOnDemand(BookingPaid::class);
});

it('auto-creates a user account for a walk-in guest when the checkbox is selected', function () {
    $checkIn = now()->addDays(10)->format('Y-m-d');
    $checkOut = now()->addDays(12)->format('Y-m-d');

    $response = $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 1,
            'auto_create_account' => 1,
            'booking_source' => 'admin_walk_in',
            'contact_name' => 'Auto Created Guest',
            'contact_email' => 'newguest@example.com',
            'contact_phone' => '09191112222',
            'initial_status' => 'approved',
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                ],
            ],
        ]);

    $newUser = User::where('email', 'newguest@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->name)->toBe('Auto Created Guest');

    $response->assertSessionHas('new_user_account');
    $sessionAccount = session('new_user_account');
    expect($sessionAccount['email'])->toBe('newguest@example.com');
    expect($sessionAccount['password'])->not->toBeEmpty();
    expect(Hash::check($sessionAccount['password'], $newUser->password))->toBeTrue();

    $booking = Booking::where('contact_email', 'newguest@example.com')->first();
    expect($booking)->not->toBeNull();
    expect($booking->user_id)->toBe($newUser->id);

    Notification::assertSentTo($newUser, NewGuestAccountCreated::class);
});

it('applies passenger category rules and admin price adjustments correctly', function () {
    PassengerCategoryRule::updateOrCreate(
        ['category_name' => 'Senior'],
        [
            'display_label' => 'Senior Citizen',
            'adjustment_type' => 'discount',
            'amount' => 200.00,
            'is_active' => true,
        ]
    );

    $checkIn = now()->addDays(4)->format('Y-m-d');
    $checkOut = now()->addDays(5)->format('Y-m-d'); // 1 night * 3000 = 3000

    $response = $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 0,
            'user_id' => $this->user->id,
            'booking_source' => 'admin_phone',
            'contact_name' => $this->user->name,
            'contact_email' => $this->user->email,
            'contact_phone' => $this->user->phone_number,
            'initial_status' => 'approved',
            'admin_discount_amount' => 300.00,
            'price_adjustment_reason' => 'Courtesy discount for senior group',
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                ],
            ],
            'guest_manifest' => [
                ['full_name' => 'Senior Guest 1', 'category' => 'Senior'], // -200
            ],
        ]);

    $booking = Booking::where('user_id', $this->user->id)->first();
    expect($booking)->not->toBeNull();
    expect((float) $booking->total_amount)->toBe(3000.00);
    expect((float) $booking->discount_amount)->toBe(200.00);
    expect((float) $booking->admin_discount_amount)->toBe(300.00);
    // 3000 - 200 - 300 = 2500
    expect((float) $booking->net_amount)->toBe(2500.00);
});

it('displays the admin-created booking in the user my-bookings dashboard', function () {
    $booking = Booking::create([
        'booking_code' => 'ST-2026-TEST1',
        'user_id' => $this->user->id,
        'status' => Booking::STATUS_APPROVED,
        'total_amount' => 3000.00,
        'discount_amount' => 0.00,
        'tax_amount' => 0.00,
        'net_amount' => 3000.00,
        'payment_status' => Booking::PAYMENT_UNPAID,
        'contact_name' => $this->user->name,
        'contact_email' => $this->user->email,
        'contact_phone' => $this->user->phone_number,
        'booked_by_admin_id' => $this->admin->id,
        'booking_source' => 'admin_phone',
    ]);

    $this->actingAs($this->user)
        ->get(route('booking.index'))
        ->assertOk()
        ->assertSee('ST-2026-TEST1');
});

it('allows admin to trigger a password reset email to the booking customer', function () {
    Password::shouldReceive('broker->sendResetLink')
        ->once()
        ->with(['email' => $this->user->email])
        ->andReturn(Password::RESET_LINK_SENT);

    $booking = Booking::create([
        'booking_code' => 'ST-2026-RST01',
        'user_id' => $this->user->id,
        'status' => Booking::STATUS_APPROVED,
        'total_amount' => 1000.00,
        'discount_amount' => 0.00,
        'tax_amount' => 0.00,
        'net_amount' => 1000.00,
        'payment_status' => Booking::PAYMENT_UNPAID,
        'contact_name' => $this->user->name,
        'contact_email' => $this->user->email,
        'contact_phone' => $this->user->phone_number,
        'booked_by_admin_id' => $this->admin->id,
        'booking_source' => 'admin_walk_in',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.send-password-reset', $booking->id))
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('includes extra-head fees and nights in room pricing', function () {
    $checkIn = now()->addDays(5)->format('Y-m-d');
    $checkOut = now()->addDays(7)->format('Y-m-d'); // 2 nights, pax 4 → (3000 + 2×500) × 2 = 8000

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 0,
            'user_id' => $this->user->id,
            'booking_source' => 'admin_phone',
            'contact_name' => $this->user->name,
            'contact_email' => $this->user->email,
            'contact_phone' => $this->user->phone_number,
            'initial_status' => 'pending',
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 4,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                ],
            ],
        ]);

    $booking = Booking::where('user_id', $this->user->id)->first();
    expect($booking)->not->toBeNull();
    expect((float) $booking->total_amount)->toBe(8000.00);
    expect((float) $booking->net_amount)->toBe(8000.00);

    $item = $booking->items()->first();
    expect((float) $item->subtotal)->toBe(8000.00);
    expect((float) $item->unit_price)->toBe(8000.00);
    expect((int) $item->nights)->toBe(2);
});

it('retains nightly totals when approving with quantity adjustments', function () {
    $checkIn = now()->addDays(5)->format('Y-m-d');
    $checkOut = now()->addDays(7)->format('Y-m-d'); // 2 nights × 3000 = 6000 per unit

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 0,
            'user_id' => $this->user->id,
            'booking_source' => 'admin_phone',
            'contact_name' => $this->user->name,
            'contact_email' => $this->user->email,
            'contact_phone' => $this->user->phone_number,
            'initial_status' => 'pending',
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                ],
            ],
        ]);

    $booking = Booking::where('user_id', $this->user->id)->first();
    expect($booking)->not->toBeNull();
    $item = $booking->items()->first();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.approve', $booking->id), [
            'items' => [$item->id => ['include' => 1, 'quantity' => 2]],
        ])
        ->assertRedirect();

    $booking->refresh();
    $item->refresh();
    expect($booking->status)->toBe('approved');
    expect((float) $item->subtotal)->toBe(12000.00);
    expect((float) $booking->total_amount)->toBe(12000.00);
});

it('rejects room pax above max occupancy', function () {
    $checkIn = now()->addDays(5)->format('Y-m-d');
    $checkOut = now()->addDays(7)->format('Y-m-d');

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 0,
            'user_id' => $this->user->id,
            'booking_source' => 'admin_phone',
            'contact_name' => $this->user->name,
            'contact_email' => $this->user->email,
            'contact_phone' => $this->user->phone_number,
            'initial_status' => 'pending',
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 5,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.selected_pax');

    expect(Booking::where('user_id', $this->user->id)->count())->toBe(0);
});

it('rejects past check-in and inverted dates for rooms', function () {
    $payload = [
        'is_walk_in' => 0,
        'user_id' => $this->user->id,
        'booking_source' => 'admin_phone',
        'contact_name' => $this->user->name,
        'contact_email' => $this->user->email,
        'contact_phone' => $this->user->phone_number,
        'initial_status' => 'pending',
    ];

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), $payload + [
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                    'check_in_date' => now()->subDay()->format('Y-m-d'),
                    'check_out_date' => now()->addDay()->format('Y-m-d'),
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.check_in_date');

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), $payload + [
            'items' => [
                [
                    'item_type' => 'room',
                    'item_id' => $this->room->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                    'check_in_date' => now()->addDays(7)->format('Y-m-d'),
                    'check_out_date' => now()->addDays(5)->format('Y-m-d'),
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.check_out_date');

    expect(Booking::where('user_id', $this->user->id)->count())->toBe(0);
});

it('prices activities per person like the customer flow', function () {
    $activity = ActivityModel::factory()->create([
        'destination_id' => $this->destination->id,
        'rate' => '₱1,300/person',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 0,
            'user_id' => $this->user->id,
            'booking_source' => 'admin_phone',
            'contact_name' => $this->user->name,
            'contact_email' => $this->user->email,
            'contact_phone' => $this->user->phone_number,
            'initial_status' => 'pending',
            'items' => [
                [
                    'item_type' => 'activity',
                    'item_id' => $activity->id,
                    'quantity' => 1,
                    'selected_pax' => 2,
                ],
            ],
        ]);

    $booking = Booking::where('user_id', $this->user->id)->first();
    expect($booking)->not->toBeNull();
    expect((float) $booking->total_amount)->toBe(2600.00);
});

it('prices add-ons with the matching tier and effective pax', function () {
    $addon = AddOnModel::factory()->create([
        'destination_id' => $this->destination->id,
        'pricing_tiers' => [
            ['min_pax' => 1, 'max_pax' => 2, 'rate' => 500],
            ['min_pax' => 3, 'max_pax' => 5, 'rate' => 400],
        ],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.store'), [
            'is_walk_in' => 0,
            'user_id' => $this->user->id,
            'booking_source' => 'admin_phone',
            'contact_name' => $this->user->name,
            'contact_email' => $this->user->email,
            'contact_phone' => $this->user->phone_number,
            'initial_status' => 'pending',
            'items' => [
                [
                    'item_type' => 'addon',
                    'item_id' => $addon->id,
                    'quantity' => 1,
                    'selected_pax' => 4,
                ],
            ],
        ]);

    $booking = Booking::where('user_id', $this->user->id)->first();
    expect($booking)->not->toBeNull();
    expect((float) $booking->total_amount)->toBe(1600.00);
});
