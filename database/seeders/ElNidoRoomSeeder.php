<?php

namespace Database\Seeders;

use App\Models\HotelModel;
use App\Models\RoomType;
use App\Services\GeminiService;
use Illuminate\Database\Seeder;

class ElNidoRoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(GeminiService $geminiService): void
    {
        $elNidoResortsPangulasianIsland = HotelModel::where('hotel_name', 'El Nido Resorts - Pangulasian Island')->first();
        $sedaLio = HotelModel::where('hotel_name', 'Seda Lio')->first();
        $cauayanIslandResort = HotelModel::where('hotel_name', 'Cauayan Island Resort')->first();
        $minilocIsland = HotelModel::where('hotel_name', 'Miniloc Island')->first();
        $spinDesignerHostel = HotelModel::where('hotel_name', 'Spin Designer Hostel')->first();
        $charlieSElNido = HotelModel::where('hotel_name', 'Charlie\'s El Nido')->first();
        $panoramaResort = HotelModel::where('hotel_name', 'Panorama Resort')->first();
        $lihimResorts = HotelModel::where('hotel_name', 'Lihim Resorts')->first();
        $bukoBeachResort = HotelModel::where('hotel_name', 'Buko Beach Resort')->first();

        $rooms = [
            [
                'hotel_id' => $elNidoResortsPangulasianIsland?->id,
                'room_name' => 'Canopy Villa',
                'description' => 'Perched 15 meters off the ground inside the forest canopy, offering a unique treetop view of the island and Bacuit Bay. Reached via a wooden buggy trail.',
                'view_type' => 'Canopy & Bay View',
                'ideal_for' => 'Couples, Honeymooners, Luxury Seekers',
                'ideal_guest' => 'Couples, Honeymooners, Luxury Seekers',
                'additional_notes' => null,
                'total_rooms' => 1,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => '1 King Bed',
                'room_size' => '89 sq.m',
                'room_amenities' => [
                    'Private Balcony',
                    'Air Conditioning',
                    'Free Wi-Fi',
                    'Bathtub',
                    'Espresso Machine',
                    'Minibar',
                    'Daybed',
                ],
                'base_price' => 1.00000000,
                'images' => [
                    'el-nido-hotels/pangulasian.jpg',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $elNidoResortsPangulasianIsland?->id,
                'room_name' => 'Beach Villa',
                'description' => 'Located right on the powdery white sand beach, allowing guests immediate access to the clear waters. Includes a spacious private veranda.',
                'view_type' => 'Beachfront',
                'ideal_for' => 'Beach Lovers, Couples, Small Families',
                'ideal_guest' => null,
                'additional_notes' => null,
                'total_rooms' => 2,
                'occupancy' => 3,
                'base_occupancy' => 2,
                'max_occupancy' => null,
                'extra_person_fee' => 5200.00000000,
                'bed_configuration' => '1 King Bed',
                'room_size' => '89 sq.m',
                'room_amenities' => [
                    'Direct Beach Access',
                    'Private Veranda',
                    'Air Conditioning',
                    'Free Wi-Fi',
                    'Outdoor Shower',
                    'Sun Loungers',
                ],
                'base_price' => 52000.00000000,
                'images' => [
                    'el-nido-hotels/pangulasian-1.jpg',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $sedaLio?->id,
                'room_name' => 'Deluxe Room',
                'description' => 'Bright and airy room featuring contemporary Filipino design accents, a private balcony, and proximity to the Lio beach strip.',
                'view_type' => 'Garden/Pool View',
                'ideal_for' => 'Leisure Travelers, Small Families, Business Travelers',
                'ideal_guest' => 'Leisure Travelers, Small Families, Business Travelers',
                'additional_notes' => null,
                'total_rooms' => 1,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => '1 King Bed or 2 Twin Beds',
                'room_size' => '45 sq.m',
                'room_amenities' => [
                    'Balcony',
                    'Air Conditioning',
                    '48-inch LED TV',
                    'Free Wi-Fi',
                    'Coffee/Tea Maker',
                    'Work Desk',
                ],
                'base_price' => 12500.00000000,
                'images' => [
                    'el-nido-hotels/seda-lio-1.jpg',
                    'rooms/Nrvw2lW9GXHT4kZTwOO2S1ub8Zr2Zv0y8Qs3Qlty.webp',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $cauayanIslandResort?->id,
                'room_name' => 'Water Villa',
                'description' => 'Built directly on stilts over the crystal-clear water of the lagoon. Features a wooden deck with sun loungers and stairs leading straight into the sea.',
                'view_type' => 'Sea View',
                'ideal_for' => 'Honeymooners, Luxury Travelers',
                'ideal_guest' => null,
                'additional_notes' => null,
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => null,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => '1 King Bed',
                'room_size' => '60 sq.m',
                'room_amenities' => [
                    'Overwater Deck',
                    'Direct Lagoon Access',
                    'Air Conditioning',
                    'Free Wi-Fi',
                    'Luxury Toiletries',
                    'Bluetooth Speaker',
                ],
                'base_price' => 38000.00000000,
                'images' => [
                    'el-nido-hotels/cauayan-1.jpg',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $minilocIsland?->id,
                'room_name' => 'Water Cottage',
                'description' => 'Rustic, eco-friendly cottage built on stilts right beside the stunning limestone cliffs. Offers a traditional island village vibe.',
                'view_type' => 'Sea View',
                'ideal_for' => 'Adventure Seekers, Nature Lovers',
                'ideal_guest' => 'Adventure Seekers, Nature Lovers',
                'additional_notes' => null,
                'total_rooms' => 1,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => '1 Double Bed or 2 Single Beds',
                'room_size' => '25 sq.m',
                'room_amenities' => [
                    'Air Conditioning',
                    'Minibar',
                    'Wifi',
                    'Private Shower',
                    'Coffee Maker',
                    'Toiletries',
                ],
                'base_price' => 28000.00000000,
                'images' => [
                    'rooms/pofl8h5mdxdZppvHK6yeALz7GQQbQAL5xCEsuczp.webp',
                    'rooms/pYndznDt3puQY2NX14C8mVF1N6P6LL0MNLuCgjuD.jpg',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $spinDesignerHostel?->id,
                'room_name' => 'Social Mixed Dormitory Bed',
                'description' => 'Air-conditioned shared dorm bunk bed with privacy curtains, personal reading lamp, and lockable storage in a highly social, award-winning hostel.',
                'view_type' => 'Courtyard View',
                'ideal_for' => 'Solo Backpackers, Social Youth Travelers, Budget Explorers',
                'ideal_guest' => 'Solo Backpackers, Social Youth Travelers, Budget Explorers',
                'additional_notes' => null,
                'total_rooms' => 2,
                'occupancy' => 1,
                'base_occupancy' => 2,
                'max_occupancy' => 1,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => 'Single Bunk Bed or 1 semi Double Bed',
                'room_size' => '12 sq. meter',
                'room_amenities' => [
                    'Privacy Curtain',
                    'Reading Lamp',
                    'Personal Locker',
                    'Shared Bathroom',
                    'Air Conditioning',
                    'Free Wi-Fi',
                ],
                'base_price' => 1900.00000000,
                'images' => [
                    'el-nido-hotels/spin-designer-1.jpg',
                    'rooms/o7LsGhQnRx6KcHkBjegdpVqVaT3mJtlbGaJaDDvy.webp',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $charlieSElNido?->id,
                'room_name' => 'Deluxe Room with Pool Access',
                'description' => 'Modern tropical room with seamless step-out access to the central swimming pool. Features premium bedding and chic interior design.',
                'view_type' => 'Pool View',
                'ideal_for' => 'Couples, Honeymooners',
                'ideal_guest' => 'Couples, Honeymooners',
                'additional_notes' => null,
                'total_rooms' => 1,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => '1 King Bed or Two Queen Sized Bed',
                'room_size' => '43 sq.m',
                'room_amenities' => [
                    'Direct Pool Access',
                    'Air Conditioning',
                    'Smart TV',
                    'Free Wi-Fi',
                    'Rain Shower',
                ],
                'base_price' => 3750.00000000,
                'images' => [
                    'rooms/HfRny0DsjXZYG1uySu7PsU34rifw4njYoqCimqzR.webp',
                    'rooms/9vtxEcM0Ml67GE7ojOiawGUM3396RyiIUHUuOOM4.webp',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $panoramaResort?->id,
                'room_name' => 'Panoramic Beach Villa',
                'description' => 'Iconic dome-shaped boutique villa combining native elements with bohemian chic interiors. Steps away from the adults-only beach club.',
                'view_type' => 'Ocean / Beachfront View',
                'ideal_for' => 'Couples, Trendy Travelers',
                'ideal_guest' => 'Couples, Trendy Travelers',
                'additional_notes' => null,
                'total_rooms' => 2,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => '1 King Bed',
                'room_size' => '45 sq.m',
                'room_amenities' => [
                    'Air Conditioning',
                    'Outdoor Lounge Area',
                    'Mini Fridge',
                    'Safe',
                    'Free Wi-Fi',
                ],
                'base_price' => 8800.00000000,
                'images' => [
                    'el-nido-hotels/panorama-1.jpg',
                    'rooms/vEWmDaoLPAB0DF860iH1oih28yplR0VjTZJc0dIf.webp',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $lihimResorts?->id,
                'room_name' => 'Luxury Villa',
                'description' => 'An expansive and heavily secluded villa blending seamlessly into the El Nido forest. Offers a highly personalized luxury experience with a dedicated butler.',
                'view_type' => 'Forest / Partial Sea View',
                'ideal_for' => 'VIPs, Luxury Travelers, Honeymooners',
                'ideal_guest' => 'VIPs, Luxury Travelers, Honeymooners',
                'additional_notes' => null,
                'total_rooms' => 1,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => '1 King Bed',
                'room_size' => '55 sq. meter',
                'room_amenities' => [
                    'Air Conditioning',
                    'Coffee maker Machine',
                    'Smart TV',
                    'Minibar',
                    'Wifi',
                    'Safe',
                ],
                'base_price' => 22855.00000000,
                'images' => [
                    'rooms/4JiedD09nvfYqCrEqkeq49qHSfaEfScTHgQQAUx4.webp',
                    'rooms/B53SLzwtwDUYungaQUamlFwMfDYzuPZeHf950HIm.webp',
                    'rooms/uW3n3nSizJDfJSUR86YUtMIegz6IrCNoT3NpmDKy.webp',
                ],
                'is_shown' => true,
            ],

            [
                'hotel_id' => $bukoBeachResort?->id,
                'room_name' => 'Standard Garden Cottage',
                'description' => 'Enjoy the refreshing ambiance of a beachfront setting, with easy access to the island’s natural beauty. The cottage combines comfortable interiors with the laid-back charm of a tropical hideaway, creating an intimate atmosphere perfect for two.',
                'view_type' => 'Beach Front',
                'ideal_for' => 'Couples, Honeymooners, Adults',
                'ideal_guest' => 'Couples, Honeymooners, Adults',
                'additional_notes' => 'Housekeeping is available on request. Check in anytime after 2:00 PM, check out anytime before 12:00 PM',
                'total_rooms' => 1,
                'occupancy' => 2,
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'extra_person_fee' => 0.00000000,
                'bed_configuration' => '1 Queen Sized Bed',
                'room_size' => '25 sq. meter',
                'room_amenities' => [
                    'Adults only',
                    'Beachfront',
                    'Free Wi-Fi',
                    'Tea/coffee maker',
                    'Air-conditioned',
                    'Toiletries',
                ],
                'base_price' => 9700.00000000,
                'images' => [
                    'rooms/J0q6OiIbX1k36V1P6RoeMKAh6hMGVfmAJllGQygH.jpg',
                    'rooms/iePtnbDBPleEBgSuqUbOnosXAlaS01fGeGqy51iS.jpg',
                ],
                'is_shown' => true,
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
