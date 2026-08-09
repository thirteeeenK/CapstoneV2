<?php

namespace App\Services\Payment;

use App\Models\Booking;
use App\Services\Payment\Drivers\PaymentDriver;
use App\Services\Payment\Drivers\SimulatorDriver;
use App\Services\Payment\Drivers\StripeDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Resolve the active driver.
     *
     * Order: explicit PAYMENT_PROVIDER (when supported) → stripe (when keys
     * present) → simulator fallback so the demo can never be blocked.
     */
    public function driver(): PaymentDriver
    {
        $provider = strtolower((string) config('services.payment.provider', 'auto'));

        if ($provider === 'stripe' && $this->stripeConfigured()) {
            return new StripeDriver();
        }

        if ($provider === 'simulator') {
            return new SimulatorDriver();
        }

        if ($this->stripeConfigured()) {
            return new StripeDriver();
        }

        return new SimulatorDriver();
    }

    /**
     * Create the payment session for a booking and persist gateway metadata.
     * When an approved booking already has a session (e.g. the user double-clicked
     * Pay, or the Stripe webhook beat the redirect), the existing session is
     * returned instead of minting a duplicate.
     *
     * @return array{url: string, reference: string, gateway: string}
     */
    public function createPayment(Booking $booking): array
    {
        if (
            $booking->status === Booking::STATUS_APPROVED
            && $booking->gateway_reference
            && $booking->payment_url
            && ($driver = $this->resolveDriver($booking->gateway))
        ) {
            Log::info('Reusing existing payment session', [
                'booking' => $booking->booking_code,
                'gateway' => $driver->name(),
                'reference' => $booking->gateway_reference,
            ]);

            return [
                'url' => $booking->payment_url,
                'reference' => $booking->gateway_reference,
                'gateway' => $driver->name(),
            ];
        }

        $driver = $this->driver();
        $result = $driver->createPayment($booking);

        $booking->gateway = $driver->name();
        $booking->gateway_reference = $result['reference'];
        $booking->payment_url = $result['url'];
        $booking->save();

        Log::info('Payment session created', [
            'booking' => $booking->booking_code,
            'gateway' => $driver->name(),
            'reference' => $result['reference'],
        ]);

        return [
            'url' => $result['url'],
            'reference' => $result['reference'],
            'gateway' => $driver->name(),
        ];
    }

    /**
     * Handle an inbound webhook for a gateway.
     *
     * @return array{booking_code?: string, reference?: string, event_id?: string}|null
     *         verified event data, null when unverified or not actionable
     */
    public function handleWebhook(string $gateway, Request $request): ?array
    {
        $driver = $this->resolveDriver($gateway);
        if (!$driver) {
            Log::warning('Unknown payment gateway webhook', ['gateway' => $gateway]);
            return null;
        }

        $eventData = [];
        if (!$driver->verifyWebhook($request->getContent(), $request, $eventData)) {
            return null;
        }

        return $eventData;
    }

    /**
     * Verify payment status after the user returns from the gateway.
     */
    public function verifyReturn(Booking $booking): bool
    {
        if (!$booking->gateway_reference) {
            return false;
        }

        $driver = $this->resolveDriver($booking->gateway);
        if (!$driver) {
            return false;
        }

        return $driver->verifyPaymentByReference($booking->gateway_reference);
    }

    protected function resolveDriver(?string $gateway): ?PaymentDriver
    {
        if ($gateway === 'stripe' && $this->stripeConfigured()) {
            return new StripeDriver();
        }

        if ($gateway === 'simulator') {
            return new SimulatorDriver();
        }

        return null;
    }

    protected function stripeConfigured(): bool
    {
        return !empty(config('services.stripe.secret_key'));
    }
}
