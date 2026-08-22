<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'question' => fake()->unique()->sentence().'?',
            'answer' => fake()->paragraph(),
            'keywords' => implode(', ', fake()->words(4)),
            'category' => fake()->randomElement(['Services', 'Booking', 'Policies']),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
