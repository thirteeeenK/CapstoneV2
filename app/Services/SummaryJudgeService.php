<?php

namespace App\Services;

use App\Models\HotelModel;
use App\Models\Review;
use App\Models\ReviewSummary;

class SummaryJudgeService
{
    public const DIMENSIONS = ['faithfulness', 'coverage', 'conciseness'];

    public function __construct(protected GeminiService $gemini) {}

    /**
     * Grade one hotel's stored AI summary against its source reviews
     * with the stronger judge model (reference-free: never sees human refs).
     *
     * @return array{hotel:string,model:string,faithfulness:int,coverage:int,conciseness:int,mean:float,reasoning:string}|array{hotel:string,error:string}
     */
    public function scoreHotel(string $hotelName): array
    {
        $hotel = HotelModel::where('hotel_name', $hotelName)->first();
        if (! $hotel) {
            return ['hotel' => $hotelName, 'error' => 'not in hotels table'];
        }

        $candidate = trim((string) ReviewSummary::where('summarizable_type', 'hotel')
            ->where('summarizable_id', $hotel->id)->value('ai_summary_text'));
        if ($candidate === '') {
            return ['hotel' => $hotelName, 'error' => 'no stored AI summary'];
        }

        $reviews = Review::published()->ofEntity('hotel', $hotel->id)->latest()->limit(50)->get();
        if ($reviews->isEmpty()) {
            return ['hotel' => $hotelName, 'error' => 'no published reviews'];
        }

        $lines = $reviews->map(fn ($r) => '['.($r->rating ?? 0).'/5] '.($r->comment ?? ''))->implode("\n");
        $prompt = str_replace(
            ['{HOTEL_NAME}', '{REVIEWS_TEXT}', '{AI_SUMMARY_TEXT}'],
            [$hotelName, $lines, $candidate],
            $this->gemini->loadSystemPrompt('summary-judge-prompt.md')
        );

        $model = (string) (config('services.gemini.judge_model') ?? 'models/gemini-3.1-pro-preview');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $raw = $this->gemini->generateContentWithModel(
                $model, 'You are a strict JSON evaluation grader.', $prompt, 0.0
            );
            $scores = $this->validate($raw);
            if ($scores !== null) {
                return [
                    'hotel' => $hotelName,
                    'model' => $model,
                    'faithfulness' => $scores['faithfulness'],
                    'coverage' => $scores['coverage'],
                    'conciseness' => $scores['conciseness'],
                    'mean' => round(($scores['faithfulness'] + $scores['coverage'] + $scores['conciseness']) / 3, 4),
                    'reasoning' => $scores['reasoning'],
                ];
            }
        }

        return ['hotel' => $hotelName, 'error' => 'judge returned invalid JSON twice'];
    }

    /** @return array{faithfulness:int,coverage:int,conciseness:int,reasoning:string}|null */
    protected function validate(?string $raw): ?array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode(trim($raw), true);
        if (! is_array($decoded)) {
            return null;
        }
        foreach (self::DIMENSIONS as $dim) {
            if (! isset($decoded[$dim]) || ! is_int($decoded[$dim]) || $decoded[$dim] < 1 || $decoded[$dim] > 5) {
                return null;
            }
        }
        if (! isset($decoded['reasoning']) || ! is_string($decoded['reasoning']) || trim($decoded['reasoning']) === '') {
            return null;
        }

        return [
            'faithfulness' => $decoded['faithfulness'],
            'coverage' => $decoded['coverage'],
            'conciseness' => $decoded['conciseness'],
            'reasoning' => trim($decoded['reasoning']),
        ];
    }

    /**
     * @param  array<int, array{faithfulness:int,coverage:int,conciseness:int,mean:float}>  $scored
     * @return array{n:int,faithfulness:float,coverage:float,conciseness:float,mean:float}
     */
    public function macroMeans(array $scored): array
    {
        $n = count($scored);
        if ($n === 0) {
            return ['n' => 0, 'faithfulness' => 0.0, 'coverage' => 0.0, 'conciseness' => 0.0, 'mean' => 0.0];
        }
        $avg = fn (string $k) => round(array_sum(array_column($scored, $k)) / $n, 4);

        return [
            'n' => $n,
            'faithfulness' => $avg('faithfulness'),
            'coverage' => $avg('coverage'),
            'conciseness' => $avg('conciseness'),
            'mean' => $avg('mean'),
        ];
    }
}
