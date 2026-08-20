<?php

namespace Database\Seeders;

use App\Models\OnboardingOption;
use Illuminate\Database\Seeder;

class OnboardingOptionSeeder extends Seeder
{
    /**
     * Seed the default onboarding options that users can choose from.
     */
    public function run(): void
    {
        $vibes = [
            ['name' => 'Beachfront', 'icon' => 'beach_access', 'description' => 'Oceanfront views & white sand'],
            ['name' => 'Luxury & Spa', 'icon' => 'spa', 'description' => 'High-end pampering & resorts'],
            ['name' => 'Nightlife & Party', 'icon' => 'local_bar', 'description' => 'Vibrant music & island bars'],
            ['name' => 'Nature & Eco', 'icon' => 'forest', 'description' => 'Pristine greenery & wildlife'],
            ['name' => 'Quiet & Serene', 'icon' => 'self_improvement', 'description' => 'Peaceful retreat away from crowds'],
            ['name' => 'Adventure & Thrills', 'icon' => 'hiking', 'description' => 'Water sports, treks & diving'],
            ['name' => 'Culinary & Dining', 'icon' => 'restaurant', 'description' => 'Seafood feasts & local cuisine'],
            ['name' => 'Family Friendly', 'icon' => 'family_restroom', 'description' => 'Safe pools & kid activities'],
        ];

        foreach ($vibes as $index => $vibe) {
            OnboardingOption::updateOrCreate(
                ['type' => 'vibe', 'name' => $vibe['name']],
                [
                    'icon' => $vibe['icon'],
                    'description' => $vibe['description'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }

        $travelerTypes = [
            ['name' => 'Solo Traveler', 'icon' => 'person', 'description' => 'Independent & flexible'],
            ['name' => 'Couple / Honeymoon', 'icon' => 'favorite', 'description' => 'Romantic & private'],
            ['name' => 'Family with Kids', 'icon' => 'family_restroom', 'description' => 'Spacious & kid friendly'],
            ['name' => 'Friends Group', 'icon' => 'groups', 'description' => 'Group suites & activities'],
        ];

        foreach ($travelerTypes as $index => $type) {
            OnboardingOption::updateOrCreate(
                ['type' => 'traveler_type', 'name' => $type['name']],
                [
                    'icon' => $type['icon'],
                    'description' => $type['description'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }

        $amenities = [
            'Private Pool',
            'Beach Access',
            'Infinity Pool',
            'Scuba Diving',
            'Sunset Cruise',
            'Buffet Breakfast',
            'Airport Transfers',
            'Spa Services',
            'Island Hopping',
            'Free Wi-Fi',
            'Balcony Ocean View',
            'Water Sports',
        ];

        foreach ($amenities as $index => $amenity) {
            OnboardingOption::updateOrCreate(
                ['type' => 'amenity', 'name' => $amenity],
                [
                    'icon' => null,
                    'description' => null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }
    }
}
