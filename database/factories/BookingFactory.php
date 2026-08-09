<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'booking_code' => 'ST-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'user_id' => User::factory(),
            'status' => Booking::STATUS_PENDING,
            'total_amount' => 6000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'net_amount' => 6000.00,
            'payment_status' => Booking::PAYMENT_UNPAID,
            'payment_method' => null,
            'payment_reference' => null,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => '0917' . fake()->numerify('#######'),
            'special_requests' => null,
            'guest_manifest' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Booking::STATUS_APPROVED,
            'approved_at' => now(),
            'payment_deadline' => now()->addHours(48),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Booking::STATUS_PAID,
            'payment_status' => Booking::PAYMENT_PAID,
            'paid_at' => now(),
            'payment_method' => 'Simulator',
        ]);
    }

    public function completed(): static
    {
        return $this->paid()->state(fn () => [
            'status' => Booking::STATUS_COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled by customer.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => Booking::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => 'Rooms unavailable',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Booking::STATUS_EXPIRED,
            'expired_at' => now(),
        ]);
    }
}