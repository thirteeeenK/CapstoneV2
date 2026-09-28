<?php

use App\Models\HotelModel;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Services\GeminiService;
use App\Services\SummaryJudgeService;

test('it retries an invalid five point judge score and accepts a valid four point score', function () {
    $hotel = HotelModel::factory()->create();
    Review::create([
        'reviewable_type' => 'hotel',
        'reviewable_id' => $hotel->id,
        'hotel_id' => $hotel->id,
        'rating' => 5,
        'comment' => 'The beachfront location and friendly staff made our stay excellent.',
        'sentiment' => Review::SENTIMENT_POSITIVE,
        'sentiment_score' => 0.9500,
        'extracted_keywords' => ['beachfront', 'staff'],
        'is_published' => true,
    ]);
    ReviewSummary::create([
        'summarizable_type' => 'hotel',
        'summarizable_id' => $hotel->id,
        'ai_summary_text' => '- Guests praised the beachfront location and friendly staff.',
    ]);

    $gemini = Mockery::mock(GeminiService::class);
    $gemini->shouldReceive('loadSystemPrompt')
        ->once()
        ->with('summary-judge-prompt.md')
        ->andReturn('Hotel: {HOTEL_NAME}\nReviews: {REVIEWS_TEXT}\nCandidate: {AI_SUMMARY_TEXT}');
    $gemini->shouldReceive('generateContentWithModel')
        ->twice()
        ->withArgs(function (string $model, string $instruction, string $prompt, float $temperature) {
            expect($instruction)->toBe('You are a strict JSON evaluation grader.');
            expect($prompt)->toContain('Candidate: - Guests praised the beachfront location and friendly staff.');
            expect($prompt)->not->toContain('human reference summary');
            expect($temperature)->toBe(0.0);

            return $model !== '';
        })
        ->andReturn(
            '{"faithfulness":5,"coverage":4,"conciseness":4,"reasoning":"Invalid old scale."}',
            '{"faithfulness":4,"coverage":3,"conciseness":4,"reasoning":"The candidate matches the recurring positive feedback."}'
        );
    app()->instance(GeminiService::class, $gemini);

    $result = app(SummaryJudgeService::class)->scoreHotel($hotel->hotel_name);

    expect($result)
        ->toMatchArray([
            'hotel' => $hotel->hotel_name,
            'faithfulness' => 4,
            'coverage' => 3,
            'conciseness' => 4,
            'mean' => 3.6667,
        ])
        ->and($result['reasoning'])->toBe('The candidate matches the recurring positive feedback.');
});
