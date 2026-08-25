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
            ['name' => 'Beachfront', 'icon' => 'beach_access', 'description' => 'Oceanfront views & white sand', 'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Luxury & Spa', 'icon' => 'spa', 'description' => 'High-end pampering & resorts', 'image_url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Nightlife & Party', 'icon' => 'local_bar', 'description' => 'Vibrant music & island bars', 'image_url' => 'https://images.unsplash.com/photo-1567899378494-47b22a2ae96a?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Nature & Eco', 'icon' => 'forest', 'description' => 'Pristine greenery & wildlife', 'image_url' => 'https://images.unsplash.com/photo-1508873696983-2df515122519?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Quiet & Serene', 'icon' => 'self_improvement', 'description' => 'Peaceful retreat away from crowds', 'image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Adventure & Thrills', 'icon' => 'hiking', 'description' => 'Water sports, treks & diving', 'image_url' => 'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Culinary & Dining', 'icon' => 'restaurant', 'description' => 'Seafood feasts & local cuisine', 'image_url' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Family Friendly', 'icon' => 'family_restroom', 'description' => 'Safe pools & kid activities', 'image_url' => 'https://images.unsplash.com/photo-1563299796-b729d0af54a5?auto=format&fit=crop&w=800&q=80'],
        ];

        foreach ($vibes as $index => $vibe) {
            OnboardingOption::updateOrCreate(
                ['type' => 'vibe', 'name' => $vibe['name']],
                [
                    'icon' => $vibe['icon'],
                    'description' => $vibe['description'],
                    'image_url' => $vibe['image_url'] ?? null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }

        $travelerTypes = [
            ['name' => 'Solo Traveler', 'icon' => 'person', 'description' => 'Independent & flexible', 'image_url' => 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Couple / Honeymoon', 'icon' => 'favorite', 'description' => 'Romantic & private', 'image_url' => 'https://images.unsplash.com/photo-1519744792095-2f2205e87b6f?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Family with Kids', 'icon' => 'family_restroom', 'description' => 'Spacious & kid friendly', 'image_url' => 'https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Friends Group', 'icon' => 'groups', 'description' => 'Group suites & activities', 'image_url' => 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=800&q=80'],
        ];

        foreach ($travelerTypes as $index => $type) {
            OnboardingOption::updateOrCreate(
                ['type' => 'traveler_type', 'name' => $type['name']],
                [
                    'icon' => $type['icon'],
                    'description' => $type['description'],
                    'image_url' => $type['image_url'] ?? null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }

        $amenities = [
            ['name' => 'Private Pool', 'icon' => 'pool'],
            ['name' => 'Beach Access', 'icon' => 'beach_access'],
            ['name' => 'Infinity Pool', 'icon' => 'pool'],
            ['name' => 'Buffet Breakfast', 'icon' => 'restaurant'],
            ['name' => 'Airport Transfers', 'icon' => 'airport_shuttle'],
            ['name' => 'Spa Services', 'icon' => 'spa'],
            ['name' => 'Free Wi-Fi', 'icon' => 'wifi'],
            ['name' => 'Balcony Ocean View', 'icon' => 'deck'],
        ];

        foreach ($amenities as $index => $amenity) {
            OnboardingOption::updateOrCreate(
                ['type' => 'amenity', 'name' => $amenity['name']],
                [
                    'icon' => $amenity['icon'],
                    'description' => null,
                    'image_url' => null,
                    'image_path' => null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }

        // Activity levels — derived from real activities.activity_level (5 distinct levels)
        // Sightseeing, Underwater, Relaxing, Adventure, Extreme
        $activities = [
            ['name' => 'Sightseeing', 'icon' => 'explore', 'description' => 'Vans, e-trikes & viewpoints', 'image_url' => 'https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Underwater', 'icon' => 'scuba_diving', 'description' => 'Scuba, helmet diving & coral reefs', 'image_url' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Relaxing', 'icon' => 'self_improvement', 'description' => 'Island hopping, sailing & kayak', 'image_url' => 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Adventure', 'icon' => 'hiking', 'description' => 'ATV, zipline & banana boat', 'image_url' => 'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Extreme', 'icon' => 'paragliding', 'description' => 'Parasailing & jet ski thrills', 'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'],
        ];

        foreach ($activities as $index => $activity) {
            OnboardingOption::updateOrCreate(
                ['type' => 'activity', 'name' => $activity['name']],
                [
                    'icon' => $activity['icon'],
                    'description' => $activity['description'],
                    'image_url' => $activity['image_url'],
                    'image_path' => null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }

        // Clean up legacy activity rows where onboarding used activity names instead of levels
        OnboardingOption::where('type', 'activity')
            ->whereIn('name', ['Scuba Diving', 'Sunset Cruise', 'Island Hopping', 'Water Sports'])
            ->delete();

        // Clean up any legacy rows where activities were stored as amenity type
        OnboardingOption::where('type', 'amenity')
            ->whereIn('name', ['Scuba Diving', 'Sunset Cruise', 'Island Hopping', 'Water Sports'])
            ->delete();
    }
}
