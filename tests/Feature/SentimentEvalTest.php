<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\User;
use App\Services\SentimentEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Avoid real Gemini calls in this test suite
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode([
                'sentiment' => 'positive',
                'confidence_score' => 0.9,
                'extracted_keywords' => ['test'],
                'ai_summary_text' => 'x',
                'top_positive_highlights' => [],
                'top_negative_highlights' => [],
                'most_frequent_keywords' => [],
            ])]]]]],
        ], 200),
    ]);
});

function makeReviewForEval(string $actual, string $predicted, string $comment = 'eval comment'): Review
{
    $user = User::factory()->create();
    $room = RoomType::factory()->create();

    $booking = Booking::factory()->create([
        'booking_code' => 'SEV-'.strtoupper(Str::random(8)),
        'user_id' => $user->id,
        'status' => Booking::STATUS_COMPLETED,
        'payment_status' => Booking::PAYMENT_PAID,
    ]);

    $item = BookingItem::factory()->create([
        'booking_id' => $booking->id,
        'item_type' => 'room',
        'item_id' => $room->id,
    ]);

    return Review::create([
        'booking_id' => $booking->id,
        'booking_item_id' => $item->id,
        'user_id' => $user->id,
        'reviewable_type' => (new RoomType)->getMorphClass(),
        'reviewable_id' => $room->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'rating' => 5,
        'comment' => $comment,
        'sentiment' => $predicted,
        'ground_truth_sentiment' => $actual,
        'sentiment_score' => 0.9,
        'extracted_keywords' => [],
        'is_verified_booking' => true,
        'is_published' => true,
    ]);
}

it('computes a 3x3 confusion matrix and per-class P/R/F1', function () {
    // pos 3: 2 correct, 1 -> neu  ; neu 2: 1 correct, 1 -> neg ; neg 1: 1 correct
    makeReviewForEval('positive', 'positive');
    makeReviewForEval('positive', 'positive');
    makeReviewForEval('positive', 'neutral');
    makeReviewForEval('neutral', 'neutral');
    makeReviewForEval('neutral', 'negative');
    makeReviewForEval('negative', 'negative');

    $svc = app(SentimentEvaluationService::class);
    $m = $svc->metrics();

    expect($m['totals']['n'])->toBe(6)
        ->and($m['matrix']['positive']['positive'])->toBe(2)
        ->and($m['matrix']['positive']['neutral'])->toBe(1)
        ->and($m['matrix']['neutral']['neutral'])->toBe(1)
        ->and($m['matrix']['neutral']['negative'])->toBe(1)
        ->and($m['matrix']['negative']['negative'])->toBe(1);

    // positive: TP2 FP0 FN1 => P1.0 R0.666.. F10.8
    expect($m['perClass']['positive']['precision'])->toBe(1.0)
        ->and($m['perClass']['positive']['recall'])->toBe(0.6667)
        ->and($m['perClass']['positive']['f1'])->toBe(0.8);

    // accuracy 4/6 = 0.6667
    expect($m['accuracy'])->toBe(0.6667);
});

it('excludes pending-AI rows from metrics and reports pending count', function () {
    makeReviewForEval('positive', 'pending', 'pending one');
    makeReviewForEval('positive', 'positive', 'done one');

    $svc = app(SentimentEvaluationService::class);
    $m = $svc->metrics();

    expect($m['totals']['n'])->toBe(1)
        ->and($m['pending'])->toBe(1);
});

it('imports ground truth CSV and updates only that column', function () {
    $r = makeReviewForEval('positive', 'positive', 'to be relabeled');
    // reset to null then import
    $r->ground_truth_sentiment = null;
    $r->save();

    $path = storage_path('app/test_gt.csv');
    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, "id,comment,human_actual\n{$r->id},\"{$r->comment}\",negative\n");

    $this->artisan('sentiment:import-ground-truth', ['file' => $path])->assertExitCode(0);

    expect($r->fresh()->ground_truth_sentiment)->toBe('negative')
        ->and($r->fresh()->sentiment)->toBe('positive'); // AI column untouched

    @unlink($path);
});

it('exports blind template without leaking AI predictions', function () {
    // create a pending eval row; it should appear, AI column must not
    $user = User::factory()->create();
    $room = RoomType::factory()->create();
    $booking = Booking::factory()->create(['booking_code' => 'SEV-TEST123', 'user_id' => $user->id, 'status' => Booking::STATUS_COMPLETED, 'payment_status' => Booking::PAYMENT_PAID]);
    $item = BookingItem::factory()->create(['booking_id' => $booking->id, 'item_type' => 'room', 'item_id' => $room->id]);
    $review = Review::create([
        'booking_id' => $booking->id, 'booking_item_id' => $item->id, 'user_id' => $user->id,
        'reviewable_type' => (new RoomType)->getMorphClass(), 'reviewable_id' => $room->id,
        'hotel_id' => $room->hotel_id, 'room_id' => $room->id, 'rating' => 5, 'comment' => 'blind comment',
        'sentiment' => 'pending', 'ground_truth_sentiment' => null, 'sentiment_score' => 0, 'extracted_keywords' => [], 'is_verified_booking' => true, 'is_published' => true,
    ]);

    $path = storage_path('app/test_template.csv');
    $this->artisan('sentiment:export-template', ['--path' => $path, '--all' => false])->assertExitCode(0);

    $csv = file_get_contents($path);
    expect($csv)->toContain((string) $review->id)
        ->and($csv)->toContain('blind comment')
        ->and($csv)->not->toContain('pending'); // header is human_actual, never AI value per row

    @unlink($path);
});

it('evaluate command prints table and supports --export', function () {
    makeReviewForEval('positive', 'positive');
    makeReviewForEval('neutral', 'neutral');

    $export = storage_path('app/test_eval_export.csv');
    $this->artisan('sentiment:evaluate', ['--export' => $export])
        ->expectsOutputToContain('Accuracy:')
        ->assertExitCode(0);

    expect(is_file($export))->toBeTrue();
    $csv = file_get_contents($export);
    expect($csv)->toContain('actual \\ predicted');

    @unlink($export);
});
