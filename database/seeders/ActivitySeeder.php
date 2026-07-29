<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Services\GeminiService;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(GeminiService $geminiService): void
    {
        $boracay = DestinationModel::firstOrCreate(
            ['name' => 'Boracay'],
        );

        $activities = [
            [
                'category' => 'Island Hopping',
                'activity_name' => 'Joiners Island Hopping',
                'activity_level' => 'Relaxing',
                'rate' => '₱850/person',
                'description' => 'Explore Boracay\'s surrounding pristine islands including Crocodile Island, Puka Beach, and Magic Island. Enjoy swimming, snorkeling in coral gardens, and a hot kawa bath experience with a complimentary seafood & BBQ buffet lunch.',
                'vibe_tags' => ['Snorkeling', 'Island Hopping', 'Buffet Lunch', 'Kawa Bath', 'Group Friendly'],
                'ideal_for' => 'Budget Travelers, Families, Social Groups, Solo Explorers',
                'notes' => 'Includes free buffet lunch + kawa bath',
            ],
            [
                'category' => 'Land Tour',
                'activity_name' => '6–7 Destinations (E-Trike)',
                'activity_level' => 'Sightseeing',
                'rate' => '₱2,000',
                'description' => 'Hop on an eco-friendly electric trike for an intimate island tour covering Keyhole, Newcoast Beach, Puka Beach, Mt. Luho viewpoint, and Ilig-Iligan Beach at your own pace.',
                'vibe_tags' => ['Eco-Friendly', 'Sightseeing', 'Custom Pace', 'Land Exploration'],
                'ideal_for' => 'Couples, Small Families, Photography Enthusiasts',
                'notes' => 'Good for 1–6 persons',
            ],
            [
                'category' => 'Land Tour',
                'activity_name' => '6–7 Destinations (FB Van/Multicab)',
                'activity_level' => 'Sightseeing',
                'rate' => '₱3,000',
                'description' => 'Group land tour around Boracay\'s famous spots in an open-air multicab. Ideal for medium-sized barkadas exploring the island\'s northern beaches and coastal views.',
                'vibe_tags' => ['Group Tour', 'Open Air', 'Sightseeing', 'Barkada Favorite'],
                'ideal_for' => 'Medium Groups, Barkadas, Extended Families',
                'notes' => 'Good for 7–10 persons',
            ],
            [
                'category' => 'Land Tour',
                'activity_name' => '6–7 Destinations (Aircon Van)',
                'activity_level' => 'Sightseeing',
                'rate' => '₱3,700',
                'description' => 'Travel around Boracay in climate-controlled luxury. Visit Keyhole, Puka Beach, Mangrove Park, and scenic heights without breaking a sweat.',
                'vibe_tags' => ['Air-conditioned', 'Comfort Tour', 'Family Friendly', 'VIP Sightseeing'],
                'ideal_for' => 'Families with Kids, Elderly Travelers, VIP Groups',
                'notes' => 'Good for up to 10 persons',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Parasailing',
                'activity_level' => 'Extreme',
                'rate' => '₱1,700/person',
                'description' => 'Soar 150 meters above the azure waters of Boracay! Experience breathtaking 360-degree aerial views of the entire 7km island while strapped securely into a parachute canopy.',
                'vibe_tags' => ['Panoramic Aerial Views', 'Extreme Height', 'High Flying', 'Insta360 Action'],
                'ideal_for' => 'Thrill Seekers, Couples, Adventure Lovers',
                'notes' => '2–3 flyers; includes free Insta360 camera use',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Parasailing (Solo)',
                'activity_level' => 'Extreme',
                'rate' => '₱2,500',
                'description' => 'Command the skies alone on a dedicated solo parasail flight over White Beach. Ultimate freedom and adrenaline in the air.',
                'vibe_tags' => ['Solo Extreme', 'High Flying', 'Panoramic Views', 'Insta360 Action'],
                'ideal_for' => 'Solo Thrill Seekers, Content Creators',
                'notes' => 'Solo flyer; includes free Insta360 camera use',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Banana Boat',
                'activity_level' => 'Adventure',
                'rate' => '₱450/person',
                'description' => 'Hold on tight as a high-speed speedboat pulls your giant yellow inflatable boat across waves. Bumpy, splashy, and laughter-filled fun!',
                'vibe_tags' => ['Splash & Fun', 'High Speed', 'Group Laughs', 'Barkada Favorite'],
                'ideal_for' => 'Barkadas, Friends, Active Families',
                'notes' => 'Minimum 6 persons; includes free Insta360 camera use',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'UFO Ride',
                'activity_level' => 'Adventure',
                'rate' => '₱650/person',
                'description' => 'Ride a floating, spinning saucer towed by a speedboat. Bounce off waves and hold on as centrifugal force spins you across the ocean surface!',
                'vibe_tags' => ['Spinning Action', 'Inflatable Watersport', 'Extreme Bouncing', 'Adrenaline'],
                'ideal_for' => 'Adrenaline Junkies, Young Adults, Groups',
                'notes' => 'Minimum 4 persons',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Jet Ski (15 mins)',
                'activity_level' => 'Extreme',
                'rate' => '₱2,200',
                'description' => 'Feel the power of high-performance personal watercraft as you race across open ocean waters. Take turns steering and riding the waves.',
                'vibe_tags' => ['High Speed', 'Self Drive', 'Wave Racing', 'Water Motorsport'],
                'ideal_for' => 'Couples, Speed Lovers, Motorsports Enthusiasts',
                'notes' => '1 unit for 2–3 persons (take turns driving); includes free Insta360 camera use',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Jet Ski (30 mins)',
                'activity_level' => 'Extreme',
                'rate' => '₱3,800',
                'description' => 'Double the time, double the adrenaline! 30 full minutes of high-speed jet ski action with plenty of time to explore the coast.',
                'vibe_tags' => ['Extended High Speed', 'Wave Racing', 'Water Motorsport', 'Insta360 Action'],
                'ideal_for' => 'Motorsports Fans, Couples, Adventure Seekers',
                'notes' => '1 unit for 2–3 persons (take turns driving); includes free Insta360 camera use',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Party Yacht',
                'activity_level' => 'Relaxing',
                'rate' => '₱1,300/person',
                'description' => 'Set sail on a stylish multi-deck catamaran yacht. Dance to DJ music, enjoy drinks & appetizers, swim off the stern, and watch Boracay\'s legendary sunset from the deck.',
                'vibe_tags' => ['VIP Sunset Cruise', 'Yacht Party', 'Music & Drinks', 'Luxury Sailing'],
                'ideal_for' => 'Party Lovers, Couples, Celebration Groups, VIP Travelers',
                'notes' => 'Private party cruise',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Party Boat',
                'activity_level' => 'Relaxing',
                'rate' => '₱800/person',
                'description' => 'Join fellow fun-loving travelers on a lively ocean party cruise featuring music, water slides, paddleboards, and sunset golden hour vibes.',
                'vibe_tags' => ['Social Party', 'Sunset Golden Hour', 'Water Slides', 'Music & Fun'],
                'ideal_for' => 'Solo Backpackers, Social Groups, Youth Travelers',
                'notes' => 'Group party cruise',
            ],
            [
                'category' => 'Diving',
                'activity_name' => 'Helmet Diving',
                'activity_level' => 'Underwater',
                'rate' => '₱850/person',
                'description' => 'Walk on the ocean floor 10 feet underwater! Special heavy air-helmet lets you breathe normally while tropical fish swim around your hands.',
                'vibe_tags' => ['Ocean Floor Walk', 'No Swim Required', 'Fish Feeding', 'Underwater Photography'],
                'ideal_for' => 'Non-swimmers, Beginners, Families, Kids',
                'notes' => 'LGU Area',
            ],
            [
                'category' => 'Diving',
                'activity_name' => 'Scuba Diving',
                'activity_level' => 'Underwater',
                'rate' => '₱1,350/person',
                'description' => 'Discover Scuba Diving for beginners with PADI certified divemasters. Dive into coral reefs, interact with marine life, and experience weightless underwater flight.',
                'vibe_tags' => ['PADI Guided', 'Coral Reef Dive', 'Marine Life', 'Deep Underwater'],
                'ideal_for' => 'Ocean Enthusiasts, Adventure Lovers, Aspiring Divers',
                'notes' => 'Includes underwater diving experience',
            ],
            [
                'category' => 'Adventure',
                'activity_name' => 'ATV + Zipline Package (Shingley Company)',
                'activity_level' => 'Adventure',
                'rate' => '₱1,100/person',
                'description' => 'Combines off-road quad bike riding through rugged mountain trails with a high-flying zip line ride across green valleys on mainland Aklan.',
                'vibe_tags' => ['Off-Road ATV', 'Zipline Flight', 'Mountain Combo', 'Adrenaline Combo'],
                'ideal_for' => 'Adventure Groups, Thrill Seekers, Outdoors Enthusiasts',
                'notes' => 'Mainland',
            ],
            [
                'category' => 'Adventure',
                'activity_name' => 'ATV + Zipline Package (Mega Paraw Company)',
                'activity_level' => 'Adventure',
                'rate' => '₱1,000/person',
                'description' => 'Action-packed combo feature: power your quad bike through dirt tracks followed by a thrilling zipline descent.',
                'vibe_tags' => ['Off-Road ATV', 'Zipline Adventure', 'Outdoor Thrills'],
                'ideal_for' => 'Barkadas, Active Travelers',
                'notes' => 'Mainland',
            ],
            [
                'category' => 'Adventure',
                'activity_name' => 'ATV Only (Mainland)',
                'activity_level' => 'Adventure',
                'rate' => '₱850',
                'description' => 'Conquer mud, rocks, and winding dirt paths driving your own 4x4 all-terrain vehicle through rural mainland landscapes.',
                'vibe_tags' => ['Off-Road 4x4', 'Trail Riding', 'Dirt & Mud Action'],
                'ideal_for' => 'Motorsports Fans, Adventure Enthusiasts',
                'notes' => 'Off-road ATV ride',
            ],
            [
                'category' => 'Adventure',
                'activity_name' => 'Zipline Only',
                'activity_level' => 'Adventure',
                'rate' => '₱750',
                'description' => 'Fly superman-style across a 600-meter cable line high above the trees with stunning coastline views.',
                'vibe_tags' => ['High Aerial Cable', 'Superman Flight', 'Forest Canopy'],
                'ideal_for' => 'Families, Youth, Adventure Seekers',
                'notes' => 'Zipline experience',
            ],
            [
                'category' => 'Adventure',
                'activity_name' => 'ATV (New Coast)',
                'activity_level' => 'Adventure',
                'rate' => '₱1,500/person',
                'description' => 'Guided premium ATV tour through Boracay Newcoast\'s private roads, pine trees, and exclusive private beach viewing deck.',
                'vibe_tags' => ['Premium ATV Trail', 'Private Coastline', 'Scenic Ocean Deck'],
                'ideal_for' => 'Couples, Photo Seekers, Premium Explorers',
                'notes' => 'Premium ATV trail',
            ],
            [
                'category' => 'Sailing',
                'activity_name' => 'Paraw Sailing (Private)',
                'activity_level' => 'Relaxing',
                'rate' => '₱750/person',
                'description' => 'Charter an authentic outrigger sailboat driven by breeze alone. Glide peacefully along White Beach during golden hour for unforgettable sunset photos.',
                'vibe_tags' => ['Traditional Sailboat', 'Private Sunset Cruise', 'Eco Wind Sailing', 'Romantic Golden Hour'],
                'ideal_for' => 'Couples, Honeymooners, Families',
                'notes' => 'Minimum 4 persons',
            ],
            [
                'category' => 'Sailing',
                'activity_name' => 'Paraw Sailing (Joiners)',
                'activity_level' => 'Relaxing',
                'rate' => '₱850/person',
                'description' => 'Experience traditional Filipino wind sailing on an outrigger boat shared with fellow sunset seekers.',
                'vibe_tags' => ['Wind Sailing', 'Sunset Views', 'Cultural Boat', 'Shared Cruise'],
                'ideal_for' => 'Solo Travelers, Small Groups, Sunset Lovers',
                'notes' => 'Shared sailing experience',
            ],
            [
                'category' => 'Other',
                'activity_name' => 'Crystal Kayak',
                'activity_level' => 'Relaxing',
                'rate' => '₱200–₱300/person',
                'description' => 'Paddle a completely clear, transparent kayak over crystal waters with local guide photo assistance. Get stunning Instagram photo shoots.',
                'vibe_tags' => ['Transparent Kayak', 'Instagram Photoshoot', 'Crystal Water', 'Guide Assistance'],
                'ideal_for' => 'Content Creators, Couples, Solo Travelers, Photo Lovers',
                'notes' => 'Price depends on the area',
            ],
        ];

        foreach ($activities as $actData) {
            $activity = ActivityModel::updateOrCreate(
                [
                    'destination_id' => $boracay->id,
                    'activity_name' => $actData['activity_name']
                ],
                [
                    'category' => $actData['category'],
                    'activity_level' => $actData['activity_level'],
                    'rate' => $actData['rate'],
                    'description' => $actData['description'],
                    'vibe_tags' => $actData['vibe_tags'],
                    'ideal_for' => $actData['ideal_for'],
                    'notes' => $actData['notes'],
                ]
            );

            // Re-generate vector embedding with rich metadata
            $text = $geminiService->buildActivityEmbeddingText($activity, $boracay->name);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $activity->activity_name);
            if ($vector) {
                $activity->embedding = $geminiService->formatVectorForDb($vector);
                $activity->save();
            }
        }
    }
}
