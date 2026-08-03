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
                'description' => 'The most popular island-hopping tour in El Nido. Explore the breathtaking Big Lagoon, Secret Lagoon, Shimizu Island, and Seven Commandos Beach. Enjoy kayaking through towering limestone cliffs and snorkeling in pristine waters.',
                'vibe_tags' => ['Lagoons', 'Kayaking', 'Snorkeling', 'Limestone Cliffs', 'Must-Do'],
                'ideal_for' => 'First-time Visitors, Families, Nature Lovers, Barkadas',
                'notes' => 'Includes buffet lunch. Eco-tourism Development Fee (ETDF) and Kayak rental usually excluded.',
            ],
            [
                'category' => 'Island Hopping',
                'activity_name' => 'El Nido Tour B (Caves & Coves)',
                'activity_level' => 'Adventure',
                'rate' => '₱1,300/person',
                'description' => 'Discover the historical and geographical wonders of Bacuit Bay. Walk along the famous Snake Island sandbar, explore Cudugnon Cave and Cathedral Cave, and relax at Pinagbuyutan Island.',
                'vibe_tags' => ['Caves', 'Sandbar', 'Historical', 'Sightseeing'],
                'ideal_for' => 'Explorers, Photography Enthusiasts, Couples',
                'notes' => 'Includes buffet lunch. Great for those who love unique rock formations.',
            ],
            [
                'category' => 'Island Hopping',
                'activity_name' => 'El Nido Tour C (Hidden Beaches & Shrines)',
                'activity_level' => 'Adventure',
                'rate' => '₱1,400/person',
                'description' => 'Known for its stunning hidden beaches and snorkeling spots. Visit Hidden Beach, Secret Beach, Matinloc Shrine, Talisay Beach, and Helicopter Island. Offers some of the best underwater views in El Nido.',
                'vibe_tags' => ['Hidden Beaches', 'Top Snorkeling', 'Marine Life', 'Iconic Views'],
                'ideal_for' => 'Strong Swimmers, Snorkelers, Adventure Seekers',
                'notes' => 'Includes buffet lunch. Waves can be a bit rough depending on the season.',
            ],
            [
                'category' => 'Island Hopping',
                'activity_name' => 'El Nido Tour D (Island Beaches)',
                'activity_level' => 'Relaxing',
                'rate' => '₱1,200/person',
                'description' => 'A more relaxed tour focusing on beautiful sandy beaches and laid-back swimming. Visit Small Lagoon, Nat Nat Beach, Cadlao Lagoon, Pasandigan Beach, and Paradise Beach.',
                'vibe_tags' => ['Relaxing', 'Beaches', 'Chill', 'Kayaking'],
                'ideal_for' => 'Families with kids, Travelers seeking a quiet escape',
                'notes' => 'Includes buffet lunch. Less crowded than Tour A and C.',
            ],
            [
                'category' => 'Adventure',
                'activity_name' => 'Taraw Cliff Canopy Walk',
                'activity_level' => 'Extreme',
                'rate' => '₱500/person',
                'description' => 'Conquer the jagged limestone cliffs of El Nido town via a safe suspension bridge and canopy walkway. Reach the view deck for an incredible panoramic shot of the entire Bacuit Bay.',
                'vibe_tags' => ['Viewpoint', 'Suspension Bridge', 'Panoramic Views', 'Limestone'],
                'ideal_for' => 'Thrill Seekers, Photographers, Active Travelers',
                'notes' => 'Requires harness (provided). Wear proper footwear.',
            ],
            [
                'category' => 'Land Tour',
                'activity_name' => 'Nacpan Beach Inland Tour',
                'activity_level' => 'Relaxing',
                'rate' => '₱600/person (Joiner) | ₱1,500 (Private Tricycle)',
                'description' => 'Travel north of the main town to visit the world-famous Nacpan Beach, known for its 4-kilometer stretch of golden sand and clear blue waters. Perfect for swimming, sunbathing, and sunset watching.',
                'vibe_tags' => ['Long Beach', 'Golden Sand', 'Sunset Views', 'Roadtrip'],
                'ideal_for' => 'Beach Bums, Couples, Solo Travelers',
                'notes' => 'Food and drinks available at beachside restobars.',
            ],
            [
                'category' => 'Diving',
                'activity_name' => 'Discover Scuba Diving (DSD)',
                'activity_level' => 'Underwater',
                'rate' => '₱4,500/person',
                'description' => 'Experience breathing underwater for the first time with a certified PADI instructor. Explore El Nido’s vibrant coral reefs, sea turtles, and colorful marine life without needing a certification.',
                'vibe_tags' => ['PADI Guided', 'Coral Reefs', 'Sea Turtles', 'Deep Underwater'],
                'ideal_for' => 'Ocean Enthusiasts, Beginners, Adventure Lovers',
                'notes' => 'Includes 2 dives, equipment, and lunch.',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Clear Kayak Rental',
                'activity_level' => 'Relaxing',
                'rate' => '₱300 - ₱500/hour',
                'description' => 'Rent a completely transparent kayak to paddle around the lagoons or shoreline. Get stunning, unobstructed views of the corals and fishes beneath you, plus amazing Instagram-worthy drone-style photos.',
                'vibe_tags' => ['Transparent Kayak', 'Instagram Photoshoot', 'Crystal Water', 'Scenic Paddling'],
                'ideal_for' => 'Content Creators, Couples, Photo Lovers',
                'notes' => 'Usually available during Tour A (Big Lagoon) or around Corong-Corong.',
            ],
            [
                'category' => 'Sailing',
                'activity_name' => 'Sunset Cruise / Party Boat',
                'activity_level' => 'Relaxing',
                'rate' => '₱1,500/person',
                'description' => 'Sail along the coast of Bacuit Bay as the sun goes down. Enjoy free-flowing drinks, acoustic music or DJ sets, and a relaxing vibe with fellow travelers.',
                'vibe_tags' => ['Sunset Golden Hour', 'Social Party', 'Music & Drinks', 'Yacht Vibe'],
                'ideal_for' => 'Groups, Solo Backpackers, Couples',
                'notes' => 'Includes some drinks and pica-pica.',
            ],
            [
                'category' => 'Water Activity',
                'activity_name' => 'Stand-Up Paddleboarding (SUP)',
                'activity_level' => 'Adventure',
                'rate' => '₱400/hour',
                'description' => 'Rent a paddleboard and glide along the calm waters of Corong-Corong or Lio Beach. A great core workout and a peaceful way to explore the coastline at your own pace.',
                'vibe_tags' => ['Paddleboarding', 'Core Workout', 'Coastline Exploration', 'Eco-Friendly'],
                'ideal_for' => 'Active Travelers, Solo Explorers, Nature Lovers',
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
                    'description' => $actData['description'],
                    'vibe_tags' => $actData['vibe_tags'],
                    'ideal_for' => $actData['ideal_for'],
                    'notes' => $actData['notes'],
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