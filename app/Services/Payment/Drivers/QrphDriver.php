<?php

namespace App\Services\Payment\Drivers;

use App\Models\Booking;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QrphDriver implements PaymentDriver
{
    protected string $provider;

    protected ?string $secretKey;

    protected ?string $webhookSecret;

    protected string $merchantName;

    protected string $merchantAccount;

    protected string $city;

    public function __construct()
    {
        $config = config('services.qrph', []);

        $this->provider = strtolower((string) ($config['provider'] ?? 'paymongo'));
        $this->secretKey = $config['secret_key'] ?: null;
        $this->webhookSecret = $config['webhook_secret'] ?: null;
        $this->merchantName = (string) ($config['merchant_name'] ?? 'SunnyTrips');
        $this->merchantAccount = (string) ($config['merchant_account'] ?? '');
        $this->city = (string) ($config['city'] ?? 'Manila');
    }

    public function name(): string
    {
        return 'qrph';
    }

    /**
     * Build the scannable QR content for a booking.
     *
     * Live mode (PayMongo QR Ph): the stored QR image (data URI or hosted URL).
     * Demo mode: an EMVCo-style QRPH string with the actual amount embedded.
     */
    public function qrContent(Booking $booking): string
    {
        // Keep the stored value stable once a QRPH session exists (image or EMVCo).
        if ($booking->gateway === 'qrph' && $booking->payment_url) {
            return $booking->payment_url;
        }

        if ($this->isPaymongoConfigured() && $booking->payment_url) {
            if (str_starts_with($booking->payment_url, 'data:image') || str_starts_with($booking->payment_url, 'https://')) {
                return $booking->payment_url;
            }

            return $booking->payment_url;
        }

        if ($booking->payment_url && $booking->gateway === 'qrph') {
            return $booking->payment_url;
        }

        return $this->buildDemoQrphString($booking);
    }

    public function createPayment(Booking $booking): array
    {
        if ($this->isPaymongoConfigured()) {
            try {
                return $this->createPaymongoQrph($booking);
            } catch (ConnectionException $e) {
                Log::warning('QRPH PayMongo unreachable — falling back to demo QR', [
                    'booking' => $booking->booking_code,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('QRPH PayMongo error — falling back to demo QR', [
                    'booking' => $booking->booking_code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->createDemoPayment($booking);
    }

    public function verifyWebhook(string $rawPayload, Request $request, array &$eventData): bool
    {
        if (! $this->webhookSecret) {
            return false;
        }

        $header = $request->header('Paymongo-Signature', '') ?: $request->header('paymongo-signature', '');
        if (! $this->verifyPaymongoSignature($rawPayload, $header)) {
            Log::warning('PayMongo webhook signature verification failed.');

            return false;
        }

        $event = json_decode($rawPayload, true);
        if (! is_array($event)) {
            return false;
        }

        $type = $event['type'] ?? ($event['data']['attributes']['type'] ?? null);

        // PayMongo sends `payment.paid` for GCash, and `source.chargeable` after redirect.
        $isPaid = $type === 'payment.paid';
        $isChargeable = $type === 'source.chargeable';

        if (! $isPaid && ! $isChargeable) {
            // Fall back: some payloads nest type inside data.attributes
            $attrType = $event['data']['attributes']['type'] ?? null;
            $status = $event['data']['attributes']['status'] ?? null;
            if ($attrType === 'gcash' && $status === 'chargeable') {
                $isChargeable = true;
            } elseif ($status === 'paid' && $attrType === 'gcash') {
                $isPaid = true;
            } else {
                return false;
            }
        }

        $data = $event['data'] ?? [];
        $attributes = $data['attributes'] ?? $event['data']['attributes'] ?? [];

        // Prefer payment resource
        $paymentStatus = $attributes['status'] ?? null;
        if ($isPaid && $paymentStatus !== 'paid') {
            return false;
        }

        // Extract booking code from metadata
        $bookingCode = $attributes['metadata']['booking_code']
            ?? $data['attributes']['metadata']['booking_code']
            ?? $event['metadata']['booking_code']
            ?? null;

        // For source.chargeable, metadata is on the source itself
        if (! $bookingCode) {
            $bookingCode = $attributes['metadata']['booking_code'] ?? null;
        }

        $reference = $attributes['source']['id'] ?? $attributes['id'] ?? $data['id'] ?? null;
        $eventId = $event['id'] ?? $event['data']['id'] ?? null;

        $eventData = [
            'event_id' => $eventId,
            'reference' => $reference,
            'booking_code' => $bookingCode,
        ];

        return $bookingCode !== null;
    }

    public function verifyPaymentByReference(string $reference): bool
    {
        // Demo references are always considered verified on manual confirm
        if (str_starts_with($reference, 'QRPH-') || str_starts_with($reference, 'DEMO-')) {
            return true;
        }

        if (! $this->isPaymongoConfigured()) {
            return true;
        }

        // QR Ph PaymentIntent — the live reference is pi_...
        if (str_starts_with($reference, 'pi_')) {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(6)
                ->get('https://api.paymongo.com/v1/payment_intents/'.$reference);

            if ($response->successful()) {
                $status = $response->json('data.attributes.status');
                if (in_array($status, ['succeeded', 'paid'], true)) {
                    return true;
                }
            }

            Log::warning('PayMongo verification could not confirm payment', [
                'reference' => $reference,
                'status' => $response->json('data.attributes.status'),
            ]);

            return false;
        }

        // Generic: try PaymentIntent first (covers any pi_ that slipped through)
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(6)
            ->get('https://api.paymongo.com/v1/payment_intents/'.$reference);

        if ($response->successful()) {
            $status = $response->json('data.attributes.status');
            if (in_array($status, ['succeeded', 'paid'], true)) {
                return true;
            }
        }

        // Fall back: it may be a Payment id (pay_...)
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(6)
            ->get('https://api.paymongo.com/v1/payments/'.$reference);

        if ($response->successful()) {
            return $response->json('data.attributes.status') === 'paid';
        }

        // Legacy GCash source (src_...) — keep for old bookings
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(6)
            ->get('https://api.paymongo.com/v1/sources/'.$reference);

        if ($response->successful()) {
            $status = $response->json('data.attributes.status');
            if (in_array($status, ['chargeable', 'paid'], true)) {
                return true;
            }
        }

        Log::warning('PayMongo verification could not confirm payment', [
            'reference' => $reference,
            'status' => $response->json('data.attributes.status'),
        ]);

        return false;
    }

    protected function createPaymongoQrph(Booking $booking): array
    {
        if (! $this->secretKey) {
            throw new \RuntimeException('PayMongo secret not configured.');
        }

        $amountCentavos = (int) round((float) $booking->net_amount * 100);
        if ($amountCentavos < 100) {
            $amountCentavos = 100;
        }

        // 1) Create PaymentIntent with qrph allowed
        $intentPayload = [
            'data' => [
                'attributes' => [
                    'amount' => $amountCentavos,
                    'currency' => 'PHP',
                    'payment_method_allowed' => ['qrph'],
                    'capture_type' => 'automatic',
                    'description' => 'SunnyTrips '.$booking->booking_code,
                    'statement_descriptor' => 'SunnyTrips',
                    'metadata' => [
                        'booking_code' => $booking->booking_code,
                    ],
                ],
            ],
        ];

        $intentResponse = Http::withBasicAuth($this->secretKey, '')
            ->timeout(10)
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/payment_intents', $intentPayload);

        if ($intentResponse->failed()) {
            Log::error('PayMongo PaymentIntent creation failed', [
                'booking' => $booking->booking_code,
                'response' => $intentResponse->body(),
            ]);

            throw new \RuntimeException('Could not create PayMongo QR Ph intent: '.$intentResponse->json('errors.0.detail', $intentResponse->body()));
        }

        $intentData = $intentResponse->json('data');
        $piId = $intentData['id'] ?? null;
        $clientKey = $intentData['attributes']['client_key'] ?? null;

        if (! $piId || ! $clientKey) {
            throw new \RuntimeException('PayMongo QR Ph intent missing id/client_key.');
        }

        // 2) Create QRPh payment method (server-side with secret key)
        $methodPayload = [
            'data' => [
                'attributes' => [
                    'type' => 'qrph',
                    'billing' => [
                        'name' => $booking->contact_name ?: ($booking->user->name ?? 'Guest'),
                        'email' => $booking->contact_email ?: ($booking->user->email ?? null),
                        'phone' => $booking->contact_phone ?? null,
                    ],
                ],
            ],
        ];

        $methodResponse = Http::withBasicAuth($this->secretKey, '')
            ->timeout(10)
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/payment_methods', $methodPayload);

        if ($methodResponse->failed()) {
            Log::error('PayMongo payment_method creation failed', [
                'booking' => $booking->booking_code,
                'pi' => $piId,
                'response' => $methodResponse->body(),
            ]);

            throw new \RuntimeException('Could not create PayMongo QR Ph method: '.$methodResponse->json('errors.0.detail', $methodResponse->body()));
        }

        $pmId = $methodResponse->json('data.id');

        if (! $pmId) {
            throw new \RuntimeException('PayMongo QR Ph method missing id.');
        }

        // 3) Attach — PayMongo returns the scannable QR image (base64 data URI)
        $attachPayload = [
            'data' => [
                'attributes' => [
                    'payment_method' => $pmId,
                    'client_key' => $clientKey,
                ],
            ],
        ];

        $attachResponse = Http::withBasicAuth($this->secretKey, '')
            ->timeout(10)
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/payment_intents/'.$piId.'/attach', $attachPayload);

        if ($attachResponse->failed()) {
            Log::error('PayMongo PaymentIntent attach failed', [
                'booking' => $booking->booking_code,
                'pi' => $piId,
                'pm' => $pmId,
                'response' => $attachResponse->body(),
            ]);

            throw new \RuntimeException('Could not attach PayMongo QR Ph method: '.$attachResponse->json('errors.0.detail', $attachResponse->body()));
        }

        $attachData = $attachResponse->json('data.attributes');
        $nextAction = $attachData['next_action'] ?? [];
        // PayMongo nests the scannable image under next_action.code.image_url
        // (code is an object with id/amount/test_url/image_url). Keep broad fallbacks.
        $imageUrl = $nextAction['code']['image_url']
            ?? $nextAction['data']['image_url']
            ?? $nextAction['image_url']
            ?? $nextAction['code']['image']
            ?? $attachResponse->json('data.attributes.next_action.code.image_url')
            ?? $attachResponse->json('data.attributes.next_action.data.image_url')
            ?? $attachResponse->json('data.attributes.next_action.image_url');
        $testUrl = $nextAction['code']['test_url']
            ?? $nextAction['code']['testUrl']
            ?? $attachResponse->json('data.attributes.next_action.code.test_url');

        if (! $imageUrl) {
            Log::error('PayMongo QR Ph attach returned no image', [
                'booking' => $booking->booking_code,
                'pi' => $piId,
                'response' => $attachResponse->body(),
            ]);

            throw new \RuntimeException('PayMongo QR Ph did not return a scannable image.');
        }

        return [
            'url' => route('booking.pay.qrph.show', $booking->booking_code),
            'reference' => $piId,
            'qr_string' => $imageUrl,
            'test_url' => $testUrl,
        ];
    }

    protected function createDemoPayment(Booking $booking): array
    {
        $qrString = $this->buildDemoQrphString($booking);

        return [
            'url' => route('booking.pay.qrph.show', $booking->booking_code),
            'reference' => 'QRPH-'.strtoupper(Str::random(8)),
            'qr_string' => $qrString,
        ];
    }

    /**
     * Build an EMVCo-style demo QRPH string with the actual amount embedded.
     */
    protected function buildDemoQrphString(Booking $booking): string
    {
        $amount = number_format((float) $booking->net_amount, 2, '.', '');
        $merchantName = Str::limit($this->merchantName, 25, '');
        $city = Str::limit($this->city, 15, '');
        $account = $this->merchantAccount ?: $booking->booking_code;

        $payload = '';
        $payload .= $this->emvField(0, '01');
        $payload .= $this->emvField(1, '12');

        // Merchant Account Info (26) — PH.QRPH GUI + account
        $sub = $this->emvField(0, 'PH.QRPH');
        $sub .= $this->emvField(1, $account);
        $payload .= $this->emvField(26, $sub);

        $payload .= $this->emvField(52, '0000');
        $payload .= $this->emvField(53, '608');
        $payload .= $this->emvField(54, $amount);
        $payload .= $this->emvField(58, 'PH');
        $payload .= $this->emvField(59, $merchantName);
        $payload .= $this->emvField(60, $city);

        // Additional data — booking code
        $payload .= $this->emvField(62, $this->emvField(1, $booking->booking_code));

        $payload .= '6304';
        $crc = $this->crc16($payload);

        return $payload.$crc;
    }

    protected function emvField(int $id, string $value): string
    {
        return sprintf('%02d%02d%s', $id, strlen($value), $value);
    }

    /**
     * CRC16 CCITT-FALSE (poly 0x1021, init 0xFFFF).
     */
    protected function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    protected function verifyPaymongoSignature(string $rawPayload, string $header): bool
    {
        if (! $header || ! $this->webhookSecret) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            $seg = explode('=', trim($pair), 2);
            if (count($seg) === 2) {
                $parts[trim($seg[0])] = trim($seg[1], '"\' ');
            }
        }

        // PayMongo uses t=<timestamp>, te=<expiry>, li=<livemode>, sig|v1|s=<hmac>
        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? $parts['sig'] ?? $parts['s'] ?? $parts['v1_signature'] ?? null;

        if (! $timestamp || ! $signature) {
            // Try alternative header format: "Paymongo-Signature: sig=...,t=..."
            foreach ($parts as $k => $v) {
                if (in_array(strtolower($k), ['signature', 'sig', 'v1'], true)) {
                    $signature = $v;
                    break;
                }
            }
            if (! $signature) {
                return false;
            }
        }

        if (abs((int) $timestamp - time()) > 600) {
            // Allow 10 min drift for webhook retries
            return false;
        }

        $signedPayload = $timestamp.'.'.$rawPayload;
        $expected = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    protected function isPaymongoConfigured(): bool
    {
        return ! empty($this->secretKey);
    }
}
