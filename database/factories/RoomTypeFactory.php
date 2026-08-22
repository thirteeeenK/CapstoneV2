<?php

namespace Database\Factories;

use App\Models\HotelModel;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    public function definition(): array
    {
        return [
            'hotel_id' => HotelModel::factory(),
            'room_name' => fake()->unique()->words(3, true),
            'base_price' => fake()->numberBetween(1000, 8000),
            'base_occupancy' => 2,
            'max_occupancy' => 4,
            'extra_person_fee' => 500.00,
            'total_rooms' => 5,
            'room_amenities' => ['WiFi', 'Air Conditioning'],
            'bed_configuration' => '1 King Bed',
            'room_size' => '35 sqm',
            'description' => fake()->sentence(),
            'view_type' => 'Ocean View',
            'images' => [],
            'is_shown' => true,
        ];
    }
}
