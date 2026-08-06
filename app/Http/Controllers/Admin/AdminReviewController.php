<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityModel;
use App\Models\HotelModel;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReviewController extends Controller
{
    /**
     * Operational DSS review analytics dashboard.
     */
    public function index(Request $request)
    {
        $totalReviews = Review::count();
        $avgRating = Review::published()->avg('rating') ?? 0;
        $avgRating = round((float) $avgRating, 2);

        $sentimentCounts = [
            'positive' => Review::where('sentiment', Review::SENTIMENT_POSITIVE)->count(),
            'neutral' => Review::where('sentiment', Review::SENTIMENT_NEUTRAL)->count(),
            'negative' => Review::where('sentiment', Review::SENTIMENT_NEGATIVE)->count(),
        ];
        $sentimentTotal = max(1, array_sum($sentimentCounts));

        // Top rated leaderboards (listings with at least 1 review)
        $leaderboards = [
            'hotels' => $this->topListings(HotelModel::class),
            'rooms' => $this->topListings(RoomType::class),
            'activities' => $this->topListings(ActivityModel::class),
        ];

        // Needs-improvement alerts: negative sentiment spikes > 15%
        $needsImprovement = ReviewSummary::where('summarizable_type', '!=', ReviewSummary::PLATFORM_OVERALL_TYPE)
            ->where('total_reviews', '>', 0)
            ->where('negative_percentage', '>', 15)
            ->orderByDesc('negative_percentage')
            ->limit(10)
            ->get()
            ->map(function (ReviewSummary $summary) {
                $instance = null;

                if (class_exists($summary->summarizable_type)) {
                    $instance = $summary->summarizable_type::find($summary->summarizable_id);
                } elseif ($morphed = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($summary->summarizable_type)) {
                    $instance = $morphed::find($summary->summarizable_id);
                }

                return [
                    'label' => $instance?->hotel_name
                        ?? $instance?->room_name
                        ?? $instance?->activity_name
                        ?? $instance?->name
                        ?? "{$summary->summarizable_type} #{$summary->summarizable_id}",
                    'negative_percentage' => (float) $summary->negative_percentage,
                    'total_reviews' => $summary->total_reviews,
                    'average_rating' => (float) $summary->average_rating,
                ];
            });

        // Keyword cloud across all entity summaries
        $keywordCounts = [];
        ReviewSummary::where('most_frequent_keywords', '!=', '[]')->get(['most_frequent_keywords'])
            ->each(function (ReviewSummary $summary) use (&$keywordCounts) {
                foreach (($summary->most_frequent_keywords ?? []) as $keyword => $count) {
                    $keywordCounts[(string) $keyword] = ($keywordCounts[(string) $keyword] ?? 0) + (int) $count;
                }
            });
        arsort($keywordCounts);
        $keywordCloud = array_slice($keywordCounts, 0, 30, true);

        // Recent reviews table
        $reviews = Review::with(['user', 'reviewable'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.reviews.index', compact(
            'totalReviews',
            'avgRating',
            'sentimentCounts',
            'sentimentTotal',
            'leaderboards',
            'needsImprovement',
            'keywordCloud',
            'reviews'
        ));
    }

    /**
     * Top-rated listings of a given type (min 1 published review).
     */
    protected function topListings(string $entityType, int $limit = 5): array
    {
        $morphType = (new $entityType)->getMorphClass();

        return Review::published()
            ->where('reviewable_type', $morphType)
            ->select(
                'reviewable_id',
                DB::raw('AVG(rating) as avg_rating'),
                DB::raw('COUNT(*) as review_count')
            )
            ->groupBy('reviewable_id')
            ->havingRaw('COUNT(*) >= 1')
            ->orderByDesc('avg_rating')
            ->orderByDesc('review_count')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($entityType) {
                $instance = $entityType::find($row->reviewable_id);

                return [
                    'label' => $instance?->hotel_name
                        ?? $instance?->room_name
                        ?? $instance?->activity_name
                        ?? $instance?->name
                        ?? "Listing #{$row->reviewable_id}",
                    'average_rating' => round((float) $row->avg_rating, 2),
                    'review_count' => (int) $row->review_count,
                ];
            })
            ->all();
    }

    /**
     * Toggle a review's public visibility.
     */
    public function togglePublish($id)
    {
        $review = Review::findOrFail($id);
        $review->is_published = !$review->is_published;
        $review->save();

        return back()->with(
            'success',
            "Review #{$review->id} " . ($review->is_published ? 'published.' : 'hidden from public.')
        );
    }

    /**
     * Remove a review permanently.
     */
    public function destroy($id)
    {
        $review = Review::findOrFail($id);
        $review->delete();

        return back()->with('success', "Review #{$review->id} deleted.");
    }
}