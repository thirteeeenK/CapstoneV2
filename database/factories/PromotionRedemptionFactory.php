<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromotionRedemption>
 */
class PromotionRedemptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'lucky_offer_id' => LuckyOffer::factory(),
            'booking_id' => Booking::factory(),
            'code' => 'LUCKY-'.strtoupper(Str::random(6)),
            'status' => PromotionRedemption::STATUS_RESERVED,
            'eligible_cents' => 1200000,
            'discount_cents' => 120000,
            'allocations' => [],
            'terms' => [],
            'reserved_at' => now(),
        ];
    }
}
