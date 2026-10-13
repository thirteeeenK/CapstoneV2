<?php

namespace Database\Factories;

use App\Models\DestinationModel;
use App\Models\TripPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripPlan>
 */
class TripPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'destination_id' => DestinationModel::factory(),
            'title' => 'My island trip',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'pax' => 2,
            'mode' => 'walking',
            'revision' => 1,
        ];
    }
}
