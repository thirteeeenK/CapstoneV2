<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Models\PaymentWebhookEvent;
use App\Models\QrphPaymentAttempt;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaid;
use App\Services\Payment\Drivers\PaymongoDriver;
use App\Services\Payment\Drivers\QrphDriver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class QrphPaymentService
{
    private ?BookingPaymentRequest $collection = null;

    public function __construct(private readonly QrphDriver $driver) {}

    public static function forGateway(string $gateway): self
    {
        return match ($gateway) {
            'qrph' => new self(new QrphDriver),
            'paymongo' => new self(new PaymongoDriver),
            default => throw new PaymentGatewayException('Unknown QR Ph payment gateway.'),
        };
    }

    private function showRoute(): string
    {
        return 'booking.pay.'.$this->driver->name().'.show';
    }

    /** @return array{url: string, reference: string, gateway: string, qr_string: string} */
    public function create(Booking $booking, ?BookingPaymentRequest $collection = null): array
    {
        $this->collection = $collection;

        return $this->locked($booking->id, function () use ($booking): array {
            $booking->refresh();
            $this->ensurePayable($booking);
            app(PaymentService::class)->assertCanSelectGateway($booking, $this->driver->name());
            if (! $this->driver->configured()) {
                throw new PaymentGatewayException('QR Ph payments are temporarily unavailable. Please contact support.');
            }

            $attempt = $this->latest($booking);
            if ($attempt && $attempt->livemode !== $this->driver->livemode()) {
                throw new PaymentGatewayException('This booking has a payment from another environment. Please contact support before retrying.');
            }
            if ($attempt?->status === 'review_required') {
                throw new PaymentGatewayException('A payment for this booking needs support review. Please do not pay again.');
            }

            if ($attempt?->intent_id) {
                $intent = $this->driver->retrieve($attempt->intent_id);
                $this->validateIntent($attempt, $intent, $booking);
                $this->applyIntent($attempt, $intent);
                $attempt->refresh();
                $booking->refresh();
                if ($attempt->status === 'paid') {
                    return $this->result($attempt, $booking);
                }
                $this->ensurePayable($booking);
                if ($attempt->status === 'pending' && $attempt->qr_image) {
                    return $this->result($attempt, $booking);
                }
            }

            if (! $attempt || $attempt->status === 'cancelled') {
                $attempt = DB::transaction(function () use ($booking): QrphPaymentAttempt {
                    $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                    $this->ensurePayable($booking);
                    app(PaymentService::class)->assertCanSelectGateway($booking, $this->driver->name());
                    if (! $this->collection && ! $this->latest($booking) && $booking->gateway === $this->driver->name()
                        && str_starts_with((string) $booking->gateway_reference, 'pi_')) {
                        throw new PaymentGatewayException('Your existing payment needs support review before a new QR can be created.');
                    }

                    return QrphPaymentAttempt::create([
                        'uuid' => (string) Str::uuid(), 'booking_id' => $booking->id,
                        'booking_payment_request_id' => $this->collection?->id,
                        'amount' => $this->amount($booking), 'currency' => 'PHP',
                        'livemode' => $this->driver->livemode(), 'status' => 'creating',
                    ]);
                });
            }

            if ($attempt->amount !== $this->amount($booking)) {
                throw new PaymentGatewayException('The booking total changed. Please contact support before paying.');
            }

            $intent = isset($intent) && $intent['id'] === $attempt->intent_id
                ? $intent : $this->driver->createIntent($attempt, $booking);
            $this->validateIntent($attempt, $intent, $booking);
            $attempt->intent_id = $intent['id'];
            $attempt->save();

            if (in_array($attempt->status, ['expired', 'failed'], true)) {
                if (($intent['attributes']['status'] ?? '') !== 'awaiting_payment_method') {
                    throw new PaymentGatewayException('The previous QR is still being processed or awaiting expiry confirmation. Check its status again shortly before retrying.');
                }
                // Renew the QR on the same intent so only one payment can succeed.
                $attempt->fill([
                    'method_id' => null, 'method_generation' => $attempt->method_generation + 1,
                    'qr_code_id' => null, 'qr_image' => null, 'test_url' => null,
                    'expires_at' => null, 'status' => 'creating',
                ])->save();
            }

            if (($intent['attributes']['status'] ?? '') === 'succeeded') {
                $this->applyIntent($attempt, $intent);

                return $this->result($attempt, $booking);
            }

            if (! $attempt->method_id) {
                if (! $attempt->expires_at) {
                    $attempt->expires_at = now()->addSeconds(config('services.qrph.expiry_seconds', 1800));
                    $attempt->save();
                }
                $method = $this->driver->createMethod($attempt);
                if (! preg_match('/^pm_[A-Za-z0-9]+$/D', $method['id'] ?? '') || ($method['attributes']['type'] ?? '') !== 'qrph') {
                    throw new PaymentGatewayException('The payment provider returned an invalid QR Ph method. Please contact support.');
                }
                $attempt->method_id = $method['id'];
                $attempt->save();
            }

            // A timed-out attachment may already have succeeded; reuse the retrieved QR.
            if (($intent['attributes']['status'] ?? '') !== 'awaiting_next_action') {
                $clientKey = $intent['attributes']['client_key'] ?? null;
                if (! is_string($clientKey) || $clientKey === '') {
                    throw new PaymentGatewayException('The payment provider could not prepare this QR. Please try again shortly.');
                }
                $intent = $this->driver->attach($attempt, $clientKey);
            }

            $this->validateIntent($attempt, $intent, $booking);
            $code = $intent['attributes']['next_action']['code'] ?? [];
            $image = $code['image_url'] ?? '';
            if (($intent['attributes']['status'] ?? '') === 'succeeded') {
                $this->applyIntent($attempt, $intent);

                return $this->result($attempt, $booking);
            }
            if (($intent['attributes']['status'] ?? '') !== 'awaiting_next_action'
                || ! is_string($image) || ! preg_match('#^data:image/(png|jpeg);base64,[A-Za-z0-9+/=\r\n]+$#D', $image)) {
                throw new PaymentGatewayException('The payment provider did not return a usable QR code. Please try again shortly.');
            }

            DB::transaction(function () use ($attempt, $booking, $code, $image): void {
                $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                $attempt = QrphPaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
                if (in_array($attempt->status, ['paid', 'review_required'], true)) {
                    return;
                }
                $attempt->fill([
                    'qr_code_id' => $code['id'] ?? null, 'qr_image' => $image,
                    'test_url' => ! $attempt->livemode && $this->validTestUrl($code['test_url'] ?? null) ? $code['test_url'] : null,
                    'status' => 'pending',
                ])->save();
                $lockedBooking->forceFill([
                    'payment_environment' => app(PaymentEnvironmentResolver::class)->mode(),
                    'gateway' => $this->driver->name(), 'gateway_reference' => $attempt->intent_id,
                    'payment_url' => $image, 'gateway_data' => ['qrph_attempt_id' => $attempt->id],
                ])->save();
            });

            return $this->result($attempt->fresh(), $booking);
        });
    }

    public function latest(Booking $booking): ?QrphPaymentAttempt
    {
        return QrphPaymentAttempt::where('booking_id', $booking->id)
            ->when($this->collection, fn ($query) => $query->where('booking_payment_request_id', $this->collection->id))
            ->where('livemode', $this->driver->livemode())->latest('id')->first();
    }

    public function check(QrphPaymentAttempt $attempt): QrphPaymentAttempt
    {
        if ($attempt->livemode !== $this->driver->livemode()) {
            throw new PaymentGatewayException('This payment belongs to another environment and requires review.');
        }

        return $this->locked($attempt->booking_id, fn (): QrphPaymentAttempt => $this->checkUnlocked($attempt->refresh()));
    }

    private function checkUnlocked(QrphPaymentAttempt $attempt): QrphPaymentAttempt
    {
        if (! $attempt->intent_id || in_array($attempt->status, ['paid', 'review_required'], true)) {
            return $attempt;
        }
        if ($attempt->livemode !== $this->driver->livemode()) {
            throw new PaymentGatewayException('This payment cannot be checked in the current environment. Please contact support.');
        }
        $intent = $this->driver->retrieve($attempt->intent_id);
        $this->validateIntent($attempt, $intent, $attempt->booking);
        $this->applyIntent($attempt, $intent);

        return $attempt->refresh();
    }

    /** @param array<string, mixed> $intent */
    private function validateIntent(QrphPaymentAttempt $attempt, array $intent, Booking $booking): void
    {
        $attributes = $intent['attributes'] ?? [];
        if (! preg_match('/^pi_[A-Za-z0-9]+$/D', $intent['id'] ?? '')
            || ($attempt->intent_id && $intent['id'] !== $attempt->intent_id)
            || ($attributes['amount'] ?? null) !== $attempt->amount
            || ($attributes['currency'] ?? null) !== 'PHP'
            || ($attributes['livemode'] ?? null) !== $attempt->livemode
            || ($attributes['metadata']['booking_code'] ?? null) !== $booking->booking_code
            || ($attributes['metadata']['attempt_id'] ?? null) !== $attempt->uuid) {
            throw new PaymentGatewayException('The payment details could not be verified. Please contact support before paying again.');
        }
    }

    /** @param array<string, mixed> $intent */
    private function applyIntent(QrphPaymentAttempt $attempt, array $intent): void
    {
        $attributes = $intent['attributes'];
        if (($attributes['status'] ?? '') === 'succeeded') {
            foreach ($attributes['payments'] ?? [] as $payment) {
                if (($payment['attributes']['status'] ?? '') === 'paid') {
                    $this->settle($attempt, $payment);

                    return;
                }
            }
            throw new PaymentGatewayException('The payment confirmation is incomplete. Please check again shortly.');
        }

        $status = match ($attributes['status'] ?? '') {
            'cancelled' => 'cancelled',
            'awaiting_payment_method' => ! empty($attributes['last_payment_error']) ? 'failed' : $attempt->status,
            'awaiting_next_action', 'processing' => 'pending',
            default => $attempt->status,
        };
        if (($attributes['status'] ?? '') === 'awaiting_next_action' && $attempt->expires_at?->isPast()) {
            $status = 'expired';
        }
        DB::transaction(function () use ($attempt, $status): void {
            $locked = QrphPaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (! in_array($locked->status, ['paid', 'review_required'], true)) {
                $locked->update(['status' => $status, 'last_checked_at' => now()]);
            }
        });
        $attempt->refresh();
    }

    /** @param array<string, mixed> $payment */
    private function settle(QrphPaymentAttempt $attempt, array $payment): void
    {
        $attributes = $payment['attributes'] ?? [];
        if (! preg_match('/^pay_[A-Za-z0-9]+$/D', $payment['id'] ?? '')
            || ($attributes['status'] ?? '') !== 'paid'
            || ($attributes['payment_intent_id'] ?? '') !== $attempt->intent_id
            || ($attributes['amount'] ?? null) !== $attempt->amount
            || ($attributes['currency'] ?? '') !== 'PHP'
            || ($attributes['livemode'] ?? null) !== $attempt->livemode
            || ($attributes['source']['type'] ?? '') !== 'qrph') {
            throw new PaymentGatewayException('The payment details could not be verified. Please contact support.');
        }

        DB::transaction(function () use ($attempt, $payment): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($attempt->booking_id);
            $locked = QrphPaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (in_array($locked->status, ['paid', 'review_required'], true)) {
                return;
            }
            if ($locked->booking_payment_request_id) {
                $request = BookingPaymentRequest::findOrFail($locked->booking_payment_request_id);
                $paidAt = isset($payment['attributes']['paid_at']) && is_numeric($payment['attributes']['paid_at'])
                    ? Carbon::createFromTimestamp((int) $payment['attributes']['paid_at']) : null;
                $receipt = app(BookingPaymentAccountingService::class)->receipt($request, $payment['id'], $locked->amount, $paidAt);
                $locked->update(['payment_id' => $payment['id'], 'status' => $receipt->allocated ? 'paid' : 'review_required', 'confirmed_at' => now(), 'last_checked_at' => now()]);

                return;
            }
            $canConfirm = $booking->status === Booking::STATUS_APPROVED
                && $booking->payment_status !== Booking::PAYMENT_PAID
                && ! $booking->isPaymentDeadlinePassed()
                && $this->amount($booking) === $locked->amount
                && (! $booking->gateway || $booking->gateway === $this->driver->name());
            $locked->update([
                'payment_id' => $payment['id'], 'status' => $canConfirm ? 'paid' : 'review_required',
                'confirmed_at' => now(), 'last_checked_at' => now(),
            ]);
            if (! $canConfirm) {
                Log::warning('Confirmed QR Ph payment requires manual review.', ['booking_id' => $booking->id, 'attempt_id' => $locked->id]);

                return;
            }
            $booking->forceFill([
                'gateway' => $this->driver->name(), 'gateway_reference' => $locked->intent_id,
                'payment_method' => $this->driver->name(), 'payment_reference' => $payment['id'],
            ])->save();
            if ($booking->markPaid('Payment verified with PayMongo QR Ph.')) {
                DB::afterCommit(function () use ($booking): void {
                    try {
                        BookingNotification::send($booking, new BookingPaid($booking));
                    } catch (\Throwable) {
                        Log::warning('QR Ph paid notification could not be dispatched.', ['booking_id' => $booking->id]);
                    }
                });
            }
        });
    }

    /** @param array{event_id: string, type: string, resource: array<string, mixed>} $event */
    public function webhook(array $event): void
    {
        $resource = $event['resource'];
        $intentId = $resource['attributes']['payment_intent_id'] ?? null;
        $attempt = is_string($intentId) ? QrphPaymentAttempt::where('intent_id', $intentId)->first() : null;
        if (! $attempt && $event['type'] === 'qrph.expired' && is_string($resource['id'] ?? null)) {
            $attempt = QrphPaymentAttempt::where('qr_code_id', $resource['id'])->first();
        }
        if (! $attempt || $attempt->livemode !== $this->driver->livemode()) {
            return;
        }
        if ($event['type'] === 'qrph.expired' && ($resource['id'] ?? null) !== $attempt->qr_code_id) {
            return;
        }
        $this->locked($attempt->booking_id, function () use ($attempt, $event, $resource): void {
            DB::transaction(function () use ($attempt, $event, $resource): void {
                Booking::query()->lockForUpdate()->findOrFail($attempt->booking_id);
                if (PaymentWebhookEvent::where('gateway', $this->driver->name())->where('event_id', $event['event_id'])->exists()) {
                    return;
                }
                if ($event['type'] === 'payment.paid') {
                    $this->settle($attempt, $resource);
                } else {
                    $attributes = $resource['attributes'] ?? [];
                    if ($event['type'] === 'payment.failed'
                        && (($attributes['amount'] ?? null) !== $attempt->amount
                            || ($attributes['currency'] ?? null) !== 'PHP'
                            || ($attributes['source']['type'] ?? '') !== 'qrph'
                            || ($attributes['livemode'] ?? null) !== $attempt->livemode)) {
                        throw new PaymentGatewayException('Payment event does not match this booking.');
                    }
                    QrphPaymentAttempt::whereKey($attempt->id)->whereNotIn('status', ['paid', 'review_required'])
                        ->update(['status' => $event['type'] === 'qrph.expired' ? 'expired' : 'failed', 'last_checked_at' => now()->toIso8601String()]);
                }
                PaymentWebhookEvent::create([
                    'gateway' => $this->driver->name(), 'event_id' => $event['event_id'],
                    'booking_code' => $attempt->booking->booking_code, 'processed_at' => now(),
                ]);
            });
        });
    }

    public function amount(Booking $booking): int
    {
        if ($this->collection) {
            return $this->collection->amount;
        }
        [$whole, $fraction] = array_pad(explode('.', (string) $booking->net_amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function ensurePayable(Booking $booking): void
    {
        if ($this->collection) {
            app(BookingPaymentPolicy::class)->assertCollectible($booking);
            if ($this->collection->booking_id !== $booking->id || $this->collection->gateway !== $this->driver->name()
                || $this->collection->livemode !== $this->driver->livemode()
                || ! in_array($this->collection->fresh()->status, ['creating', 'pending'], true)) {
                throw new PaymentGatewayException('This collection is not eligible for QR Ph payment.');
            }

            return;
        }
        if ($booking->payment_plan_version === 2) {
            throw new PaymentGatewayException('Choose full payment or a deposit from the payment page.');
        }
        if ($booking->status !== Booking::STATUS_APPROVED || $booking->isPaymentDeadlinePassed()
            || $booking->payment_status !== Booking::PAYMENT_UNPAID || $this->amount($booking) < 100) {
            throw new PaymentGatewayException('This booking is not eligible for QR Ph payment. Please contact support.');
        }
    }

    private function validTestUrl(mixed $url): bool
    {
        return is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(parse_url($url, PHP_URL_HOST), ['secure-authentication.paymongo.com', 'test.paymongo.com'], true);
    }

    /** @return array{url: string, reference: string, gateway: string, qr_string: string} */
    private function result(QrphPaymentAttempt $attempt, Booking $booking): array
    {
        if ($attempt->booking_payment_request_id) {
            $request = BookingPaymentRequest::findOrFail($attempt->booking_payment_request_id);

            return ['url' => route('booking.installment.show', [$booking->booking_code, $request->uuid]), 'reference' => (string) $attempt->intent_id, 'gateway' => $this->driver->name(), 'qr_string' => (string) $attempt->qr_image];
        }

        return [
            'url' => route($attempt->status === 'paid' ? 'booking.show' : $this->showRoute(), $booking->booking_code),
            'reference' => (string) $attempt->intent_id, 'gateway' => $this->driver->name(), 'qr_string' => (string) $attempt->qr_image,
        ];
    }

    private function locked(int $bookingId, callable $operation): mixed
    {
        return app(PaymentService::class)->withBookingLock($bookingId, $operation);
    }
}
