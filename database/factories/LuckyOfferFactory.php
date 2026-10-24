<?php

namespace Database\Factories;

use App\Models\DestinationModel;
use App\Models\LuckyOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LuckyOffer>
 */
class LuckyOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'destination_id' => DestinationModel::factory(),
            'room_id' => 1,
            'activity_ids' => [],
            'nights' => 2,
            'pax' => 2,
            'check_in_date' => now()->addDays(14)->toDateString(),
            'check_out_date' => now()->addDays(16)->toDateString(),
            'max_budget' => 20000,
            'quoted_total_cents' => 1200000,
            'selection_signature' => hash('sha256', Str::random()),
            'expires_at' => now()->addDays(3),
        ];
    }
}
