<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingInstallmentRequest;
use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Models\QrphPaymentAttempt;
use App\Services\Payment\BookingPaymentAccountingService;
use App\Services\Payment\InstallmentPaymentService;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingInstallmentController extends Controller
{
    public function __construct(private readonly InstallmentPaymentService $payments, private readonly BookingPaymentAccountingService $accounting) {}

    private function booking(string $code): Booking
    {
        $booking = Booking::where('booking_code', $code)->first();
        abort_unless($booking && ((auth()->check() && $booking->user_id === auth()->id()) || auth('admin')->check()), 404);
        abort_unless($booking->payment_plan_version === 2, 404);

        return $booking;
    }

    public function page(string $code): View
    {
        $booking = $this->booking($code);
        $active = BookingPaymentRequest::where('booking_id', $booking->id)->whereIn('status', BookingPaymentRequest::ACTIVE)->first();

        return view('booking.installment-pay', [
            'booking' => $booking, 'active' => $active, 'summary' => $this->accounting->summary($booking),
            'gateways' => app(PaymentService::class)->availableGateways(),
        ]);
    }

    public function store(StoreBookingInstallmentRequest $request, string $code): RedirectResponse
    {
        $booking = $this->booking($code);
        try {
            $collection = $this->payments->prepare($booking, $request->validated('purpose'), $request->validated('gateway'));
            $result = $this->payments->start($collection);

            return redirect()->away($result['url']);
        } catch (PaymentGatewayException|ConnectionException $exception) {
            return redirect()->route('booking.pay', $code)->with('error', $exception instanceof PaymentGatewayException ? $exception->getMessage() : 'The payment provider could not be reached. Resume your payment to check its status before trying another method.');
        }
    }

    public function show(string $code, string $uuid): View|RedirectResponse
    {
        $booking = $this->booking($code);
        $collection = BookingPaymentRequest::where('booking_id', $booking->id)->where('uuid', $uuid)->firstOrFail();
        if (in_array($collection->status, ['succeeded', 'review_required'], true)) {
            return redirect()->route('booking.show', $code);
        }
        if ($collection->gateway === 'gotyme') {
            return redirect()->route('booking.pay.gotyme.show', $code);
        }
        $attempt = QrphPaymentAttempt::where('booking_payment_request_id', $collection->id)->latest('id')->first();

        return view('booking.installment-status', compact('booking', 'collection', 'attempt'));
    }

    public function confirm(string $code, string $uuid): RedirectResponse
    {
        $booking = $this->booking($code);
        $collection = BookingPaymentRequest::where('booking_id', $booking->id)->where('uuid', $uuid)->firstOrFail();
        try {
            $this->payments->check($collection);
            $collection->refresh();
            if (in_array($collection->status, ['succeeded', 'review_required'], true)) {
                return redirect()->route('booking.show', $code)->with(
                    $collection->status === 'succeeded' ? 'success' : 'error',
                    $collection->status === 'succeeded' ? 'Payment verified. Your payment balance has been updated.' : 'Payment received and awaiting support review. Please do not pay again.'
                );
            }
        } catch (PaymentGatewayException|ConnectionException) {
            return back()->with('error', 'Payment could not be verified right now. Please check again shortly; do not pay twice.');
        }

        return back()->with('error', 'Payment has not been confirmed yet.');
    }

    public function cancel(string $code, string $uuid): RedirectResponse
    {
        $booking = $this->booking($code);
        $collection = BookingPaymentRequest::where('booking_id', $booking->id)->where('uuid', $uuid)->firstOrFail();
        try {
            $this->payments->closeUnpaid($collection);
        } catch (PaymentGatewayException|ConnectionException) {
            return back()->with('error', 'The payment outcome is not yet certain. Contact support before changing the payment method.');
        }

        return redirect()->route('booking.pay', $code)->with('success', 'The existing payment has been checked. You can choose another method only after it is safely closed.');
    }

    public function resume(string $code, string $uuid): RedirectResponse
    {
        $booking = $this->booking($code);
        $collection = BookingPaymentRequest::where('booking_id', $booking->id)->where('uuid', $uuid)->firstOrFail();
        try {
            if (! in_array($collection->status, ['creating', 'pending'], true)) {
                throw new PaymentGatewayException('This collection cannot be resumed. Contact support if it needs review.');
            }
            $result = $this->payments->start($collection);

            return redirect()->away($result['url']);
        } catch (PaymentGatewayException|ConnectionException) {
            return back()->with('error', 'The existing payment cannot be resumed right now. Contact support before paying again.');
        }
    }
}
