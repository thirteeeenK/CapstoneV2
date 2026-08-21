<?php

namespace App\Http\Controllers;

use App\Models\AdminModel;
use App\Models\Booking;
use App\Notifications\BookingCancellationRequested;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaid;
use App\Services\BookingExpiryService;
use App\Services\BookingRequestService;
use App\Services\Payment\Drivers\QrphDriver;
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
    public function pay(Request $request, $bookingCode)
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

        $gateway = $request->input('gateway');

        if (! in_array($gateway, ['card', 'qrph'], true)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'Please select a payment method before proceeding.');
        }

        if ($gateway === 'qrph') {
            $this->paymentService->createQrphPayment($booking);

            return redirect()->route('booking.pay.qrph.show', $booking->booking_code);
        }

        // Card chosen — drop any stale QRPH session so the selected method wins.
        if ($booking->gateway === 'qrph') {
            $booking->gateway = null;
            $booking->gateway_reference = null;
            $booking->payment_url = null;
            $booking->save();
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

    // ───────────────────────── QRPH (GCash / GoTyme) ─────────────────────────

    /**
     * Start a QRPH payment session (PayMongo or demo fallback).
     */
    public function qrphInit($bookingCode)
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

        $this->paymentService->createQrphPayment($booking);

        return redirect()->route('booking.pay.qrph.show', $booking->booking_code);
    }

    /**
     * Show the QRPH QR (real PayMongo QR Ph image or demo EMVCo string).
     */
    public function qrphShow($bookingCode)
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

        // Ensure a QRPH session exists (idempotent)
        if ($booking->gateway !== 'qrph' || ! $booking->gateway_reference || ! $booking->payment_url) {
            $this->paymentService->createQrphPayment($booking->fresh());
            $booking->refresh();
        }

        $qrContent = app(QrphDriver::class)->qrContent($booking);
        // Live QR Ph bookings have a pi_ reference and a base64 image
        $isDemo = str_starts_with($booking->gateway_reference ?? '', 'QRPH-')
            || str_starts_with($booking->gateway_reference ?? '', 'DEMO-')
            || (! str_starts_with($qrContent, 'data:image') && ! str_starts_with($qrContent, 'https://'));
        // If the reference is pi_ we are definitely live even if the heuristic above misfires
        if (str_starts_with($booking->gateway_reference ?? '', 'pi_')) {
            $isDemo = false;
        }
        $qrIsImage = str_starts_with($qrContent, 'data:image') || str_starts_with($qrContent, 'https://');
        $qrTestUrl = $booking->gateway_data['test_url'] ?? null;

        return view('booking.qrph', compact('booking', 'qrContent', 'isDemo', 'qrIsImage', 'qrTestUrl'));
    }

    /**
     * Return URL after PayMongo (kept for legacy / direct-link safety).
     * Polls the PaymentIntent — only marks paid when PayMongo confirms.
     */
    public function qrphReturn($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($booking->status === Booking::STATUS_APPROVED) {
            if ($this->paymentService->verifyReturn($booking)) {
                $booking->payment_method = 'qrph';
                $booking->save();
                $this->markAsPaid($booking);

                return redirect()->route('booking.show', $booking->booking_code)
                    ->with('success', 'Payment received via QRPH! Your booking is confirmed.');
            }

            // Not yet paid — keep the QR on screen so the user can scan and retry
            return redirect()->route('booking.pay.qrph.show', $booking->booking_code)
                ->with('error', 'Payment not yet confirmed — scan the QR in your GoTyme / GCash / Maya app, complete the payment, then tap "I\'ve completed the payment" to confirm.');
        }

        return redirect()->route('booking.show', $booking->booking_code);
    }

    /**
     * Poll-on-button confirm: verifies with PayMongo for live (pi_) bookings,
     * instantly confirms demo (QRPH-*) bookings. Chosen per user request.
     */
    public function qrphConfirm($bookingCode)
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

        // Demo refs are instantly confirmable (no PayMongo key / offline)
        if (str_starts_with($booking->gateway_reference ?? '', 'QRPH-') || str_starts_with($booking->gateway_reference ?? '', 'DEMO-')) {
            $booking->payment_method = 'qrph';
            $booking->save();
            $this->markAsPaid($booking);

            return redirect()->route('booking.show', $booking->booking_code)
                ->with('success', 'Payment received via QRPH! Your booking is confirmed.');
        }

        // Live pi_ — poll PayMongo; only mark paid when PayMongo says succeeded
        if (! $this->paymentService->verifyReturn($booking)) {
            return redirect()->route('booking.pay.qrph.show', $booking->booking_code)
                ->with('error', 'Payment not yet confirmed — please scan the QR in your GoTyme / GCash / Maya app, complete the payment, then try again. If your wallet had insufficient balance, the transaction would have been declined.');
        }

        $booking->payment_method = 'qrph';
        $booking->save();
        $this->markAsPaid($booking);

        return redirect()->route('booking.show', $booking->booking_code)
            ->with('success', 'Payment received via QRPH! Your booking is confirmed.');
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
