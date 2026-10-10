<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPayment>
 */
class BookingPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->approved(),
            'provider' => 'gotyme',
            'sender_reference' => fake()->unique()->bothify('GT-########'),
            'normalized_reference' => fn (array $attributes): string => strtoupper(preg_replace('/[^A-Z0-9]/i', '', $attributes['sender_reference'])),
            'claimed_amount' => fake()->randomFloat(2, 100, 20_000),
            'verified_amount' => null,
            'status' => BookingPayment::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'verified_at' => null,
            'verified_by_admin_id' => null,
            'admin_note' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingPayment::STATUS_VERIFIED,
            'verified_amount' => $attributes['claimed_amount'],
            'verified_at' => now(),
            'verified_by_admin_id' => null,
        ]);
    }
}
