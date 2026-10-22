<?php

namespace Database\Factories;

use App\Models\DestinationModel;
use App\Models\KnowledgeArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeArticle>
 */
class KnowledgeArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'category' => fake()->randomElement(array_keys(KnowledgeArticle::CATEGORIES)),
            'destination_id' => DestinationModel::factory(),
            'source_label' => 'SunnyTrips travel desk',
            'source_url' => null,
            'reviewed_at' => now()->subWeek()->toDateString(),
            'expires_at' => null,
            'status' => 'published',
            'reviewed_by' => 'Travel desk',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()->toDateString()]);
    }

    public function unreviewed(): static
    {
        return $this->state(['reviewed_at' => null]);
    }
}
