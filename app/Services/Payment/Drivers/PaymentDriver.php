<?php

namespace App\Services\Payment\Drivers;

use App\Models\Booking;
use Illuminate\Http\Request;

interface PaymentDriver
{
    /**
     * Driver name ('stripe', 'simulator').
     */
    public function name(): string;

    /**
     * Create a payment session for the booking.
     *
     * @return array{url: string, reference: string}
     */
    public function createPayment(Booking $booking): array;

    /**
     * Verify an incoming webhook payload; if it confirms a payment, return the
     * booking code it belongs to (or the gateway reference in 'reference' key of $eventData).
     */
    public function verifyWebhook(string $rawPayload, Request $request, array &$eventData): bool;

    /**
     * Verify payment status by gateway reference (used on redirect-back without webhooks).
     */
    public function verifyPaymentByReference(string $reference): bool;
}
