<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HotelModel;
use App\Models\DestinationModel;

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
            'images' => ['hotels/sample1.jpg'],
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
            'images' => ['hotels/sample2.jpg'],
        ]);

        // 3
        HotelModel::create([
            'hotel_name' => 'Boracay Ocean Club Beach Resort',
            'type' => 'high-end-luxury-hotels',
            'vibe_tags' => ['Beachfront Resort', 'Scuba & Diving', 'Ocean Views', 'Laid-back Luxury'],
            'featured_amenities' => ['Beachfront Pool', 'PADI Dive Center', 'Massage Spa', 'Oceanview Dining'],
            'hotel_description' => "Boracay Ocean Club is located in Station 3, right along White Beach with direct beachfront access. The resort features a beachfront pool, spa services, and dive center, making it ideal for adventure seekers and beach lovers. With ocean-view rooms and a quieter location, it's perfect for travelers who want a more laid-back Boracay experience.",
            'destination_id' => $destinationId,
            'specific_address' => "Station 3, White Beach",
            'latitude' => 11.9509299,
            'longitude' => 121.9257116,
            'images' => ['hotels/sample3.jpg'],
        ]);

        // 4
        HotelModel::create([
            'hotel_name' => 'Henann Garden Resort',
            'type' => 'high-end-luxury-hotels',
            'vibe_tags' => ['Tropical Garden', 'Lagoon Pools', 'Central Location', 'Resort Oasis'],
            'featured_amenities' => ['Multiple Lagoon Pools', 'Lush Gardens', 'Buffet Breakfast', 'Fitness Center'],
            'hotel_description' => "Henann Garden is located in Station 2, just a 2-minute walk from White Beach and close to D'Mall. It has several lagoon-style pools and lush landscaping. With spacious rooms and on-site dining, it's perfect for travelers seeking comfort and convenience in the island's center.",
            'destination_id' => $destinationId,
            'specific_address' => "Station 2, White Beach",
            'latitude' => 11.9591852,
            'longitude' => 121.9252052,
            'images' => ['hotels/sample4.jpg'],
        ]);

        // 5
        HotelModel::create([
            'hotel_name' => 'My Station Hotel',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Affordable Comfort', 'Convenient', 'Station 1 Quiet', 'Modern Minimalist'],
            'featured_amenities' => ['Free Wi-Fi', 'Air Conditioning', '24/7 Front Desk', 'Concierge Tour Desk'],
            'hotel_description' => "Located in Station 1, My Station Hotel is just a short walk from the pristine White Beach. With modern, comfortable rooms and a welcoming atmosphere, this hotel offers a relaxing stay while being close to local attractions. Guests can enjoy easy access to restaurants, shopping, and the island's nightlife, making it perfect for both relaxation and adventure seekers.",
            'destination_id' => $destinationId,
            'specific_address' => "Station 1",
            'latitude' => 11.9653679,
            'longitude' => 121.9200853,
            'images' => ['hotels/sample5.jpg'],
        ]);

        // 6
        HotelModel::create([
            'hotel_name' => 'Frendz Resort & Hostel',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Social & Vibrant', 'Backpacker Community', 'Pool Parties', 'Nightlife Access'],
            'featured_amenities' => ['Outdoor Swimming Pool', 'Common Lounge & Bar', 'Social Events', 'Shared Kitchen'],
            'hotel_description' => "Found in Station 1, Frendz is a popular social spot for travelers. It's about a 5-minute walk to White Beach and has a pool and a common area that often hosts events. It's close to bars and restaurants but still maintains a laid-back vibe.",
            'destination_id' => $destinationId,
            'specific_address' => "Station 1",
            'latitude' => 11.9641647,
            'longitude' => 121.9223174,
            'images' => ['hotels/sample6.jpg'],
        ]);

        // 7
        HotelModel::create([
            'hotel_name' => 'Lazy Dog Bed & Breakfast',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Pet Friendly', 'Kitesurfing Hub', 'Rustic Eco Vibe', 'Tranquil & Cozy'],
            'featured_amenities' => ['Pet Friendly', 'In-house Cafe', 'Garden Courtyard', 'Kite Storage'],
            'hotel_description' => "Located near Bulabog Beach, Lazy Dog provides a cozy environment with both private rooms and dormitories. It's about a 2-minute walk to Bulabog Beach and 10 minutes to White Beach Station 2. There's no swimming pool, but the area is peaceful and just a short walk to D'Mall and various restaurants.",
            'destination_id' => $destinationId,
            'specific_address' => "Bulabog Beach",
            'latitude' => 11.9646558,
            'longitude' => 121.9261436,
            'images' => ['hotels/sample7.jpg'],
        ]);

        // 8
        HotelModel::create([
            'hotel_name' => 'Happiness Hostel',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Bohemian Vibe', 'Healthy Dining', 'Community Spirit', 'Chic Backpacker'],
            'featured_amenities' => ['Plunge Pool', 'Vegan & Israeli Cafe', 'Co-working Space', 'Rooftop Lounge'],
            'hotel_description' => "Near Bulabog Beach in Station 2, Happiness Hostel offers a bohemian vibe with both dorms and private rooms. It's a 2-minute walk to Bulabog and around 10 minutes to White Beach. The property has a small pool and an in-house restaurant with communal areas for socializing.",
            'destination_id' => $destinationId,
            'specific_address' => "Station 2 / Bulabog",
            'latitude' => 11.9343383,
            'longitude' => 121.8881044,
            'images' => ['hotels/sample8.jpg'],
        ]);

        // 9
        HotelModel::create([
            'hotel_name' => 'The Muse Hotel',
            'type' => 'mid-affordable-hotels',
            'vibe_tags' => ['Stylish Modern', 'Indoor Pool', 'Station 1 Beachfront', 'Boutique Elegance'],
            'featured_amenities' => ['Indoor Heated Pool', 'Rooftop Sun Deck', 'Beachfront Restaurant', 'Spa Services'],
            'hotel_description' => "Located in Station 1, The Muse is a stylish beachfront hotel with easy access to White Beach. It features an indoor pool, restaurant, and modern rooms. The quieter beachfront location is perfect for relaxation, while still being near several restaurants.",
            'destination_id' => $destinationId,
            'specific_address' => "Station 1",
            'latitude' => 11.9450172,
            'longitude' => 121.8335292,
            'images' => ['hotels/sample9.jpg'],
        ]);
    }
}