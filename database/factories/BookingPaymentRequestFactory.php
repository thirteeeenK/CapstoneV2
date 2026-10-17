<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPaymentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BookingPaymentRequest> */
class BookingPaymentRequestFactory extends Factory
{
    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'booking_id' => Booking::factory()->approved(),
            'purpose' => 'deposit', 'amount' => 300000, 'quote_amount' => 600000, 'currency' => 'PHP',
            'gateway' => 'paymongo', 'livemode' => true, 'status' => 'creating', 'due_at' => now()->addDays(2)];
    }
}
