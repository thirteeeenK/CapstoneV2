<?php

namespace Database\Factories;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityModel>
 */
class ActivityModelFactory extends Factory
{
    protected $model = ActivityModel::class;

    public function definition(): array
    {
        return [
            'destination_id' => DestinationModel::factory(),
            'activity_name' => fake()->unique()->words(3, true),
            'category' => fake()->randomElement(['Water Activity', 'Island Hopping', 'Land Tour', 'Adventure']),
            'activity_level' => fake()->randomElement(['Relaxing', 'Sightseeing', 'Adventure']),
            'rate' => '₱'.fake()->numberBetween(300, 2000).'/person',
            'duration' => fake()->randomElement(['3 Hours', 'Half Day', 'Full Day']),
            'capacity' => 'Up to '.fake()->numberBetween(6, 20).' guests',
            'requirements' => null,
            'ideal_for' => 'Everyone',
            'vibe_tags' => [],
            'description' => fake()->sentence(),
            'inclusions' => ['Lunch', 'Life vest'],
            'exclusions' => [],
            'itinerary' => [],
            'notes' => null,
            'images' => [],
            'is_shown' => true,
        ];
    }
}
