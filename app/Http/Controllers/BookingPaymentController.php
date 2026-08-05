<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaid;
use App\Services\BookingExpiryService;
use App\Services\BookingRequestService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

class BookingPaymentController extends Controller
{
    protected PaymentService $paymentService;
    protected BookingExpiryService $expiryService;
    protected BookingRequestService $bookingRequestService;

    public function __construct(
        PaymentService $paymentService,
        BookingExpiryService $expiryService,
        BookingRequestService $bookingRequestService
    ) {
        $this->paymentService = $paymentService;
        $this->expiryService = $expiryService;
        $this->bookingRequestService = $bookingRequestService;
    }

    /**
     * Payment page — only reachable while the booking is approved.
     */
    public function show($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed within the 48-hour window.');
        }

        if ($booking->status !== Booking::STATUS_APPROVED) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking is not ready for payment.');
        }

        return view('booking.pay', compact('booking'));
    }

    /**
     * Create the gateway session and redirect the user to pay.
     */
    public function pay($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed within the 48-hour window.');
        }

        if ($booking->status !== Booking::STATUS_APPROVED) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking is not ready for payment.');
        }

        $result = $this->paymentService->createPayment($booking);

        return redirect()->away($result['url']);
    }

    /**
     * Simulator payment confirmation page.
     */
    public function simulatorShow($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed within the 48-hour window.');
        }

        if ($booking->status !== Booking::STATUS_APPROVED) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking is not ready for payment.');
        }

        return view('booking.simulator', compact('booking'));
    }

    /**
     * Simulator "successful payment" confirmation.
     */
    public function simulatorConfirm($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed within the 48-hour window.');
        }

        if ($booking->status !== Booking::STATUS_APPROVED) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking is not ready for payment.');
        }

        $this->markAsPaid($booking);

        return redirect()->route('booking.show', $booking->booking_code)
            ->with('success', 'Payment received! Your booking is confirmed.');
    }

    /**
     * Return URL after the gateway checkout — verifies and marks paid.
     */
    public function return($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($booking->status === Booking::STATUS_APPROVED && $this->paymentService->verifyReturn($booking)) {
            $this->markAsPaid($booking);
        }

        return redirect()->route('booking.show', $booking->booking_code);
    }

    /**
     * User-initiated cancellation while pending or approved (before payment).
     */
    public function cancel($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if (!in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_APPROVED], true)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking can no longer be cancelled.');
        }

        $booking->cancelled_at = now();
        $booking->cancellation_reason = request('reason') ?: 'Cancelled by customer before payment.';
        $booking->markStatus(Booking::STATUS_CANCELLED, $booking->cancellation_reason);

        BookingNotification::send($booking, new BookingCancelled($booking));

        return redirect()->route('booking.show', $booking->booking_code)
            ->with('success', 'Booking cancelled. You can rebook anytime.');
    }

    /**
     * Rebuild cart items from the booking for rebooking.
     */
    public function rebook($bookingCode, Request $request)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if (!in_array($booking->status, [Booking::STATUS_REJECTED, Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED], true)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'Only rejected, cancelled, or expired bookings can be rebooked.');
        }

        $count = $this->bookingRequestService->recreateCartFromBooking($booking, $request);

        return redirect()->route('cart.index')
            ->with('success', "Added {$count} item" . ($count === 1 ? '' : 's') . " back to your Trip Basket for rebooking.");
    }

    /**
     * Resolve a booking by code, restricted to its owner or an administrator.
     * 404 is returned (not 403) so booking codes cannot be probed.
     */
    protected function loadOwnedBooking(string $bookingCode): Booking
    {
        $booking = Booking::with(['items', 'user', 'history'])
            ->where('booking_code', $bookingCode)
            ->first();

        $isOwner = auth()->check() && $booking && $booking->user_id === auth()->id();
        $isAdmin = auth('admin')->check();

        if (! $booking || (! $isOwner && ! $isAdmin)) {
            abort(404);
        }

        return $booking;
    }

    /**
     * Mark a booking as paid (idempotent) and notify the customer.
     */
    protected function markAsPaid(Booking $booking): void
    {
        if ($booking->status === Booking::STATUS_PAID) {
            return;
        }

        $booking->paid_at = now();
        $booking->payment_status = Booking::PAYMENT_PAID;
        $booking->markStatus(Booking::STATUS_PAID, 'Payment completed.');

        BookingNotification::send($booking, new BookingPaid($booking));
    }
}
