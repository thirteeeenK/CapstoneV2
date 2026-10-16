<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\QrphPaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QrphPaymentAttempt>
 */
class QrphPaymentAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'booking_id' => Booking::factory()->approved(),
            'intent_id' => 'pi_'.Str::random(24),
            'method_id' => 'pm_'.Str::random(24),
            'qr_code_id' => 'qr_'.Str::random(24),
            'amount' => 600000,
            'currency' => 'PHP',
            'livemode' => false,
            'status' => 'pending',
            'qr_image' => 'data:image/png;base64,'.base64_encode('fixture'),
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
