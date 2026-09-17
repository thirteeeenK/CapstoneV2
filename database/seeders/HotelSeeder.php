<?php

namespace Database\Seeders;

use App\Models\DestinationModel;
use App\Models\HotelModel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $boracay = DestinationModel::where('name', 'Boracay')->firstOrFail();
        $elNido = DestinationModel::where('name', 'El Nido')->firstOrFail();

        // Boracay hotels
        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Henann Palm Beach Resort',
            ],
            [
                'hotel_name' => 'Henann Palm Beach Resort',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Luxury',
                    'Beachfront',
                    'Infinity Pool',
                    'Romantic',
                    'Sunset Views',
                ],
                'featured_amenities' => [
                    'Infinity Pool',
                    'Swim-up Bar',
                    'Direct Beach Access',
                    'Spa',
                    'Fine Dining Restaurant',
                ],
                'hotel_description' => 'Henann Palm Beach is located in Station 2, right along White Beach, offering direct beachfront access. The resort features a stunning beachfront infinity pool, swim-up bar, and spacious modern rooms. It\'s an ideal spot for travelers looking to enjoy luxury and ocean views in the heart of Boracay.',
                'specific_address' => 'Station 2, White Beach',
                'latitude' => 11.95644170,
                'longitude' => 121.92574660,
                'images' => [
                    'hotels/hennan-palm-beach-resort/hen.jpg',
                    'hotels/hennan-palm-beach-resort/hen-out.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Canyon Hotels & Resorts Boracay',
            ],
            [
                'hotel_name' => 'Canyon Hotels & Resorts Boracay',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Boutique Luxury',
                    'Quiet & Cozy',
                    'Modern Elegance',
                    'Family Friendly',
                ],
                'featured_amenities' => [
                    'Swimming Pool',
                    'Boutique Lounge',
                    'Free Shuttle',
                    'In-house Restaurant',
                ],
                'hotel_description' => 'Canyon is nestled in Station 2, just a short walk from White Beach and D\'Mall. This boutique hotel offers cozy, well-appointed rooms and a peaceful atmosphere. With its quiet location yet easy access to shops and restaurants, it\'s great for couples and families seeking a balance of relaxation and convenience.',
                'specific_address' => 'Station 2 (Near D\'Mall)',
                'latitude' => 11.95856200,
                'longitude' => 121.92723100,
                'images' => [
                    'hotels/canyon-hotel/canyon.jpg',
                    'hotels/canyon-hotel/out.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Boracay Ocean Club Beach Resort',
            ],
            [
                'hotel_name' => 'Boracay Ocean Club Beach Resort',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Beachfront Resort',
                    'Scuba & Diving',
                    'Ocean Views',
                    'Laid-back Luxury',
                ],
                'featured_amenities' => [
                    'Beachfront Pool',
                    'PADI Dive Center',
                    'Massage Spa',
                    'Oceanview Dining',
                ],
                'hotel_description' => 'Boracay Ocean Club is located in Station 3, right along White Beach with direct beachfront access. The resort features a beachfront pool, spa services, and dive center, making it ideal for adventure seekers and beach lovers. With ocean-view rooms and a quieter location, it\'s perfect for travelers who want a more laid-back Boracay experience.',
                'specific_address' => 'Station 3, White Beach',
                'latitude' => 11.95092990,
                'longitude' => 121.92571160,
                'images' => [
                    'hotels/Boracay-Ocean-Club-Beach-Resort/ocean_outside.webp',
                    'hotels/Boracay-Ocean-Club-Beach-Resort/ocean-pool.jpg',
                    'hotels/Boracay-Ocean-Club-Beach-Resort/ocean_hall.webp',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Henann Garden Resort',
            ],
            [
                'hotel_name' => 'Henann Garden Resort',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Tropical Garden',
                    'Lagoon Pools',
                    'Central Location',
                    'Resort Oasis',
                ],
                'featured_amenities' => [
                    'Multiple Lagoon Pools',
                    'Lush Gardens',
                    'Buffet Breakfast',
                    'Fitness Center',
                ],
                'hotel_description' => 'Henann Garden is located in Station 2, just a 2-minute walk from White Beach and close to D\'Mall. It has several lagoon-style pools and lush landscaping. With spacious rooms and on-site dining, it\'s perfect for travelers seeking comfort and convenience in the island\'s center.',
                'specific_address' => 'Station 2, White Beach',
                'latitude' => 11.95918520,
                'longitude' => 121.92520520,
                'images' => [
                    'hotels/Henann-Garden-Resort/hennann.jpg',
                    'hotels/Henann-Garden-Resort/outside-hen.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'My Station Hotel',
            ],
            [
                'hotel_name' => 'My Station Hotel',
                'type' => 'mid-affordable-hotels',
                'vibe_tags' => [
                    'Affordable Comfort',
                    'Convenient',
                    'Station 1 Quiet',
                    'Modern Minimalist',
                ],
                'featured_amenities' => [
                    'Free Wi-Fi',
                    'Air Conditioning',
                    '24/7 Front Desk',
                    'Concierge Tour Desk',
                ],
                'hotel_description' => 'Located in Station 1, My Station Hotel is just a short walk from the pristine White Beach. With modern, comfortable rooms and a welcoming atmosphere, this hotel offers a relaxing stay while being close to local attractions. Guests can enjoy easy access to restaurants, shopping, and the island\'s nightlife, making it perfect for both relaxation and adventure seekers.',
                'specific_address' => 'Station 1',
                'latitude' => 11.96536790,
                'longitude' => 121.92008530,
                'images' => [
                    'hotels/My-Station-Hotel/station-out.jpg',
                    'hotels/My-Station-Hotel/station-beach.webp',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Frendz Resort & Hostel',
            ],
            [
                'hotel_name' => 'Frendz Resort & Hostel',
                'type' => 'mid-affordable-hotels',
                'vibe_tags' => [
                    'Social & Vibrant',
                    'Backpacker Community',
                    'Pool Parties',
                    'Nightlife Access',
                ],
                'featured_amenities' => [
                    'Outdoor Swimming Pool',
                    'Common Lounge & Bar',
                    'Social Events',
                    'Shared Kitchen',
                ],
                'hotel_description' => 'Found in Station 1, Frendz is a popular social spot for travelers. It\'s about a 5-minute walk to White Beach and has a pool and a common area that often hosts events. It\'s close to bars and restaurants but still maintains a laid-back vibe.',
                'specific_address' => 'Station 1',
                'latitude' => 11.96416470,
                'longitude' => 121.92231740,
                'images' => [
                    'hotels/Frendz-Hostel-Boracay/frendz-pool.jpg',
                    'hotels/Frendz-Hostel-Boracay/frends.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Lazy Dog Bed & Breakfast',
            ],
            [
                'hotel_name' => 'Lazy Dog Bed & Breakfast',
                'type' => 'mid-affordable-hotels',
                'vibe_tags' => [
                    'Pet Friendly',
                    'Kitesurfing Hub',
                    'Rustic Eco Vibe',
                    'Tranquil & Cozy',
                ],
                'featured_amenities' => [
                    'Pet Friendly',
                    'In-house Cafe',
                    'Garden Courtyard',
                    'Kite Storage',
                ],
                'hotel_description' => 'Located near Bulabog Beach, Lazy Dog provides a cozy environment with both private rooms and dormitories. It\'s about a 2-minute walk to Bulabog Beach and 10 minutes to White Beach Station 2. There\'s no swimming pool, but the area is peaceful and just a short walk to D\'Mall and various restaurants.',
                'specific_address' => 'Bulabog Beach',
                'latitude' => 11.96465580,
                'longitude' => 121.92614360,
                'images' => [
                    'hotels/Lazy-Dog/lazy-dog.jpg',
                    'hotels/Lazy-Dog/outside.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Happiness Hostel',
            ],
            [
                'hotel_name' => 'Happiness Hostel',
                'type' => 'mid-affordable-hotels',
                'vibe_tags' => [
                    'Bohemian Vibe',
                    'Healthy Dining',
                    'Community Spirit',
                    'Chic Backpacker',
                ],
                'featured_amenities' => [
                    'Plunge Pool',
                    'Vegan & Israeli Cafe',
                    'Co-working Space',
                    'Rooftop Lounge',
                ],
                'hotel_description' => 'Near Bulabog Beach in Station 2, Happiness Hostel offers a bohemian vibe with both dorms and private rooms. It\'s a 2-minute walk to Bulabog and around 10 minutes to White Beach. The property has a small pool and an in-house restaurant with communal areas for socializing.',
                'specific_address' => 'Station 2 / Bulabog',
                'latitude' => 11.93433830,
                'longitude' => 121.88810440,
                'images' => [
                    'hotels/happiness-hotel/pool.jpg',
                    'hotels/happiness-hotel/happiness.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'The Muse Hotel',
            ],
            [
                'hotel_name' => 'The Muse Hotel',
                'type' => 'mid-affordable-hotels',
                'vibe_tags' => [
                    'Stylish Modern',
                    'Indoor Pool',
                    'Station 1 Beachfront',
                    'Boutique Elegance',
                ],
                'featured_amenities' => [
                    'Indoor Heated Pool',
                    'Rooftop Sun Deck',
                    'Beachfront Restaurant',
                    'Spa Services',
                ],
                'hotel_description' => 'Located in Station 1, The Muse is a stylish beachfront hotel with easy access to White Beach. It features an indoor pool, restaurant, and modern rooms. The quieter beachfront location is perfect for relaxation, while still being near several restaurants.',
                'specific_address' => 'Station 1',
                'latitude' => 11.94501720,
                'longitude' => 121.83352920,
                'images' => [
                    'hotels/muse-hotel/muse.webp',
                    'hotels/muse-hotel/muse-lounge.webp',
                ],
                'is_shown' => true,
                'destination_id' => $boracay->id,
            ],
        );

        // El Nido hotels
        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'El Nido Resorts - Pangulasian Island',
            ],
            [
                'hotel_name' => 'El Nido Resorts - Pangulasian Island',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Luxury',
                    'Eco-Friendly',
                    'Honeymoon',
                    'Beachfront',
                    'Secluded',
                ],
                'featured_amenities' => [
                    'Private Beach',
                    'Infinity Pool',
                    'Spa & Wellness Center',
                    'PADI Dive Center',
                    'On-site Restaurant & Bar',
                ],
                'hotel_description' => 'Pangulasian Island is El Nido Resorts’ luxury island hideaway in Bacuit Bay, offering breathtaking views of both the sunrise and sunset.',
                'specific_address' => 'Pangulasian Island, El Nido, 5313 Palawan',
                'latitude' => 11.11315450,
                'longitude' => 117.33355060,
                'images' => [
                    'el-nido-hotels/pangulasian.jpg',
                    'el-nido-hotels/pangulasian-1.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Seda Lio',
            ],
            [
                'hotel_name' => 'Seda Lio',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Family-Friendly',
                    'Modern',
                    'Accessible',
                    'Eco-Tourism',
                    'Relaxing',
                ],
                'featured_amenities' => [
                    'Large Outdoor Pool',
                    'Kids Club',
                    'Fitness Center',
                    'Misto Restaurant',
                    'Direct Beach Access',
                ],
                'hotel_description' => 'Situated within the Lio Tourism Estate, Seda Lio offers modern comforts and seamless access to nature. Perfect for families and leisure travelers.',
                'specific_address' => 'Lio Tourism Estate, Barangay Villa Libertad, El Nido, Palawan',
                'latitude' => 11.21142570,
                'longitude' => 119.41831200,
                'images' => [
                    'el-nido-hotels/seda-lio-1.jpg',
                    'el-nido-hotels/seda-lio-2.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Cauayan Island Resort',
            ],
            [
                'hotel_name' => 'Cauayan Island Resort',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Luxury',
                    'Tropical',
                    'Romance',
                    'Wellness',
                    'Private Island',
                ],
                'featured_amenities' => [
                    'Infinity Pool',
                    'Overwater Spa',
                    'Cauayan Restaurant',
                    'Diving & Snorkeling Water Activities',
                ],
                'hotel_description' => 'A premier luxury resort surrounded by pristine marine life and lush tropical greenery, famous for its iconic overwater villas.',
                'specific_address' => 'Cauayan Island Bacuit Bay, El Nido, 5313 Palawan',
                'latitude' => 11.26131870,
                'longitude' => 119.35301170,
                'images' => [
                    'el-nido-hotels/cauayan-1.jpg',
                    'el-nido-hotels/cauayan-2.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Miniloc Island',
            ],
            [
                'hotel_name' => 'Miniloc Island',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Eco-Friendly',
                    'Adventure',
                    'Family-Friendly',
                    'Rustic',
                    'Snorkeling',
                ],
                'featured_amenities' => [
                    'House Reef',
                    'Kayaking',
                    'Marine Sports Guide',
                    'Open-air Restaurant',
                    'Kids Activity Center',
                ],
                'hotel_description' => 'Designed like a coastal village, Miniloc Island is the gateway to discovering the famous Big and Small Lagoons of El Nido.',
                'specific_address' => 'Miniloc, El Nido, Miniloc Island, El Nido, 5313 Palawan',
                'latitude' => 11.14978900,
                'longitude' => 119.32006320,
                'images' => [
                    'el-nido-hotels/miniloc-1.jpg',
                    'el-nido-hotels/miniloc-2.jpg',
                    'el-nido-hotels/miniloc-3.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Spin Designer Hostel',
            ],
            [
                'hotel_name' => 'Spin Designer Hostel',
                'type' => 'mid-affordable-hotels',
                'vibe_tags' => [
                    'Youthful',
                    'Social',
                    'Budget-Friendly',
                    'Backpacker',
                    'Trendy',
                ],
                'featured_amenities' => [
                    'Shared Lounge',
                    'Communal Kitchen',
                    'BBQ Facilities',
                    'Game Room',
                    'Laundry Services',
                ],
                'hotel_description' => 'An award-winning designer hostel in El Nido town proper, offering a vibrant, social atmosphere for solo travelers and young groups.',
                'specific_address' => 'Balinsasayaw Road, cor Calle Real, El Nido, 5313 Palawan',
                'latitude' => 11.18021540,
                'longitude' => 119.39275210,
                'images' => [
                    'el-nido-hotels/spin-designer-1.jpg',
                    'el-nido-hotels/spin-designer-2.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Charlie\'s El Nido',
            ],
            [
                'hotel_name' => 'Charlie\'s El Nido',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Modern',
                    'Chic',
                    'Relaxing',
                    'Tropical Design',
                    'Accessible',
                ],
                'featured_amenities' => [
                    'Pool',
                    'Yoga Pavilion',
                    'Restaurant',
                    'Fitness Center',
                    'Spa Services',
                    'Wifi',
                ],
                'hotel_description' => 'A beautifully designed boutique hotel set slightly inland, providing a tranquil and lush escape away from the busy town center.',
                'specific_address' => 'Km 279 National Highway, Barangay, El Nido, 5313 Palawan',
                'latitude' => 11.20959090,
                'longitude' => 119.42386310,
                'images' => [
                    'hotels/FInBKhO2OsiqhdgrJMs0HBPgMluataZdnQpVZFsf.webp',
                    'hotels/3ElbNmdtgQ4XN7Q5xWvv98AcUvKqiDr4rmvoDr7j.webp',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Panorama Resort',
            ],
            [
                'hotel_name' => 'Panorama Resort',
                'type' => 'mid-affordable-hotels',
                'vibe_tags' => [
                    'Adults Only',
                    'Sunset Views',
                    'Aesthetic',
                    'Beachfront',
                    'Bohemian',
                ],
                'featured_amenities' => [
                    'Beachfront resort with pool',
                    'dining',
                    'Wi-Fi',
                    'beach access',
                    'views and Corong-Corong location.',
                ],
                'hotel_description' => 'An adults-only boutique resort in Corong-Corong known for its iconic dome-shaped architecture, chill beach club, and stunning sunset views.',
                'specific_address' => 'Taytay - El Nido National Hwy, El Nido, Palawan',
                'latitude' => 11.15726280,
                'longitude' => 119.39802820,
                'images' => [
                    'el-nido-hotels/panorama-1.jpg',
                    'el-nido-hotels/panorama-2.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Buko Beach Resort',
            ],
            [
                'hotel_name' => 'Buko Beach Resort',
                'type' => 'mid-affordable-hotels',
                'vibe_tags' => [
                    'Rustic Charm',
                    'Intimate',
                    'Sunset Views',
                    'Beachfront',
                    'Tropical',
                ],
                'featured_amenities' => [
                    'Infinity Plunge Pool',
                    'Open-air Bar',
                    'Massage Services',
                    'Tour Desk',
                ],
                'hotel_description' => 'A small, intimate beachfront resort offering native-style luxury cottages and grand villas. Perfect for watching the famous Corong-Corong sunsets.',
                'specific_address' => 'Lot 10 Sitio Lugadia, Corong-corong Pob. (Barangay 4), El Nido, 5313 Palawan',
                'latitude' => 11.15698130,
                'longitude' => 119.39814140,
                'images' => [
                    'el-nido-hotels/buko-beach-resort-1.jpg',
                    'el-nido-hotels/buko-beach-resort-2.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );

        HotelModel::updateOrCreate(
            [
                'hotel_name' => 'Lihim Resorts',
            ],
            [
                'hotel_name' => 'Lihim Resorts',
                'type' => 'high-end-luxury-hotels',
                'vibe_tags' => [
                    'Ultra-Luxury',
                    'Secluded',
                    'Nature Immersion',
                    'Exclusive',
                    'Personalized',
                ],
                'featured_amenities' => [
                    'Beachfront luxury resort with pools',
                    'dining',
                    'spa',
                    'fitness facilities and water activities.',
                ],
                'hotel_description' => 'A hidden gem tucked in the lush forests of El Nido. Lihim (meaning "secret") offers an exclusive, highly personalized luxury experience.',
                'specific_address' => '5CV2+56, 999 Sitio Caalan, El Nido, 5313 Palawan',
                'latitude' => 11.19212250,
                'longitude' => 119.39742260,
                'images' => [
                    'el-nido-hotels/lihim-1.jpg',
                    'el-nido-hotels/lihim-2.jpg',
                    'el-nido-hotels/lihim-3.jpg',
                ],
                'is_shown' => true,
                'destination_id' => $elNido->id,
            ],
        );
    }
}
