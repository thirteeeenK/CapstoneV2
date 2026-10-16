<?php

namespace App\Services\Payment\Drivers;

use App\Models\Booking;
use App\Services\Payment\PaymentGatewayException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeDriver implements PaymentDriver
{
    protected string $secretKey;

    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = (string) config('services.stripe.secret_key');
        $this->webhookSecret = config('services.stripe.webhook_secret') ?: null;
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function createPayment(Booking $booking): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(6)
            ->withHeaders(['Idempotency-Key' => 'checkout-'.$booking->booking_code.'-'.$booking->net_amount])
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $booking->booking_code,
                'success_url' => route('booking.pay.return', $booking->booking_code),
                'cancel_url' => route('booking.show', $booking->booking_code),
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => 'php',
                'line_items[0][price_data][unit_amount]' => (int) round((float) $booking->net_amount * 100),
                'line_items[0][price_data][product_data][name]' => 'SunnyTrips Booking '.$booking->booking_code,
                'metadata[booking_code]' => $booking->booking_code,
            ]);

        if ($response->failed()) {
            Log::error('Stripe checkout session creation failed', [
                'booking' => $booking->booking_code,
                'http_status' => $response->status(),
            ]);
            throw new PaymentGatewayException('Card checkout could not be started. Please try again shortly or choose another method.');
        }

        $session = $response->json();

        return [
            'url' => $session['url'] ?? throw new PaymentGatewayException('Card checkout is temporarily unavailable.'),
            'reference' => $session['id'] ?? throw new PaymentGatewayException('Card checkout is temporarily unavailable.'),
        ];
    }

    public function verifyWebhook(string $rawPayload, Request $request, array &$eventData): bool
    {
        if (! $this->webhookSecret) {
            return false;
        }

        $header = $request->header('Stripe-Signature', '');
        if (! $this->verifySignature($rawPayload, $header)) {
            Log::warning('Stripe webhook signature verification failed.');

            return false;
        }

        $event = json_decode($rawPayload, true);
        if (! is_array($event) || ($event['type'] ?? null) !== 'checkout.session.completed') {
            return false;
        }

        $session = $event['data']['object'] ?? [];
        if (($session['payment_status'] ?? null) !== 'paid') {
            return false;
        }

        $eventData = [
            'event_id' => $event['id'] ?? null,
            'reference' => $session['id'] ?? null,
            'booking_code' => $session['client_reference_id'] ?? ($session['metadata']['booking_code'] ?? null),
            'amount' => $session['amount_total'] ?? null,
            'currency' => isset($session['currency']) ? strtoupper((string) $session['currency']) : null,
        ];

        return true;
    }

    public function verifyPaymentByReference(string $reference): bool
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(6)
            ->get('https://api.stripe.com/v1/checkout/sessions/'.$reference);

        if ($response->failed()) {
            Log::warning('Stripe session retrieval failed', ['reference' => $reference, 'http_status' => $response->status()]);

            return false;
        }

        return $response->json('payment_status') === 'paid';
    }

    /**
     * Verify the Stripe-Signature header (t=...,v1=...) with HMAC-SHA256.
     */
    protected function verifySignature(string $rawPayload, string $header): bool
    {
        if (! $header) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            $seg = explode('=', $pair, 2);
            if (count($seg) === 2) {
                $parts[$seg[0]] = $seg[1];
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;

        if (! $timestamp || ! $signature) {
            return false;
        }

        if (abs((int) $timestamp - time()) > 300) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$rawPayload;
        $expected = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }
}
