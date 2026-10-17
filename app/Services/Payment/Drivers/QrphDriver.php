<?php

declare(strict_types=1);

namespace App\Services\Payment\Drivers;

use App\Models\Booking;
use App\Models\QrphPaymentAttempt;
use App\Services\Payment\PaymentEnvironmentResolver;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\QrphPaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QrphDriver implements PaymentDriver
{
    public function name(): string
    {
        return 'qrph';
    }

    public function configured(): bool
    {
        $mode = $this->livemode() ? 'live' : 'test';

        return str_starts_with((string) $this->configuration('secret_key'), 'sk_'.$mode.'_');
    }

    public function livemode(): bool
    {
        return app(PaymentEnvironmentResolver::class)->gatewayIsLive($this->name());
    }

    protected function configuration(string $key): mixed
    {
        return app(PaymentEnvironmentResolver::class)->qrConfiguration($this->name(), $key);
    }

    public function qrContent(Booking $booking): string
    {
        return (string) $booking->payment_url;
    }

    public function createPayment(Booking $booking): array
    {
        return QrphPaymentService::forGateway($this->name())->create($booking);
    }

    /** @return array<string, mixed> */
    public function createIntent(QrphPaymentAttempt $attempt, Booking $booking): array
    {
        return $this->request('POST', '/payment_intents', [
            'amount' => $attempt->amount,
            'currency' => 'PHP',
            'payment_method_allowed' => ['qrph'],
            'capture_type' => 'automatic',
            'description' => 'SunnyTrips '.$booking->booking_code,
            'metadata' => array_filter(['booking_code' => $booking->booking_code, 'attempt_id' => $attempt->uuid,
                'payment_request_id' => $attempt->booking_payment_request_id ? (string) $attempt->booking_payment_request_id : null]),
        ], $attempt->uuid.'-intent');
    }

    /** @return array<string, mixed> */
    public function createMethod(QrphPaymentAttempt $attempt): array
    {
        return $this->request('POST', '/payment_methods', [
            'type' => 'qrph',
            'expiry_seconds' => $this->configuration('expiry_seconds') ?? 1800,
        ], $attempt->uuid.'-method-'.$attempt->method_generation);
    }

    /** @return array<string, mixed> */
    public function attach(QrphPaymentAttempt $attempt, string $clientKey): array
    {
        return $this->request('POST', '/payment_intents/'.$attempt->intent_id.'/attach', [
            'payment_method' => $attempt->method_id,
            'client_key' => $clientKey,
        ], $attempt->uuid.'-attach-'.$attempt->method_generation);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $reference): array
    {
        if (! preg_match('/^pi_[A-Za-z0-9]+$/D', $reference)) {
            throw new PaymentGatewayException('This QR Ph payment reference needs support review.');
        }

        return $this->request('GET', '/payment_intents/'.$reference);
    }

    public function cancel(string $reference, string $idempotencyKey): void
    {
        if (! preg_match('/^pi_[A-Za-z0-9]+$/D', $reference)) {
            throw new PaymentGatewayException('The payment intent reference is invalid.');
        }
        $this->request('POST', '/payment_intents/'.$reference.'/cancel', [], $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $attributes = [], ?string $idempotencyKey = null): array
    {
        if (! $this->configured()) {
            throw new PaymentGatewayException('QR Ph payments are temporarily unavailable. Please contact support.');
        }

        $client = Http::withBasicAuth($this->configuration('secret_key'), '')
            ->acceptJson()->asJson()->connectTimeout(3)->timeout(10);
        if ($idempotencyKey) {
            $client = $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = $method === 'GET'
                ? $client->get('https://api.paymongo.com/v1'.$path)
                : $client->post('https://api.paymongo.com/v1'.$path, ['data' => ['attributes' => $attributes]]);
        } catch (ConnectionException) {
            throw new PaymentGatewayException('The payment provider could not be reached. Please check again shortly; your booking is still unpaid.');
        }

        if (! $response->successful() || ! is_array($response->json('data'))) {
            Log::warning('PayMongo request unsuccessful.', ['operation' => $method.' '.$path, 'http_status' => $response->status()]);
            throw new PaymentGatewayException('The payment provider could not complete this request. Please try again shortly or contact support.');
        }

        return $response->json('data');
    }

    public function verifyPaymentByReference(string $reference): bool
    {
        $attempt = QrphPaymentAttempt::where('intent_id', $reference)->first();

        return $attempt !== null && $attempt->livemode === $this->livemode()
            && QrphPaymentService::forGateway($this->name())->check($attempt)->status === 'paid';
    }

    public function verifyWebhook(string $rawPayload, Request $request, array &$eventData): bool
    {
        $secret = $this->configuration('webhook_secret');
        $parts = [];
        foreach (explode(',', (string) $request->header('Paymongo-Signature')) as $pair) {
            $values = explode('=', trim($pair), 2);
            if (count($values) === 2) {
                $parts[$values[0]] = $values[1];
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts[$this->livemode() ? 'li' : 'te'] ?? '';
        if (! $secret || ! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300
            || ! preg_match('/^[a-f0-9]{64}$/D', $signature)
            || ! hash_equals(hash_hmac('sha256', $timestamp.'.'.$rawPayload, $secret), $signature)) {
            return false;
        }

        $event = json_decode($rawPayload, true);
        $attributes = $event['data']['attributes'] ?? [];
        $resource = $attributes['data'] ?? [];
        $type = $attributes['type'] ?? '';
        if (($attributes['livemode'] ?? null) !== $this->livemode()
            || ! is_string($event['data']['id'] ?? null)
            || ! str_starts_with($event['data']['id'], 'evt_')
            || ! in_array($type, ['payment.paid', 'payment.failed', 'qrph.expired'], true)
            || ! is_array($resource)) {
            return false;
        }

        $eventData = ['event_id' => $event['data']['id'], 'type' => $type, 'resource' => $resource];

        return true;
    }
}
