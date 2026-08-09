<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentWebhookEvent;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaid;
use App\Services\Payment\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
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
     *
     * Every verified event is recorded in the payment_webhook_events ledger
     * before any state change, so Stripe replaying a delivery (or the gateway
     * firing the same event twice) is a no-op. State changes themselves go
     * through Booking::markPaid(), whose atomic approved→paid transition makes
     * the handler safe against double delivery even without the ledger.
     */
    public function handle(string $gateway, Request $request)
    {
        $event = $this->paymentService->handleWebhook($gateway, $request);

        if (!$event) {
            return response('ignored', Response::HTTP_OK);
        }

        $eventId = $event['event_id'] ?? null;
        $bookingCode = $event['booking_code'] ?? $event['reference'] ?? null;

        if (!$bookingCode) {
            return response('not actionable', Response::HTTP_OK);
        }

        if ($eventId) {
            try {
                PaymentWebhookEvent::create([
                    'gateway' => $gateway,
                    'event_id' => $eventId,
                    'booking_code' => $bookingCode,
                    'processed_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return response('already processed', Response::HTTP_OK);
            }
        }

        $booking = Booking::where('booking_code', $bookingCode)->first();

        if (!$booking) {
            return response('not actionable', Response::HTTP_OK);
        }

        if ($booking->markPaid('Payment confirmed via ' . $gateway . ' webhook.')) {
            BookingNotification::send($booking, new BookingPaid($booking));
        }

        return response('ok', Response::HTTP_OK);
    }
}