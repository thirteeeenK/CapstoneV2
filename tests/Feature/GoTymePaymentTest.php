<?php

declare(strict_types=1);

use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Notifications\BookingPaid;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    config()->set('services.gotyme', [
        'qr_image' => 'images/qr_gotyme.jpg',
        'account_name' => 'SunnyTrips Test Account',
        'account_number' => '09170000000',
    ]);

    $this->user = onboardedUser();
    $this->admin = AdminModel::create([
        'name' => 'Payment Verifier',
        'email' => 'payments@sunnytrips.test',
        'password' => 'password',
        'two_factor_required' => false,
    ]);
});

it('shows the personal GoTyme option and configured QR details', function (): void {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '9991.00',
    ]);

    $this->actingAs($this->user)
        ->get(route('booking.pay', $booking->booking_code))
        ->assertOk()
        ->assertSee('Pay to GoTyme QR');

    $this->actingAs($this->user)
        ->post(route('booking.pay.gotyme', $booking->booking_code))
        ->assertRedirect(route('booking.pay.gotyme.show', $booking->booking_code));

    $this->actingAs($this->user)
        ->get(route('booking.pay.gotyme.show', $booking->booking_code))
        ->assertOk()
        ->assertSee('SunnyTrips Test Account')
        ->assertSee('09170000000')
        ->assertSee('images/qr_gotyme.jpg', false)
        ->assertSee('₱9,991.00', false);
});

it('stores a submitted transfer without marking the booking paid', function (): void {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '9991.00',
    ]);

    $this->actingAs($this->user)
        ->post(route('booking.pay.gotyme.submit', $booking->booking_code), [
            'sender_ref' => 'GT-1234-5678',
            'claimed_amount' => '5000.00',
        ])
        ->assertRedirect(route('booking.pay.gotyme.show', $booking->booking_code))
        ->assertSessionHas('success');

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_APPROVED)
        ->and($booking->payment_status)->toBe(Booking::PAYMENT_UNPAID)
        ->and($booking->payment_method)->toBe('gotyme')
        ->and($booking->payment_reference)->toBe('GT-1234-5678');

    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'provider' => 'gotyme',
        'sender_reference' => 'GT-1234-5678',
        'normalized_reference' => 'GT12345678',
        'claimed_amount' => '5000.00',
        'status' => BookingPayment::STATUS_SUBMITTED,
        'verified_amount' => null,
    ]);
});

it('rejects an empty or duplicate GoTyme reference', function (): void {
    $booking = Booking::factory()->approved()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->from(route('booking.pay.gotyme.show', $booking->booking_code))
        ->post(route('booking.pay.gotyme.submit', $booking->booking_code), [
            'sender_ref' => '',
            'claimed_amount' => '100.00',
        ])
        ->assertRedirect(route('booking.pay.gotyme.show', $booking->booking_code))
        ->assertSessionHasErrors('sender_ref');

    $this->actingAs($this->user)->post(route('booking.pay.gotyme.submit', $booking->booking_code), [
        'sender_ref' => 'GT-ONE-REFERENCE',
        'claimed_amount' => '100.00',
    ]);

    $this->actingAs($this->user)
        ->from(route('booking.pay.gotyme.show', $booking->booking_code))
        ->post(route('booking.pay.gotyme.submit', $booking->booking_code), [
            'sender_ref' => 'gt one reference',
            'claimed_amount' => '100.00',
        ])
        ->assertRedirect(route('booking.pay.gotyme.show', $booking->booking_code))
        ->assertSessionHasErrors('sender_ref');

    expect($booking->payments()->count())->toBe(1);
});

it('uses admin verified amounts for partial balance and completes only when fully covered', function (): void {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '9991.00',
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.gotyme.submit', $booking->booking_code), [
        'sender_ref' => 'GT-PARTIAL-ONE',
        'claimed_amount' => '6000.00',
    ]);
    $firstPayment = $booking->payments()->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.mark-paid', $booking->id), [
            'booking_payment_id' => $firstPayment->id,
            'verified_amount' => '5000.00',
            'admin_note' => 'Matched against GoTyme transaction history.',
        ])
        ->assertRedirect(route('admin.bookings.show', $booking->id))
        ->assertSessionHas('success', 'Transfer verified. Remaining balance: ₱4,991.00.');

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_APPROVED)
        ->and($booking->payment_status)->toBe(Booking::PAYMENT_PARTIAL)
        ->and($firstPayment->fresh()->verified_amount)->toBe('5000.00');
    Notification::assertNothingSent();

    $this->actingAs($this->user)->post(route('booking.pay.gotyme.submit', $booking->booking_code), [
        'sender_ref' => 'GT-PARTIAL-TWO',
        'claimed_amount' => '4991.00',
    ]);
    $secondPayment = $booking->payments()->where('sender_reference', 'GT-PARTIAL-TWO')->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.mark-paid', $booking->id), [
            'booking_payment_id' => $secondPayment->id,
            'verified_amount' => '4991.00',
        ])
        ->assertRedirect(route('admin.bookings.show', $booking->id))
        ->assertSessionHas('success', "Booking {$booking->booking_code} is fully paid via verified GoTyme transfer(s).");

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_PAID)
        ->and($booking->payment_status)->toBe(Booking::PAYMENT_PAID)
        ->and($booking->payment_method)->toBe('gotyme')
        ->and($booking->paid_at)->not->toBeNull();

    Notification::assertSentTo($this->user, BookingPaid::class);
});

it('does not let a customer submit a transfer for another users booking', function (): void {
    $booking = Booking::factory()->approved()->create([
        'user_id' => onboardedUser()->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('booking.pay.gotyme.submit', $booking->booking_code), [
            'sender_ref' => 'GT-NOT-MINE',
            'claimed_amount' => '100.00',
        ])
        ->assertNotFound();

    expect($booking->payments()->count())->toBe(0);
});

it('flags an admin verified overpayment while completing the booking', function (): void {
    $booking = Booking::factory()->approved()->create([
        'user_id' => $this->user->id,
        'net_amount' => '1000.00',
    ]);

    $this->actingAs($this->user)->post(route('booking.pay.gotyme.submit', $booking->booking_code), [
        'sender_ref' => 'GT-OVERPAYMENT',
        'claimed_amount' => '1100.00',
    ]);
    $payment = $booking->payments()->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.bookings.mark-paid', $booking->id), [
            'booking_payment_id' => $payment->id,
            'verified_amount' => '1100.00',
        ])
        ->assertRedirect(route('admin.bookings.show', $booking->id))
        ->assertSessionHas('success', "Booking {$booking->booking_code} is fully paid via verified GoTyme transfer(s). Review the ₱100.00 overpayment.");

    expect($booking->fresh()->status)->toBe(Booking::STATUS_PAID);
});
