<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPaymentRequest;
use App\Models\CartItem;
use App\Models\RoomType;
use App\Notifications\BookingApproved;
use App\Services\Payment\BookingPaymentPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 12:33:00', 'Asia/Manila'));
    Notification::fake();
    Http::preventStrayRequests();
    config(['system_settings.payments.installments_enabled' => true]);
});

function datedPolicyRequest(string $arrival, array $attributes = []): Booking
{
    $room = RoomType::factory()->create(['total_rooms' => 5]);
    $room->hotel->update(['check_in_time' => '14:00:00']);
    $booking = Booking::factory()->for(onboardedUser())->create(array_replace(['net_amount' => '8500.00'], $attributes));
    BookingItem::factory()->create([
        'booking_id' => $booking->id, 'item_id' => $room->id, 'item_type' => 'room',
        'check_in_date' => $arrival, 'check_out_date' => Carbon::parse($arrival)->addDay(),
        'quantity' => 1, 'subtotal' => '8500.00', 'availability_status' => 'pending',
        'item_snapshot' => ['check_in_time' => '14:00:00'],
    ]);

    return $booking;
}

test('dated deposit eligibility uses Philippine calendar days from submission', function (string $arrival, bool $eligible, int $days) {
    $booking = datedPolicyRequest($arrival);
    $terms = app(BookingPaymentPolicy::class)->approvalData($booking);

    expect($terms['payment_policy'])->toMatchArray(['deposit_eligible' => $eligible, 'advance_days_at_submission' => $days]);
    expect($terms['payment_deadline']->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-19 12:33:00');
    if ($eligible) {
        expect($terms['balance_due_at']->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-23 14:00:00');
        expect($terms['deposit_required_cents'])->toBe(425000);
    } else {
        expect($terms['balance_due_at'])->toBeNull();
        expect($terms['payment_policy']['deposit_explanation'])->toContain('more than 7 days ahead');
    }
})->with([['2026-10-23', false, 6], ['2026-10-24', false, 7], ['2026-10-25', true, 8]]);

test('eligibility is stable across UTC midnight and month boundaries', function (string $submitted, string $arrival, bool $eligible) {
    config(['app.timezone' => 'UTC']);
    $this->travelTo(Carbon::parse($submitted, 'Asia/Manila'));
    $booking = datedPolicyRequest($arrival);

    $terms = app(BookingPaymentPolicy::class)->approvalData($booking);

    expect($terms['payment_policy']['deposit_eligible'])->toBe($eligible);
    expect($terms['payment_policy']['submitted_at'])->toContain('+08:00');
})->with([
    ['2026-10-31 00:01:00', '2026-11-07', false],
    ['2026-10-31 23:59:00', '2026-11-08', true],
    ['2028-02-28 23:59:00', '2028-03-07', true],
]);

test('approval does not restart the submission clock or permit extending it by override', function () {
    $booking = datedPolicyRequest('2026-10-27');
    $this->travel(1)->days();
    $policy = app(BookingPaymentPolicy::class);
    $terms = $policy->approvalData($booking);

    expect($terms['payment_deadline']->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-19 12:33:00');
    expect(fn () => $policy->approvalData($booking, '2026-10-20T12:33'))->toThrow(ValidationException::class);
});

test('an expired pending request cannot receive a fresh payment clock', function () {
    $booking = datedPolicyRequest('2026-10-27', ['created_at' => now()->subDays(3)]);
    $item = $booking->items->first();

    $this->actingAs(settingsAdmin(), 'admin')->from(route('admin.bookings.show', $booking))->post(route('admin.bookings.approve', $booking), [
        'items' => [$item->id => ['include' => 1, 'quantity' => 1]],
    ])->assertRedirect()->assertSessionHasErrors('payment_deadline_override');

    expect($booking->fresh()->status)->toBe('pending');
    expect($item->fresh()->availability_status)->toBe('pending');
    Notification::assertNothingSent();
});

test('a legacy tomorrow request shows visible approval errors then approves with a shortened full only deadline once', function () {
    $booking = datedPolicyRequest('2026-10-18');
    $item = $booking->items->first();
    $admin = settingsAdmin();
    $payload = ['items' => [$item->id => ['include' => 1, 'quantity' => 1]], 'admin_notes' => 'Keep the accepted arrival.'];

    $this->actingAs($admin, 'admin')->from(route('admin.bookings.show', $booking))->followingRedirects()
        ->post(route('admin.bookings.approve', $booking), $payload + ['payment_deadline_override' => '2026-10-18T15:00'])
        ->assertOk()->assertSee('Booking could not be approved')->assertSee('Keep the accepted arrival.');
    expect($booking->fresh()->status)->toBe('pending');
    expect($item->fresh()->availability_status)->toBe('pending');
    Notification::assertNothingSent();

    $this->post(route('admin.bookings.approve', $booking), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
    $booking->refresh();
    expect($booking->status)->toBe('approved');
    expect($booking->payment_policy)->toMatchArray(['deposit_eligible' => false, 'shortened_window' => true, 'legacy_arrival_accepted' => true, 'service_time_source' => 'hotel_check_in']);
    expect($booking->payment_deadline->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-18 13:59:00');
    expect($booking->items->first()->check_in_date->toDateString())->toBe('2026-10-18');
    $savedTerms = $booking->payment_policy;
    $this->post(route('admin.bookings.approve', $booking), $payload)->assertSessionHas('error');
    expect($booking->fresh()->payment_policy)->toBe($savedTerms);
    Notification::assertSentToTimes($booking->user, BookingApproved::class, 1);

    $this->actingAs($booking->user)->get(route('booking.pay', $booking->booking_code))
        ->assertOk()->assertSee('more than 7 days ahead')->assertDontSee('Pay 50% to reserve');
});

test('new room cart requests reject today and tomorrow but accept two days ahead', function (int $days, bool $accepted) {
    $room = RoomType::factory()->create();
    $date = now('Asia/Manila')->addDays($days);
    $response = $this->actingAs(onboardedUser())->postJson(route('cart.add'), [
        'item_type' => 'room', 'item_id' => $room->id, 'quantity' => 1, 'selected_pax' => 1,
        'check_in_date' => $date->toDateString(), 'check_out_date' => $date->addDay()->toDateString(),
    ]);
    if ($accepted) {
        $response->assertSuccessful();
        expect(CartItem::count())->toBe(1);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('check_in_date');
        expect(CartItem::count())->toBe(0);
    }
})->with([[0, false], [1, false], [2, true]]);

test('checkout rechecks a room basket that became too close overnight', function () {
    $user = onboardedUser();
    $room = RoomType::factory()->create();
    $cart = CartItem::factory()->create(['user_id' => $user->id, 'item_type' => 'room', 'item_id' => $room->id,
        'check_in_date' => '2026-10-18', 'check_out_date' => '2026-10-19', 'is_selected' => true]);

    $this->actingAs($user)->postJson(route('checkout.process'), [
        'contact_name' => 'Date Test', 'contact_email' => $user->email, 'contact_phone' => '09171234567',
    ])->assertUnprocessable();

    expect(Booking::count())->toBe(0);
    expect($cart->fresh())->not->toBeNull();
});

test('forged deposits fail for every gateway without creating provider requests', function (string $gateway) {
    config(['services.qrph.secret_key' => 'sk_test_boundary', 'services.stripe.test_secret_key' => 'sk_test_boundary']);
    $booking = datedPolicyRequest('2026-10-24');
    $booking->forceFill(['status' => 'approved', 'approved_at' => now(), ...app(BookingPaymentPolicy::class)->approvalData($booking)])->save();

    $this->actingAs($booking->user)->post(route('booking.installment.store', $booking->booking_code), ['purpose' => 'deposit', 'gateway' => $gateway])
        ->assertRedirect(route('booking.pay', $booking->booking_code))
        ->assertSessionHas('error', 'This installment is unavailable or below the minimum payment. Choose full payment.');

    expect(BookingPaymentRequest::count())->toBe(0);
    Http::assertNothingSent();
})->with(['paymongo', 'stripe', 'qrph', 'gotyme']);

test('new admin room requests enforce the prospective two day minimum', function (int $days, bool $accepted) {
    $user = onboardedUser();
    $room = RoomType::factory()->create(['total_rooms' => 5]);
    $arrival = now('Asia/Manila')->addDays($days);
    $response = $this->actingAs(settingsAdmin(), 'admin')->post(route('admin.bookings.store'), [
        'is_walk_in' => 0, 'user_id' => $user->id, 'booking_source' => 'admin_phone',
        'contact_name' => 'Date Test', 'contact_email' => $user->email, 'contact_phone' => '09171234567', 'initial_status' => 'pending',
        'items' => [['item_type' => 'room', 'item_id' => $room->id, 'quantity' => 1, 'selected_pax' => 1,
            'check_in_date' => $arrival->toDateString(), 'check_out_date' => $arrival->addDay()->toDateString()]],
    ]);
    if ($accepted) {
        $response->assertSessionHasNoErrors();
        expect(Booking::sole()->payment_policy)->toMatchArray(['request_policy_revision' => 3, 'minimum_arrival_days' => 2, 'initial_window_hours' => 48]);
    } else {
        $response->assertSessionHasErrors('items.0.check_in_date');
        expect(Booking::count())->toBe(0);
    }
})->with([[1, false], [2, true]]);

test('a valid checkout snapshots the room time and original submission window', function () {
    $user = onboardedUser();
    $room = RoomType::factory()->create(['total_rooms' => 5]);
    $room->hotel->update(['check_in_time' => '14:00:00']);
    CartItem::factory()->create(['user_id' => $user->id, 'item_type' => 'room', 'item_id' => $room->id,
        'check_in_date' => '2026-10-25', 'check_out_date' => '2026-10-26', 'is_selected' => true]);
    $this->actingAs($user)->postJson(route('checkout.process'), [
        'contact_name' => 'Date Test', 'contact_email' => $user->email, 'contact_phone' => '09171234567',
    ])->assertSuccessful();
    $booking = Booking::sole();
    expect($booking->payment_policy)->toMatchArray(['request_policy_revision' => 3, 'initial_window_hours' => 48]);
    $room->hotel->update(['check_in_time' => '18:00:00']);
    config(['system_settings.payments.initial_window_hours' => 72]);
    $terms = app(BookingPaymentPolicy::class)->approvalData($booking);
    expect($terms['payment_policy']['service_start'])->toBe('2026-10-25T14:00:00+08:00');
    expect($terms['payment_deadline']->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-19 12:33:00');
});

test('room basket date edits cannot bypass the two day minimum', function () {
    $user = onboardedUser();
    $room = RoomType::factory()->create();
    $cart = CartItem::factory()->create(['user_id' => $user->id, 'item_type' => 'room', 'item_id' => $room->id,
        'check_in_date' => '2026-10-25', 'check_out_date' => '2026-10-26']);

    $this->actingAs($user)->patchJson(route('cart.update', $cart), [
        'check_in_date' => '2026-10-18', 'check_out_date' => '2026-10-19',
    ])->assertUnprocessable()->assertJsonValidationErrors('check_in_date');
    expect($cart->fresh()->check_in_date->toDateString())->toBe('2026-10-25');
});

test('disabling deposits still caps full payment before check in', function () {
    config(['system_settings.payments.installments_enabled' => false]);
    $terms = app(BookingPaymentPolicy::class)->approvalData(datedPolicyRequest('2026-10-18'));

    expect($terms['payment_plan_version'])->toBeNull();
    expect($terms['payment_policy']['deposit_eligible'])->toBeFalse();
    expect($terms['payment_deadline']->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-18 13:59:00');
});

test('a service cutoff that has passed requires review instead of a past payment deadline', function () {
    $booking = datedPolicyRequest('2026-10-17');
    $this->travelTo(Carbon::parse('2026-10-17 15:00:00', 'Asia/Manila'));

    expect(fn () => app(BookingPaymentPolicy::class)->approvalData($booking))->toThrow(ValidationException::class, 'No valid payment window remains');
    expect($booking->fresh()->status)->toBe('pending');
});
