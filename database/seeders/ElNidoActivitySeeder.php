<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Services\GeminiService;

class ElNidoActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(GeminiService $geminiService): void
    {
        $elnido = DestinationModel::firstOrCreate(
            ['name' => 'El Nido'],
        );

        $activities = [
            [
                'category' => 'Island Hopping',
                'activity_name' => 'El Nido Tour A (Lagoons & Beaches)',
                'activity_level' => 'Adventure',
                'rate' => '₱1,200/person',
                'duration' => 'Full Day (9:00 AM - 4:00 PM)',
                'capacity' => 'Joiner Group (Max 15-20 pax)',
                'requirements' => 'ETDF Eco-Tourism Ticket, Swimwear, Waterproof Bag',
                'description' => 'The most popular island-hopping tour in El Nido. Explore the breathtaking Big Lagoon, Secret Lagoon, Shimizu Island, and Seven Commandos Beach. Enjoy kayaking through towering limestone cliffs and snorkeling in pristine waters.',
                'vibe_tags' => ['Lagoons', 'Kayaking', 'Snorkeling', 'Limestone Cliffs', 'Must-Do'],
                'ideal_for' => 'First-time Visitors, Families, Nature Lovers, Barkadas',
                'inclusions' => ['Boat Transfer', 'Buffet Lunch', 'Life Vest', 'Licensed Tour Guide'],
                'exclusions' => ['ETDF Eco-Tourism Fee (₱200)', 'Big Lagoon User Fee (₱200)', 'Kayak Rental (₱250–₱300)'],
                'itinerary' => [
                    ['title' => 'Assembly & Port Departure', 'duration' => '30 mins'],
                    ['title' => 'Big Lagoon Kayaking', 'duration' => '1.5 hours'],
                    ['title' => 'Shimizu Island Buffet Lunch', 'duration' => '1 hour'],
                    ['title' => 'Secret Lagoon Exploration', 'duration' => '1 hour'],
                    ['title' => 'Seven Commandos Beach Swim & Relax', 'duration' => '1 hour'],
                ],
                'notes' => 'Includes buffet lunch. Eco-tourism Development Fee (ETDF) and Kayak rental usually excluded.',
            ],
            [
                'category' => 'Island Hopping',
                'activity_name' => 'El Nido Tour B (Caves & Coves)',
                'activity_level' => 'Adventure',
                'rate' => '₱1,300/person',
                'duration' => 'Full Day (9:00 AM - 4:00 PM)',
                'capacity' => 'Joiner Group (Max 15-20 pax)',
                'requirements' => 'Aqua Shoes for cave exploration, Waterproof Bag',
                'description' => 'Discover the historical and geographical wonders of Bacuit Bay. Walk along the famous Snake Island sandbar, explore Cudugnon Cave and Cathedral Cave, and relax at Pinagbuyutan Island.',
                'vibe_tags' => ['Caves', 'Sandbar', 'Historical', 'Sightseeing'],
                'ideal_for' => 'Explorers, Photography Enthusiasts, Couples',
                'inclusions' => ['Boat Transfer', 'Buffet Lunch', 'Life Vest', 'Tour Guide'],
                'exclusions' => ['ETDF Eco-Tourism Fee (₱200)', 'Cathedral Cave Entrance'],
                'itinerary' => [
                    ['title' => 'Boarding at Port', 'duration' => '30 mins'],
                    ['title' => 'Snake Island Sandbar Walk', 'duration' => '1.5 hours'],
                    ['title' => 'Cudugnon Cave Exploration', 'duration' => '1 hour'],
                    ['title' => 'Beachside Buffet Lunch', 'duration' => '1 hour'],
                    ['title' => 'Pinagbuyutan Island Relaxation', 'duration' => '1 hour'],
                ],
                'notes' => 'Includes buffet lunch. Great for those who love unique rock formations.',
            ],
            [
                'category' => 'Island Hopping',
                'activity_name' => 'El Nido Tour C (Hidden Beaches & Shrines)',
                'activity_level' => 'Adventure',
                'rate' => '₱1,400/person',
                'duration' => 'Full Day (9:00 AM - 4:00 PM)',
                'capacity' => 'Joiner Group (Max 15-20 pax)',
                'requirements' => 'Strong Swimming Skills Recommended, Aqua Shoes',
                'description' => 'Known for its stunning hidden beaches and snorkeling spots. Visit Hidden Beach, Secret Beach, Matinloc Shrine, Talisay Beach, and Helicopter Island. Offers some of the best underwater views in El Nido.',
                'vibe_tags' => ['Hidden Beaches', 'Top Snorkeling', 'Marine Life', 'Iconic Views'],
                'ideal_for' => 'Strong Swimmers, Snorkelers, Adventure Seekers',
                'inclusions' => ['Boat Transfer', 'Buffet Lunch', 'Life Vest', 'Tour Guide'],
                'exclusions' => ['ETDF Eco-Tourism Fee (₱200)', 'Matinloc Shrine Entrance (₱200)'],
                'itinerary' => [
                    ['title' => 'Depart Port', 'duration' => '30 mins'],
                    ['title' => 'Helicopter Island Snorkeling', 'duration' => '1 hour'],
                    ['title' => 'Secret Beach Swim-Through', 'duration' => '1 hour'],
                    ['title' => 'Talisay Beach Lunch Break', 'duration' => '1 hour'],
                    ['title' => 'Hidden Beach & Matinloc Shrine', 'duration' => '1.5 hours'],
                ],
                'notes' => 'Includes buffet lunch. Waves can be a bit rough depending on the season.',
            ],
            [
                'category' => 'Island Hopping',
                'activity_name' => 'El Nido Tour D (Island Beaches)',
                'activity_level' => 'Relaxing',
                'rate' => '₱1,200/person',
                'duration' => 'Full Day (9:00 AM - 4:00 PM)',
                'capacity' => 'Joiner Group (Max 15 pax)',
                'requirements' => 'Sunscreen, Swimwear, Towel',
                'description' => 'A more relaxed tour focusing on beautiful sandy beaches and laid-back swimming. Visit Small Lagoon, Nat Nat Beach, Cadlao Lagoon, Pasandigan Beach, and Paradise Beach.',
                'vibe_tags' => ['Relaxing', 'Beaches', 'Chill', 'Kayaking'],
                'ideal_for' => 'Families with kids, Travelers seeking a quiet escape',
                'inclusions' => ['Boat Transfer', 'Buffet Lunch', 'Life Vest', 'Guide'],
                'exclusions' => ['ETDF Eco-Tourism Fee (₱200)', 'Cadlao Lagoon Kayak Rental'],
                'itinerary' => [
                    ['title' => 'Port Assembly & Launching', 'duration' => '30 mins'],
                    ['title' => 'Cadlao Lagoon Kayaking', 'duration' => '1.5 hours'],
                    ['title' => 'Pasandingan Beach Lunch Break', 'duration' => '1 hour'],
                    ['title' => 'Paradise & Nat Nat Beach Chill', 'duration' => '2 hours'],
                ],
                'notes' => 'Includes buffet lunch. Less crowded than Tour A and C.',
            ],
            [
                'category' => 'Adventure',
                'activity_name' => 'Taraw Cliff Canopy Walk',
                'activity_level' => 'Extreme',
                'rate' => '₱500/person',
                'duration' => '1 to 2 Hours',
                'capacity' => 'Small Groups / Individuals',
                'requirements' => 'Closed Shoes / Sturdy Footwear Required, Age 8+',
                'description' => 'Conquer the jagged limestone cliffs of El Nido town via a safe suspension bridge and canopy walkway. Reach the view deck for an incredible panoramic shot of the entire Bacuit Bay.',
                'vibe_tags' => ['Viewpoint', 'Suspension Bridge', 'Panoramic Views', 'Limestone'],
                'ideal_for' => 'Thrill Seekers, Photographers, Active Travelers',
                'inclusions' => ['Safety Harness & Helmet', 'Guide Fee', 'Canopy Walk Access Pass'],
                'exclusions' => ['Footwear Rental (if using slippers)', 'Personal Tips'],
                'itinerary' => [
                    ['title' => 'Safety Briefing & Harness Fitting', 'duration' => '15 mins'],
                    ['title' => 'Suspension Bridge Crossing', 'duration' => '30 mins'],
                    ['title' => 'Limestone View Deck Photo Session', 'duration' => '30 mins'],
                    ['title' => 'Descent back to base', 'duration' => '15 mins'],
                ],
                'notes' => 'Requires harness (provided). Wear proper footwear.',
            ],
            [
                'category' => 'Land Tour',
                'activity_name' => 'Nacpan Beach Inland Tour',
                'activity_level' => 'Relaxing',
                'rate' => '₱600/person (Joiner) | ₱1,500 (Private Tricycle)',
                'duration' => 'Half Day (4 to 5 Hours)',
                'capacity' => '1–4 pax (Tricycle) / Joiner Van',
                'requirements' => 'Comfortable Clothes, Towel, Sun Protection',
                'description' => 'Travel north of the main town to visit the world-famous Nacpan Beach, known for its 4-kilometer stretch of golden sand and clear blue waters. Perfect for swimming, sunbathing, and sunset watching.',
                'vibe_tags' => ['Long Beach', 'Golden Sand', 'Sunset Views', 'Roadtrip'],
                'ideal_for' => 'Beach Bums, Couples, Solo Travelers',
                'inclusions' => ['Roundtrip Land Transport', 'Driver / Local Guide'],
                'exclusions' => ['Nacpan Environmental Fee (₱50)', 'Food & Beverages'],
                'itinerary' => [
                    ['title' => 'Pick-up from El Nido Town', 'duration' => '45 mins'],
                    ['title' => 'Nacpan Twin Beach Walk & Swim', 'duration' => '2.5 hours'],
                    ['title' => 'Sunset at Sunmai Bar', 'duration' => '1 hour'],
                    ['title' => 'Return Drive to Town', 'duration' => '45 mins'],
                ],
                'notes' => 'Food and drinks available at beachside restobars.',
            ],
            [
                'category' => 'Diving',
                'activity_name' => 'Discover Scuba Diving (DSD)',
                'activity_level' => 'Underwater',
                'rate' => '₱4,500/person',
                'duration' => 'Full Day (Includes 2 Dives)',
                'capacity' => 'Small Group (Max 4 students per instructor)',
                'requirements' => 'Good Physical Health, No flight within 18 hours after dive',
                'description' => 'Experience breathing underwater for the first time with a certified PADI instructor. Explore El Nido’s vibrant coral reefs, sea turtles, and colorful marine life without needing a certification.',
                'vibe_tags' => ['PADI Guided', 'Coral Reefs', 'Sea Turtles', 'Deep Underwater'],
                'ideal_for' => 'Ocean Enthusiasts, Beginners, Adventure Lovers',
                'inclusions' => ['Full Scuba Gear Rental', 'PADI Instructor Guide', '2 Boat Dives', 'Onboard Lunch & Drinks'],
                'exclusions' => ['Marine Park Diver Permit Fee'],
                'itinerary' => [
                    ['title' => 'Dive Shop Briefing & Gear Fitting', 'duration' => '1 hour'],
                    ['title' => 'Shallow Water Skills Practice', 'duration' => '45 mins'],
                    ['title' => 'First Coral Reef Dive', 'duration' => '45 mins'],
                    ['title' => 'Onboard Lunch & Rest', 'duration' => '1 hour'],
                    ['title' => 'Second Coral Reef Dive', 'duration' => '45 mins'],
                ],
                'notes' => 'Includes 2 dives, equipment, and lunch.',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Clear Kayak Rental',
                'activity_level' => 'Relaxing',
                'rate' => '₱300 - ₱500/hour',
                'duration' => '1 Hour',
                'capacity' => '1–2 Persons',
                'requirements' => 'Swimwear, Life vest',
                'description' => 'Rent a completely transparent kayak to paddle around the lagoons or shoreline. Get stunning, unobstructed views of the corals and fishes beneath you, plus amazing Instagram-worthy drone-style photos.',
                'vibe_tags' => ['Transparent Kayak', 'Instagram Photoshoot', 'Crystal Water', 'Scenic Paddling'],
                'ideal_for' => 'Content Creators, Couples, Photo Lovers',
                'inclusions' => ['Clear Kayak Rental', 'Paddles', 'Life Vests'],
                'exclusions' => ['Photo / Drone Operator Fee'],
                'itinerary' => [
                    ['title' => 'Safety Briefing & Kayak Handover', 'duration' => '10 mins'],
                    ['title' => 'Freestyle Kayaking & Photoshoot', 'duration' => '50 mins'],
                ],
                'notes' => 'Usually available during Tour A (Big Lagoon) or around Corong-Corong.',
            ],
            [
                'category' => 'Sailing',
                'activity_name' => 'Sunset Cruise / Party Boat',
                'activity_level' => 'Relaxing',
                'rate' => '₱1,500/person',
                'duration' => '3 Hours (4:00 PM - 7:00 PM)',
                'capacity' => 'Joiner Group (Max 30 pax)',
                'requirements' => 'Beach party wear',
                'description' => 'Sail along the coast of Bacuit Bay as the sun goes down. Enjoy free-flowing drinks, acoustic music or DJ sets, and a relaxing vibe with fellow travelers.',
                'vibe_tags' => ['Sunset Golden Hour', 'Social Party', 'Music & Drinks', 'Yacht Vibe'],
                'ideal_for' => 'Groups, Solo Backpackers, Couples',
                'inclusions' => ['Welcome Cocktails', 'Pica-Pica / Appetizers', 'Music & DJ Set', 'Boat Crew'],
                'exclusions' => ['Premium Spirits'],
                'itinerary' => [
                    ['title' => 'Boarding at Corong-Corong Beach', 'duration' => '20 mins'],
                    ['title' => 'Coastal Sailing & Swimming Stop', 'duration' => '1 hour'],
                    ['title' => 'Sunset Golden Hour Toast', 'duration' => '1 hour'],
                    ['title' => 'Return Docking', 'duration' => '20 mins'],
                ],
                'notes' => 'Includes some drinks and pica-pica.',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Stand-Up Paddleboarding (SUP)',
                'activity_level' => 'Adventure',
                'rate' => '₱400/hour',
                'duration' => '1 Hour',
                'capacity' => '1 Person per board',
                'requirements' => 'Basic balance skills, Swimwear',
                'description' => 'Rent a paddleboard and glide along the calm waters of Corong-Corong or Lio Beach. A great core workout and a peaceful way to explore the coastline at your own pace.',
                'vibe_tags' => ['Paddleboarding', 'Core Workout', 'Coastline Exploration', 'Eco-Friendly'],
                'ideal_for' => 'Active Travelers, Solo Explorers, Nature Lovers',
                'inclusions' => ['SUP Board & Paddle Rental', 'Life Vest'],
                'exclusions' => ['Personal Instructor'],
                'itinerary' => [
                    ['title' => 'Brief Instruction & Launch', 'duration' => '10 mins'],
                    ['title' => 'Coastline Paddling Session', 'duration' => '50 mins'],
                ],
                'notes' => 'Best done early morning or late afternoon during sunset.',
            ],
        ];

        foreach ($activities as $actData) {
            $activity = ActivityModel::updateOrCreate(
                [
                    'destination_id' => $elnido->id,
                    'activity_name' => $actData['activity_name']
                ],
                [
                    'category' => $actData['category'],
                    'activity_level' => $actData['activity_level'],
                    'rate' => $actData['rate'],
                    'duration' => $actData['duration'] ?? null,
                    'capacity' => $actData['capacity'] ?? null,
                    'requirements' => $actData['requirements'] ?? null,
                    'description' => $actData['description'],
                    'vibe_tags' => $actData['vibe_tags'],
                    'ideal_for' => $actData['ideal_for'],
                    'inclusions' => $actData['inclusions'] ?? [],
                    'exclusions' => $actData['exclusions'] ?? [],
                    'itinerary' => $actData['itinerary'] ?? [],
                    'notes' => $actData['notes'] ?? null,
                ]
            );

            // Re-generate vector embedding with rich metadata using cosine similarity architecture
            $text = $geminiService->buildActivityEmbeddingText($activity, $elnido->name);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $activity->activity_name);
            if ($vector) {
                $activity->embedding = $geminiService->formatVectorForDb($vector);
                $activity->save();
            }
        }
    }
}