<?php

namespace Database\Factories;

use App\Models\ChatSession;
use App\Models\SupportInquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportInquiry>
 */
class SupportInquiryFactory extends Factory
{
    protected $model = SupportInquiry::class;

    public function definition(): array
    {
        return [
            'ticket_number' => 'TKT-' . now()->format('Ymd') . '-' . strtoupper(fake()->unique()->bothify('####')),
            'chat_session_id' => ChatSession::factory(),
            'user_id' => null,
            'assigned_admin_id' => null,
            'status' => SupportInquiry::STATUS_PENDING,
            'requested_at' => now(),
            'assigned_at' => null,
            'returned_to_ai_at' => null,
            'resolved_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => SupportInquiry::STATUS_PENDING]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => SupportInquiry::STATUS_HUMAN_ACTIVE]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}