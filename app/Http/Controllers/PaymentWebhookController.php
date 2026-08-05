<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaid;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentWebhookController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Receive and verify payment gateway webhooks.
     */
    public function handle(string $gateway, Request $request)
    {
        $code = $this->paymentService->handleWebhook($gateway, $request);

        if (!$code) {
            return response('ignored', Response::HTTP_OK);
        }

        $booking = Booking::where('booking_code', $code)->first();

        if (!$booking || $booking->status !== Booking::STATUS_APPROVED) {
            return response('not actionable', Response::HTTP_OK);
        }

        $booking->paid_at = now();
        $booking->payment_status = Booking::PAYMENT_PAID;
        $booking->markStatus(Booking::STATUS_PAID, 'Payment confirmed via ' . $gateway . ' webhook.');

        BookingNotification::send($booking, new BookingPaid($booking));

        return response('ok', Response::HTTP_OK);
    }
}
