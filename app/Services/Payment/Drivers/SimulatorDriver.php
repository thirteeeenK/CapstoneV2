<?php

namespace App\Services\Payment\Drivers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SimulatorDriver implements PaymentDriver
{
    public function name(): string
    {
        return 'simulator';
    }

    public function createPayment(Booking $booking): array
    {
        return [
            'url' => route('booking.pay.simulator', $booking->booking_code),
            'reference' => 'SIM-' . strtoupper(Str::random(8)),
        ];
    }

    public function verifyWebhook(string $rawPayload, Request $request, array &$eventData): bool
    {
        return false;
    }

    public function verifyPaymentByReference(string $reference): bool
    {
        return true;
    }
}
