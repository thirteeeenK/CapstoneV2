<?php

namespace App\Services\Payment\Drivers;

use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Services\Payment\PaymentEnvironmentResolver;
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
        $resolver = app(PaymentEnvironmentResolver::class);
        $this->secretKey = (string) $resolver->stripeConfiguration('secret_key');
        $this->webhookSecret = $resolver->stripeConfiguration('webhook_secret');
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function createPayment(Booking $booking, ?BookingPaymentRequest $collection = null): array
    {
        app(PaymentEnvironmentResolver::class)->assertBooking($booking);
        if (! $this->secretKey) {
            throw new PaymentGatewayException('Configure a Stripe key matching this deployment’s payment mode.');
        }
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(6)
            ->withHeaders(['Idempotency-Key' => $collection ? 'collection-'.$collection->uuid : 'checkout-'.$booking->booking_code.'-'.$booking->net_amount])
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $booking->booking_code,
                'success_url' => $collection ? route('booking.installment.show', [$booking->booking_code, $collection->uuid]) : route('booking.pay.return', $booking->booking_code),
                'cancel_url' => route('booking.show', $booking->booking_code),
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => 'php',
                'line_items[0][price_data][unit_amount]' => $collection?->amount ?? (int) round((float) $booking->net_amount * 100),
                'line_items[0][price_data][product_data][name]' => 'SunnyTrips Booking '.$booking->booking_code,
                'metadata[booking_code]' => $booking->booking_code,
                'metadata[payment_request_uuid]' => $collection?->uuid ?? '',
            ]);

        if ($response->failed()) {
            Log::error('Stripe checkout session creation failed', [
                'booking' => $booking->booking_code,
                'http_status' => $response->status(),
            ]);
            throw new PaymentGatewayException('Card checkout could not be started. Please try again shortly or choose another method.');
        }

        $session = $response->json();
        if ($collection && (parse_url((string) ($session['url'] ?? ''), PHP_URL_SCHEME) !== 'https'
            || parse_url((string) ($session['url'] ?? ''), PHP_URL_HOST) !== 'checkout.stripe.com'
            || ! preg_match('/^cs_[A-Za-z0-9_]+$/D', $session['id'] ?? ''))) {
            throw new PaymentGatewayException('Stripe returned an invalid checkout session. Contact support before retrying.');
        }

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
        if (($session['payment_status'] ?? null) !== 'paid'
            || ($session['livemode'] ?? null) !== app(PaymentEnvironmentResolver::class)->isLive()) {
            return false;
        }

        $eventData = [
            'livemode' => $session['livemode'] ?? null,
            'payment_request_uuid' => $session['metadata']['payment_request_uuid'] ?? null,
            'payment_id' => $session['payment_intent'] ?? null,
            'paid_at' => $event['created'] ?? null,
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
        if (! $this->secretKey) {
            return false;
        }
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(6)
            ->get('https://api.stripe.com/v1/checkout/sessions/'.$reference);

        if ($response->failed()) {
            Log::warning('Stripe session retrieval failed', ['reference' => $reference, 'http_status' => $response->status()]);

            return false;
        }

        return $response->json('payment_status') === 'paid'
            && $response->json('livemode') === app(PaymentEnvironmentResolver::class)->isLive();
    }

    /** @return array<string, mixed> */
    public function retrieveSession(string $reference): array
    {
        if (! $this->secretKey) {
            throw new PaymentGatewayException('Configure a Stripe key matching this deployment’s payment mode.');
        }
        if (! preg_match('/^cs_[A-Za-z0-9_]+$/D', $reference)) {
            throw new PaymentGatewayException('The Stripe session reference is invalid.');
        }
        $response = Http::withBasicAuth($this->secretKey, '')->connectTimeout(3)->timeout(8)
            ->get('https://api.stripe.com/v1/checkout/sessions/'.$reference);
        if (! $response->successful()) {
            throw new PaymentGatewayException('Stripe could not verify this payment. Please check again shortly.');
        }

        return $response->json();
    }

    public function expireSession(string $reference, string $idempotencyKey): void
    {
        if (! $this->secretKey) {
            throw new PaymentGatewayException('Configure a Stripe key matching this deployment’s payment mode.');
        }
        if (! preg_match('/^cs_[A-Za-z0-9_]+$/D', $reference)) {
            throw new PaymentGatewayException('The Stripe session reference is invalid.');
        }
        $response = Http::withBasicAuth($this->secretKey, '')->connectTimeout(3)->timeout(8)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('https://api.stripe.com/v1/checkout/sessions/'.$reference.'/expire');
        if (! $response->successful()) {
            throw new PaymentGatewayException('The existing Stripe session could not be closed safely.');
        }
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
