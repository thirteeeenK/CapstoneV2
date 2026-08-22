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
        $boracay = DestinationModel::where('name', 'Boracay')->first();
        $destinationId = $boracay ? $boracay->id : 1;

        // 1
        HotelModel::create([
            'hotel_name' => 'Henann Palm Beach Resort',
            'type' => 'high-end-luxury-hotels',
            'vibe_tags' => ['Luxury', 'Beachfront', 'Infinity Pool', 'Romantic', 'Sunset Views'],
            'featured_amenities' => ['Infinity Pool', 'Swim-up Bar', 'Direct Beach Access', 'Spa', 'Fine Dining Restaurant'],
            'hotel_description' => "Henann Palm Beach is located in Station 2, right along White Beach, offering direct beachfront access. The resort features a stunning beachfront infinity pool, swim-up bar, and spacious modern rooms. It's an ideal spot for travelers looking to enjoy luxury and ocean views in the heart of Boracay.",
            'destination_id' => $destinationId,
            'specific_address' => 'Station 2, White Beach',
            'latitude' => 11.95644170,
            'longitude' => 121.92574660,
            'images' => ['hotels/hennan-palm-beach-resort/hen.jpg', 'hotels/hennan-palm-beach-resort/hen out.jpg'],
        ]);

        // 2
        HotelModel::create([
            'hotel_name' => 'Canyon Hotels & Resorts Boracay',
            'type' => 'high-end-luxury-hotels',
            'vibe_tags' => ['Boutique Luxury', 'Quiet & Cozy', 'Modern Elegance', 'Family Friendly'],
            'featured_amenities' => ['Swimming Pool', 'Boutique Lounge', 'Free Shuttle', 'In-house Restaurant'],
            'hotel_description' => "Canyon is nestled in Station 2, just a short walk from White Beach and D'Mall. This boutique hotel offers cozy, well-appointed rooms and a peaceful atmosphere. With its quiet location yet easy access to shops and restaurants, it's great for couples and families seeking a balance of relaxation and convenience.",
            'destination_id' => $destinationId,
            'specific_address' => "Station 2 (Near D'Mall)",
            'latitude' => 11.958562,
            'longitude' => 121.927231,
            'images' => ['hotels/canyon-hotel/canyon.jpg', 'hotels/canyon-hotel/out.jpg'],
        ]);

        // 3
        HotelModel::create([
            'hotel_name' => 'Boracay Ocean Club Beach Resort',
            'type' => 'high-end-luxury-hotels',
            'vibe_tags' => ['Beachfront Resort', 'Scuba & Diving', 'Ocean Views', 'Laid-back Luxury'],
            'featured_amenities' => ['Beachfront Pool', 'PADI Dive Center', 'Massage Spa', 'Oceanview Dining'],
            'hotel_description' => "Boracay Ocean Club is located in Station 3, right along White Beach with direct beachfront access. The resort features a beachfront pool, spa services, and dive center, making it ideal for adventure seekers and beach lovers. With ocean-view rooms and a quieter location, it's perfect for travelers who want a more laid-back Boracay experience.",
            'destination_id' => $destinationId,
            'specific_address' => 'Station 3, White Beach',
            'latitude' => 11.9509299,
            'longitude' => 121.9257116,
            'images' => ['hotels/Boracay-Ocean-Club-Beach-Resort/ocean_outside.webp', 'hotels/Boracay-Ocean-Club-Beach-Resort/ocean pool_.jpg', 'hotels/Boracay-Ocean-Club-Beach-Resort/ocean_hall.webp'],
        ]);

        // 4
        HotelModel::create([
            'hotel_name' => 'Henann Garden Resort',
            'type' => 'high-end-luxury-hotels',
            'vibe_tags' => ['Tropical Garden', 'Lagoon Pools', 'Central Location', 'Resort Oasis'],
            'featured_amenities' => ['Multiple Lagoon Pools', 'Lush Gardens', 'Buffet Breakfast', 'Fitness Center'],
            'hotel_description' => "Henann Garden is located in Station 2, just a 2-minute walk from White Beach and close to D'Mall. It has several lagoon-style pools and lush landscaping. With spacious rooms and on-site dining, it's perfect for travelers seeking comfort and convenience in the island's center.",
            'destination_id' => $destinationId,
            'specific_address' => 'Station 2, White Beach',
            'latitude' => 11.9591852,
            'longitude' => 121.9252052,
            'images' => ['hotels/Henann-Garden-Resort/hennann.jpg', 'hotels/Henann-Garden-Resort/outside hen.jpg'],
        ]);

        // 5
        HotelModel::create([
            'hotel_name' => 'My Station Hotel',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Affordable Comfort', 'Convenient', 'Station 1 Quiet', 'Modern Minimalist'],
            'featured_amenities' => ['Free Wi-Fi', 'Air Conditioning', '24/7 Front Desk', 'Concierge Tour Desk'],
            'hotel_description' => "Located in Station 1, My Station Hotel is just a short walk from the pristine White Beach. With modern, comfortable rooms and a welcoming atmosphere, this hotel offers a relaxing stay while being close to local attractions. Guests can enjoy easy access to restaurants, shopping, and the island's nightlife, making it perfect for both relaxation and adventure seekers.",
            'destination_id' => $destinationId,
            'specific_address' => 'Station 1',
            'latitude' => 11.9653679,
            'longitude' => 121.9200853,
            'images' => ['hotels/My-Station-Hotel/station out.jpg', 'hotels/My-Station-Hotel/station beach.webp'],
        ]);

        // 6
        HotelModel::create([
            'hotel_name' => 'Frendz Resort & Hostel',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Social & Vibrant', 'Backpacker Community', 'Pool Parties', 'Nightlife Access'],
            'featured_amenities' => ['Outdoor Swimming Pool', 'Common Lounge & Bar', 'Social Events', 'Shared Kitchen'],
            'hotel_description' => "Found in Station 1, Frendz is a popular social spot for travelers. It's about a 5-minute walk to White Beach and has a pool and a common area that often hosts events. It's close to bars and restaurants but still maintains a laid-back vibe.",
            'destination_id' => $destinationId,
            'specific_address' => 'Station 1',
            'latitude' => 11.9641647,
            'longitude' => 121.9223174,
            'images' => ['hotels/Frendz-Hostel-Boracay/frendz pool.jpg', 'hotels/Frendz-Hostel-Boracay/frends.jpg'],
        ]);

        // 7
        HotelModel::create([
            'hotel_name' => 'Lazy Dog Bed & Breakfast',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Pet Friendly', 'Kitesurfing Hub', 'Rustic Eco Vibe', 'Tranquil & Cozy'],
            'featured_amenities' => ['Pet Friendly', 'In-house Cafe', 'Garden Courtyard', 'Kite Storage'],
            'hotel_description' => "Located near Bulabog Beach, Lazy Dog provides a cozy environment with both private rooms and dormitories. It's about a 2-minute walk to Bulabog Beach and 10 minutes to White Beach Station 2. There's no swimming pool, but the area is peaceful and just a short walk to D'Mall and various restaurants.",
            'destination_id' => $destinationId,
            'specific_address' => 'Bulabog Beach',
            'latitude' => 11.9646558,
            'longitude' => 121.9261436,
            'images' => ['hotels/Lazy-Dog/lazy dog.jpg', 'hotels/Lazy-Dog/outside.jpg'],
        ]);

        // 8
        HotelModel::create([
            'hotel_name' => 'Happiness Hostel',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Bohemian Vibe', 'Healthy Dining', 'Community Spirit', 'Chic Backpacker'],
            'featured_amenities' => ['Plunge Pool', 'Vegan & Israeli Cafe', 'Co-working Space', 'Rooftop Lounge'],
            'hotel_description' => "Near Bulabog Beach in Station 2, Happiness Hostel offers a bohemian vibe with both dorms and private rooms. It's a 2-minute walk to Bulabog and around 10 minutes to White Beach. The property has a small pool and an in-house restaurant with communal areas for socializing.",
            'destination_id' => $destinationId,
            'specific_address' => 'Station 2 / Bulabog',
            'latitude' => 11.9343383,
            'longitude' => 121.8881044,
            'images' => ['hotels/happiness-hotel/pool.jpg', 'hotels/happiness-hotel/happiness.jpg'],
        ]);

        // 9
        HotelModel::create([
            'hotel_name' => 'The Muse Hotel',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Stylish Modern', 'Indoor Pool', 'Station 1 Beachfront', 'Boutique Elegance'],
            'featured_amenities' => ['Indoor Heated Pool', 'Rooftop Sun Deck', 'Beachfront Restaurant', 'Spa Services'],
            'hotel_description' => 'Located in Station 1, The Muse is a stylish beachfront hotel with easy access to White Beach. It features an indoor pool, restaurant, and modern rooms. The quieter beachfront location is perfect for relaxation, while still being near several restaurants.',
            'destination_id' => $destinationId,
            'specific_address' => 'Station 1',
            'latitude' => 11.9450172,
            'longitude' => 121.8335292,
            'images' => ['hotels/muse-hotel/muse.webp', 'hotels/muse-hotel/muse lounge.webp'],
        ]);

        $elNido = DestinationModel::where('name', 'El Nido')->first();
        $destinationId = $elNido->id;

        // 1. El Nido Resorts - Pangulasian Island
        HotelModel::create([
            'hotel_name' => 'El Nido Resorts - Pangulasian Island',
            'destination_id' => $destinationId,
            'type' => 'Luxury Eco-Resort',
            'vibe_tags' => ['Luxury', 'Eco-Friendly', 'Honeymoon', 'Beachfront', 'Secluded'],
            'featured_amenities' => ['Private Beach', 'Infinity Pool', 'Spa & Wellness Center', 'PADI Dive Center', 'On-site Restaurant & Bar'],
            'hotel_description' => 'Pangulasian Island is El Nido Resorts’ luxury island hideaway in Bacuit Bay, offering breathtaking views of both the sunrise and sunset.',
            'specific_address' => 'Pangulasian Island, Bacuit Bay, El Nido, Palawan',
            'latitude' => 11.1167,
            'longitude' => 119.3361,
            'images' => ['https://images.unsplash.com/photo-1540555700478-4be289fbecef'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 2. Seda Lio
        HotelModel::create([
            'hotel_name' => 'Seda Lio',
            'destination_id' => $destinationId,
            'type' => 'Beach Resort Hotel',
            'vibe_tags' => ['Family-Friendly', 'Modern', 'Accessible', 'Eco-Tourism', 'Relaxing'],
            'featured_amenities' => ['Large Outdoor Pool', 'Kids Club', 'Fitness Center', 'Misto Restaurant', 'Direct Beach Access'],
            'hotel_description' => 'Situated within the Lio Tourism Estate, Seda Lio offers modern comforts and seamless access to nature. Perfect for families and leisure travelers.',
            'specific_address' => 'Lio Tourism Estate, Barangay Villa Libertad, El Nido, Palawan',
            'latitude' => 11.2031,
            'longitude' => 119.4218,
            'images' => ['https://images.unsplash.com/photo-1520250497591-112f2f40a3f4'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 3. Cauayan Island Resort
        HotelModel::create([
            'hotel_name' => 'Cauayan Island Resort',
            'destination_id' => $destinationId,
            'type' => 'Boutique Island Resort',
            'vibe_tags' => ['Luxury', 'Tropical', 'Romance', 'Wellness', 'Private Island'],
            'featured_amenities' => ['Infinity Pool', 'Overwater Spa', 'Cauayan Restaurant', 'Diving & Snorkeling'],
            'hotel_description' => 'A premier luxury resort surrounded by pristine marine life and lush tropical greenery, famous for its iconic overwater villas.',
            'specific_address' => 'Cauayan Island, Bacuit Bay, El Nido, Palawan',
            'latitude' => 11.2828,
            'longitude' => 119.3486,
            'images' => ['https://images.unsplash.com/photo-1544551763-46a013bb70d5'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 4. El Nido Resorts - Miniloc Island
        HotelModel::create([
            'hotel_name' => 'El Nido Resorts - Miniloc Island',
            'destination_id' => $destinationId,
            'type' => 'Eco-Discovery Resort',
            'vibe_tags' => ['Eco-Friendly', 'Adventure', 'Family-Friendly', 'Rustic', 'Snorkeling'],
            'featured_amenities' => ['House Reef', 'Kayaking', 'Marine Sports Guide', 'Open-air Restaurant', 'Kids Activity Center'],
            'hotel_description' => 'Designed like a coastal village, Miniloc Island is the gateway to discovering the famous Big and Small Lagoons of El Nido.',
            'specific_address' => 'Miniloc Island, Bacuit Bay, El Nido, Palawan',
            'latitude' => 11.1528,
            'longitude' => 119.3172,
            'images' => ['https://images.unsplash.com/photo-1499793983690-e29da59ef1c2'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 5. Spin Designer Hostel
        HotelModel::create([
            'hotel_name' => 'Spin Designer Hostel',
            'destination_id' => $destinationId,
            'type' => 'Boutique Hostel',
            'vibe_tags' => ['Youthful', 'Social', 'Budget-Friendly', 'Backpacker', 'Trendy'],
            'featured_amenities' => ['Shared Lounge', 'Communal Kitchen', 'BBQ Facilities', 'Game Room', 'Laundry Services'],
            'hotel_description' => 'An award-winning designer hostel in El Nido town proper, offering a vibrant, social atmosphere for solo travelers and young groups.',
            'specific_address' => 'Balinsasayaw Road, Maligaya, El Nido, Palawan',
            'latitude' => 11.1786,
            'longitude' => 119.3905,
            'images' => ['https://images.unsplash.com/photo-1555854877-bab0e564b8d5'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 6. Charlie's El Nido
        HotelModel::create([
            'hotel_name' => 'Charlie\'s El Nido',
            'destination_id' => $destinationId,
            'type' => 'Boutique Hotel',
            'vibe_tags' => ['Modern', 'Chic', 'Relaxing', 'Tropical Design', 'Accessible'],
            'featured_amenities' => ['Outdoor Swimming Pool', 'Yoga Pavilion', 'Restaurant', 'Fitness Center', 'Spa Services'],
            'hotel_description' => 'A beautifully designed boutique hotel set slightly inland, providing a tranquil and lush escape away from the busy town center.',
            'specific_address' => 'National Highway, Barangay Villa Libertad, El Nido, Palawan',
            'latitude' => 11.1925,
            'longitude' => 119.4147,
            'images' => ['https://images.unsplash.com/photo-1566073771259-6a8506099945'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 7. Panorama Resort
        HotelModel::create([
            'hotel_name' => 'Panorama Resort',
            'destination_id' => $destinationId,
            'type' => 'Boutique Beach Resort',
            'vibe_tags' => ['Adults Only', 'Sunset Views', 'Aesthetic', 'Beachfront', 'Bohemian'],
            'featured_amenities' => ['Beach Club', 'Outdoor Pool', 'Sunset Lounge', 'Restaurant', 'Water Sports'],
            'hotel_description' => 'An adults-only boutique resort in Corong-Corong known for its iconic dome-shaped architecture, chill beach club, and stunning sunset views.',
            'specific_address' => 'Corong-Corong Beach, El Nido, Palawan',
            'latitude' => 11.1661,
            'longitude' => 119.3942,
            'images' => ['https://images.unsplash.com/photo-1498654896293-37aacf113fd9'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 8. Buko Beach Resort
        HotelModel::create([
            'hotel_name' => 'Buko Beach Resort',
            'destination_id' => $destinationId,
            'type' => 'Native Boutique Resort',
            'vibe_tags' => ['Rustic Charm', 'Intimate', 'Sunset Views', 'Beachfront', 'Tropical'],
            'featured_amenities' => ['Infinity Plunge Pool', 'Open-air Bar', 'Massage Services', 'Tour Desk'],
            'hotel_description' => 'A small, intimate beachfront resort offering native-style luxury cottages and grand villas. Perfect for watching the famous Corong-Corong sunsets.',
            'specific_address' => 'Sitio Lugadia, Barangay Corong-Corong, El Nido, Palawan',
            'latitude' => 11.1633,
            'longitude' => 119.3951,
            'images' => ['https://images.unsplash.com/photo-1510414842594-a61c69b5ae57'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 9. Matinloc Resort
        HotelModel::create([
            'hotel_name' => 'Matinloc Resort',
            'destination_id' => $destinationId,
            'type' => 'Luxury Resort',
            'vibe_tags' => ['Exclusive', 'Nature Integration', 'Private Island', 'Secluded', 'Luxury'],
            'featured_amenities' => ['Private Beach', 'Infinity Pool', 'Library', 'Fitness Center', 'Spa'],
            'hotel_description' => 'Nestled along the limestone cliffs of Matinloc Island, this resort offers unparalleled luxury and exclusivity in the heart of Bacuit Bay.',
            'specific_address' => 'Matinloc Island, El Nido, Palawan',
            'latitude' => 11.2001,
            'longitude' => 119.2933,
            'images' => ['https://images.unsplash.com/photo-1582719478250-c89cae4dc85b'],
            'embedding' => null,
            'is_shown' => true,
        ]);

        // 10. Lihim Resorts
        HotelModel::create([
            'hotel_name' => 'Lihim Resorts',
            'destination_id' => $destinationId,
            'type' => 'Ultra-Luxury Resort',
            'vibe_tags' => ['Ultra-Luxury', 'Secluded', 'Nature Immersion', 'Exclusive', 'Personalized'],
            'featured_amenities' => ['Butler Service', 'Private Lounge', 'Luxury Spa', 'Gourmet Dining', 'Private Yachts'],
            'hotel_description' => 'A hidden gem tucked in the lush forests of El Nido. Lihim (meaning "secret") offers an exclusive, highly personalized luxury experience.',
            'specific_address' => 'Sitio Caalan, Barangay Masagana, El Nido, Palawan',
            'latitude' => 11.1895,
            'longitude' => 119.3921,
            'images' => ['https://images.unsplash.com/photo-1571896349842-33c89424de2d'],
            'embedding' => null,
            'is_shown' => true,
        ]);
    }
}
