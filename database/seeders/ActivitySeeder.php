<?php

namespace Database\Seeders;

use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Services\GeminiService;
use Illuminate\Database\Seeder;

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
                'activity_name' => 'Joiners Island Hopping',
                'category' => 'Island Hopping',
                'activity_level' => 'Relaxing',
                'rate' => '₱850/person',
                'duration' => 'Full Day (9:00 AM - 4:00 PM)',
                'capacity' => 'Joiner Group (Max 20 pax)',
                'requirements' => 'Bring own swimwear and towel.',
                'description' => 'Explore Boracay\'s surrounding pristine islands including Crocodile Island, Puka Beach, and Magic Island. Enjoy swimming, snorkeling in coral gardens, and a hot kawa bath experience with a complimentary seafood & BBQ buffet lunch.',
                'vibe_tags' => [
                    'Snorkeling',
                    'Island Hopping',
                    'Buffet Lunch',
                    'Kawa Bath',
                    'Group Friendly',
                ],
                'ideal_for' => 'Budget Travelers, Families, Social Groups, Solo Explorers',
                'inclusions' => [
                    'Boat transfer',
                    'Buffet Lunch',
                    'Kawa Bath',
                    'Tour Guide',
                ],
                'exclusions' => [
                    'Snorkeling Fee (₱40)',
                    'Crystal Cove Entrance (₱300)',
                    'Magic Island Cliff Diving (₱250)',
                ],
                'itinerary' => [
                    [
                        'title' => 'Meetup at Station 2',
                        'duration' => '30 mins',
                    ],
                    [
                        'title' => 'Puka Beach',
                        'duration' => '1 hour',
                    ],
                    [
                        'title' => 'Crocodile Island Snorkeling',
                        'duration' => '1 hour',
                    ],
                    [
                        'title' => 'Buffet Lunch & Kawa Bath',
                        'duration' => '1.5 hours',
                    ],
                    [
                        'title' => 'Magic Island (Optional)',
                        'duration' => '1 hour',
                    ],
                ],
                'notes' => 'Includes free buffet lunch + kawa bath',
                'images' => [
                    'activities/m9zngtqONBYUQeyIqeAYxhp2OUyjmeBxWWoKzqKn.webp',
                ],
                'is_shown' => true,
                'latitude' => 11.96580000,
                'longitude' => 121.92600000,
                'specific_address' => null,
            ],

            [
                'activity_name' => '6–7 Destinations (Aircon Van)',
                'category' => 'Land Tour',
                'activity_level' => 'Sightseeing',
                'rate' => '₱3,700',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Travel around Boracay in climate-controlled luxury. Visit Keyhole, Puka Beach, Mangrove Park, and scenic heights without breaking a sweat.',
                'vibe_tags' => [
                    'Air-conditioned',
                    'Comfort Tour',
                    'Family Friendly',
                    'VIP Sightseeing',
                ],
                'ideal_for' => 'Families with Kids, Elderly Travelers, VIP Groups',
                'inclusions' => [],
                'exclusions' => [],
                'itinerary' => [],
                'notes' => 'Good for up to 10 persons',
                'images' => null,
                'is_shown' => true,
                'latitude' => 11.95950000,
                'longitude' => 121.92900000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Parasailing',
                'category' => 'Water Activity',
                'activity_level' => 'Extreme',
                'rate' => '₱1,700/person',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Soar 150 meters above the azure waters of Boracay! Experience breathtaking 360-degree aerial views of the entire 7km island while strapped securely into a parachute canopy.',
                'vibe_tags' => [
                    'Panoramic Aerial Views',
                    'Extreme Height',
                    'High Flying',
                    'Insta360 Action',
                ],
                'ideal_for' => 'Thrill Seekers, Couples, Adventure Lovers',
                'inclusions' => null,
                'exclusions' => null,
                'itinerary' => null,
                'notes' => '2–3 flyers; includes free Insta360 camera use',
                'images' => [
                    'activities/ohkju3kWrlo9sDS7tCAin10BYw7rJ4A4bJOSXVs7.jpg',
                ],
                'is_shown' => true,
                'latitude' => 11.97020000,
                'longitude' => 121.92930000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Banana Boat',
                'category' => 'Water Activity',
                'activity_level' => 'Adventure',
                'rate' => '₱450/person',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Hold on tight as a high-speed speedboat pulls your giant yellow inflatable boat across waves. Bumpy, splashy, and laughter-filled fun!',
                'vibe_tags' => [
                    'Splash & Fun',
                    'High Speed',
                    'Group Laughs',
                    'Barkada Favorite',
                ],
                'ideal_for' => 'Barkadas, Friends, Active Families',
                'inclusions' => null,
                'exclusions' => null,
                'itinerary' => null,
                'notes' => 'Minimum 6 persons; includes free Insta360 camera use',
                'images' => [
                    'activities/sJzTvatHKSWRmsy58CR8e7ZAy9vNAISX9UFmt3A8.webp',
                ],
                'is_shown' => true,
                'latitude' => 11.95950000,
                'longitude' => 121.92900000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'UFO Ride',
                'category' => 'Water Activity',
                'activity_level' => 'Adventure',
                'rate' => '₱650/person',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Ride a floating, spinning saucer towed by a speedboat. Bounce off waves and hold on as centrifugal force spins you across the ocean surface!',
                'vibe_tags' => [
                    'Spinning Action',
                    'Inflatable Watersport',
                    'Extreme Bouncing',
                    'Adrenaline',
                ],
                'ideal_for' => 'Adrenaline Junkies, Young Adults, Groups',
                'inclusions' => null,
                'exclusions' => null,
                'itinerary' => null,
                'notes' => 'Minimum 4 persons',
                'images' => [
                    'activities/0zOLIV7jf9tEDghZv4MIJBXZbgh3pGFbj3dEDcly.jpg',
                ],
                'is_shown' => true,
                'latitude' => 11.96800000,
                'longitude' => 121.92480000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Jet Ski (30 mins)',
                'category' => 'Water Activity',
                'activity_level' => 'Extreme',
                'rate' => '₱3,800',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Double the time, double the adrenaline! 30 full minutes of high-speed jet ski action with plenty of time to explore the coast.',
                'vibe_tags' => [
                    'Extended High Speed',
                    'Wave Racing',
                    'Water Motorsport',
                    'Insta360 Action',
                ],
                'ideal_for' => 'Motorsports Fans, Couples, Adventure Seekers',
                'inclusions' => null,
                'exclusions' => null,
                'itinerary' => null,
                'notes' => '1 unit for 2–3 persons (take turns driving); includes free Insta360 camera use',
                'images' => [
                    'activities/5HjQx0j2woeVmYF27SySd1MXs1FDG5jTKnjAQD2A.jpg',
                ],
                'is_shown' => true,
                'latitude' => 11.96850000,
                'longitude' => 121.92980000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Party Yacht',
                'category' => 'Water Activity',
                'activity_level' => 'Relaxing',
                'rate' => '₱1,300/person',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Set sail on a stylish multi-deck catamaran yacht. Dance to DJ music, enjoy drinks & appetizers, swim off the stern, and watch Boracay\'s legendary sunset from the deck.',
                'vibe_tags' => [
                    'VIP Sunset Cruise',
                    'Yacht Party',
                    'Music & Drinks',
                    'Luxury Sailing',
                ],
                'ideal_for' => 'Party Lovers, Couples, Celebration Groups, VIP Travelers',
                'inclusions' => [],
                'exclusions' => [],
                'itinerary' => [],
                'notes' => 'Private party cruise',
                'images' => null,
                'is_shown' => true,
                'latitude' => 11.95900000,
                'longitude' => 121.92850000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Party Boat',
                'category' => 'Water Activity',
                'activity_level' => 'Relaxing',
                'rate' => '₱800/person',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Join fellow fun-loving travelers on a lively ocean party cruise featuring music, water slides, paddleboards, and sunset golden hour vibes.',
                'vibe_tags' => [
                    'Social Party',
                    'Sunset Golden Hour',
                    'Water Slides',
                    'Music & Fun',
                ],
                'ideal_for' => 'Solo Backpackers, Social Groups, Youth Travelers',
                'inclusions' => [],
                'exclusions' => [],
                'itinerary' => [],
                'notes' => 'Group party cruise',
                'images' => null,
                'is_shown' => true,
                'latitude' => 11.95850000,
                'longitude' => 121.92880000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Helmet Diving',
                'category' => 'Diving',
                'activity_level' => 'Underwater',
                'rate' => '₱850/person',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Walk on the ocean floor 10 feet underwater! Special heavy air-helmet lets you breathe normally while tropical fish swim around your hands.',
                'vibe_tags' => [
                    'Ocean Floor Walk',
                    'No Swim Required',
                    'Fish Feeding',
                    'Underwater Photography',
                ],
                'ideal_for' => 'Non-swimmers, Beginners, Families, Kids',
                'inclusions' => [],
                'exclusions' => [],
                'itinerary' => [],
                'notes' => 'LGU Area',
                'images' => null,
                'is_shown' => false,
                'latitude' => 11.97200000,
                'longitude' => 121.92450000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Scuba Diving',
                'category' => 'Diving',
                'activity_level' => 'Underwater',
                'rate' => '₱1,350/person',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Discover Scuba Diving for beginners with PADI certified divemasters. Dive into coral reefs, interact with marine life, and experience weightless underwater flight.',
                'vibe_tags' => [
                    'PADI Guided',
                    'Coral Reef Dive',
                    'Marine Life',
                    'Deep Underwater',
                ],
                'ideal_for' => 'Ocean Enthusiasts, Adventure Lovers, Aspiring Divers',
                'inclusions' => null,
                'exclusions' => null,
                'itinerary' => [
                    [
                        'title' => 'Location for Scuba Diving: BICOL',
                        'duration' => '15 mins',
                    ],
                ],
                'notes' => 'Includes underwater diving experience',
                'images' => [
                    'activities/lD6Whjc4gUaGrLSgzEaGFAFbxUEuYGwTzo3ASoIB.jpg',
                ],
                'is_shown' => true,
                'latitude' => 11.97000000,
                'longitude' => 121.92600000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'ATV',
                'category' => 'Adventure',
                'activity_level' => 'Adventure',
                'rate' => '₱850',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Conquer mud, rocks, and winding dirt paths driving your own 4x4 all-terrain vehicle through rural mainland landscapes.',
                'vibe_tags' => [
                    'Off-Road 4x4',
                    'Trail Riding',
                    'Dirt & Mud Action',
                ],
                'ideal_for' => 'Motorsports Fans, Adventure Enthusiasts',
                'inclusions' => null,
                'exclusions' => null,
                'itinerary' => null,
                'notes' => 'Off-road ATV ride',
                'images' => [
                    'activities/OgpzwMtxbtkt7MYWb79KpgUW50z8G3gNlhmuT4xU.webp',
                ],
                'is_shown' => true,
                'latitude' => 11.92800000,
                'longitude' => 121.94600000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Zipline',
                'category' => 'Adventure',
                'activity_level' => 'Adventure',
                'rate' => '₱750',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Fly superman-style across a 600-meter cable line high above the trees with stunning coastline views.',
                'vibe_tags' => [
                    'High Aerial Cable',
                    'Superman Flight',
                    'Forest Canopy',
                ],
                'ideal_for' => 'Families, Youth, Adventure Seekers',
                'inclusions' => null,
                'exclusions' => null,
                'itinerary' => null,
                'notes' => 'Zipline experience',
                'images' => [
                    'activities/DrBFIsdFqxpcxLxm75TbJBZKnhkjpnUXJE9fBer4.jpg',
                ],
                'is_shown' => true,
                'latitude' => 11.92750000,
                'longitude' => 121.94550000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Paraw Sailing (Private)',
                'category' => 'Sailing',
                'activity_level' => 'Relaxing',
                'rate' => '₱750/person',
                'duration' => null,
                'capacity' => null,
                'requirements' => null,
                'description' => 'Charter an authentic outrigger sailboat driven by breeze alone. Glide peacefully along White Beach during golden hour for unforgettable sunset photos.',
                'vibe_tags' => [
                    'Traditional Sailboat',
                    'Private Sunset Cruise',
                    'Eco Wind Sailing',
                    'Romantic Golden Hour',
                ],
                'ideal_for' => 'Couples, Honeymooners, Families',
                'inclusions' => [],
                'exclusions' => [],
                'itinerary' => [],
                'notes' => 'Minimum 4 persons',
                'images' => null,
                'is_shown' => true,
                'latitude' => 11.96580000,
                'longitude' => 121.92550000,
                'specific_address' => null,
            ],

            [
                'activity_name' => 'Crystal Kayak - Boracay',
                'category' => 'Other',
                'activity_level' => 'Relaxing',
                'rate' => '₱300/person',
                'duration' => '30 mins',
                'capacity' => '1 Pax',
                'requirements' => null,
                'description' => 'Paddle a completely clear, transparent kayak over crystal waters with local guide photo assistance. Get stunning Instagram photo shoots.',
                'vibe_tags' => [
                    'Transparent Kayak',
                    'Instagram Photoshoot',
                    'Crystal Water',
                    'Guide Assistance',
                ],
                'ideal_for' => 'Content Creators, Couples, Solo Travelers, Photo Lovers',
                'inclusions' => null,
                'exclusions' => null,
                'itinerary' => null,
                'notes' => 'Price depends on the area',
                'images' => [
                    'activities/cDpnzLNUl2TBaQrbzAhROLXNGlfWaHWACElNrr3J.jpg',
                ],
                'is_shown' => true,
                'latitude' => 11.96000000,
                'longitude' => 121.92860000,
                'specific_address' => null,
            ],
        ];

        foreach ($activities as $actData) {
            $name = $actData['activity_name'];
            unset($actData['activity_name']);

            $activity = ActivityModel::updateOrCreate(
                [
                    'destination_id' => $boracay->id,
                    'activity_name' => $name,
                ],
                $actData
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
