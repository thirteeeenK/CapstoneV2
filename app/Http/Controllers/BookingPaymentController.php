<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitGoTymePaymentRequest;
use App\Models\AdminModel;
use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Notifications\BookingCancellationRequested;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaid;
use App\Services\AdminNotificationService;
use App\Services\BookingExpiryService;
use App\Services\BookingRequestService;
use App\Services\Payment\GoTymePaymentService;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentService;
use App\Services\Payment\QrphPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BookingPaymentController extends Controller
{
    protected PaymentService $paymentService;

    protected BookingExpiryService $expiryService;

    protected BookingRequestService $bookingRequestService;

    public function __construct(
        PaymentService $paymentService,
        BookingExpiryService $expiryService,
        BookingRequestService $bookingRequestService,
        protected GoTymePaymentService $goTymePaymentService,
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
        if ($booking->payment_plan_version === 2) {
            return app(BookingInstallmentController::class)->page($bookingCode);
        }

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed by the agreed payment deadline.');
        }

        if ($booking->status !== Booking::STATUS_APPROVED) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking is not ready for payment.');
        }

        if ($this->goTymePaymentService->hasActiveTransfer($booking)) {
            return redirect()->route('booking.pay.gotyme.show', $booking->booking_code);
        }

        $gateways = $this->paymentService->availableGateways();

        return view('booking.pay', compact('booking', 'gateways'));
    }

    /**
     * Create the gateway session and redirect the user to pay.
     */
    public function pay(Request $request, $bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);
        if ($booking->payment_plan_version === 2) {
            return redirect()->route('booking.pay', $bookingCode);
        }

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed by the agreed payment deadline.');
        }

        if ($booking->status !== Booking::STATUS_APPROVED) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking is not ready for payment.');
        }

        $gateway = $request->input('gateway');

        if (! in_array($gateway, ['card', 'qrph', 'gotyme', 'paymongo'], true)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'Please select a payment method before proceeding.');
        }

        if ($gateway === 'gotyme') {
            return $this->goTymeInit($bookingCode);
        }

        if ($this->goTymePaymentService->hasActiveTransfer($booking)) {
            return redirect()->route('booking.pay.gotyme.show', $booking->booking_code)
                ->with('error', 'A GoTyme transfer is already awaiting or has received verification. Continue there to avoid splitting payment methods.');
        }

        if ($gateway === 'qrph') {
            return $this->qrphInit($bookingCode);
        }

        if ($gateway === 'paymongo') {
            return $this->paymongoInit($bookingCode);
        }

        try {
            $result = $this->paymentService->createCardPayment($booking);
        } catch (PaymentGatewayException $exception) {
            return redirect()->route('booking.show', $booking->booking_code)->with('error', $exception->getMessage());
        }

        return redirect()->away($result['url']);
    }

    /**
     * Simulator payment confirmation page.
     */
    public function simulatorShow($bookingCode)
    {
        $booking = $this->loadOwnedBooking($bookingCode);
        abort_unless(! app()->isProduction() && $booking->gateway === 'simulator' && $booking->payment_plan_version !== 2, 404);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed by the agreed payment deadline.');
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
        abort_unless(! app()->isProduction() && $booking->gateway === 'simulator' && $booking->payment_plan_version !== 2, 404);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed by the agreed payment deadline.');
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
        if ($booking->payment_plan_version === 2) {
            return redirect()->route('booking.pay', $bookingCode);
        }

        if (in_array($booking->gateway, ['qrph', 'paymongo'], true)) {
            return $this->confirmQrPayment($bookingCode, $booking->gateway);
        }

        abort_if(app()->isProduction() && $booking->gateway === 'simulator', 404);

        if ($booking->status === Booking::STATUS_APPROVED && $this->paymentService->verifyReturn($booking)) {
            $this->markAsPaid($booking);
        }

        return redirect()->route('booking.show', $booking->booking_code);
    }

    public function qrphInit(string $bookingCode): RedirectResponse
    {
        return $this->startQrPayment($bookingCode, 'qrph');
    }

    public function paymongoInit(string $bookingCode): RedirectResponse
    {
        return $this->startQrPayment($bookingCode, 'paymongo');
    }

    private function startQrPayment(string $bookingCode, string $gateway): RedirectResponse
    {
        $booking = $this->loadOwnedBooking($bookingCode);
        if ($booking->payment_plan_version === 2) {
            return redirect()->route('booking.pay', $bookingCode);
        }

        try {
            $result = QrphPaymentService::forGateway($gateway)->create($booking);

            return redirect()->to($result['url']);
        } catch (PaymentGatewayException $exception) {
            return redirect()->route('booking.show', $booking->booking_code)->with('error', $exception->getMessage());
        }
    }

    public function qrphShow(string $bookingCode): View|RedirectResponse
    {
        return $this->showQrPayment($bookingCode, 'qrph');
    }

    public function paymongoShow(string $bookingCode): View|RedirectResponse
    {
        return $this->showQrPayment($bookingCode, 'paymongo');
    }

    private function showQrPayment(string $bookingCode, string $gateway): View|RedirectResponse
    {
        $booking = $this->loadOwnedBooking($bookingCode);
        if ($booking->status !== Booking::STATUS_APPROVED) {
            return redirect()->route('booking.show', $booking->booking_code);
        }

        if ($booking->isPaymentDeadlinePassed()) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'The booking payment deadline has passed. Contact support if you already paid.');
        }

        $attempt = QrphPaymentService::forGateway($gateway)->latest($booking);
        if (! $attempt || ! $attempt->qr_image) {
            return redirect()->route('booking.pay', $booking->booking_code)
                ->with('error', 'Create a QR Ph payment from this page to continue.');
        }

        $qrExpired = $attempt->expires_at?->isPast() || in_array($attempt->status, ['expired', 'cancelled', 'failed'], true);

        return view('booking.qrph', compact('booking', 'attempt', 'qrExpired', 'gateway'));
    }

    public function qrphReturn(string $bookingCode): RedirectResponse
    {
        return $this->qrphConfirm($bookingCode);
    }

    public function qrphConfirm(string $bookingCode): RedirectResponse
    {
        return $this->confirmQrPayment($bookingCode, 'qrph');
    }

    public function paymongoReturn(string $bookingCode): RedirectResponse
    {
        return $this->confirmQrPayment($bookingCode, 'paymongo');
    }

    public function paymongoConfirm(string $bookingCode): RedirectResponse
    {
        return $this->confirmQrPayment($bookingCode, 'paymongo');
    }

    private function confirmQrPayment(string $bookingCode, string $gateway): RedirectResponse
    {
        $booking = $this->loadOwnedBooking($bookingCode);
        if ($booking->payment_status === Booking::PAYMENT_PAID) {
            return redirect()->route('booking.show', $booking->booking_code);
        }
        $service = QrphPaymentService::forGateway($gateway);
        $attempt = $service->latest($booking);
        if (! $attempt || ! $attempt->intent_id) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'There is no verifiable QR Ph payment for this booking.');
        }

        try {
            $attempt = $service->check($attempt);
        } catch (PaymentGatewayException $exception) {
            return redirect()->route('booking.pay.'.$gateway.'.show', $booking->booking_code)
                ->with('error', $exception->getMessage());
        }

        if ($attempt->status === 'paid') {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('success', $booking->payment_plan_version === 2 ? 'QR Ph payment confirmed. Your verified booking balance has been updated.' : 'QR Ph payment confirmed. Your booking is now paid.');
        }
        if ($attempt->status === 'review_required') {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'A payment was received and needs support review. Please do not pay again.');
        }

        return redirect()->route('booking.pay.'.$gateway.'.show', $booking->booking_code)
            ->with('error', match ($attempt->status) {
                'expired' => 'This QR code has expired. If you already paid, contact support before trying again.',
                'failed' => 'The payment attempt failed. Check your bank or wallet before trying again.',
                'cancelled' => 'This payment was cancelled. It has not confirmed your booking.',
                default => 'Payment is still pending. Complete payment in your banking app, then check again shortly.',
            });
    }

    public function goTymeInit(string $bookingCode): RedirectResponse
    {
        $booking = $this->loadOwnedBooking($bookingCode);
        if ($booking->payment_plan_version === 2) {
            return redirect()->route('booking.pay', $bookingCode);
        }

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed by the agreed payment deadline.');
        }

        try {
            $this->goTymePaymentService->initialize($booking);
        } catch (PaymentGatewayException $exception) {
            return redirect()->route('booking.show', $booking->booking_code)->with('error', $exception->getMessage());
        }

        return redirect()->route('booking.pay.gotyme.show', $booking->booking_code);
    }

    public function goTymeShow(string $bookingCode): RedirectResponse|View
    {
        $booking = $this->loadOwnedBooking($bookingCode);
        if ($booking->payment_plan_version === 2 && ! BookingPaymentRequest::where('booking_id', $booking->id)->where('gateway', 'gotyme')->whereIn('status', BookingPaymentRequest::ACTIVE)->exists()) {
            return redirect()->route('booking.pay', $bookingCode);
        }

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed by the agreed payment deadline.');
        }

        if ($booking->status !== Booking::STATUS_APPROVED) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking is not ready for payment.');
        }

        if ($booking->gateway !== 'gotyme') {
            try {
                $booking = $this->goTymePaymentService->initialize($booking);
            } catch (PaymentGatewayException $exception) {
                return redirect()->route('booking.show', $booking->booking_code)->with('error', $exception->getMessage());
            }
        }

        $booking->load(['payments' => fn ($query) => $query->where('provider', 'gotyme')]);

        return view('booking.gotyme', [
            'booking' => $booking,
            'paymentSummary' => $this->goTymePaymentService->summary($booking),
            'collection' => $booking->payment_plan_version === 2 ? BookingPaymentRequest::where('booking_id', $booking->id)->where('gateway', 'gotyme')->whereIn('status', BookingPaymentRequest::ACTIVE)->first() : null,
            'goTyme' => config('services.gotyme'),
        ]);
    }

    public function goTymeSubmit(SubmitGoTymePaymentRequest $request, string $bookingCode): RedirectResponse
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed by the agreed payment deadline.');
        }

        $validated = $request->validated();
        try {
            $this->goTymePaymentService->submit(
                $booking,
                $validated['sender_ref'],
                (string) $validated['claimed_amount']
            );
        } catch (PaymentGatewayException $exception) {
            return redirect()->route('booking.show', $booking->booking_code)->with('error', $exception->getMessage());
        }

        return redirect()->route('booking.pay.gotyme.show', $booking->booking_code)
            ->with('success', 'GoTyme transfer submitted — awaiting admin verification. Your balance changes only after verification.');
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
                    $admin->notify(new BookingCancellationRequested($booking->fresh(), includeMail: false));
                }
            }
        } catch (\Throwable $e) {
            // Admin notification failure should not block the request.
            Log::warning('cancellation-request admin notify dispatch failed', [
                'booking_id' => $booking->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }

        app(AdminNotificationService::class)->send('cancellation_requested', $booking->fresh());

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
        $booking = Booking::with(['items', 'user', 'history', 'payments'])
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
        abort_if($booking->payment_plan_version === 2, 409, 'Verify the collection before updating its balance.');
        if ($booking->markPaid()) {
            BookingNotification::send($booking, new BookingPaid($booking));
        }
    }
}
