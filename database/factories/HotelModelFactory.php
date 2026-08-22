<?php

namespace Database\Factories;

use App\Models\DestinationModel;
use App\Models\HotelModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelModel>
 */
class HotelModelFactory extends Factory
{
    protected $model = HotelModel::class;

    public function definition(): array
    {
        return [
            'hotel_name' => fake()->unique()->company().' Resort',
            'destination_id' => DestinationModel::factory(),
            'type' => fake()->randomElement(['Resort', 'Inn', 'Hostel', 'Hotel']),
            'hotel_description' => fake()->sentence(),
            'specific_address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(5, 20),
            'longitude' => fake()->longitude(117, 127),
            'is_shown' => true,
            'images' => [],
        ];
    }
}
