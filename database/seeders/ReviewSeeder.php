<?php

namespace Database\Seeders;

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReviewSeeder extends Seeder
{
    /**
     * Seed verified reviews across seeded listings + AI summaries (cold-start fix).
     */
    public function run(): void
    {
        if (Review::exists()) {
            $this->command?->info('Reviews already exist — skipping ReviewSeeder.');

            return;
        }

        $gemini = app(GeminiService::class);

        $users = $this->ensureUsers();

        $hotels = HotelModel::limit(8)->get();
        $rooms = RoomType::limit(12)->get();
        $activities = ActivityModel::limit(10)->get();
        $packages = Package::limit(6)->get();

        $pool = collect($this->reviewPool());
        $targets = $this->buildTargetPool($hotels, $rooms, $activities, $packages);

        if ($targets->isEmpty()) {
            $this->command?->warn('No listings to attach reviews to. Run the listing seeders first.');

            return;
        }

        $index = 0;

        foreach ($pool as $i => $entry) {
            $target = $targets[$i % $targets->count()];
            $user = $users[$i % count($users)];

            $analysis = $gemini->analyzeReviewSentiment($entry['comment']);

            $booking = $this->createCompletedBooking($user, $target);

            Review::create([
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'reviewable_type' => $target['type'],
                'reviewable_id' => $target['id'],
                'hotel_id' => $target['hotel_id'],
                'room_id' => $target['room_id'],
                'activity_id' => $target['activity_id'],
                'package_id' => $target['package_id'],
                'rating' => $entry['rating'],
                'comment' => $entry['comment'],
                'sentiment' => $analysis['sentiment'],
                'sentiment_score' => $analysis['confidence_score'],
                'extracted_keywords' => $analysis['extracted_keywords'],
                'is_verified_booking' => true,
                'is_published' => true,
                'created_at' => now()->subDays($index + 1)->subHours(rand(0, 12)),
                'updated_at' => now(),
            ]);

            $index++;
        }

        $this->rebuildSummaries($gemini);

        $this->command?->info("Seeded {$index} verified reviews + AI summaries.");
    }

    /**
     * Guarantee a pool of named users for the reviews.
     */
    protected function ensureUsers(): array
    {
        $names = [
            ['Maria Santos', 'maria.santos@example.com'],
            ['John Dela Cruz', 'john.delacruz@example.com'],
            ['Jose Ramirez', 'jose.ramirez@example.com'],
            ['Ana Reyes', 'ana.reyes@example.com'],
            ['Michael Tan', 'michael.tan@example.com'],
            ['Kaye Villanueva', 'kaye.villanueva@example.com'],
            ['Paolo Garcia', 'paolo.garcia@example.com'],
            ['Nicole Bautista', 'nicole.bautista@example.com'],
        ];

        $users = [];

        foreach ($names as [$name, $email]) {
            $users[] = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('12345678')]
            );
        }

        return $users;
    }

    /**
     * Build a flat pool of review targets with their direct FK columns.
     */
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

    /**
     * Create a minimal completed booking + item so the review is fully legitimate.
     */
    protected function createCompletedBooking(User $user, array $target): Booking
    {
        $booking = Booking::create([
            'booking_code' => 'RVT-'.strtoupper(Str::random(8)),
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

        return $booking;
    }

    /**
     * Rebuild review_summaries for every entity with reviews + the platform overall.
     */
    protected function rebuildSummaries(GeminiService $gemini): void
    {
        $groups = Review::published()
            ->get(['reviewable_type', 'reviewable_id'])
            ->groupBy('reviewable_type');

        foreach ($groups as $type => $rows) {
            foreach ($rows->groupBy('reviewable_id') as $id => $_) {
                $reviews = Review::published()->ofEntity($type, (int) $id)->with('user')->get();

                $label = $this->entityLabel($type, (int) $id);

                $this->storeSummary($gemini, $type, (int) $id, $label, $reviews);
            }
        }

        $platform = Review::published()->with('user')->get();

        if ($platform->isNotEmpty()) {
            $this->storeSummary($gemini, ReviewSummary::PLATFORM_OVERALL_TYPE, null, 'SunnyTrips Overall Platform', $platform);
        }
    }

    protected function storeSummary(GeminiService $gemini, string $type, ?int $id, string $label, $reviews): void
    {
        $total = $reviews->count();

        if ($total === 0) {
            return;
        }

        $ai = $gemini->summarizeReviews($reviews, $label);

        ReviewSummary::updateOrCreate(
            ['summarizable_type' => $type, 'summarizable_id' => $id],
            [
                'total_reviews' => $total,
                'average_rating' => round($reviews->avg('rating'), 2),
                'positive_percentage' => round(($reviews->where('sentiment', Review::SENTIMENT_POSITIVE)->count() / $total) * 100, 2),
                'neutral_percentage' => round(($reviews->where('sentiment', Review::SENTIMENT_NEUTRAL)->count() / $total) * 100, 2),
                'negative_percentage' => round(($reviews->where('sentiment', Review::SENTIMENT_NEGATIVE)->count() / $total) * 100, 2),
                'ai_summary_text' => $ai['ai_summary_text'],
                'top_positive_highlights' => $ai['top_positive_highlights'],
                'top_negative_highlights' => $ai['top_negative_highlights'],
                'most_frequent_keywords' => $ai['most_frequent_keywords'],
                'last_analyzed_at' => now(),
            ]
        );
    }

    /**
     * Human-readable label for a reviewable entity.
     */
    protected function entityLabel(string $type, int $id): string
    {
        $class = class_exists($type)
            ? $type
            : Relation::getMorphedModel($type);

        if (! $class) {
            return "Listing #{$id}";
        }

        $instance = $class::find($id);

        if ($instance) {
            return $instance->hotel_name
                ?? $instance->room_name
                ?? $instance->activity_name
                ?? $instance->name
                ?? "Listing #{$id}";
        }

        return "Listing #{$id}";
    }

    /**
     * 55 realistic English + Taglish verified reviews.
     */
    protected function reviewPool(): array
    {
        return [
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
        ];
    }
}
