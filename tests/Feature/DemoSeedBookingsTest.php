<?php

use App\Models\Booking;
use App\Models\RoomType;
use App\Models\User;
use App\Services\DemoSeedBookingService;
use App\Services\ReviewService;
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

test('service seeds one approved and one completed reviewable booking', function () {
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

    [$approved, $completed] = [$bookings[0], $bookings[1]];

    expect($approved->status)->toBe(Booking::STATUS_APPROVED)
        ->and($approved->payment_status)->toBe(Booking::PAYMENT_UNPAID)
        ->and($approved->booking_source)->toBe('demo_seed')
        ->and($approved->payment_deadline->greaterThan(now()->addDays(29)))->toBeTrue()
        ->and($approved->booking_code)->toMatch('/^ST-\d{4}-[A-Z0-9]{5}$/');
    expect($approved->history()->where('from_status', 'pending')->where('to_status', 'approved')->exists())->toBeTrue();

    expect($approved->items)->toHaveCount(1)
        ->and($approved->net_amount)->toBe('5000.00');

    expect($completed->status)->toBe(Booking::STATUS_COMPLETED)
        ->and($completed->payment_status)->toBe(Booking::PAYMENT_PAID)
        ->and($completed->booking_source)->toBe('demo_seed')
        ->and($completed->paid_at)->not->toBeNull()
        ->and($completed->booking_code)->toMatch('/^ST-\d{4}-[A-Z0-9]{5}$/');
    expect($completed->history()->where('from_status', 'pending')->where('to_status', 'approved')->exists())->toBeTrue();
    expect($completed->history()->where('from_status', 'approved')->where('to_status', 'paid')->exists())->toBeTrue();
    expect($completed->history()->where('from_status', 'paid')->where('to_status', 'completed')->exists())->toBeTrue();

    expect($completed->items->first()->item_title)->toStartWith('TEST')
        ->and($completed->net_amount)->toBe('1.00');

    $eligible = app(ReviewService::class)->eligibleBookings($user);
    expect($eligible->pluck('booking_id'))->toContain($completed->id)
        ->and($eligible->pluck('booking_id'))->not->toContain($approved->id);

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

test('new signup owns one payable approved booking and one reviewable completed booking when demo mode on', function () {
    Notification::fake();
    Config::set('app.demo_mode', true);
    RoomType::factory()->create(['base_price' => 2000.00, 'base_occupancy' => 2, 'is_shown' => true]);

    $response = $this->post('/register', [
        'name' => 'Demo Tester',
        'email' => 'demotester@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address' => '123 Test St, Manila',
        'phone_number' => '09123456789',
        'age_confirmed' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'ai_disclosure_accepted' => '1',
    ]);

    $response->assertRedirect(route('onboarding.index', absolute: false));

    $user = User::where('email', 'demotester@example.com')->first();
    $approved = $user->bookings()->where('status', Booking::STATUS_APPROVED)->get();
    expect($approved)->toHaveCount(1);

    foreach ($approved as $booking) {
        expect($booking->isPaymentDeadlinePassed())->toBeFalse();
    }

    $completed = $user->bookings()->where('status', Booking::STATUS_COMPLETED)->get();
    expect($completed)->toHaveCount(1)
        ->and($completed->first()->payment_status)->toBe(Booking::PAYMENT_PAID);

    $eligible = app(ReviewService::class)->eligibleBookings($user);
    expect($eligible->pluck('booking_id'))->toContain($completed->first()->id);

    Notification::assertNothingSent();
});
