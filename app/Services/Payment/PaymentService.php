<?php

namespace App\Services\Payment;

use App\Models\Booking;
use App\Models\QrphPaymentAttempt;
use App\Services\Payment\Drivers\PaymentDriver;
use App\Services\Payment\Drivers\PaymongoDriver;
use App\Services\Payment\Drivers\QrphDriver;
use App\Services\Payment\Drivers\SimulatorDriver;
use App\Services\Payment\Drivers\StripeDriver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function driver(): PaymentDriver
    {
        return match (config('services.payment.provider', 'auto')) {
            'paymongo' => new PaymongoDriver,
            'qrph' => new QrphDriver,
            'simulator' => new SimulatorDriver,
            default => $this->stripeConfigured() ? new StripeDriver : new SimulatorDriver,
        };
    }

    /** @return array{url: string, reference: string, gateway: string} */
    public function createPayment(Booking $booking): array
    {
        $driver = $this->driver();

        return $driver instanceof QrphDriver
            ? QrphPaymentService::forGateway($driver->name())->create($booking)
            : $this->createSession($booking, $driver);
    }

    /** @return array{url: string, reference: string, gateway: string} */
    public function createCardPayment(Booking $booking): array
    {
        if (! $this->stripeConfigured() && app()->isProduction()) {
            throw new PaymentGatewayException('Card payments are temporarily unavailable. Please choose another method.');
        }

        return $this->createSession($booking, $this->stripeConfigured() ? new StripeDriver : new SimulatorDriver);
    }

    /** @return array{url: string, reference: string, gateway: string} */
    private function createSession(Booking $booking, PaymentDriver $driver): array
    {
        return $this->withBookingLock($booking->id, function () use ($booking, $driver): array {
            $booking->refresh();
            if ($booking->status !== Booking::STATUS_APPROVED || $booking->isPaymentDeadlinePassed()) {
                throw new PaymentGatewayException('This booking is not ready for payment.');
            }
            $this->assertCanSelectGateway($booking, $driver->name() === 'stripe' ? 'card' : 'simulator');
            if ($booking->gateway === $driver->name() && $booking->gateway_reference && $booking->payment_url) {
                return ['url' => $booking->payment_url, 'reference' => $booking->gateway_reference, 'gateway' => $driver->name()];
            }

            try {
                $result = $driver->createPayment($booking);
            } catch (ConnectionException) {
                throw new PaymentGatewayException('The payment provider could not be reached. Please try again shortly.');
            }

            $booking->forceFill([
                'gateway' => $driver->name(), 'gateway_reference' => $result['reference'],
                'payment_url' => $result['url'], 'gateway_data' => null,
            ])->save();
            Log::info('Payment session created.', ['booking_id' => $booking->id, 'gateway' => $driver->name()]);

            return ['url' => $result['url'], 'reference' => $result['reference'], 'gateway' => $driver->name()];
        });
    }

    /** @return array{url: string, reference: string, gateway: string, qr_string: string} */
    public function createQrphPayment(Booking $booking): array
    {
        return QrphPaymentService::forGateway('qrph')->create($booking);
    }

    /** @return array{url: string, reference: string, gateway: string, qr_string: string} */
    public function createPaymongoPayment(Booking $booking): array
    {
        return QrphPaymentService::forGateway('paymongo')->create($booking);
    }

    public function assertCanSelectGateway(Booking $booking, string $gateway): void
    {
        $liveAttempt = QrphPaymentAttempt::where('booking_id', $booking->id)->where('livemode', true)
            ->whereIn('status', ['creating', 'pending', 'review_required'])->exists();
        if ($gateway !== 'paymongo' && $liveAttempt) {
            throw new PaymentGatewayException('A live PayMongo payment is already in progress or needs review. Continue that payment before changing methods.');
        }
        if ($gateway !== 'gotyme' && app(GoTymePaymentService::class)->hasActiveTransfer($booking)) {
            throw new PaymentGatewayException('A GoTyme transfer is awaiting or has received verification. Continue there before changing methods.');
        }
        if (! in_array($gateway, ['card', 'stripe'], true) && $booking->gateway === 'stripe' && $booking->gateway_reference) {
            throw new PaymentGatewayException('A Stripe checkout is already associated with this booking. Continue there or contact support before changing methods.');
        }
    }

    public function withBookingLock(int $bookingId, callable $operation): mixed
    {
        $lock = Cache::lock('qrph-booking-'.$bookingId, 60);
        if (! $lock->get()) {
            throw new PaymentGatewayException('A payment request is already in progress. Please try again shortly.');
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed>|null */
    public function handleWebhook(string $gateway, Request $request): ?array
    {
        $driver = $this->resolveDriver($gateway);
        $eventData = [];

        return $driver && $driver->verifyWebhook($request->getContent(), $request, $eventData) ? $eventData : null;
    }

    public function verifyReturn(Booking $booking): bool
    {
        if (in_array($booking->gateway, ['qrph', 'paymongo'], true)) {
            $service = QrphPaymentService::forGateway($booking->gateway);
            $attempt = $service->latest($booking);

            return $attempt !== null && $service->check($attempt)->status === 'paid';
        }
        if (! $booking->gateway_reference) {
            return false;
        }

        return $this->resolveDriver($booking->gateway)?->verifyPaymentByReference($booking->gateway_reference) ?? false;
    }

    protected function resolveDriver(?string $gateway): ?PaymentDriver
    {
        return match ($gateway) {
            'stripe' => $this->stripeConfigured() ? new StripeDriver : null,
            'paymongo' => new PaymongoDriver,
            'qrph' => new QrphDriver,
            'simulator' => new SimulatorDriver,
            default => null,
        };
    }

    protected function stripeConfigured(): bool
    {
        return ! empty(config('services.stripe.secret_key'));
    }

    /** @return array<string, bool> */
    public function availableGateways(): array
    {
        return [
            'card' => $this->stripeConfigured(),
            'qrph' => (new QrphDriver)->configured(),
            'gotyme' => true,
            'paymongo' => (new PaymongoDriver)->configured(),
        ];
    }
}
