<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use App\Models\QrphPaymentAttempt;
use App\Services\Payment\Drivers\PaymongoDriver;
use App\Services\Payment\Drivers\QrphDriver;
use App\Services\Payment\Drivers\StripeDriver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InstallmentPaymentService
{
    public function __construct(private readonly BookingPaymentPolicy $policy, private readonly BookingPaymentAccountingService $accounting) {}

    private function gatewayIsLive(string $gateway): bool
    {
        return match ($gateway) {
            'paymongo', 'gotyme', 'cash', 'stripe', 'qrph' => app(PaymentEnvironmentResolver::class)->gatewayIsLive($gateway),
            default => throw new PaymentGatewayException('Select a supported payment method.'),
        };
    }

    /** @return array<string, array{label: string, enabled: bool, reason: ?string}> */
    public function gatewayOptions(Booking $booking): array
    {
        $configured = app(PaymentService::class)->availableGateways();
        $options = [];
        foreach (['paymongo' => 'PayMongo QR Ph', 'stripe' => 'Stripe Card', 'gotyme' => 'Manual GoTyme', 'qrph' => 'QR Ph Sandbox'] as $gateway => $name) {
            $live = $this->gatewayIsLive($gateway);
            $matches = $live === ($booking->payment_environment !== 'test');
            $ready = $configured[$gateway === 'stripe' ? 'card' : $gateway];
            $options[$gateway] = [
                'label' => $name.($gateway === 'qrph' ? '' : ($live ? ' — Live' : ' — Sandbox')),
                'enabled' => $ready && $matches && $booking->payment_environment === app(PaymentEnvironmentResolver::class)->mode(),
                'reason' => $booking->payment_environment !== app(PaymentEnvironmentResolver::class)->mode()
                    ? 'Review this booking’s saved payment environment before paying.'
                    : (! $ready ? 'Configure credentials for this deployment’s payment mode.' : (! $matches
                    ? ($live ? 'Real payments are disabled for this sandbox booking.' : 'Test payments are disabled for this live booking.')
                    : ($gateway === 'gotyme' ? ($live ? 'Your payment is credited after admin verification.' : 'Simulated transfer only. Do not send real money; staff verify the test receipt.') : null))),
            ];
        }

        return $options;
    }

    public function prepare(Booking $booking, string $purpose, string $gateway): BookingPaymentRequest
    {
        return app(PaymentService::class)->withBookingLock($booking->id, fn () => DB::transaction(function () use ($booking, $purpose, $gateway): BookingPaymentRequest {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->policy->assertCollectible($booking);
            if (($gateway === 'paymongo' && ! (new PaymongoDriver)->configured())
                || ($gateway === 'qrph' && ! (new QrphDriver)->configured())
                || ($gateway === 'stripe' && ! app(PaymentEnvironmentResolver::class)->stripeConfiguration('secret_key'))) {
                throw new PaymentGatewayException('This payment method is temporarily unavailable. Choose another configured method.');
            }
            $live = $this->gatewayIsLive($gateway);
            if ($live !== ($booking->payment_environment !== 'test')) {
                throw new PaymentGatewayException('This payment method belongs to another environment. Choose a method matching this booking’s live or sandbox mode.');
            }
            $existing = BookingPaymentRequest::where('booking_id', $booking->id)->whereIn('status', BookingPaymentRequest::ACTIVE)->first();
            if ($existing) {
                if ($existing->gateway !== $gateway || $existing->purpose !== $purpose || $existing->status === 'review_required') {
                    throw new PaymentGatewayException('A payment is already in progress or needs review. Continue it before changing the amount or method.');
                }

                return $existing;
            }
            $summary = $this->accounting->summary($booking);
            $amount = match ($purpose) {
                'full' => $summary['remaining_cents'],
                'deposit' => ($booking->payment_policy['deposit_eligible'] ?? false) && ! $booking->deposit_secured_at
                    ? max(0, $booking->deposit_required_cents - $summary['verified_cents']) : 0,
                'balance' => $booking->deposit_secured_at ? $summary['remaining_cents'] : 0,
                default => 0,
            };
            if ($amount < 100 || ($purpose === 'deposit' && $summary['remaining_cents'] - $amount < 100)) {
                throw new PaymentGatewayException('This installment is unavailable or below the minimum payment. Choose full payment.');
            }
            $booking->forceFill(['payment_plan' => $purpose === 'deposit' || $booking->deposit_secured_at ? 'deposit_50' : 'full'])->save();

            return BookingPaymentRequest::create([
                'uuid' => (string) Str::uuid(), 'booking_id' => $booking->id, 'purpose' => $purpose,
                'amount' => $amount, 'quote_amount' => $summary['due_cents'], 'gateway' => $gateway,
                'livemode' => $live, 'status' => 'creating',
                'due_at' => $booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline,
            ]);
        }));
    }

    /** @return array{url: string} */
    public function start(BookingPaymentRequest $request): array
    {
        if (in_array($request->gateway, ['paymongo', 'qrph'], true)) {
            $result = QrphPaymentService::forGateway($request->gateway)->create($request->booking, $request);
            $request->refresh();
            $request->update(['provider_reference' => $result['reference'], 'status' => $request->status === 'creating' ? 'pending' : $request->status]);

            return ['url' => $result['url']];
        }
        if ($request->gateway === 'gotyme') {
            DB::transaction(function () use ($request): void {
                Booking::query()->lockForUpdate()->findOrFail($request->booking_id);
                $request->refresh();
                if (! in_array($request->status, ['creating', 'pending'], true)) {
                    throw new PaymentGatewayException('This collection is no longer awaiting payment.');
                }
                app(GoTymePaymentService::class)->initialize($request->booking);
                $request->update(['status' => 'pending']);
            });

            return ['url' => route('booking.pay.gotyme.show', $request->booking->booking_code)];
        }

        return app(PaymentService::class)->withBookingLock($request->booking_id, function () use ($request): array {
            $request->refresh();
            $this->policy->assertCollectible($request->booking->fresh());
            if ($request->checkout_url) {
                return ['url' => $request->checkout_url];
            }
            $result = (new StripeDriver)->createPayment($request->booking, $request);
            $request->update(['provider_reference' => $result['reference'], 'checkout_url' => $result['url'], 'status' => 'pending']);
            $request->booking->forceFill(['gateway' => 'stripe', 'gateway_reference' => $result['reference'], 'payment_url' => $result['url']])->save();

            return ['url' => $result['url']];
        });
    }

    public function check(BookingPaymentRequest $request): void
    {
        if ($request->livemode !== app(PaymentEnvironmentResolver::class)->gatewayIsLive($request->gateway)) {
            throw new PaymentGatewayException('This collection belongs to another payment environment and requires review.');
        }
        if (in_array($request->status, ['succeeded', 'review_required', 'cancelled'], true)) {
            return;
        }
        if (in_array($request->gateway, ['paymongo', 'qrph'], true)) {
            $attempt = QrphPaymentAttempt::where('booking_payment_request_id', $request->id)->latest('id')->first();
            if ($attempt) {
                QrphPaymentService::forGateway($request->gateway)->check($attempt);
            }

            return;
        }
        if ($request->gateway === 'stripe' && $request->provider_reference) {
            $session = (new StripeDriver)->retrieveSession($request->provider_reference);
            if (($session['payment_status'] ?? null) === 'paid') {
                $this->stripeReceipt($request, [
                    'reference' => $session['id'] ?? null, 'payment_id' => $session['payment_intent'] ?? null,
                    'amount' => $session['amount_total'] ?? null, 'currency' => strtoupper($session['currency'] ?? ''),
                    'livemode' => $session['livemode'] ?? null,
                    'payment_request_uuid' => $session['metadata']['payment_request_uuid'] ?? null,
                ]);
            } elseif (($session['status'] ?? null) === 'expired') {
                $request->update(['status' => 'expired']);
            }
        }
    }

    public function closeUnpaid(BookingPaymentRequest $request): void
    {
        $this->check($request);
        $request->refresh();
        if (! in_array($request->status, BookingPaymentRequest::ACTIVE, true) || $request->status === 'review_required') {
            return;
        }
        if ($request->gateway === 'gotyme') {
            DB::transaction(function () use ($request): void {
                Booking::query()->lockForUpdate()->findOrFail($request->booking_id);
                $request->refresh();
                if ($request->status === 'succeeded') {
                    return;
                }
                if ($request->booking->payments()->where('booking_payment_request_id', $request->id)->where('status', 'submitted')->exists()) {
                    throw new PaymentGatewayException('A submitted manual transfer needs staff review.');
                }
                $request->update(['status' => 'cancelled']);
            });

            return;
        }
        if (in_array($request->gateway, ['paymongo', 'qrph'], true)) {
            $attempt = QrphPaymentAttempt::where('booking_payment_request_id', $request->id)->latest('id')->first();
            if (! $attempt?->intent_id) {
                throw new PaymentGatewayException('An uncertain provider request needs reconciliation.');
            }
            $driver = $request->gateway === 'paymongo' ? new PaymongoDriver : new QrphDriver;
            $driver->cancel($attempt->intent_id, $request->uuid.'-cancel');
            $attempt = QrphPaymentService::forGateway($request->gateway)->check($attempt);
            if ($attempt->status === 'cancelled') {
                $request->refresh();
                if ($request->status !== 'succeeded') {
                    $request->update(['status' => 'cancelled']);
                }
            }

            return;
        }
        if ($request->gateway === 'stripe' && $request->provider_reference) {
            (new StripeDriver)->expireSession($request->provider_reference, $request->uuid.'-expire');
            $this->check($request);
        }
    }

    /** @param array<string, mixed> $event */
    public function stripeReceipt(BookingPaymentRequest $request, array $event): void
    {
        if ($request->gateway !== 'stripe' || ($event['reference'] ?? null) !== $request->provider_reference
            || ($event['amount'] ?? null) !== $request->amount || ($event['currency'] ?? null) !== 'PHP'
            || ($event['livemode'] ?? null) !== $request->livemode
            || ($event['payment_request_uuid'] ?? null) !== $request->uuid
            || ! preg_match('/^pi_[A-Za-z0-9_]+$/D', $event['payment_id'] ?? '')) {
            throw new PaymentGatewayException('The Stripe payment does not match this collection.');
        }
        $paidAt = isset($event['paid_at']) && is_numeric($event['paid_at']) ? Carbon::createFromTimestamp((int) $event['paid_at']) : null;
        $this->accounting->receipt($request, $event['payment_id'], $request->amount, $paidAt);
    }
}
