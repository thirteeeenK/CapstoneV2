<?php

namespace Database\Factories;

use App\Models\TripItinerary;
use App\Models\TripPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripItinerary>
 */
class TripItineraryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_plan_id' => TripPlan::factory(),
            'revision' => 1,
            'snapshot' => ['days' => [], 'selections' => [], 'unscheduled' => []],
        ];
    }
}
