<?php

namespace Database\Seeders;

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SentimentEvalSeeder extends Seeder
{
    /**
     * Exactly 100 reviews: 62 kept understandable existing + 38 new,
     * overall 70 pos / 20 neu / 10 neg intent, IDs 1..100 after TRUNCATE RESTART IDENTITY.
     * 70% (≈70) assigned round-robin to John Kenly Pamor / Bien Batan / Erica Mae Bonite,
     * 30% (≈30) to the 8 existing accounts. All blind: sentiment='pending'.
     */
    public function run(): void
    {
        if (Review::whereHas('booking', fn ($q) => $q->where('booking_code', 'like', 'SEV-%'))->exists()) {
            $this->command?->info('Sentiment eval reviews already exist — skipping SentimentEvalSeeder.');

            return;
        }

        [$groupmates, $others] = $this->ensureUsers();

        $hotels = HotelModel::limit(8)->get();
        $rooms = RoomType::limit(12)->get();
        $activities = ActivityModel::limit(10)->get();
        $packages = Package::limit(6)->get();

        $targets = $this->buildTargetPool($hotels, $rooms, $activities, $packages);

        if ($targets->isEmpty()) {
            $this->command?->warn('No listings to attach eval reviews to. Run the listing seeders first.');

            return;
        }

        $pool = collect($this->evalPool())->shuffle(20260825)->values();

        $index = 0;

        foreach ($pool as $entry) {
            $target = $targets[$index % $targets->count()];
            // 70% groupmates round-robin, 30% existing round-robin
            $user = $index < 70
                ? $groupmates[$index % count($groupmates)]
                : $others[($index - 70) % count($others)];

            $booking = $this->createCompletedBooking($user, $target);

            Review::create([
                'booking_id' => $booking->id,
                'booking_item_id' => $booking->items()->first()?->id,
                'user_id' => $user->id,
                'reviewable_type' => $target['type'],
                'reviewable_id' => $target['id'],
                'hotel_id' => $target['hotel_id'],
                'room_id' => $target['room_id'],
                'activity_id' => $target['activity_id'],
                'package_id' => $target['package_id'],
                'rating' => $entry['rating'],
                'comment' => $entry['comment'],
                'sentiment' => 'pending',
                'sentiment_score' => 0.0000,
                'ground_truth_sentiment' => null,
                'extracted_keywords' => [],
                'is_verified_booking' => true,
                'is_published' => true,
                'created_at' => now()->subDays($index + 1)->subHours(rand(0, 12)),
                'updated_at' => now(),
            ]);

            $index++;
        }

        $this->command?->info("Seeded {$index} blind eval reviews (IDs 1..{$index}, 70% groupmates round-robin, 70/20/10). Next: sentiment:export-template -> label -> sentiment:import-ground-truth -> sentiment:run-ai -> sentiment:evaluate");
    }

    /**
     * Ensure the 3 groupmates exist, plus the 8 existing accounts.
     *
     * @return array{0: User[], 1: User[]}
     */
    protected function ensureUsers(): array
    {
        $groupmates = [
            User::firstOrCreate(['email' => 'johnkenlypamor13@gmail.com'], ['name' => 'John Kenly Pamor', 'password' => Hash::make('12345678')]),
            User::firstOrCreate(['email' => 'bien.batan@example.com'], ['name' => 'Bien Batan', 'password' => Hash::make('12345678')]),
            User::firstOrCreate(['email' => 'erica.mae.bonite@example.com'], ['name' => 'Erica Mae Bonite', 'password' => Hash::make('12345678')]),
        ];

        $others = [];
        foreach ([
            ['Maria Santos', 'maria.santos@example.com'],
            ['John Dela Cruz', 'john.delacruz@example.com'],
            ['Jose Ramirez', 'jose.ramirez@example.com'],
            ['Ana Reyes', 'ana.reyes@example.com'],
            ['Michael Tan', 'michael.tan@example.com'],
            ['Kaye Villanueva', 'kaye.villanueva@example.com'],
            ['Paolo Garcia', 'paolo.garcia@example.com'],
            ['Nicole Bautista', 'nicole.bautista@example.com'],
        ] as [$name, $email]) {
            $others[] = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('12345678')]);
        }

        return [$groupmates, $others];
    }

    protected function buildTargetPool($hotels, $rooms, $activities, $packages): Collection
    {
        $pool = collect();

        foreach ($hotels as $hotel) {
            $pool->push([
                'type' => (new HotelModel)->getMorphClass(),
                'id' => $hotel->id,
                'hotel_id' => $hotel->id,
                'room_id' => null,
                'activity_id' => null,
                'package_id' => null,
                'label' => $hotel->hotel_name,
            ]);
        }

        foreach ($rooms as $room) {
            $pool->push([
                'type' => (new RoomType)->getMorphClass(),
                'id' => $room->id,
                'hotel_id' => $room->hotel_id,
                'room_id' => $room->id,
                'activity_id' => null,
                'package_id' => null,
                'label' => $room->room_name.' at '.($room->hotel?->hotel_name ?? 'Hotel'),
            ]);
        }

        foreach ($activities as $activity) {
            $pool->push([
                'type' => (new ActivityModel)->getMorphClass(),
                'id' => $activity->id,
                'hotel_id' => null,
                'room_id' => null,
                'activity_id' => $activity->id,
                'package_id' => null,
                'label' => $activity->activity_name,
            ]);
        }

        foreach ($packages as $package) {
            $pool->push([
                'type' => (new Package)->getMorphClass(),
                'id' => $package->id,
                'hotel_id' => null,
                'room_id' => null,
                'activity_id' => null,
                'package_id' => $package->id,
                'label' => $package->name,
            ]);
        }

        return $pool;
    }

    protected function createCompletedBooking(User $user, array $target): Booking
    {
        $booking = Booking::create([
            'booking_code' => 'SEV-'.strtoupper(Str::random(8)),
            'user_id' => $user->id,
            'status' => Booking::STATUS_COMPLETED,
            'total_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => 0,
            'payment_status' => Booking::PAYMENT_PAID,
            'payment_method' => 'Simulator',
            'contact_name' => $user->name,
            'contact_email' => $user->email,
            'contact_phone' => '09171234567',
            'approved_at' => now()->subDays(10),
            'paid_at' => now()->subDays(9),
        ]);

        $itemType = match ($target['type']) {
            (new ActivityModel)->getMorphClass() => 'activity',
            (new Package)->getMorphClass() => 'package',
            default => 'room',
        };

        BookingItem::create([
            'booking_id' => $booking->id,
            'item_type' => $itemType,
            'item_id' => $target['id'],
            'item_title' => $target['label'],
            'item_subtitle' => $itemType === 'room' ? '1 night stay' : 'Standard booking',
            'hotel_name' => $itemType === 'room' ? ($target['label'] ?? null) : null,
            'unit_price' => 0,
            'quantity' => 1,
            'selected_pax' => 2,
            'subtotal' => 0,
            'availability_status' => BookingItem::AVAIL_AVAILABLE,
            'item_snapshot' => [],
        ]);

        return $booking->load('items');
    }

    /**
     * Kept understandable existing: 55 ReviewSeeder + 7 valid ST (very good hotel etc.)
     * New 38: 22 pos / 12 neu / 4 neg to reach 70/20/10 overall.
     * Combined 100 shuffled with seed 20260825.
     *
     * @return array<int, array{rating:int, comment:string}>
     */
    public function evalPool(): array
    {
        return array_merge($this->keptPool(), $this->newPool());
    }

    /** @return array<int, array{rating:int, comment:string}> */
    public function keptPool(): array
    {
        return [
            // 55 from ReviewSeeder (all understandable)
            ['rating' => 5, 'comment' => 'Super ganda ng room! Clean bed, working AC, and ang bait ng staff. Will definitely book again next summer.'],
            ['rating' => 5, 'comment' => 'The ocean view was breathtaking and spotless. Staff were super polite and always smiling.'],
            ['rating' => 4, 'comment' => 'Spacious balcony with a perfect beachfront view. Water pressure can be low during peak hours though.'],
            ['rating' => 5, 'comment' => 'Breakfast buffet was fantastic, lobby spotless, and check-in took under five minutes.'],
            ['rating' => 3, 'comment' => 'Nice pool area and friendly front desk, but the Wi-Fi in the secondary building was spotty.'],
            ['rating' => 4, 'comment' => 'Quiet AC, comfortable king bed, daily housekeeping was top notch.'],
            ['rating' => 5, 'comment' => 'Amazing sunset views from the balcony, super worth it! The staff even surprised us on our anniversary.'],
            ['rating' => 2, 'comment' => 'Room smelled a bit musty and the air conditioner was noisy. Location is great though.'],
            ['rating' => 5, 'comment' => 'Very clean and cozy. Ang sarap ng breakfast at mabait ang lahat ng staff.'],
            ['rating' => 4, 'comment' => 'Good value for money, great location near D\'Mall. Bed was comfortable and room was quiet.'],
            ['rating' => 5, 'comment' => 'Loved every minute. The infinity pool with direct beach access was the highlight of our stay.'],
            ['rating' => 3, 'comment' => 'Decent stay overall. Some noise from the pool area at night, but the room itself was comfortable.'],
            ['rating' => 4, 'comment' => 'Friendly staff and fast check-in. Bathroom could use some renovation but everything was clean.'],
            ['rating' => 5, 'comment' => 'Perfect for families! The kids loved the pool and the staff were so helpful with our requests.'],
            ['rating' => 2, 'comment' => 'Expectation was high because of the photos, but the room was small and a bit dated.'],
            ['rating' => 5, 'comment' => 'Spacious room, amazing sea view, sulit na sulit ang bayad namin.'],
            ['rating' => 4, 'comment' => 'Great gym and very quiet at night. Wi-Fi was fast enough for remote work.'],
            ['rating' => 5, 'comment' => 'The tour guide was punctual and gave clear instructions. Highly recommended for families!'],
            ['rating' => 4, 'comment' => 'Beautiful island hopping tour, the lagoons were stunning. Boat waiting time was about 20 minutes.'],
            ['rating' => 5, 'comment' => 'Best island tour ever! Super organized, maaasahan ang guide, and the snorkeling spots were amazing.'],
            ['rating' => 3, 'comment' => 'The tour itself was good but the pickup was delayed by 30 minutes. Communicate better please.'],
            ['rating' => 5, 'comment' => 'Ang galing ng guide namin! Very informative and patient with the kids. Five stars talaga.'],
            ['rating' => 4, 'comment' => 'Great value, well-planned itinerary. Bring sunscreen, sobrang init pero worth it.'],
            ['rating' => 2, 'comment' => 'Boat was crowded and the lunch was underwhelming. Scenery was the saving grace.'],
            ['rating' => 5, 'comment' => 'Seamless booking from start to finish. The package included everything we expected and more.'],
            ['rating' => 4, 'comment' => 'Great promo package! The hotel + tour combo saved us a lot. Just wish transfer was included.'],
            ['rating' => 5, 'comment' => 'Everything matched the description perfectly. Smooth transaction and responsive support.'],
            ['rating' => 3, 'comment' => 'The itinerary was good but one activity got cancelled due to weather without a same-day replacement.'],
            ['rating' => 5, 'comment' => 'Ang ganda ng package! Sulit, hassle-free, and the customer service was outstanding.'],
            ['rating' => 4, 'comment' => 'Solid package overall. Minor hiccup with the voucher redemption but support fixed it fast.'],
            ['rating' => 5, 'comment' => 'Room was exactly like the photos, super clean, and the staff upgraded us for free!'],
            ['rating' => 4, 'comment' => 'Comfortable stay, good breakfast, near the beach. Pool closes a bit early at 8 PM though.'],
            ['rating' => 5, 'comment' => 'Mabait lahat ng staff, ang linis ng kwarto, at ang sarap ng food. 10/10!'],
            ['rating' => 3, 'comment' => 'Average experience. The room was fine but the hallway smelled of cigarette smoke.'],
            ['rating' => 5, 'comment' => 'The most relaxing stay we\'ve had. Quiet, clean, and the bed was heaven after a long island day.'],
            ['rating' => 4, 'comment' => 'Nice modern room with a great view. Extra person fee was a bit steep though.'],
            ['rating' => 5, 'comment' => 'Beautiful resort, spotless facilities, and everyone was so accommodating. Would love to come back!'],
            ['rating' => 2, 'comment' => 'Overpriced for the size of the room. The aircon was old and made noise the whole night.'],
            ['rating' => 4, 'comment' => 'The diving experience was incredible! Equipment was well-maintained and the crew was professional.'],
            ['rating' => 5, 'comment' => 'First time parasailing and the crew made me feel safe the whole time. Sobrang saya!'],
            ['rating' => 4, 'comment' => 'Great kayak tour through the mangroves. Guide was knowledgeable about the local wildlife.'],
            ['rating' => 3, 'comment' => 'Fun activity but the meeting point was hard to find. Suggest better signage.'],
            ['rating' => 5, 'comment' => 'ATV tour was a blast! The guides were friendly and the trail had amazing viewpoints.'],
            ['rating' => 4, 'comment' => 'Sunset sailing was magical. Boat was clean and the crew served refreshments.'],
            ['rating' => 2, 'comment' => 'Tour ran late and the guide seemed rushed. Not the experience we paid for.'],
            ['rating' => 5, 'comment' => 'Hotel staff went above and beyond — they even prepared a birthday cake for my wife.'],
            ['rating' => 4, 'comment' => 'Good location, clean room, decent breakfast. Slightly noisy during peak season.'],
            ['rating' => 5, 'comment' => 'Super linis, super ganda, super bait ng lahat. Perfect honeymoon stay!'],
            ['rating' => 3, 'comment' => 'Room service took an hour to arrive, but the food was delicious when it came.'],
            ['rating' => 5, 'comment' => 'The package was seamless — booking, payment, and vouchers all worked perfectly.'],
            ['rating' => 4, 'comment' => 'Great trip overall! Our only note is that the boat transfers could be more organized.'],
            ['rating' => 5, 'comment' => '100% sulit! Everything was smooth and the activities were fun for the whole barkada.'],
            ['rating' => 4, 'comment' => 'Good experience, responsive support on WhatsApp. The hotel choice included was excellent.'],
            ['rating' => 3, 'comment' => 'Average tour — nothing wrong but nothing extraordinary either. Decent value.'],
            ['rating' => 5, 'comment' => 'From booking to checkout, everything was perfect. Will recommend SunnyTrips to all my friends!'],
            // 7 valid ST (kept, nonsense removed)
            ['rating' => 4, 'comment' => 'very good hotel'],
            ['rating' => 5, 'comment' => 'The room was fantastic and the staff were very accommodating. Highly recommend!'],
            ['rating' => 4, 'comment' => 'Admin-created backfill review for a past completed booking.'],
            ['rating' => 5, 'comment' => 'Excellent stay, clean room and friendly staff. Will come back!'],
            ['rating' => 5, 'comment' => 'Amazing villa with great view and helpful hosts.'],
            ['rating' => 5, 'comment' => 'Great diving experience — crew was professional and equipment well-maintained.'],
            ['rating' => 2, 'comment' => 'very bad hotel and staffs — room was not clean and AC was noisy.'],
        ];
    }

    /** @return array<int, array{rating:int, comment:string}> */
    protected function newPool(): array
    {
        return [
            // 22 new positive (to hit 70 pos total)
            ['rating' => 5, 'comment' => 'Incredible hospitality! Staff remembered our anniversary and made it extra special.'],
            ['rating' => 5, 'comment' => 'Pristine beach, crystal water, and the kindest staff. Our best Boracay trip yet!'],
            ['rating' => 5, 'comment' => 'Room was luxurious, quiet, and spotless. We slept like babies every night.'],
            ['rating' => 5, 'comment' => 'Island hopping was perfectly timed — no crowds, great snorkeling, tasty lunch.'],
            ['rating' => 5, 'comment' => 'The resort exceeded all expectations. Food, service, and views were world-class.'],
            ['rating' => 4, 'comment' => 'Great stay overall, staff were attentive and the pool area was beautiful.'],
            ['rating' => 5, 'comment' => 'We loved the sunset cruise — romantic, calm, and very well organized.'],
            ['rating' => 5, 'comment' => 'Tour guide was excellent, kept everyone safe and entertained. Highly recommend!'],
            ['rating' => 5, 'comment' => 'The package was great value — everything as advertised and even better in person.'],
            ['rating' => 4, 'comment' => 'Good hotel, great location, breakfast was delicious. Minor queue at check-in only.'],
            ['rating' => 5, 'comment' => 'The beachfront room had an unbeatable view. Waking up to the sea was perfect!'],
            ['rating' => 5, 'comment' => 'Staff were so warm and helpful, they assisted with our island transfers smoothly.'],
            ['rating' => 5, 'comment' => 'Snorkeling spots were vibrant and uncrowded. Guide was very knowledgeable.'],
            ['rating' => 5, 'comment' => 'All transfers were on time, communication was clear. Very professional team!'],
            ['rating' => 4, 'comment' => 'Great value tour, well paced and not rushed. Enjoyed every stop!'],
            ['rating' => 5, 'comment' => 'The spa treatment after the tour was heavenly. Perfect end to the day!'],
            ['rating' => 5, 'comment' => 'Kids had a blast at the activity — safe, fun, and very well supervised.'],
            ['rating' => 5, 'comment' => 'Food was amazing, especially the fresh seafood. Staff were attentive and kind.'],
            ['rating' => 4, 'comment' => 'Nice room, comfortable bed, and a lovely sea breeze from the balcony.'],
            ['rating' => 5, 'comment' => 'We felt genuinely cared for — staff went the extra mile every day.'],
            ['rating' => 5, 'comment' => 'The villa was private and peaceful, yet close to everything. Perfect balance!'],
            ['rating' => 5, 'comment' => 'Every detail was thought through. We will definitely book with SunnyTrips again!'],
            // 12 new neutral (to hit 20 neu total)
            ['rating' => 3, 'comment' => 'Standard check-in at 3PM, room as booked, no extra fees. As expected.'],
            ['rating' => 3, 'comment' => 'The activity started on time and ended on time. No issues.'],
            ['rating' => 3, 'comment' => 'Hotel provided the listed amenities. Nothing extra, nothing missing.'],
            ['rating' => 3, 'comment' => 'Tour included lunch and boat transfer as described. Portions were adequate.'],
            ['rating' => 3, 'comment' => 'Room was as shown in photos. AC worked, Wi-Fi was average in the room.'],
            ['rating' => 3, 'comment' => 'We were assigned the booked category. View was as per description.'],
            ['rating' => 3, 'comment' => 'Breakfast was available 6–10AM, buffet style. Standard selection.'],
            ['rating' => 3, 'comment' => 'Transfer took about 25 minutes by van. Driver was on time.'],
            ['rating' => 3, 'comment' => 'Itinerary followed the posted schedule. Briefing was given at the port.'],
            ['rating' => 3, 'comment' => 'Booking confirmation was sent by email and accepted without issue.'],
            ['rating' => 3, 'comment' => 'The package covered accommodation and listed tours. No add-ons included.'],
            ['rating' => 3, 'comment' => 'Stayed two nights in the standard room. Facilities were as listed.'],
            // 4 new negative (to hit 10 neg total)
            ['rating' => 2, 'comment' => 'Room had a damp smell and the shower pressure was very low. Needs fixing.'],
            ['rating' => 1, 'comment' => 'Poor communication — tour started an hour late with no update. Disappointing.'],
            ['rating' => 2, 'comment' => 'Mold in the bathroom corner and slow drain. Staff were polite but did not fix it.'],
            ['rating' => 1, 'comment' => 'Boat was overcrowded and life vests were worn out. Did not feel safe.'],
        ];
    }
}
