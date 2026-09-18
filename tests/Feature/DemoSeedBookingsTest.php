<?php

use App\Models\Booking;
use App\Models\RoomType;
use App\Models\User;
use App\Services\DemoSeedBookingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

test('registration creates no seed bookings when demo mode off', function () {
    Config::set('app.demo_mode', false);

    $this->post('/register', [
        'name' => 'No Seed',
        'email' => 'noseed@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test St, Manila',
        'phone_number' => '09123456789',
        'age_confirmed' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'ai_disclosure_accepted' => '1',
    ]);

    $user = User::where('email', 'noseed@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->bookings()->count())->toBe(0);
});

test('service seeds one real and one demo approved booking', function () {
    Notification::fake();
    Config::set('app.demo_mode', true);

    $room = RoomType::factory()->create([
        'room_name' => 'Seed Ocean View',
        'base_price' => 2500.00,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500.00,
        'total_rooms' => 5,
        'is_shown' => true,
    ]);
    $user = onboardedUser(['email' => 'seedme@example.com']);

    app(DemoSeedBookingService::class)->seed($user);

    $bookings = $user->bookings()->orderBy('id')->get();
    expect($bookings)->toHaveCount(2);

    foreach ($bookings as $booking) {
        expect($booking->status)->toBe(Booking::STATUS_APPROVED)
            ->and($booking->payment_status)->toBe(Booking::PAYMENT_UNPAID)
            ->and($booking->booking_source)->toBe('demo_seed')
            ->and($booking->payment_deadline->greaterThan(now()->addDays(29)))->toBeTrue()
            ->and($booking->booking_code)->toMatch('/^ST-\d{4}-[A-Z0-9]{5}$/');
        expect($booking->history()->where('from_status', 'pending')->where('to_status', 'approved')->exists())->toBeTrue();
    }

    expect($bookings[0]->items)->toHaveCount(1)
        ->and($bookings[0]->net_amount)->toBe('5000.00');
    expect($bookings[1]->items->first()->item_title)->toStartWith('TEST')
        ->and($bookings[1]->net_amount)->toBe('1.00');

    Notification::assertNothingSent();
});

test('service is idempotent', function () {
    Config::set('app.demo_mode', true);
    RoomType::factory()->create(['base_price' => 2000.00, 'base_occupancy' => 2, 'is_shown' => true]);
    $user = onboardedUser(['email' => 'idem@example.com']);

    $service = app(DemoSeedBookingService::class);
    $service->seed($user);
    $service->seed($user);

    expect($user->bookings()->count())->toBe(2);
});
