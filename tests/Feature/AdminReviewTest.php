<?php

use App\Models\AdminModel;
use App\Models\HotelModel;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode([
                    'sentiment' => 'positive',
                    'confidence_score' => 0.95,
                    'extracted_keywords' => ['clean', 'quiet', 'nice view'],
                    'ai_summary_text' => '- Clean and quiet atmosphere',
                    'top_positive_highlights' => ['clean'],
                    'top_negative_highlights' => [],
                    'most_frequent_keywords' => [['keyword' => 'clean', 'count' => 1]],
                ])]]]],
            ],
        ], 200),
    ]);
});

function createReviewTestAdmin(): AdminModel
{
    return AdminModel::create([
        'name' => 'Admin User',
        'email' => 'admin_review_test_'.uniqid().'@sunnytrips.com',
        'password' => 'password',
    ]);
}

function createReviewTestRoom(): RoomType
{
    return RoomType::factory()->create([
        'room_name' => 'Standard Deluxe',
        'base_price' => 1500.00,
        'base_occupancy' => 2,
        'max_occupancy' => 2,
        'extra_person_fee' => 0,
        'total_rooms' => 3,
    ]);
}

it('renders the admin review dashboard and partial table', function () {
    $admin = createReviewTestAdmin();
    $room = createReviewTestRoom();

    for ($i = 0; $i < 3; $i++) {
        Review::create([
            'reviewable_type' => (new RoomType)->getMorphClass(),
            'reviewable_id' => $room->id,
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'is_published' => true,
            'sentiment' => Review::SENTIMENT_POSITIVE,
            'rating' => 5,
            'comment' => 'Great experience number '.$i,
        ]);
    }

    ReviewSummary::create([
        'summarizable_type' => (new HotelModel)->getMorphClass(),
        'summarizable_id' => $room->hotel_id,
        'total_reviews' => 10,
        'average_rating' => 2.5,
        'positive_percentage' => 20,
        'neutral_percentage' => 40,
        'negative_percentage' => 40,
        'most_frequent_keywords' => ['noisy' => 4, 'slow service' => 2],
        'last_analyzed_at' => now(),
    ]);

    // Full page render
    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.reviews.index'));

    $response->assertOk()
        ->assertViewIs('admin.reviews.index')
        ->assertViewHas(['totalReviews', 'avgRating', 'sentimentCounts', 'leaderboards', 'needsImprovement', 'keywordCloud', 'reviews', 'chartData']);

    // Partial AJAX / live-search render
    $partialResponse = $this->actingAs($admin, 'admin')
        ->get(route('admin.reviews.index', ['partial' => '1']), ['X-Requested-With' => 'XMLHttpRequest']);

    $partialResponse->assertOk()
        ->assertViewIs('admin.reviews._table')
        ->assertViewHas('reviews');
});

it('filters reviews by rating and sentiment on admin dashboard', function () {
    $admin = createReviewTestAdmin();
    $room = createReviewTestRoom();

    Review::create([
        'reviewable_type' => (new RoomType)->getMorphClass(),
        'reviewable_id' => $room->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'rating' => 1,
        'sentiment' => Review::SENTIMENT_NEGATIVE,
        'comment' => 'Very bad experience',
    ]);

    Review::create([
        'reviewable_type' => (new RoomType)->getMorphClass(),
        'reviewable_id' => $room->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'rating' => 5,
        'sentiment' => Review::SENTIMENT_POSITIVE,
        'comment' => 'Very good experience',
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.reviews.index', ['star' => 1, 'sentiment' => Review::SENTIMENT_NEGATIVE]));

    $response->assertOk();
    $paginator = $response->viewData('reviews');
    expect($paginator->total())->toBe(1);
});

it('can toggle review publication and featured flags', function () {
    $admin = createReviewTestAdmin();
    $room = createReviewTestRoom();

    $review = Review::create([
        'reviewable_type' => (new RoomType)->getMorphClass(),
        'reviewable_id' => $room->id,
        'rating' => 4,
        'comment' => 'Decent stay',
        'is_published' => true,
        'is_featured' => false,
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.reviews.toggle-publish', $review->id))
        ->assertRedirect();

    expect($review->fresh()->is_published)->toBeFalse();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.reviews.toggle-featured', $review->id))
        ->assertRedirect();

    expect($review->fresh()->is_featured)->toBeTrue();
});

it('can delete a review', function () {
    $admin = createReviewTestAdmin();
    $room = createReviewTestRoom();

    $review = Review::create([
        'reviewable_type' => (new RoomType)->getMorphClass(),
        'reviewable_id' => $room->id,
        'rating' => 3,
        'comment' => 'Average stay',
    ]);

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.reviews.destroy', $review->id))
        ->assertRedirect();

    expect(Review::find($review->id))->toBeNull();
});
