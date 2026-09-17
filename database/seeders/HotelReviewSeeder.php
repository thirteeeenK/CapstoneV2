<?php

namespace Database\Seeders;

use App\Models\HotelModel;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Services\GeminiService;
use App\Services\HotelReviewsMdParser;
use Illuminate\Database\Seeder;

/**
 * Seeds the curated real third-party hotel reviews from
 * database/seeders/data/hotel_reviews.md (hotel-level only).
 *
 * Runs synchronously: sentiment + per-hotel summaries are computed inline
 * (same math as ReviewSeeder) so a fresh `migrate --seed` deploy is
 * complete without depending on queue timing. Idempotent — skips rows
 * already present and hotels missing from the database (deleted).
 */
class HotelReviewSeeder extends Seeder
{
    public function run(GeminiService $gemini): void
    {
        ['reviews' => $reviews, 'errors' => $errors] = HotelReviewsMdParser::parse(
            database_path('seeders/data/hotel_reviews.md')
        );

        foreach ($errors as $error) {
            $this->command?->warn($error);
        }

        $hotels = HotelModel::pluck('id', 'hotel_name');
        $created = 0;
        $skipped = 0;
        $touched = [];

        foreach ($reviews as $index => $row) {
            if (! isset($hotels[$row['hotel']])) {
                $skipped++;

                continue;
            }

            $hotelId = $hotels[$row['hotel']];

            $exists = Review::where('hotel_id', $hotelId)
                ->where('reviewable_type', 'hotel')
                ->where('reviewable_id', $hotelId)
                ->where('reviewer_name', $row['reviewer'])
                ->where('comment', $row['comment'])
                ->exists();

            if ($exists) {
                $skipped++;
                $touched[$hotelId] = $row['hotel'];

                continue;
            }

            $analysis = $gemini->analyzeReviewSentiment($row['comment']);

            Review::create([
                'booking_id' => null,
                'user_id' => null,
                'reviewer_name' => $row['reviewer'],
                'reviewable_type' => 'hotel',
                'reviewable_id' => $hotelId,
                'hotel_id' => $hotelId,
                'room_id' => null,
                'activity_id' => null,
                'package_id' => null,
                'rating' => $row['rating'],
                'comment' => $row['comment'],
                'sentiment' => $analysis['sentiment'],
                'sentiment_score' => $analysis['confidence_score'],
                'extracted_keywords' => $analysis['extracted_keywords'],
                'is_verified_booking' => false,
                'is_published' => true,
                'created_at' => now()->subDays($index + 1)->subHours($index % 13),
                'updated_at' => now(),
            ]);

            $touched[$hotelId] = $row['hotel'];
            $created++;
        }

        foreach ($touched as $hotelId => $label) {
            $hotelReviews = Review::published()->ofEntity('hotel', (int) $hotelId)->with('user')->get();

            if ($hotelReviews->isEmpty()) {
                continue;
            }

            $total = $hotelReviews->count();
            $ai = $gemini->summarizeReviews($hotelReviews, $label);

            ReviewSummary::updateOrCreate(
                ['summarizable_type' => 'hotel', 'summarizable_id' => $hotelId],
                [
                    'total_reviews' => $total,
                    'average_rating' => round($hotelReviews->avg('rating'), 2),
                    'positive_percentage' => round(($hotelReviews->where('sentiment', Review::SENTIMENT_POSITIVE)->count() / $total) * 100, 2),
                    'neutral_percentage' => round(($hotelReviews->where('sentiment', Review::SENTIMENT_NEUTRAL)->count() / $total) * 100, 2),
                    'negative_percentage' => round(($hotelReviews->where('sentiment', Review::SENTIMENT_NEGATIVE)->count() / $total) * 100, 2),
                    'ai_summary_text' => $ai['ai_summary_text'],
                    'top_positive_highlights' => $ai['top_positive_highlights'],
                    'top_negative_highlights' => $ai['top_negative_highlights'],
                    'most_frequent_keywords' => $ai['most_frequent_keywords'],
                    'last_analyzed_at' => now(),
                ]
            );
        }

        $this->command?->info("HotelReviewSeeder: created {$created}, skipped {$skipped}, summaries for ".count($touched).' hotel(s).');

        // Platform overall must cover every published review (pool + manual),
        // otherwise the user-side strip goes stale while the admin counts drift.
        $allPublished = Review::published()->with('user')->get();

        if ($allPublished->isNotEmpty()) {
            $total = $allPublished->count();
            $ai = $gemini->summarizeReviews($allPublished, 'SunnyTrips Overall Platform');

            ReviewSummary::updateOrCreate(
                ['summarizable_type' => ReviewSummary::PLATFORM_OVERALL_TYPE, 'summarizable_id' => null],
                [
                    'total_reviews' => $total,
                    'average_rating' => round($allPublished->avg('rating'), 2),
                    'positive_percentage' => round(($allPublished->where('sentiment', Review::SENTIMENT_POSITIVE)->count() / $total) * 100, 2),
                    'neutral_percentage' => round(($allPublished->where('sentiment', Review::SENTIMENT_NEUTRAL)->count() / $total) * 100, 2),
                    'negative_percentage' => round(($allPublished->where('sentiment', Review::SENTIMENT_NEGATIVE)->count() / $total) * 100, 2),
                    'ai_summary_text' => $ai['ai_summary_text'],
                    'top_positive_highlights' => $ai['top_positive_highlights'],
                    'top_negative_highlights' => $ai['top_negative_highlights'],
                    'most_frequent_keywords' => $ai['most_frequent_keywords'],
                    'last_analyzed_at' => now(),
                ]
            );

            $this->command?->info("HotelReviewSeeder: platform overall rebuilt over {$total} reviews.");
        }
    }
}
