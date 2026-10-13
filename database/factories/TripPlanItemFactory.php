<?php

namespace Database\Factories;

use App\Models\ActivityModel;
use App\Models\TripPlan;
use App\Models\TripPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripPlanItem>
 */
class TripPlanItemFactory extends Factory
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
            'item_type' => 'activity',
            'item_id' => ActivityModel::factory(),
            'options' => ['pax' => 2, 'quantity' => 1],
        ];
    }
}
