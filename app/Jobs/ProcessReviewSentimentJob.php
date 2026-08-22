<?php

namespace App\Jobs;

use App\Models\Review;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessReviewSentimentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reviewId) {}

    public function handle(GeminiService $gemini): void
    {
        $review = Review::find($this->reviewId);

        if (! $review) {
            return;
        }

        $analysis = $gemini->analyzeReviewSentiment($review->comment);

        $review->sentiment = $analysis['sentiment'];
        $review->sentiment_score = $analysis['confidence_score'];
        $review->extracted_keywords = $analysis['extracted_keywords'];
        $review->save();
    }
}
