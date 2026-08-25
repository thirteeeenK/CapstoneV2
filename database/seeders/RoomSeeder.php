<?php

namespace Database\Seeders;

use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\GeminiService;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(GeminiService $geminiService): void
    {
        $henannPalm = HotelModel::where('hotel_name', 'Henann Palm Beach Resort')->first();
        $canyon = HotelModel::where('hotel_name', 'Canyon Hotels & Resorts Boracay')->first();
        $oceanClub = HotelModel::where('hotel_name', 'Boracay Ocean Club Beach Resort')->first();
        $henannGarden = HotelModel::where('hotel_name', 'Henann Garden Resort')->first();
        $myStation = HotelModel::where('hotel_name', 'My Station Hotel')->first();
        $frendz = HotelModel::where('hotel_name', 'Frendz Resort & Hostel')->first();
        $lazyDog = HotelModel::where('hotel_name', 'Lazy Dog Bed & Breakfast')->first();
        $happiness = HotelModel::where('hotel_name', 'Happiness Hostel')->first();
        $theMuse = HotelModel::where('hotel_name', 'The Muse Hotel')->first();

        $rooms = [
            // Henann Palm Beach Resort
            [
                'hotel_id' => $henannPalm?->id,
                'room_name' => 'Premier Ocean View Room',
                'description' => 'Spacious luxury oceanfront room with floor-to-ceiling glass doors opening directly onto a private balcony overlooking White Beach. Features marble bathroom, rainfall shower, and luxury toiletries.',
                'view_type' => 'Ocean View',
                'ideal_for' => 'Couples, Honeymooners, Luxury Seekers',
                'total_rooms' => 2,
                'occupancy' => 4,
                'base_occupancy' => 2,
                'max_occupancy' => 4,
                'extra_person_fee' => 0.00,
                'bed_configuration' => '1 King Bed',
                'room_size' => '40 sq.m',
                'room_amenities' => ['Balcony', 'Ocean View', 'Air Conditioning', 'Free Wi-Fi', 'Marble Bathroom', 'Rainfall Shower', 'Minibar', 'Smart TV'],
                'base_price' => 9500.00,
                'images' => ['hotels/hennan-palm-beach-resort/hen bed.jpg'],
            ],
            [
                'hotel_id' => $henannPalm?->id,
                'room_name' => 'Direct Pool Access Suite',
                'description' => 'Ground floor luxury room with step-out access straight from your private terrace into the resort swimming pool. Ultimate resort relaxation.',
                'view_type' => 'Pool View',
                'ideal_for' => 'Couples, Pool Enthusiasts',
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 3,
                'extra_person_fee' => 1100.00,
                'bed_configuration' => '1 King Bed or 2 Twin Beds',
                'room_size' => '45 sq.m',
                'room_amenities' => ['Direct Pool Access', 'Private Terrace', 'Air Conditioning', 'Free Wi-Fi', 'Bathtub', 'Minibar', 'Smart TV'],
                'base_price' => 11200.00,
                'images' => ['rooms/sample2.jpg'],
            ],

            // Canyon Hotels
            [
                'hotel_id' => $canyon?->id,
                'room_name' => 'Deluxe Suite',
                'description' => 'Cozy modern suite featuring warm wooden finishes, plush bedding, and proximity to D\'Mall shopping district. Quiet and serene atmosphere.',
                'view_type' => 'Garden View',
                'ideal_for' => 'Couples, Small Families',
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00,
                'bed_configuration' => '1 Queen Bed',
                'room_size' => '32 sq.m',
                'room_amenities' => ['Air Conditioning', 'Free Wi-Fi', 'Coffee Maker', 'Safe', 'Desk'],
                'base_price' => 5200.00,
                'images' => ['rooms/sample3.jpg'],
            ],

            // Boracay Ocean Club
            [
                'hotel_id' => $oceanClub?->id,
                'room_name' => 'Diamond Oceanfront Room',
                'description' => 'Tranquil beachfront sanctuary in Station 3. Panoramic sea views and immediate access to the resort\'s PADI diving facility.',
                'view_type' => 'Ocean View',
                'ideal_for' => 'Scuba Divers, Beach Lovers, Couples',
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00,
                'bed_configuration' => '1 King Bed',
                'room_size' => '38 sq.m',
                'room_amenities' => ['Ocean View', 'Balcony', 'Air Conditioning', 'Dive Locker', 'Free Wi-Fi'],
                'base_price' => 6800.00,
                'images' => ['rooms/sample4.jpg'],
            ],

            // Henann Garden
            [
                'hotel_id' => $henannGarden?->id,
                'room_name' => 'Premier Family Room',
                'description' => 'Large family-oriented room surrounding the resort lagoon pool. Multiple bed options and spacious lounge area.',
                'view_type' => 'Lagoon Pool View',
                'ideal_for' => 'Families, Large Groups',
                'total_rooms' => 2,
                'occupancy' => 4,
                'base_occupancy' => 2,
                'max_occupancy' => 4,
                'extra_person_fee' => 850.00,
                'bed_configuration' => '1 Queen Bed & 2 Twin Beds',
                'room_size' => '52 sq.m',
                'room_amenities' => ['Lagoon View', 'Balcony', 'Air Conditioning', 'Free Wi-Fi', 'Refrigerator', 'Bathtub'],
                'base_price' => 8400.00,
                'images' => ['rooms/sample5.jpg'],
            ],

            // My Station Hotel
            [
                'hotel_id' => $myStation?->id,
                'room_name' => 'Standard Double Room',
                'description' => 'Clean, efficient, and affordable accommodation in Station 1. High-speed internet and quick access to White Beach.',
                'view_type' => 'City View',
                'ideal_for' => 'Budget Travelers, Solo Explorers',
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00,
                'bed_configuration' => '1 Double Bed',
                'room_size' => '22 sq.m',
                'room_amenities' => ['Air Conditioning', 'Free Wi-Fi', 'En-suite Bathroom', 'Flat Screen TV'],
                'base_price' => 2200.00,
                'images' => ['rooms/sample6.jpg'],
            ],

            // Frendz Hostel
            [
                'hotel_id' => $frendz?->id,
                'room_name' => 'Social Mixed Dormitory Bed',
                'description' => 'Air-conditioned shared dorm bunk bed with privacy curtains, personal reading lamp, lockable storage, and access to nightly hostel social events.',
                'view_type' => 'Courtyard View',
                'ideal_for' => 'Solo Backpackers, Social Youth Travelers',
                'total_rooms' => 2,
                'occupancy' => 1,
                'base_occupancy' => 1,
                'max_occupancy' => 1,
                'extra_person_fee' => 0.00,
                'bed_configuration' => 'Single Bunk Bed',
                'room_size' => 'Shared 8-Bed Dorm',
                'room_amenities' => ['Privacy Curtain', 'Reading Lamp', 'Personal Locker', 'Shared Bathroom', 'Air Conditioning', 'Free Wi-Fi'],
                'base_price' => 850.00,
                'images' => ['rooms/sample7.jpg'],
            ],

            // Lazy Dog
            [
                'hotel_id' => $lazyDog?->id,
                'room_name' => 'Breeze Garden Room',
                'description' => 'Pet-friendly rustic room surrounded by lush tropical courtyard greenery, seconds away from Bulabog kiteboarding beach.',
                'view_type' => 'Garden View',
                'ideal_for' => 'Pet Owners, Kitesurfers, Eco Travelers',
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 3,
                'extra_person_fee' => 500.00,
                'bed_configuration' => '1 Queen Bed',
                'room_size' => '28 sq.m',
                'room_amenities' => ['Pet Friendly', 'Garden Patio', 'Air Conditioning', 'Free Wi-Fi', 'Hot Shower'],
                'base_price' => 2800.00,
                'images' => ['rooms/sample8.jpg'],
            ],

            // Happiness Hostel
            [
                'hotel_id' => $happiness?->id,
                'room_name' => 'Boho Private Double Room',
                'description' => 'Chic bohemian styled private room with handmade bamboo accents, en-suite bath, and access to communal plunge pool.',
                'view_type' => 'Pool / Courtyard View',
                'ideal_for' => 'Digital Nomads, Couples, Wellness Seekers',
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00,
                'bed_configuration' => '1 Queen Bed',
                'room_size' => '26 sq.m',
                'room_amenities' => ['En-suite Bathroom', 'Air Conditioning', 'Free Wi-Fi', 'Workspace Desk'],
                'base_price' => 3200.00,
                'images' => ['rooms/sample9.jpg'],
            ],

            // The Muse Hotel
            [
                'hotel_id' => $theMuse?->id,
                'room_name' => 'Beachfront Executive Suite',
                'description' => 'Elegant Station 1 suite boasting sea breeze balconies, modern indoor pool privileges, and high-end furnishings.',
                'view_type' => 'Partial Ocean View',
                'ideal_for' => 'Chic Couples, Premium Travelers',
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 3,
                'extra_person_fee' => 600.00,
                'bed_configuration' => '1 King Bed',
                'room_size' => '36 sq.m',
                'room_amenities' => ['Balcony', 'Air Conditioning', 'Free Wi-Fi', 'Indoor Pool Access', 'Espresso Machine'],
                'base_price' => 4900.00,
                'images' => ['rooms/sample10.jpg'],
            ],
        ];

        foreach ($rooms as $roomData) {
            if (! $roomData['hotel_id']) {
                continue;
            }

            $room = RoomType::updateOrCreate(
                [
                    'hotel_id' => $roomData['hotel_id'],
                    'room_name' => $roomData['room_name'],
                ],
                $roomData
            );

            // Re-generate vector embedding
            $text = $geminiService->buildRoomEmbeddingText($room, $room->hotel?->hotel_name, $room->hotel?->destination?->name);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $room->room_name);
            if ($vector) {
                $room->embedding = $geminiService->formatVectorForDb($vector);
                $room->save();
            }
        }
    }
}
