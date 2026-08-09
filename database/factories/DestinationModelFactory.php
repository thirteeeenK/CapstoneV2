<?php

namespace Database\Factories;

use App\Models\DestinationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestinationModel>
 */
class DestinationModelFactory extends Factory
{
    protected $model = DestinationModel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city() . ' Island',
            'region' => fake()->randomElement(['Luzon', 'Visayas', 'Mindanao']),
            'description' => fake()->sentence(),
            'image' => null,
            'latitude' => fake()->latitude(5, 20),
            'longitude' => fake()->longitude(117, 127),
        ];
    }
}