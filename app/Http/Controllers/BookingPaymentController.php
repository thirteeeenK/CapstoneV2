<?php

namespace App\Http\Controllers;

use App\Models\AdminModel;
use App\Models\Booking;
use App\Notifications\BookingCancellationRequested;
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
     * User requests cancellation (pending/approved → cancellation_requested).
     * Reason is required.
     */
    public function cancel(Request $request, $bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:2000',
        ]);

        if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_APPROVED], true)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking can no longer be cancelled.');
        }

        $reason = trim($validated['reason']);
        $from = $booking->status;

        $updated = $booking->transitionTo(
            Booking::STATUS_CANCELLATION_REQUESTED,
            [Booking::STATUS_PENDING, Booking::STATUS_APPROVED],
            [
                'cancellation_request_reason' => $reason,
                'cancellation_requested_at' => now(),
                'cancellation_requested_from' => $from,
            ],
            'Cancellation requested: '.$reason
        );

        if (! $updated) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking can no longer be cancelled.');
        }

        // Notify admins (DB + mail via Notifiable)
        try {
            $admins = AdminModel::all();
            if ($admins->isNotEmpty()) {
                foreach ($admins as $admin) {
                    $admin->notify(new BookingCancellationRequested($booking->fresh()));
                }
            }
        } catch (\Throwable $e) {
            // Admin notification failure should not block the request.
        }

        return redirect()->route('booking.show', $booking->booking_code)
            ->with('success', 'Cancellation requested. Our team will review your request and reply with a decision.');
    }

    /**
     * Withdraw a pending cancellation request (cancellation_requested → prior status).
     */
    public function withdrawCancellation($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($booking->status !== Booking::STATUS_CANCELLATION_REQUESTED) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'No cancellation request to withdraw.');
        }

        $restoreTo = $booking->cancellation_requested_from ?: Booking::STATUS_PENDING;
        if (! in_array($restoreTo, [Booking::STATUS_PENDING, Booking::STATUS_APPROVED], true)) {
            $restoreTo = Booking::STATUS_PENDING;
        }

        $updated = $booking->transitionTo(
            $restoreTo,
            [Booking::STATUS_CANCELLATION_REQUESTED],
            [],
            'Cancellation request withdrawn by customer.'
        );

        if (! $updated) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'Could not withdraw the request.');
        }

        return redirect()->route('booking.show', $booking->booking_code)
            ->with('success', 'Cancellation request withdrawn. Your booking is active again.');
    }

    /**
     * Rebuild cart items from the booking for rebooking.
     */
    public function rebook($bookingCode, Request $request)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if (! in_array($booking->status, [Booking::STATUS_REJECTED, Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED], true)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'Only rejected, cancelled, or expired bookings can be rebooked.');
        }

        $count = $this->bookingRequestService->recreateCartFromBooking($booking, $request);

        return redirect()->route('cart.index')
            ->with('success', "Added {$count} item".($count === 1 ? '' : 's').' back to your Trip Basket for rebooking.');
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
        if ($booking->markPaid()) {
            BookingNotification::send($booking, new BookingPaid($booking));
        }
    }
}
