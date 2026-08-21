<?php

namespace App\Services\Payment;

use App\Models\Booking;
use App\Services\Payment\Drivers\PaymentDriver;
use App\Services\Payment\Drivers\QrphDriver;
use App\Services\Payment\Drivers\SimulatorDriver;
use App\Services\Payment\Drivers\StripeDriver;
use Illuminate\Http\Client\ConnectionException;
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
            return new StripeDriver;
        }

        if ($provider === 'qrph' && $this->qrphConfigured()) {
            return new QrphDriver;
        }

        if ($provider === 'simulator') {
            return new SimulatorDriver;
        }

        if ($this->stripeConfigured()) {
            return new StripeDriver;
        }

        if ($this->qrphConfigured()) {
            return new QrphDriver;
        }

        return new SimulatorDriver;
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

        try {
            $result = $driver->createPayment($booking);
        } catch (ConnectionException $e) {
            Log::warning('Payment gateway unreachable — falling back to simulator', [
                'booking' => $booking->booking_code,
                'gateway' => $driver->name(),
                'error' => $e->getMessage(),
            ]);

            $driver = new SimulatorDriver;
            $result = $driver->createPayment($booking);
        }

        $booking->gateway = $driver->name();
        $booking->gateway_reference = $result['reference'];

        if ($driver instanceof QrphDriver && isset($result['qr_string'])) {
            $booking->payment_url = $result['qr_string'];

            Log::info('Payment session created', [
                'booking' => $booking->booking_code,
                'gateway' => 'qrph',
                'reference' => $result['reference'],
            ]);
            $booking->save();

            return [
                'url' => $result['url'],
                'reference' => $result['reference'],
                'gateway' => 'qrph',
                'qr_string' => $result['qr_string'],
            ];
        }

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
     * Create a QRPH payment session, always using the QRPH driver.
     *
     * @return array{url: string, reference: string, gateway: string, qr_string?: string}
     */
    public function createQrphPayment(Booking $booking): array
    {
        // Reuse existing QRPH session if one already exists
        if (
            $booking->status === Booking::STATUS_APPROVED
            && $booking->gateway === 'qrph'
            && $booking->gateway_reference
            && $booking->payment_url
        ) {
            $driver = new QrphDriver;

            return [
                'url' => route('booking.pay.qrph.show', $booking->booking_code),
                'reference' => $booking->gateway_reference,
                'gateway' => 'qrph',
                'qr_string' => $driver->qrContent($booking),
                'test_url' => $booking->gateway_data['test_url'] ?? null,
            ];
        }

        $driver = new QrphDriver;

        try {
            $result = $driver->createPayment($booking);
        } catch (ConnectionException $e) {
            Log::warning('QRPH PayMongo unreachable — falling back to demo QR', [
                'booking' => $booking->booking_code,
                'error' => $e->getMessage(),
            ]);
            $result = (new QrphDriver)->createPayment($booking);
        }

        $qrString = $result['qr_string'] ?? $result['url'];

        // Live QR Ph: payment_url holds the base64 QR image (TEXT); demo: EMVCo string.
        $booking->payment_url = $qrString;
        $booking->gateway = 'qrph';
        $booking->gateway_reference = $result['reference'];
        if (isset($result['test_url'])) {
            $booking->gateway_data = array_filter(['test_url' => $result['test_url']]);
        }
        $booking->save();

        $isLive = str_starts_with($result['reference'] ?? '', 'pi_') && str_starts_with($qrString, 'data:image');

        Log::info('QRPH payment session created', [
            'booking' => $booking->booking_code,
            'reference' => $result['reference'],
            'mode' => $isLive ? 'paymongo_qrph' : 'demo',
        ]);

        return [
            'url' => $result['url'],
            'reference' => $result['reference'],
            'gateway' => 'qrph',
            'qr_string' => $qrString,
            'test_url' => $result['test_url'] ?? null,
        ];
    }

    /**
     * Handle an inbound webhook for a gateway.
     *
     * @return array{booking_code?: string, reference?: string, event_id?: string}|null
     *                                                                                  verified event data, null when unverified or not actionable
     */
    public function handleWebhook(string $gateway, Request $request): ?array
    {
        $driver = $this->resolveDriver($gateway);
        if (! $driver) {
            Log::warning('Unknown payment gateway webhook', ['gateway' => $gateway]);

            return null;
        }

        $eventData = [];
        if (! $driver->verifyWebhook($request->getContent(), $request, $eventData)) {
            return null;
        }

        return $eventData;
    }

    /**
     * Verify payment status after the user returns from the gateway.
     */
    public function verifyReturn(Booking $booking): bool
    {
        if (! $booking->gateway_reference) {
            return false;
        }

        $driver = $this->resolveDriver($booking->gateway);
        if (! $driver) {
            return false;
        }

        return $driver->verifyPaymentByReference($booking->gateway_reference);
    }

    protected function resolveDriver(?string $gateway): ?PaymentDriver
    {
        if ($gateway === 'stripe' && $this->stripeConfigured()) {
            return new StripeDriver;
        }

        if ($gateway === 'qrph') {
            return new QrphDriver;
        }

        if ($gateway === 'simulator') {
            return new SimulatorDriver;
        }

        return null;
    }

    protected function stripeConfigured(): bool
    {
        return ! empty(config('services.stripe.secret_key'));
    }

    protected function qrphConfigured(): bool
    {
        return ! empty(config('services.qrph.secret_key'));
    }

    /**
     * Payment methods the user may choose from on the booking page.
     *
     * @return array<string, bool>
     */
    public function availableGateways(): array
    {
        return [
            'card' => $this->stripeConfigured(),
            'qrph' => true, // demo QR works without PayMongo keys; real QR when configured
        ];
    }
}
