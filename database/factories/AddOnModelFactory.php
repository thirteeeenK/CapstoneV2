<?php

namespace Database\Factories;

use App\Models\AddOnModel;
use App\Models\DestinationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddOnModel>
 */
class AddOnModelFactory extends Factory
{
    protected $model = AddOnModel::class;

    public function definition(): array
    {
        return [
            'destination_id' => DestinationModel::factory(),
            'name' => fake()->unique()->words(3, true),
            'type' => fake()->randomElement(['Transfer', 'Equipment Rental', 'Sim Card']),
            'description' => fake()->sentence(),
            'inclusions' => [],
            'pricing_tiers' => [
                ['min_pax' => 1, 'max_pax' => 1, 'rate' => 1850],
                ['min_pax' => 2, 'max_pax' => 2, 'rate' => 1450],
            ],
            'surcharges' => [],
            'is_shown' => true,
        ];
    }
}