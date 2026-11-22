<?php

namespace Database\Factories;

use App\Models\EmailCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailCampaign>
 */
class EmailCampaignFactory extends Factory
{
    /**
     * Define the model's default state: a manual draft to everyone opted in.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'subject' => 'Island deals for {first_name}',
            'preheader' => 'Fresh promotions this week',
            'headline' => 'Your next island escape',
            'body' => "Hand-picked stays and tours are waiting.\n\nBook early for the best dates.",
            'cta_label' => 'Explore now',
            'cta_target' => 'lucky',
            'send_mode' => EmailCampaign::MODE_MANUAL,
            'audience' => ['segment' => 'all', 'destinations' => [], 'traveler_types' => [], 'inactive_days' => 90],
            'status' => EmailCampaign::STATUS_DRAFT,
        ];
    }

    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => ['send_mode' => EmailCampaign::MODE_SCHEDULED, 'status' => EmailCampaign::STATUS_SCHEDULED, 'scheduled_at' => $at ?? now()->addHour()]);
    }

    public function automatic(string $trigger = EmailCampaign::TRIGGER_PROMOTION_LIVE): static
    {
        return $this->state(fn () => ['send_mode' => EmailCampaign::MODE_AUTOMATIC, 'trigger' => $trigger, 'status' => EmailCampaign::STATUS_ACTIVE, 'activated_at' => now()->subMinute()]);
    }
}
