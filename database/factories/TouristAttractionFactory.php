<?php

namespace Database\Factories;

use App\Models\DestinationModel;
use App\Models\TouristAttraction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TouristAttraction>
 */
class TouristAttractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destination_id' => DestinationModel::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'latitude' => 11.9658,
            'longitude' => 121.9255,
            'scheduling' => ['duration_minutes' => 60, 'environment' => 'outdoor', 'opens_at' => '09:00', 'closes_at' => '18:00'],
            'is_shown' => true,
        ];
    }
}
