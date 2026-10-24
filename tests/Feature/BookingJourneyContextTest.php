<?php

use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\BookingJourneyContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-13 12:00', 'Asia/Manila'));
    $this->booking = Booking::factory()->paid()->create();
});

function journeyItem(Booking $booking, array $attributes): BookingItem
{
    return BookingItem::factory()->create($attributes + ['booking_id' => $booking->id, 'availability_status' => 'available']);
}

it('keeps a Lucky group in one window and lists its undated activities there', function () {
    $room = journeyItem($this->booking, ['check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'item_snapshot' => ['lucky_group_id' => 'grp-1']]);
    $activity = journeyItem($this->booking, ['item_type' => 'activity', 'item_snapshot' => ['lucky_group_id' => 'grp-1']]);

    $context = BookingJourneyContext::for($this->booking);

    expect($context->groups)->toHaveCount(1)
        ->and($context->group('trip:'.$room->id)['undated']->modelKeys())->toBe([$activity->id])
        ->and($context->undated)->toBeEmpty()
        ->and($context->tripStart()->toDateString())->toBe('2026-10-20')
        ->and($context->tripEnd()->toDateString())->toBe('2026-10-22');
});

it('splits separate trips into windows and merges back-to-back stays', function () {
    $first = journeyItem($this->booking, ['check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22']);
    journeyItem($this->booking, ['check_in_date' => '2026-10-23', 'check_out_date' => '2026-10-24']);
    $later = journeyItem($this->booking, ['item_type' => 'activity', 'check_in_date' => '2026-11-10']);

    $context = BookingJourneyContext::for($this->booking);

    expect(array_keys($context->groups))->toBe(['trip:'.$first->id, 'trip:'.$later->id])
        ->and($context->group('trip:'.$first->id)['end']->toDateString())->toBe('2026-10-24')
        ->and($context->inferredGrouping)->toBeTrue();
});

it('keeps undated catalog services out of every window instead of blocking them', function () {
    journeyItem($this->booking, ['check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22']);
    $undated = journeyItem($this->booking, ['item_type' => 'activity']);
    journeyItem($this->booking, ['item_type' => 'activity', 'check_in_date' => '2026-10-21', 'availability_status' => 'unavailable']);

    $context = BookingJourneyContext::for($this->booking);

    expect($context->groups)->toHaveCount(1)->and($context->undated->modelKeys())->toBe([$undated->id])
        ->and($context->included)->toHaveCount(2);
});

it('treats an entirely undated booking as having no trip window', function () {
    journeyItem($this->booking, ['item_type' => 'package']);

    $context = BookingJourneyContext::for($this->booking);

    expect($context->groups)->toBe([])->and($context->tripEnd())->toBeNull()->and($context->undated)->toHaveCount(1);
});

it('applies a recorded trip extension to the final window only', function () {
    $first = journeyItem($this->booking, ['check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-21']);
    $second = journeyItem($this->booking, ['check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-03']);
    $this->booking->update(['trip_end_override' => Carbon::parse('2026-11-05 12:00', 'Asia/Manila')]);

    $context = BookingJourneyContext::for($this->booking->fresh());

    expect($context->group('trip:'.$first->id)['end']->toDateString())->toBe('2026-10-21')
        ->and($context->group('trip:'.$second->id)['end']->timezone('Asia/Manila')->format('Y-m-d H:i'))->toBe('2026-11-05 12:00');
});

it('addresses the account, an admin guest contact, or nobody', function () {
    expect(BookingJourneyContext::for($this->booking)->recipientEmail)->toBe($this->booking->user->email);

    $admin = AdminModel::create(['name' => 'Desk Admin', 'email' => 'desk@example.com', 'password' => 'password']);
    $guest = Booking::factory()->paid()->create(['user_id' => null, 'booked_by_admin_id' => $admin->id, 'booking_source' => 'admin_walk_in', 'contact_email' => 'walkin@example.com']);
    $context = BookingJourneyContext::for($guest);
    expect($context->recipientEmail)->toBe('walkin@example.com')->and($context->isGuest)->toBeTrue();

    $orphaned = Booking::factory()->paid()->create(['user_id' => null, 'booking_source' => 'customer_web']);
    expect(BookingJourneyContext::for($orphaned)->recipientEmail)->toBeNull();
});

it('reports deposit, overdue and paid states from the saved payment record', function () {
    $booking = Booking::factory()->approved()->create(['payment_plan_version' => 2, 'payment_status' => 'partial', 'deposit_secured_at' => now()]);
    expect(BookingJourneyContext::for($booking)->paymentState())->toBe('deposit')
        ->and(BookingJourneyContext::for($booking)->reservationSecured())->toBeTrue();

    $booking->update(['balance_overdue_at' => now()]);
    expect(BookingJourneyContext::for($booking->fresh())->paymentState())->toBe('overdue');

    $unpaid = Booking::factory()->approved()->create(['payment_plan_version' => 2]);
    expect(BookingJourneyContext::for($unpaid)->reservationSecured())->toBeFalse()
        ->and(BookingJourneyContext::for($this->booking)->paymentState())->toBe('paid');
});
