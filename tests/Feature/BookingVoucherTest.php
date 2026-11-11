<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Notifications\BookingPaid;
use Illuminate\Support\Carbon;

it('sends guests to sign in before they can download a voucher', function () {
    $booking = Booking::factory()->paid()->create();

    $this->get(route('booking.voucher', $booking->booking_code))->assertRedirect(route('login'));
});

it('hides another traveler’s voucher behind a 404', function () {
    $booking = Booking::factory()->paid()->create();

    $this->actingAs(onboardedUser())->get(route('booking.voucher', $booking->booking_code))->assertNotFound();
});

it('offers no voucher for a booking that has not been reviewed or no longer stands', function (string $state) {
    $user = onboardedUser();
    $factory = Booking::factory();
    $booking = ($state === 'pending' ? $factory : $factory->{$state}())->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('booking.voucher', $booking->booking_code))->assertNotFound();
    $this->actingAs($user)->get(route('booking.show', $booking->booking_code))->assertOk()->assertDontSee('Download Voucher (PDF)');
})->with(['pending', 'cancelled', 'rejected', 'expired']);

it('downloads a paid booking as a laid-out PDF voucher', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->paid()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('booking.voucher', $booking->booking_code))
        ->assertOk()->assertHeader('content-type', 'application/pdf')
        ->assertDownload('sunnytrips-'.$booking->booking_code.'-voucher.pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

it('prints a paid voucher with the reference, the service and payment breakdown and a settled balance', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->paid()->create(['user_id' => $user->id, 'total_amount' => 5200, 'promotion_discount_amount' => 500, 'net_amount' => 4700,
        'gateway_reference' => 'SIM-REF-123', 'guest_manifest' => [['full_name' => 'Ana Reyes', 'special_notes' => 'Vegetarian']]]);
    BookingItem::factory()->create(['booking_id' => $booking->id, 'item_title' => 'Garden Room', 'hotel_name' => 'Cove Resort', 'subtotal' => 4400, 'availability_status' => 'available']);
    BookingItem::factory()->create(['booking_id' => $booking->id, 'item_type' => 'activity', 'item_title' => 'Sunset Sail', 'subtotal' => 800, 'availability_status' => 'unavailable']);

    $this->actingAs($user)->get(route('booking.voucher', [$booking->booking_code, 'print' => 1]))->assertOk()
        ->assertSeeInOrder(['Travel Voucher', 'Booking reference', $booking->booking_code, 'Paid in full'])
        ->assertSee('Ana Reyes')
        ->assertSeeInOrder(['Garden Room', 'Cove Resort', '₱4,400.00', 'Sunset Sail', 'Excluded — not charged'])
        ->assertSeeInOrder(['Services subtotal', '₱5,200.00', 'Lucky discount', '−₱500.00', 'Booking total', '₱4,700.00', 'Paid so far', '₱4,700.00', 'Fully settled'])
        ->assertSee('SIM-REF-123')
        ->assertSee('No day-by-day itinerary is attached to this booking.')
        ->assertSee('Print voucher');
});

it('labels an approved but unpaid booking as a summary with the amount and deadline still owed', function () {
    $user = onboardedUser();
    $booking = Booking::factory()->approved()->create(['user_id' => $user->id, 'net_amount' => 6000, 'payment_deadline' => Carbon::parse('2026-11-20 10:00', 'Asia/Manila')]);

    $this->actingAs($user)->get(route('booking.show', $booking->booking_code))->assertOk()
        ->assertSee('Download Booking Summary (PDF)')->assertSee(route('booking.voucher', [$booking->booking_code, 'print' => 1]), false);

    $deadline = $booking->fresh()->payment_deadline->timezone('Asia/Manila');

    $this->actingAs($user)->get(route('booking.voucher', [$booking->booking_code, 'print' => 1]))->assertOk()
        ->assertSee('Booking Summary')->assertSee('Awaiting payment')->assertDontSee('Travel Voucher')
        ->assertSee('Payment due: ₱6,000.00 by '.$deadline->format('M j, Y g:i A').' (Philippine time).')
        ->assertSeeInOrder(['Remaining balance · due '.$deadline->format('M j, Y'), '₱6,000.00']);
});

it('attaches the voucher PDF to the payment confirmation email', function () {
    $booking = Booking::factory()->paid()->create();

    $mail = (new BookingPaid($booking))->toMail($booking->user);

    expect($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['name'])->toBe('sunnytrips-'.$booking->booking_code.'-voucher.pdf')
        ->and($mail->rawAttachments[0]['data'])->toStartWith('%PDF');
});
