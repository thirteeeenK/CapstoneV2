<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'is_active' => true,
            'code_prefix' => 'LUCKY',
            'discount_type' => Promotion::TYPE_PERCENT,
            'percent_off' => 10,
            'fixed_off_cents' => null,
            'max_discount_cents' => null,
            'min_eligible_cents' => 0,
            'per_traveler_limit' => 1,
            'codes_per_traveler_per_day' => 3,
            'offer_valid_hours' => 72,
            'award_weight' => 1,
        ];
    }

    public function fixed(int $cents): static
    {
        return $this->state(fn () => ['discount_type' => Promotion::TYPE_FIXED, 'percent_off' => null, 'fixed_off_cents' => $cents]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
