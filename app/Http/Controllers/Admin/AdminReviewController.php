<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityModel;
use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\Package;
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

        // Recent reviews table with filters
        $reviewsQuery = Review::with(['user', 'reviewable', 'booking'])
            ->latest();

        if ($request->filled('star')) {
            $reviewsQuery->where('rating', (int) $request->query('star'));
        }

        if ($request->filled('sentiment')) {
            $reviewsQuery->where('sentiment', $request->query('sentiment'));
        }

        if ($request->filled('entity_type')) {
            $reviewsQuery->where('reviewable_type', $request->query('entity_type'));
        }

        if ($request->filled('destination_id')) {
            $destId = $request->query('destination_id');
            $reviewsQuery->where(function ($q) use ($destId) {
                $q->whereIn('hotel_id', HotelModel::where('destination_id', $destId)->pluck('id'))
                  ->orWhereIn('activity_id', ActivityModel::where('destination_id', $destId)->pluck('id'))
                  ->orWhereIn('package_id', Package::where('destination_id', $destId)->pluck('id'));
            });
        }

        if ($request->filled('hotel_id')) {
            $reviewsQuery->where('hotel_id', $request->query('hotel_id'));
        }

        if ($request->filled('room_id')) {
            $reviewsQuery->where('room_id', $request->query('room_id'));
        }

        if ($request->filled('featured')) {
            $reviewsQuery->where('is_featured', $request->query('featured') === '1');
        }

        $reviews = $reviewsQuery->paginate(15)->withQueryString();

        // Chart data for the ApexCharts analytics dashboard
        $ratingDist = Review::selectRaw('rating, COUNT(*) as c')
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('c', 'rating')
            ->map(fn($count) => (int) $count)
            ->all();

        $chartData = [
            'sentiment' => $sentimentCounts,
            'ratingDist' => $ratingDist,
            'leaderboards' => $leaderboards,
            'needsImprovement' => $needsImprovement,
            'keywords' => $keywordCloud,
        ];

        $sentimentMeta = [
            'positive' => ['label' => 'Positive', 'icon' => 'sentiment_satisfied', 'bar' => 'bg-emerald-500'],
            'neutral' => ['label' => 'Neutral', 'icon' => 'sentiment_neutral', 'bar' => 'bg-amber-400'],
            'negative' => ['label' => 'Negative', 'icon' => 'sentiment_dissatisfied', 'bar' => 'bg-rose-500'],
        ];

        if ($request->boolean('partial')) {
            return view('admin.reviews._table', compact('reviews', 'sentimentMeta'));
        }

        $destinations = DestinationModel::orderBy('name')->get(['id', 'name']);
        $hotels = HotelModel::orderBy('hotel_name')->get(['id', 'hotel_name', 'destination_id']);
        $rooms = RoomType::orderBy('room_name')->get(['id', 'hotel_id', 'room_name']);

        return view('admin.reviews.index', compact(
            'totalReviews',
            'avgRating',
            'sentimentCounts',
            'sentimentTotal',
            'leaderboards',
            'needsImprovement',
            'keywordCloud',
            'reviews',
            'destinations',
            'hotels',
            'rooms',
            'chartData',
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
     * Toggle whether a review is featured on the landing page.
     */
    public function toggleFeatured($id)
    {
        $review = Review::findOrFail($id);
        $review->is_featured = !$review->is_featured;
        $review->save();

        return back()->with(
            'success',
            "Review #{$review->id} " . ($review->is_featured ? 'featured on the landing page.' : 'removed from the landing page.')
        );
    }

    /**
     * Form for creating reviews — booking backfill or manual entry.
     */
    public function create()
    {
        $reviewedBookingIds = Review::pluck('booking_id')->filter();

        $bookings = \App\Models\Booking::with(['user', 'items'])
            ->withCount('items')
            ->where('status', \App\Models\Booking::STATUS_COMPLETED)
            ->when($reviewedBookingIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $reviewedBookingIds))
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (\App\Models\Booking $booking) {
                return [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'contact_name' => $booking->contact_name,
                    'guest_name' => $booking->user?->name ?? '—',
                    'items' => $booking->items
                        ->filter(fn($item) => array_key_exists($item->item_type, \App\Services\ReviewService::REVIEWABLE_ITEM_TYPES))
                        ->map(fn($item) => [
                            'id' => $item->id,
                            'item_type' => $item->item_type,
                            'item_title' => $item->item_title,
                            'item_subtitle' => $item->item_subtitle,
                        ])
                        ->values(),
                ];
            });

        $hotels = HotelModel::orderBy('hotel_name')->get(['id', 'hotel_name']);
        $rooms = RoomType::with('hotel:id,hotel_name')->orderBy('room_name')->get(['id', 'hotel_id', 'room_name']);
        $activities = ActivityModel::orderBy('activity_name')->get(['id', 'activity_name']);
        $packages = Package::orderBy('name')->get(['id', 'name']);

        $threshold = (int) config('services.gemini.review_summary_threshold', 3);

        return view('admin.reviews.create', compact('bookings', 'hotels', 'rooms', 'activities', 'packages', 'threshold'));
    }

    /**
     * Persist an admin-authored review — booking backfill or manual entry.
     */
    public function store(Request $request)
    {
        $isManual = $request->input('review_mode') === 'manual';

        if ($isManual) {
            $validated = $request->validate([
                'reviewer_name' => ['required', 'string', 'max:255'],
                'manual_entity_type' => ['required', 'string', 'in:hotel,room,activity,package'],
                'manual_entity_id' => ['required', 'integer'],
                'rating' => ['required', 'integer', 'between:1,5'],
                'comment' => ['required', 'string', 'min:10', 'max:2000'],
            ]);

            $review = app(\App\Services\ReviewService::class)->manualAdminStore(
                $validated['reviewer_name'],
                $validated['manual_entity_type'],
                (int) $validated['manual_entity_id'],
                (int) $validated['rating'],
                $validated['comment']
            );

            return redirect()
                ->route('admin.reviews.index')
                ->with('success', "Manual review #{$review->id} created for {$validated['manual_entity_type']} #{$validated['manual_entity_id']}. Sentiment analysis and summary updates are queued.");
        }

        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'booking_item_id' => ['nullable', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $booking = \App\Models\Booking::findOrFail($validated['booking_id']);

        $review = app(\App\Services\ReviewService::class)->adminStore(
            $booking,
            (int) $validated['rating'],
            $validated['comment'],
            !empty($validated['booking_item_id']) ? (int) $validated['booking_item_id'] : null
        );

        return redirect()
            ->route('admin.reviews.index')
            ->with('success', "Review #{$review->id} created for booking {$booking->booking_code}. Sentiment analysis and summary updates are queued.");
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