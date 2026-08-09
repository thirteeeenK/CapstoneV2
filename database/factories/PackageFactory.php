<?php

namespace Database\Factories;

use App\Models\DestinationModel;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'destination_id' => DestinationModel::factory(),
            'name' => fake()->unique()->sentence(3),
            'type' => fake()->randomElement(['Vacation Deal', 'Tour Package']),
            'price' => fake()->numberBetween(2500, 15000),
            'days' => 3,
            'nights' => 2,
            'min_pax' => 2,
            'valid_from' => null,
            'valid_to' => null,
            'generic_inclusions' => ['Hotel', 'Tour'],
            'images' => [],
            'is_active' => true,
        ];
    }
}