<?php

namespace App\Jobs;

use App\Models\Review;
use App\Models\ReviewSummary;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateEntityReviewSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $entityType,
        public ?int $entityId,
        public string $entityLabel
    ) {
    }

    public function handle(GeminiService $gemini): void
    {
        $existing = ReviewSummary::where('summarizable_type', $this->entityType)
            ->where('summarizable_id', $this->entityId)
            ->first();

        if ($existing && $existing->last_analyzed_at !== null) {
            $threshold = (int) config('services.gemini.review_summary_threshold', 3);

            $sinceQuery = Review::published()->where('created_at', '>', $existing->last_analyzed_at);

            if ($this->entityId !== null) {
                $sinceQuery = $sinceQuery->ofEntity($this->entityType, $this->entityId);
            }

            if ($sinceQuery->count() < $threshold) {
                return;
            }
        }

        $query = Review::published();

        if ($this->entityId === null) {
            // Platform-wide overall summary covers every published review.
            $query = $query->with('user');
        } else {
            $query = $query->ofEntity($this->entityType, $this->entityId)->with('user');
        }

        $reviews = $query->latest()->limit(50)->get();

        $total = $reviews->count();

        $summaryData = [
            'summarizable_type' => $this->entityType,
            'summarizable_id' => $this->entityId,
            'total_reviews' => $total,
            'average_rating' => $total > 0 ? round($reviews->avg('rating'), 2) : 0.00,
            'positive_percentage' => 0.00,
            'neutral_percentage' => 0.00,
            'negative_percentage' => 0.00,
            'ai_summary_text' => null,
            'top_positive_highlights' => [],
            'top_negative_highlights' => [],
            'most_frequent_keywords' => [],
            'last_analyzed_at' => now(),
        ];

        if ($total > 0) {
            $summaryData['positive_percentage'] = round(($reviews->where('sentiment', Review::SENTIMENT_POSITIVE)->count() / $total) * 100, 2);
            $summaryData['neutral_percentage'] = round(($reviews->where('sentiment', Review::SENTIMENT_NEUTRAL)->count() / $total) * 100, 2);
            $summaryData['negative_percentage'] = round(($reviews->where('sentiment', Review::SENTIMENT_NEGATIVE)->count() / $total) * 100, 2);

            $ai = $gemini->summarizeReviews($reviews, $this->entityLabel);

            $summaryData['ai_summary_text'] = $ai['ai_summary_text'];
            $summaryData['top_positive_highlights'] = $ai['top_positive_highlights'];
            $summaryData['top_negative_highlights'] = $ai['top_negative_highlights'];
            $summaryData['most_frequent_keywords'] = $ai['most_frequent_keywords'];
        }

        ReviewSummary::updateOrCreate(
            ['summarizable_type' => $this->entityType, 'summarizable_id' => $this->entityId],
            $summaryData
        );
    }

    /**
     * Human label used when the caller did not provide one.
     */
    public static function labelFor(string $entityType, ?int $entityId): string
    {
        if ($entityId === null) {
            return 'SunnyTrips Overall Platform';
        }

        if (!class_exists($entityType)) {
            return "{$entityType} #{$entityId}";
        }

        $instance = $entityType::find($entityId);

        if ($instance) {
            return $instance->hotel_name
                ?? $instance->room_name
                ?? $instance->activity_name
                ?? $instance->name
                ?? "{$entityType} #{$entityId}";
        }

        return "{$entityType} #{$entityId}";
    }
}