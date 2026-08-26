<?php

namespace App\Services;

use App\Models\Review;
use Illuminate\Support\Collection;

class SentimentEvaluationService
{
    public const LABELS = ['negative', 'neutral', 'positive'];

    public const PENDING = 'pending';

    /**
     * Build raw 3x3 counts actual(ground_truth) x predicted(sentiment).
     *
     * @return array{
     *   matrix: array<string, array<string, int>>,
     *   totals: array{actual: array<string,int>, predicted: array<string,int>, n: int},
     *   pending: int
     * }
     */
    public function matrix(): array
    {
        $blank = array_fill_keys(self::LABELS, array_fill_keys(self::LABELS, 0));

        $rows = Review::whereNotNull('ground_truth_sentiment')
            ->whereIn('ground_truth_sentiment', self::LABELS)
            ->selectRaw('ground_truth_sentiment as actual, sentiment as predicted, COUNT(*) as cnt')
            ->whereIn('sentiment', self::LABELS)
            ->groupBy('actual', 'predicted')
            ->get();

        foreach ($rows as $r) {
            $blank[$r->actual][$r->predicted] = (int) $r->cnt;
        }

        $pending = (int) Review::whereNotNull('ground_truth_sentiment')
            ->whereIn('ground_truth_sentiment', self::LABELS)
            ->where('sentiment', self::PENDING)
            ->count();

        $actualTotals = array_fill_keys(self::LABELS, 0);
        $predTotals = array_fill_keys(self::LABELS, 0);
        $n = 0;

        foreach (self::LABELS as $a) {
            foreach (self::LABELS as $p) {
                $c = $blank[$a][$p];
                $actualTotals[$a] += $c;
                $predTotals[$p] += $c;
                $n += $c;
            }
        }

        return [
            'matrix' => $blank,
            'totals' => ['actual' => $actualTotals, 'predicted' => $predTotals, 'n' => $n],
            'pending' => $pending,
        ];
    }

    /**
     * Labeled reviews collection (ground truth present, predicted is a real label).
     */
    public function labeledReviews(): Collection
    {
        return Review::whereNotNull('ground_truth_sentiment')
            ->whereIn('ground_truth_sentiment', self::LABELS)
            ->whereIn('sentiment', self::LABELS)
            ->select(['id', 'comment', 'rating', 'sentiment', 'ground_truth_sentiment', 'sentiment_score'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Per-class TP/FP/FN/P/R/F1/support + accuracy/macroF1/weightedF1.
     *
     * Division-by-zero → 0.0.
     *
     * @return array{
     *   matrix: array<string, array<string,int>>,
     *   totals: array{actual: array<string,int>, predicted: array<string,int>, n:int},
     *   pending:int,
     *   perClass: array<string, array{tp:int,fp:int,fn:int,support:int,precision:float,recall:float,f1:float}>,
     *   accuracy: float,
     *   macroF1: float,
     *   weightedF1: float
     * }
     */
    public function metrics(): array
    {
        $m = $this->matrix();
        $matrix = $m['matrix'];
        $n = $m['totals']['n'];
        $perClass = [];
        $f1s = [];

        foreach (self::LABELS as $c) {
            $tp = $matrix[$c][$c];

            $fp = 0;
            foreach (self::LABELS as $a) {
                if ($a !== $c) {
                    $fp += $matrix[$a][$c];
                }
            }

            $fn = 0;
            foreach (self::LABELS as $p) {
                if ($p !== $c) {
                    $fn += $matrix[$c][$p];
                }
            }

            $support = $m['totals']['actual'][$c];
            $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0.0;
            $recall = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0.0;
            $f1 = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;

            $perClass[$c] = [
                'tp' => $tp,
                'fp' => $fp,
                'fn' => $fn,
                'support' => $support,
                'precision' => round($precision, 4),
                'recall' => round($recall, 4),
                'f1' => round($f1, 4),
            ];

            $f1s[] = round($f1, 4);
        }

        $trace = 0;
        foreach (self::LABELS as $c) {
            $trace += $matrix[$c][$c];
        }

        $accuracy = $n > 0 ? $trace / $n : 0.0;
        $macroF1 = count($f1s) ? array_sum($f1s) / count($f1s) : 0.0;

        $weightedF1 = 0.0;
        if ($n > 0) {
            foreach (self::LABELS as $c) {
                $weightedF1 += $perClass[$c]['f1'] * $perClass[$c]['support'];
            }
            $weightedF1 /= $n;
        }

        return [
            'matrix' => $matrix,
            'totals' => $m['totals'],
            'pending' => $m['pending'],
            'perClass' => $perClass,
            'accuracy' => round($accuracy, 4),
            'macroF1' => round($macroF1, 4),
            'weightedF1' => round($weightedF1, 4),
        ];
    }
}
