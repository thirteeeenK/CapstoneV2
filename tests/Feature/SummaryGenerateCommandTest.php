<?php

use App\Jobs\UpdateEntityReviewSummaryJob;
use App\Models\HotelModel;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Queue;

function createAnalyzedReview(string $entityType, int $entityId, string $comment = 'The beach access and staff were excellent.'): Review
{
    return Review::create([
        'reviewable_type' => $entityType,
        'reviewable_id' => $entityId,
        'hotel_id' => $entityType === 'hotel' ? $entityId : null,
        'room_id' => $entityType === 'room' ? $entityId : null,
        'rating' => 5,
        'comment' => $comment,
        'sentiment' => Review::SENTIMENT_POSITIVE,
        'sentiment_score' => 0.9500,
        'extracted_keywords' => ['beach', 'staff'],
        'is_published' => true,
    ]);
}

function summaryOutput(string $text): array
{
    return [
        'ai_summary_text' => $text,
        'top_positive_highlights' => ['Excellent beach access'],
        'top_negative_highlights' => [],
        'most_frequent_keywords' => ['beach' => 1, 'staff' => 1],
    ];
}

test('it queues a missing hotel summary by default', function () {
    Queue::fake();

    $hotel = HotelModel::factory()->create();
    createAnalyzedReview('hotel', $hotel->id);

    $this->artisan('summary:generate', ['--type' => 'hotel', '--hotel' => (string) $hotel->id])
        ->expectsOutputToContain('queued=1')
        ->assertExitCode(0);

    Queue::assertPushed(UpdateEntityReviewSummaryJob::class, function (UpdateEntityReviewSummaryJob $job) use ($hotel) {
        return $job->entityType === 'hotel'
            && $job->entityId === $hotel->id
            && $job->force === true;
    });
});

test('it reports missing work in dry run without dispatching a job', function () {
    Queue::fake();

    $hotel = HotelModel::factory()->create();
    createAnalyzedReview('hotel', $hotel->id);

    $this->artisan('summary:generate', ['--type' => 'hotel', '--hotel' => (string) $hotel->id, '--dry-run' => true])
        ->expectsOutputToContain('Would generate hotel')
        ->expectsOutputToContain('would_generate=1')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
    expect(ReviewSummary::count())->toBe(0);
});

test('it generates a hotel summary synchronously', function () {
    $hotel = HotelModel::factory()->create();
    createAnalyzedReview('hotel', $hotel->id);

    $gemini = Mockery::mock(GeminiService::class);
    $gemini->shouldReceive('summarizeReviews')
        ->once()
        ->andReturn(summaryOutput('- Guests praised the beach access and helpful staff.'));
    app()->instance(GeminiService::class, $gemini);

    $this->artisan('summary:generate', ['--type' => 'hotel', '--hotel' => (string) $hotel->id, '--sync' => true])
        ->expectsOutputToContain('generated=1')
        ->assertExitCode(0);

    expect(ReviewSummary::where('summarizable_type', 'hotel')
        ->where('summarizable_id', $hotel->id)
        ->value('ai_summary_text'))
        ->toBe('- Guests praised the beach access and helpful staff.');
});

test('it generates a room summary synchronously', function () {
    $hotel = HotelModel::factory()->create();
    $room = RoomType::factory()->for($hotel, 'hotel')->create();
    createAnalyzedReview('room', $room->id, 'The room was spotless and had a beautiful sea view.');

    $gemini = Mockery::mock(GeminiService::class);
    $gemini->shouldReceive('summarizeReviews')
        ->once()
        ->andReturn(summaryOutput('- Guests praised the spotless room and sea view.'));
    app()->instance(GeminiService::class, $gemini);

    $this->artisan('summary:generate', ['--type' => 'room', '--room' => (string) $room->id, '--sync' => true])
        ->expectsOutputToContain('generated=1')
        ->assertExitCode(0);

    expect(ReviewSummary::where('summarizable_type', 'room')
        ->where('summarizable_id', $room->id)
        ->value('ai_summary_text'))
        ->toBe('- Guests praised the spotless room and sea view.');
});
