<?php

use App\Models\AdminModel;
use App\Models\Booking;
use App\Notifications\BookingCancellationApproved;
use App\Notifications\BookingCancellationDenied;
use App\Notifications\BookingCancellationRequested;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('requires a reason of at least 10 characters to request cancellation', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
    ]);

    $this->actingAs($user)->post(route('booking.cancel', $booking->booking_code), [
        'reason' => 'short',
    ])->assertSessionHasErrors('reason');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_PENDING);
});

it('lets a user request cancellation on a pending booking and notifies admins', function () {
    $user = onboardedUser();
    $admin = AdminModel::create([
        'name' => 'Admin Test',
        'email' => 'admin-cancel-1@test.com',
        'password' => 'password',
    ]);
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
    ]);

    $reason = 'My travel dates changed and I need to cancel this booking.';

    $this->actingAs($user)->post(route('booking.cancel', $booking->booking_code), [
        'reason' => $reason,
    ])->assertRedirect(route('booking.show', $booking->booking_code))
        ->assertSessionHas('success');

    $booking->refresh();

    expect($booking->status)->toBe(Booking::STATUS_CANCELLATION_REQUESTED)
        ->and($booking->cancellation_request_reason)->toBe($reason)
        ->and($booking->cancellation_requested_from)->toBe(Booking::STATUS_PENDING)
        ->and($booking->cancellation_requested_at)->not->toBeNull()
        ->and($booking->isHoldStatus())->toBeTrue();

    expect($booking->history()->where('to_status', Booking::STATUS_CANCELLATION_REQUESTED)->exists())->toBeTrue();

    Notification::assertSentTo($admin, BookingCancellationRequested::class);
});

it('lets a user request cancellation on an approved booking', function () {
    $user = onboardedUser();
    AdminModel::create([
        'name' => 'Admin Test 2',
        'email' => 'admin-cancel-2@test.com',
        'password' => 'password',
    ]);
    $booking = Booking::factory()->approved()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->post(route('booking.cancel', $booking->booking_code), [
        'reason' => 'Change of plans requires cancellation for this approved booking.',
    ])->assertRedirect();

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLATION_REQUESTED)
        ->and($booking->fresh()->cancellation_requested_from)->toBe(Booking::STATUS_APPROVED);
});

it('prevents cancellation requests on paid bookings', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->paid()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->post(route('booking.cancel', $booking->booking_code), [
        'reason' => 'I want to cancel even though it is already paid, should be blocked.',
    ])->assertRedirect()
        ->assertSessionHas('error');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_PAID);
});

it('lets an admin approve a cancellation request and notifies the user', function () {
    $user = onboardedUser();
    $admin = AdminModel::create([
        'name' => 'Admin Approve',
        'email' => 'admin-approve@test.com',
        'password' => 'password',
    ]);
    $booking = Booking::factory()->cancellationRequested(Booking::STATUS_PENDING)->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($admin, 'admin')->post(route('admin.bookings.cancel-request.approve', $booking->id), [
        'admin_message' => 'Approved — you may rebook anytime.',
    ])->assertRedirect(route('admin.bookings.show', $booking->id))
        ->assertSessionHas('success');

    $booking->refresh();

    expect($booking->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($booking->cancellation_reason)->toBe('Approved — you may rebook anytime.')
        ->and($booking->isHoldStatus())->toBeFalse();

    Notification::assertSentTo($user, BookingCancellationApproved::class);
});

it('lets an admin deny a cancellation request keeping inventory held', function () {
    $user = onboardedUser();
    $admin = AdminModel::create([
        'name' => 'Admin Deny',
        'email' => 'admin-deny@test.com',
        'password' => 'password',
    ]);
    $booking = Booking::factory()->cancellationRequested()->create([
        'user_id' => $user->id,
    ]);

    $denyMessage = 'Your booking is within the non-cancellable window. Please contact support for alternatives.';

    $this->actingAs($admin, 'admin')->post(route('admin.bookings.cancel-request.deny', $booking->id), [
        'admin_message' => $denyMessage,
    ])->assertRedirect(route('admin.bookings.show', $booking->id))
        ->assertSessionHas('success');

    $booking->refresh();

    expect($booking->status)->toBe(Booking::STATUS_CANCELLATION_DENIED)
        ->and($booking->cancellation_reason)->toBe($denyMessage)
        ->and($booking->isHoldStatus())->toBeTrue();

    Notification::assertSentTo($user, BookingCancellationDenied::class);
});

it('requires an admin message when denying a cancellation', function () {
    $admin = AdminModel::create([
        'name' => 'Admin Deny 2',
        'email' => 'admin-deny2@test.com',
        'password' => 'password',
    ]);
    $booking = Booking::factory()->cancellationRequested()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.bookings.cancel-request.deny', $booking->id), [
        'admin_message' => 'short',
    ])->assertSessionHasErrors('admin_message');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLATION_REQUESTED);
});

it('lets a user withdraw a cancellation request', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->cancellationRequested(Booking::STATUS_APPROVED)->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->post(route('booking.cancel.withdraw', $booking->booking_code))
        ->assertRedirect(route('booking.show', $booking->booking_code))
        ->assertSessionHas('success');

    $booking->refresh();

    expect($booking->status)->toBe(Booking::STATUS_APPROVED);
});

it('shows cancellation request on the booking show page for the owner', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->cancellationRequested()->create([
        'user_id' => $user->id,
        'cancellation_request_reason' => 'Need to cancel due to personal emergency.',
    ]);

    $this->actingAs($user)->get(route('booking.show', $booking->booking_code))
        ->assertOk()
        ->assertSee('Cancellation under review', false)
        ->assertSee('Need to cancel due to personal emergency.', false)
        ->assertSee('Withdraw Request', false);
});

it('shows denied message on the booking show page', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->cancellationDenied()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->get(route('booking.show', $booking->booking_code))
        ->assertOk()
        ->assertSee('Cancellation not approved', false)
        ->assertSee('Denied by admin', false);
});
