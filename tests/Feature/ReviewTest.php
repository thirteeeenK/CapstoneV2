<?php

use App\Models\ActivityModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode([
                    'sentiment' => 'positive',
                    'confidence_score' => 0.94,
                    'extracted_keywords' => ['ocean view', 'clean room', 'friendly staff'],
                    'ai_summary_text' => "- Guests love the ocean view\n- Staff is friendly",
                    'top_positive_highlights' => ['ocean view'],
                    'top_negative_highlights' => [],
                    'most_frequent_keywords' => [['keyword' => 'ocean view', 'count' => 2]],
                ])]]]],
            ],
        ], 200),
    ]);

    $destination = DestinationModel::create(['name' => 'Boracay', 'description' => 'Test', 'image' => null]);
    $hotel = HotelModel::create([
        'hotel_name' => 'Test Beach Resort',
        'destination_id' => $destination->id,
        'type' => 'Resort',
        'hotel_description' => 'A test resort.',
        'specific_address' => 'Station 1, White Beach',
        'latitude' => 11.9674,
        'longitude' => 121.9251,
        'is_shown' => true,
        'images' => [],
    ]);
    $this->room = RoomType::create([
        'hotel_id' => $hotel->id,
        'room_name' => 'Deluxe Ocean View',
        'base_price' => 2000.00,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'extra_person_fee' => 500.00,
        'total_rooms' => 5,
        'room_amenities' => [],
        'images' => [],
        'is_shown' => true,
    ]);
    $this->activity = ActivityModel::create([
        'destination_id' => $destination->id,
        'activity_name' => 'Island Hopping Tour',
        'description' => 'Tour the islands.',
        'category' => 'island-hopping',
        'activity_level' => 'Easy',
        'rate' => '₱1,500 / person',
        'is_shown' => true,
        'images' => [],
    ]);
    $this->package = Package::create([
        'destination_id' => $destination->id,
        'name' => 'Boracay Escape Promo',
        'type' => 'standard',
        'price' => 9999.00,
        'days' => 3,
        'nights' => 2,
        'min_pax' => 2,
        'generic_inclusions' => ['Hotel', 'Tour'],
        'images' => [],
        'is_active' => true,
    ]);

    $this->user = User::factory()->create(['name' => 'Juan Dela Cruz']);
    $this->user->preferences_embedding = '[' . implode(',', array_fill(0, 3072, '0.1')) . ']';
    $this->user->save();

    $this->admin = \App\Models\AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@sunnytripstest.com',
        'password' => 'password',
    ]);
});

function makeCompletedBooking(User $user, RoomType $room, string $status = 'completed'): Booking
{
    $booking = Booking::create([
        'booking_code' => 'RVT-' . strtoupper(\Illuminate\Support\Str::random(8)),
        'user_id' => $user->id,
        'status' => $status,
        'total_amount' => 6000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'net_amount' => 6000,
        'payment_status' => 'paid',
        'payment_method' => 'Simulator',
        'contact_name' => $user->name,
        'contact_email' => $user->email,
        'contact_phone' => '09171234567',
    ]);

    BookingItem::create([
        'booking_id' => $booking->id,
        'item_type' => 'room',
        'item_id' => $room->id,
        'item_title' => $room->room_name,
        'item_subtitle' => '1 night stay',
        'hotel_name' => 'Test Beach Resort',
        'unit_price' => 2000,
        'quantity' => 1,
        'selected_pax' => 2,
        'subtotal' => 6000,
        'availability_status' => 'available',
        'item_snapshot' => [],
    ]);

    return $booking;
}

it('publishes a verified review for a completed booking and runs AI sentiment', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $response = $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'The ocean view room was breathtaking and spotless! Staff were super polite.',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $review = Review::first();

    expect($review)->not->toBeNull()
        ->and($review->booking_id)->toBe($booking->id)
        ->and($review->user_id)->toBe($this->user->id)
        ->and($review->reviewable_type)->toBe((new RoomType)->getMorphClass())
        ->and($review->room_id)->toBe($this->room->id)
        ->and($review->hotel_id)->toBe($this->room->hotel_id)
        ->and($review->rating)->toBe(5)
        ->and($review->is_verified_booking)->toBeTrue()
        ->and($review->is_published)->toBeTrue()
        ->and($review->sentiment)->toBe(Review::SENTIMENT_POSITIVE)
        ->and($review->extracted_keywords)->toContain('ocean view');

    // AI summary record should exist for the room + platform after the sync queue run
    expect(ReviewSummary::where('summarizable_type', (new RoomType)->getMorphClass())->where('summarizable_id', $this->room->id)->exists())->toBeTrue()
        ->and(ReviewSummary::where('summarizable_type', ReviewSummary::PLATFORM_OVERALL_TYPE)->exists())->toBeTrue();
});

it('rejects reviews for bookings that are not completed', function () {
    $booking = makeCompletedBooking($this->user, $this->room, 'paid');

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'This should be rejected since the trip is not done.',
    ])->assertStatus(422);

    expect(Review::count())->toBe(0);
});

it('rejects reviews for another users booking', function () {
    $other = User::factory()->create();
    $booking = makeCompletedBooking($other, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 4,
        'comment' => 'I did not book this, so this must fail.',
    ])->assertStatus(403);

    expect(Review::count())->toBe(0);
});

it('allows only one review per booking', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $payload = [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'First review for this completed booking.',
    ];

    $this->actingAs($this->user)->postJson(route('reviews.store'), $payload)->assertStatus(201);

    $this->actingAs($this->user)->postJson(route('reviews.store'), $payload)->assertStatus(422);

    expect(Review::count())->toBe(1);
});

it('validates rating and comment length', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 6,
        'comment' => 'Valid comment body with enough characters.',
    ])->assertStatus(422);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 3,
        'comment' => 'too short',
    ])->assertStatus(422);

    expect(Review::count())->toBe(0);
});

it('hides unpublished reviews from the public hub', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'A wonderful stay with a gorgeous sunrise view.',
    ])->assertStatus(201);

    $review = Review::first();
    $review->is_published = false;
    $review->save();

    $this->get(route('reviews.index'))->assertOk()->assertDontSee('gorgeous sunrise view');

    $review->is_published = true;
    $review->save();

    $this->get(route('reviews.index'))->assertOk()->assertSee('gorgeous sunrise view');
});

it('lists reviewable bookings for the authenticated user', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $response = $this->actingAs($this->user)->getJson(route('reviews.eligible'));

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('bookings.0.booking_id', $booking->id)
        ->assertJsonPath('bookings.0.reviewable_items.0.item_title', 'Deluxe Ocean View');
});

it('supports reviewing activities and packages', function () {
    $activityBooking = Booking::create([
        'booking_code' => 'RVT-ACT' . strtoupper(\Illuminate\Support\Str::random(6)),
        'user_id' => $this->user->id,
        'status' => 'completed',
        'total_amount' => 3000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'net_amount' => 3000,
        'payment_status' => 'paid',
        'payment_method' => 'Simulator',
        'contact_name' => $this->user->name,
        'contact_email' => $this->user->email,
        'contact_phone' => '09171234567',
    ]);

    BookingItem::create([
        'booking_id' => $activityBooking->id,
        'item_type' => 'activity',
        'item_id' => $this->activity->id,
        'item_title' => $this->activity->activity_name,
        'item_subtitle' => '1 pax',
        'unit_price' => 3000,
        'quantity' => 1,
        'selected_pax' => 1,
        'subtotal' => 3000,
        'availability_status' => 'available',
        'item_snapshot' => [],
    ]);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $activityBooking->id,
        'rating' => 5,
        'comment' => 'The island hopping guide was punctual and the tour was fantastic.',
    ])->assertStatus(201);

    expect(Review::first()->activity_id)->toBe($this->activity->id)
        ->and(Review::first()->reviewable_type)->toBe((new ActivityModel)->getMorphClass());
});

it('lets admins view the review analytics dashboard', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'Friendly staff and a spotless room, will book again.',
    ])->assertStatus(201);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reviews.index'))
        ->assertOk()
        ->assertSee('Review Analytics & Sentiment', false)
        ->assertSee('Deluxe Ocean View');
});

it('formats the reviewer alias as first name + last initial', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'Great stay with excellent service all around.',
    ])->assertStatus(201);

    expect(Review::first()->reviewer_alias)->toBe('Juan C.');
});

it('skips summary regeneration below the configurable threshold', function () {
    $morph = (new RoomType)->getMorphClass();

    foreach (range(1, 2) as $i) {
        $booking = makeCompletedBooking($this->user, $this->room);
        Review::create([
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'reviewable_type' => $morph,
            'reviewable_id' => $this->room->id,
            'hotel_id' => $this->room->hotel_id,
            'room_id' => $this->room->id,
            'rating' => 5,
            'comment' => "Threshold probe review number {$i}.",
        ]);
    }

    $summary = ReviewSummary::create([
        'summarizable_type' => $morph,
        'summarizable_id' => $this->room->id,
        'total_reviews' => 2,
        'average_rating' => 5.00,
        'ai_summary_text' => 'ORIGINAL CACHED SUMMARY',
        'last_analyzed_at' => now()->subDay(),
    ]);

    config(['services.gemini.review_summary_threshold' => 3]);

    (new \App\Jobs\UpdateEntityReviewSummaryJob($morph, $this->room->id, 'Deluxe Ocean View'))
        ->handle(app(\App\Services\GeminiService::class));

    $summary->refresh();

    expect($summary->last_analyzed_at->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($summary->ai_summary_text)->toBe('ORIGINAL CACHED SUMMARY');
});

it('regenerates the summary once the threshold of new reviews is reached', function () {
    $morph = (new RoomType)->getMorphClass();

    foreach (range(1, 3) as $i) {
        $booking = makeCompletedBooking($this->user, $this->room);
        Review::create([
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'reviewable_type' => $morph,
            'reviewable_id' => $this->room->id,
            'hotel_id' => $this->room->hotel_id,
            'room_id' => $this->room->id,
            'rating' => 5,
            'comment' => "Threshold probe review number {$i}.",
        ]);
    }

    $summary = ReviewSummary::create([
        'summarizable_type' => $morph,
        'summarizable_id' => $this->room->id,
        'total_reviews' => 3,
        'average_rating' => 5.00,
        'ai_summary_text' => 'ORIGINAL CACHED SUMMARY',
        'last_analyzed_at' => now()->subDay(),
    ]);

    config(['services.gemini.review_summary_threshold' => 3]);

    (new \App\Jobs\UpdateEntityReviewSummaryJob($morph, $this->room->id, 'Deluxe Ocean View'))
        ->handle(app(\App\Services\GeminiService::class));

    $summary->refresh();

    expect($summary->last_analyzed_at->gte(now()->subMinutes(5)))->toBeTrue()
        ->and($summary->ai_summary_text)->toContain('ocean view');
});

it('shows only featured published reviews on the landing page', function () {
    $bookingOne = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $bookingOne->id,
        'rating' => 5,
        'comment' => 'FEATURED TESTIMONIAL OCEANFRONT PARADISE',
    ])->assertStatus(201);

    $featured = Review::first();
    $featured->is_featured = true;
    $featured->save();

    $bookingTwo = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $bookingTwo->id,
        'rating' => 4,
        'comment' => 'REGULAR REVIEW NOT FEATURED ANYWHERE',
    ])->assertStatus(201);

    $this->get(route('landing'))
        ->assertOk()
        ->assertSee('FEATURED TESTIMONIAL OCEANFRONT PARADISE', false)
        ->assertDontSee('REGULAR REVIEW NOT FEATURED ANYWHERE', false)
        ->assertSee('See all reviews', false)
        ->assertSee(route('reviews.index'), false);
});

it('lets admins feature and unfeature a review', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'A delightful stay worth sharing on the homepage.',
    ])->assertStatus(201);

    $review = Review::first();
    expect($review->is_featured)->toBeFalse();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.reviews.toggle-featured', $review->id))
        ->assertRedirect();

    expect($review->fresh()->is_featured)->toBeTrue();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.reviews.toggle-featured', $review->id))
        ->assertRedirect();

    expect($review->fresh()->is_featured)->toBeFalse();
});

it('lets admins backfill a review for a past booking', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.reviews.store'), [
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => 'Backfilled feedback recorded by the admin for a past trip.',
        ])
        ->assertRedirect(route('admin.reviews.index'));

    $review = Review::first();

    expect($review)->not->toBeNull()
        ->and($review->booking_id)->toBe($booking->id)
        ->and($review->user_id)->toBe($this->user->id)
        ->and($review->reviewable_type)->toBe((new RoomType)->getMorphClass())
        ->and($review->room_id)->toBe($this->room->id)
        ->and($review->is_published)->toBeTrue()
        ->and($review->is_verified_booking)->toBeTrue();
});

it('blocks admin backfill for an already-reviewed booking', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->user)->postJson(route('reviews.store'), [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'The original guest review for this booking.',
    ])->assertStatus(201);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.reviews.store'), [
            'booking_id' => $booking->id,
            'rating' => 3,
            'comment' => 'This duplicate backfill must be rejected.',
        ])
        ->assertStatus(422);

    expect(Review::count())->toBe(1);
});

it('lists unreviewed completed bookings on the admin create form', function () {
    $booking = makeCompletedBooking($this->user, $this->room);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reviews.create'))
        ->assertOk()
        ->assertSee($booking->booking_code)
        ->assertSee('Deluxe Ocean View');

    Review::create([
        'booking_id' => $booking->id,
        'user_id' => $this->user->id,
        'reviewable_type' => (new RoomType)->getMorphClass(),
        'reviewable_id' => $this->room->id,
        'hotel_id' => $this->room->hotel_id,
        'room_id' => $this->room->id,
        'rating' => 5,
        'comment' => 'Already reviewed so it must disappear from the picker.',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reviews.create'))
        ->assertOk()
        ->assertDontSee($booking->booking_code);
});

it('lets admins create a manual review without a booking', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.reviews.store'), [
            'review_mode' => 'manual',
            'reviewer_name' => 'Maria Santos',
            'manual_entity_type' => 'room',
            'manual_entity_id' => $this->room->id,
            'rating' => 5,
            'comment' => 'Absolutely stunning ocean view room. The staff was incredibly attentive and the amenities were top-notch.',
        ])
        ->assertRedirect(route('admin.reviews.index'));

    $review = Review::first();

    expect($review)->not->toBeNull()
        ->and($review->booking_id)->toBeNull()
        ->and($review->user_id)->toBeNull()
        ->and($review->reviewer_name)->toBe('Maria Santos')
        ->and($review->reviewable_type)->toBe((new RoomType)->getMorphClass())
        ->and($review->room_id)->toBe($this->room->id)
        ->and($review->is_verified_booking)->toBeFalse()
        ->and($review->is_published)->toBeTrue()
        ->and($review->sentiment)->toBe(Review::SENTIMENT_POSITIVE)
        ->and($review->extracted_keywords)->toContain('ocean view');
});

it('lets admins create a manual review for a hotel', function () {
    $hotel = HotelModel::first();

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.reviews.store'), [
            'review_mode' => 'manual',
            'reviewer_name' => 'Kenji Tanaka',
            'manual_entity_type' => 'hotel',
            'manual_entity_id' => $hotel->id,
            'rating' => 4,
            'comment' => 'Beautiful resort with excellent facilities. Would definitely recommend to other travelers.',
        ])
        ->assertRedirect(route('admin.reviews.index'));

    $review = Review::first();

    expect($review->reviewer_name)->toBe('Kenji Tanaka')
        ->and($review->reviewable_type)->toBe((new HotelModel)->getMorphClass())
        ->and($review->hotel_id)->toBe($hotel->id)
        ->and($review->is_verified_booking)->toBeFalse();
});

it('uses reviewer_name as the alias for manual reviews', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.reviews.store'), [
            'review_mode' => 'manual',
            'reviewer_name' => 'Sarah Jenkins',
            'manual_entity_type' => 'activity',
            'manual_entity_id' => $this->activity->id,
            'rating' => 5,
            'comment' => 'The island hopping tour was spectacular. Great guide and beautiful stops.',
        ])
        ->assertRedirect();

    expect(Review::first()->reviewer_alias)->toBe('Sarah Jenkins');
});

it('validates manual review fields', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(route('admin.reviews.store'), [
            'review_mode' => 'manual',
            'reviewer_name' => '',
            'manual_entity_type' => 'invalid',
            'manual_entity_id' => 99999,
            'rating' => 6,
            'comment' => 'too short',
        ])
        ->assertStatus(422);

    expect(Review::count())->toBe(0);
});

it('shows entity collections on the admin create form for manual mode', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.reviews.create'))
        ->assertOk()
        ->assertSee('Manual Entry')
        ->assertSee('Deluxe Ocean View')
        ->assertSee('Island Hopping Tour')
        ->assertSee('Boracay Escape Promo');
});
