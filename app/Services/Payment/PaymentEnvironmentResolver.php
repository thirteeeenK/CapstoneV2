<?php

namespace App\Services\Payment;

use App\Models\Booking;

final class PaymentEnvironmentResolver
{
    public function mode(): string
    {
        if (app()->environment(['local', 'testing'])) {
            return 'test';
        }
        if (app()->isProduction()) {
            return 'live';
        }
        $mode = config('services.payment.mode');
        if (! in_array($mode, ['live', 'test'], true)) {
            throw new PaymentGatewayException('Configure PAYMENT_MODE as test or live for this deployment.');
        }

        return $mode;
    }

    public function isLive(): bool
    {
        return $this->mode() === 'live';
    }

    public function gatewayIsLive(string $gateway): bool
    {
        return $gateway === 'qrph' || $gateway === 'simulator' ? false : $this->isLive();
    }

    public function assertBooking(Booking $booking): void
    {
        if ($booking->payment_environment && $booking->payment_environment !== $this->mode()) {
            throw new PaymentGatewayException('This booking has saved terms from another payment environment. Review its payment history before correcting its terms.');
        }
    }

    public function stripeConfiguration(string $field): ?string
    {
        $mode = $this->mode();
        $value = config('services.stripe.'.$mode.'_'.$field);
        if (! $value) {
            $value = config('services.stripe.'.$field);
        }
        if ($field === 'secret_key' && ! preg_match('/^(sk|rk)_'.$mode.'_/', (string) $value)) {
            return null;
        }

        return $value ?: null;
    }

    public function qrConfiguration(string $gateway, string $field): mixed
    {
        $section = $gateway === 'paymongo' && ! $this->isLive() ? 'qrph' : $gateway;

        return config('services.'.$section.'.'.$field);
    }
}
