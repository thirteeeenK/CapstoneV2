<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\GeminiService;

class ElNidoRoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(GeminiService $geminiService): void
    {
        $pangulasian = HotelModel::where('hotel_name', 'El Nido Resorts - Pangulasian Island')->first();
        $sedaLio = HotelModel::where('hotel_name', 'Seda Lio')->first();
        $cauayan = HotelModel::where('hotel_name', 'Cauayan Island Resort')->first();
        $miniloc = HotelModel::where('hotel_name', 'El Nido Resorts - Miniloc Island')->first();
        $spinHostel = HotelModel::where('hotel_name', 'Spin Designer Hostel')->first();
        $charlies = HotelModel::where('hotel_name', 'Charlie\'s El Nido')->first();
        $panorama = HotelModel::where('hotel_name', 'Panorama Resort')->first();
        $bukoBeach = HotelModel::where('hotel_name', 'Buko Beach Resort')->first();
        $matinloc = HotelModel::where('hotel_name', 'Matinloc Resort')->first();
        $lihim = HotelModel::where('hotel_name', 'Lihim Resorts')->first();

        $rooms = [
            // El Nido Resorts - Pangulasian Island
            [
                'hotel_id' => $pangulasian?->id,
                'room_name' => 'Canopy Villa',
                'description' => 'Perched 15 meters off the ground inside the forest canopy, offering a unique treetop view of the island and Bacuit Bay. Reached via a wooden buggy trail.',
                'view_type' => 'Canopy & Bay View',
                'ideal_for' => 'Couples, Honeymooners, Luxury Seekers',
                'ideal_guest' => 'Couples',
                'total_rooms' => 8,
                'total_number_of_rooms' => 8,
                'occupancy' => 2,
                'bed_configuration' => '1 King Bed',
                'room_size' => '89 sq.m',
                'room_amenities' => ['Private Balcony', 'Air Conditioning', 'Free Wi-Fi', 'Bathtub', 'Espresso Machine', 'Minibar', 'Daybed'],
                'base_price' => 45000.00,
                'images' => ['rooms/pangulasian_canopy.jpg'],
                'is_shown' => true,
            ],
            [
                'hotel_id' => $pangulasian?->id,
                'room_name' => 'Beach Villa',
                'description' => 'Located right on the powdery white sand beach, allowing guests immediate access to the clear waters. Includes a spacious private veranda.',
                'view_type' => 'Beachfront',
                'ideal_for' => 'Beach Lovers, Couples, Small Families',
                'ideal_guest' => 'Couples',
                'total_rooms' => 24,
                'total_number_of_rooms' => 24,
                'occupancy' => 3,
                'bed_configuration' => '1 King Bed',
                'room_size' => '89 sq.m',
                'room_amenities' => ['Direct Beach Access', 'Private Veranda', 'Air Conditioning', 'Free Wi-Fi', 'Outdoor Shower', 'Sun Loungers'],
                'base_price' => 52000.00,
                'images' => ['rooms/pangulasian_beach.jpg'],
                'is_shown' => true,
            ],

            // Seda Lio
            [
                'hotel_id' => $sedaLio?->id,
                'room_name' => 'Deluxe Room',
                'description' => 'Bright and airy room featuring contemporary Filipino design accents, a private balcony, and proximity to the Lio beach strip.',
                'view_type' => 'Garden/Pool View',
                'ideal_for' => 'Leisure Travelers, Small Families, Business Travelers',
                'ideal_guest' => 'Solo or Couples',
                'total_rooms' => 100,
                'total_number_of_rooms' => 100,
                'occupancy' => 2,
                'bed_configuration' => '1 King Bed or 2 Twin Beds',
                'room_size' => '45 sq.m',
                'room_amenities' => ['Balcony', 'Air Conditioning', '48-inch LED TV', 'Free Wi-Fi', 'Coffee/Tea Maker', 'Work Desk'],
                'base_price' => 12500.00,
                'images' => ['rooms/sedalio_deluxe.jpg'],
                'is_shown' => true,
            ],

            // Cauayan Island Resort
            [
                'hotel_id' => $cauayan?->id,
                'room_name' => 'Water Villa',
                'description' => 'Built directly on stilts over the crystal-clear water of the lagoon. Features a wooden deck with sun loungers and stairs leading straight into the sea.',
                'view_type' => 'Sea View',
                'ideal_for' => 'Honeymooners, Luxury Travelers',
                'ideal_guest' => 'Couples',
                'total_rooms' => 10,
                'total_number_of_rooms' => 10,
                'occupancy' => 2,
                'bed_configuration' => '1 King Bed',
                'room_size' => '60 sq.m',
                'room_amenities' => ['Overwater Deck', 'Direct Lagoon Access', 'Air Conditioning', 'Free Wi-Fi', 'Luxury Toiletries', 'Bluetooth Speaker'],
                'base_price' => 38000.00,
                'images' => ['rooms/cauayan_watervilla.jpg'],
                'is_shown' => true,
            ],

            // El Nido Resorts - Miniloc Island
            [
                'hotel_id' => $miniloc?->id,
                'room_name' => 'Water Cottage',
                'description' => 'Rustic, eco-friendly cottage built on stilts right beside the stunning limestone cliffs. Offers a traditional island village vibe.',
                'view_type' => 'Sea View',
                'ideal_for' => 'Adventure Seekers, Nature Lovers',
                'ideal_guest' => 'Couples',
                'total_rooms' => 5,
                'total_number_of_rooms' => 5,
                'occupancy' => 2,
                'bed_configuration' => '1 Double Bed or 2 Single Beds',
                'room_size' => '25 sq.m',
                'room_amenities' => ['Private Veranda', 'Air Conditioning', 'En-suite Bathroom', 'Eco Toiletries'],
                'base_price' => 28000.00,
                'images' => ['rooms/miniloc_watercottage.jpg'],
                'is_shown' => true,
            ],

            // Spin Designer Hostel
            [
                'hotel_id' => $spinHostel?->id,
                'room_name' => 'Social Mixed Dormitory Bed',
                'description' => 'Air-conditioned shared dorm bunk bed with privacy curtains, personal reading lamp, and lockable storage in a highly social, award-winning hostel.',
                'view_type' => 'Courtyard View',
                'ideal_for' => 'Solo Backpackers, Social Youth Travelers, Budget Explorers',
                'ideal_guest' => 'Solo Travelers',
                'total_rooms' => 15,
                'total_number_of_rooms' => 15,
                'occupancy' => 1,
                'bed_configuration' => 'Single Bunk Bed',
                'room_size' => 'Shared Dorm',
                'room_amenities' => ['Privacy Curtain', 'Reading Lamp', 'Personal Locker', 'Shared Bathroom', 'Air Conditioning', 'Free Wi-Fi'],
                'base_price' => 1100.00,
                'images' => ['rooms/spin_dorm.jpg'],
                'is_shown' => true,
            ],

            // Charlie's El Nido
            [
                'hotel_id' => $charlies?->id,
                'room_name' => 'Deluxe Room with Pool Access',
                'description' => 'Modern tropical room with seamless step-out access to the central swimming pool. Features premium bedding and chic interior design.',
                'view_type' => 'Pool View',
                'ideal_for' => 'Couples, Digital Nomads',
                'ideal_guest' => 'Couples',
                'total_rooms' => 8,
                'total_number_of_rooms' => 8,
                'occupancy' => 2,
                'bed_configuration' => '1 King Bed',
                'room_size' => '43 sq.m',
                'room_amenities' => ['Direct Pool Access', 'Air Conditioning', 'Smart TV', 'Free Wi-Fi', 'Rain Shower'],
                'base_price' => 7500.00,
                'images' => ['rooms/charlies_poolaccess.jpg'],
                'is_shown' => true,
            ],

            // Panorama Resort
            [
                'hotel_id' => $panorama?->id,
                'room_name' => 'Panoramic Beach Villa',
                'description' => 'Iconic dome-shaped boutique villa combining native elements with bohemian chic interiors. Steps away from the adults-only beach club.',
                'view_type' => 'Ocean / Beachfront View',
                'ideal_for' => 'Couples, Trendy Travelers',
                'ideal_guest' => 'Adults Only',
                'total_rooms' => 8,
                'total_number_of_rooms' => 8,
                'occupancy' => 2,
                'bed_configuration' => '1 King Bed',
                'room_size' => '45 sq.m',
                'room_amenities' => ['Air Conditioning', 'Outdoor Lounge Area', 'Mini Fridge', 'Safe', 'Free Wi-Fi'],
                'base_price' => 18000.00,
                'images' => ['rooms/panorama_villa.jpg'],
                'is_shown' => true,
            ],

            // Lihim Resorts
            [
                'hotel_id' => $lihim?->id,
                'room_name' => 'Luxury Villa',
                'description' => 'An expansive and heavily secluded villa blending seamlessly into the El Nido forest. Offers a highly personalized luxury experience with a dedicated butler.',
                'view_type' => 'Forest / Partial Sea View',
                'ideal_for' => 'VIPs, Luxury Travelers, Honeymooners',
                'ideal_guest' => 'Couples',
                'total_rooms' => 16,
                'total_number_of_rooms' => 16,
                'occupancy' => 2,
                'bed_configuration' => '1 King Bed',
                'room_size' => '120 sq.m',
                'room_amenities' => ['Butler Service', 'Private Plunge Pool', 'Smart Home Features', 'Walk-in Closet', 'Gourmet Minibar'],
                'base_price' => 60000.00,
                'images' => ['rooms/lihim_luxuryvilla.jpg'],
                'is_shown' => true,
            ],
        ];

        foreach ($rooms as $roomData) {
            if (!$roomData['hotel_id'])
                continue;

            $room = RoomType::updateOrCreate(
                [
                    'hotel_id' => $roomData['hotel_id'],
                    'room_name' => $roomData['room_name']
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