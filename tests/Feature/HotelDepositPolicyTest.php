<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\Payment\BookingPaymentPolicy;
use App\Services\SystemSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 02:00:00', 'Asia/Manila'));
    config(['system_settings.payments.installments_enabled' => true]);
});

function hotelPolicyBooking(string $arrival): Booking
{
    $booking = Booking::factory()->approved()->for(onboardedUser())->create(['net_amount' => '8500.00']);
    BookingItem::factory()->create([
        'booking_id' => $booking->id, 'item_type' => 'room', 'item_title' => 'Hotel policy example',
        'check_in_date' => $arrival, 'check_out_date' => Carbon::parse($arrival)->addDays(2),
        'availability_status' => 'available',
    ]);

    return $booking;
}

test('hotel approvals offer a deposit within 48 hours and a balance two days before check in', function () {
    $booking = hotelPolicyBooking('2026-10-25');

    $booking->forceFill(app(BookingPaymentPolicy::class)->approvalData($booking))->save();

    expect($booking->payment_policy['deposit_eligible'])->toBeTrue();
    expect($booking->deposit_required_cents)->toBe(425000);
    expect($booking->payment_deadline->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-19 02:00:00');
    expect($booking->balance_due_at->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-23 09:00:00');
    $this->actingAs($booking->user)->get(route('booking.pay', $booking->booking_code))
        ->assertOk()->assertSee('Pay 50% to reserve')->assertSee('4,250.00')->assertSee('Oct 23, 2026 9:00 AM');
});

test('a hotel booking with its balance cutoff already passed requires full payment before the service cutoff', function () {
    $booking = hotelPolicyBooking('2026-10-18');

    $booking->forceFill(app(BookingPaymentPolicy::class)->approvalData($booking))->save();

    expect($booking->payment_policy['deposit_eligible'])->toBeFalse();
    expect($booking->balance_due_at)->toBeNull();
    expect($booking->payment_deadline->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-18 08:59:00');
    $this->actingAs($booking->user)->get(route('booking.pay', $booking->booking_code))
        ->assertOk()->assertSee('2-day balance rule')->assertDontSee('Pay 50% to reserve');
});

test('a near term hotel booking requires full payment even when a deposit interval could fit', function () {
    $booking = hotelPolicyBooking('2026-10-20');

    $booking->forceFill(app(BookingPaymentPolicy::class)->approvalData($booking))->save();

    expect($booking->payment_policy['deposit_eligible'])->toBeFalse();
    expect($booking->balance_due_at)->toBeNull();
    expect($booking->payment_deadline->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-19 02:00:00');
    $this->actingAs($booking->user)->get(route('booking.pay', $booking->booking_code))
        ->assertOk()->assertSee('more than 7 days ahead')->assertDontSee('Pay 50% to reserve');
});

test('shortening the initial hotel deadline cannot bypass the seven day deposit gate', function () {
    $booking = hotelPolicyBooking('2026-10-20');
    $policy = app(BookingPaymentPolicy::class);

    $terms = $policy->approvalData($booking, '2026-10-18T08:00');

    expect($terms['payment_policy']['deposit_eligible'])->toBeFalse();
    expect($terms['payment_deadline']->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-18 08:00:00');
    expect($terms['balance_due_at'])->toBeNull();
    expect(fn () => $policy->approvalData($booking, '2026-10-20T09:00'))->toThrow(ValidationException::class);
});

test('saving the shorter hotel balance rule affects new approvals while preserving saved booking terms', function () {
    $settings = app(SystemSettingsService::class);
    $settings->save('payments', array_replace($settings->section('payments'), ['balance_lead_days' => 7]), 0, settingsAdmin());
    $existing = hotelPolicyBooking('2026-11-01');
    $existing->forceFill(app(BookingPaymentPolicy::class)->approvalData($existing))->save();
    $savedPolicy = $existing->fresh()->payment_policy;
    $savedBalance = $existing->balance_due_at->toIso8601String();

    $this->actingAs(settingsAdmin(), 'admin')->patch('/admin/settings/payments', [
        'section' => 'payments', 'revision' => 1,
        'values' => ['installments_enabled' => true, 'initial_window_hours' => 48, 'balance_lead_days' => 2, 'undated_balance_days' => 7, 'service_cutoff_hours' => 24],
    ])->assertSessionHasNoErrors();

    $terms = app(BookingPaymentPolicy::class)->approvalData(hotelPolicyBooking('2026-10-25'));
    expect($terms['balance_due_at']->timezone('Asia/Manila')->toDateTimeString())->toBe('2026-10-23 09:00:00');
    expect($existing->fresh()->payment_policy)->toBe($savedPolicy);
    expect($existing->fresh()->balance_due_at->toIso8601String())->toBe($savedBalance);
});
