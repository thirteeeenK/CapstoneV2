<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingPaymentTransaction> */
class BookingPaymentTransactionFactory extends Factory
{
    public function definition(): array
    {
        return ['booking_id' => Booking::factory()->approved(), 'gateway' => 'paymongo',
            'livemode' => true, 'source_reference' => 'pay_'.fake()->unique()->bothify('????????????????'),
            'kind' => 'receipt', 'amount' => 300000, 'currency' => 'PHP', 'allocated' => true, 'occurred_at' => now()];
    }
}
