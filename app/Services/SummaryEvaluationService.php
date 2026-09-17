<?php

namespace App\Services;

/**
 * ROUGE-1 / ROUGE-2 / ROUGE-L similarity between an AI candidate summary
 * and a blind human reference summary (both plain text).
 *
 * Pure PHP, no packages. Tokenizer: lowercase + split on non-alphanumerics.
 * ROUGE-N overlap counts n-gram multisets (min of candidate/reference counts).
 */
class SummaryEvaluationService
{
    /**
     * @return string[] Token list.
     */
    public function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $tokens = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [] : array_values($tokens);
    }

    /**
     * @return array{recall: float, precision: float, f1: float}
     */
    public function rougeN(string $candidate, string $reference, int $n): array
    {
        $c = $this->ngramCounts($this->tokenize($candidate), $n);
        $r = $this->ngramCounts($this->tokenize($reference), $n);

        $refTotal = array_sum($r);
        $candTotal = array_sum($c);

        if ($refTotal === 0 || $candTotal === 0) {
            return ['recall' => 0.0, 'precision' => 0.0, 'f1' => 0.0];
        }

        $overlap = 0;
        foreach ($c as $gram => $count) {
            if (isset($r[$gram])) {
                $overlap += min($count, $r[$gram]);
            }
        }

        return $this->prf($overlap, $candTotal, $refTotal);
    }

    /**
     * @return array{recall: float, precision: float, f1: float}
     */
    public function rougeL(string $candidate, string $reference): array
    {
        $c = $this->tokenize($candidate);
        $r = $this->tokenize($reference);

        if ($c === [] || $r === []) {
            return ['recall' => 0.0, 'precision' => 0.0, 'f1' => 0.0];
        }

        $lcs = $this->lcsLength($c, $r);

        return $this->prf($lcs, count($c), count($r));
    }

    /**
     * Full score triple for one candidate/reference pair.
     *
     * @return array{r1: array{f1: float, ...}, r2: ..., rl: ...} each with recall/precision/f1
     */
    public function scorePair(string $candidate, string $reference): array
    {
        return [
            'r1' => $this->rougeN($candidate, $reference, 1),
            'r2' => $this->rougeN($candidate, $reference, 2),
            'rl' => $this->rougeL($candidate, $reference),
        ];
    }

    /**
     * Macro-average F1 across pairs (each hotel weighted equally).
     *
     * @param  array<int, array{r1: array{f1: float}, r2: array{f1: float}, rl: array{f1: float}}>  $pairs
     */
    public function macroF1(array $pairs): array
    {
        $n = count($pairs);
        if ($n === 0) {
            return ['r1' => 0.0, 'r2' => 0.0, 'rl' => 0.0, 'n' => 0];
        }

        $sum = ['r1' => 0.0, 'r2' => 0.0, 'rl' => 0.0];
        foreach ($pairs as $p) {
            $sum['r1'] += $p['r1']['f1'];
            $sum['r2'] += $p['r2']['f1'];
            $sum['rl'] += $p['rl']['f1'];
        }

        return [
            'r1' => round($sum['r1'] / $n, 4),
            'r2' => round($sum['r2'] / $n, 4),
            'rl' => round($sum['rl'] / $n, 4),
            'n' => $n,
        ];
    }

    /**
     * Parse `## Hotel Name (...)` sections with dash-bullet bodies.
     *
     * @return array<string, string> hotel name => reference text (bullets joined)
     */
    public function parseReferencesMd(string $path): array
    {
        $refs = [];
        $current = null;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (str_starts_with($line, '## ')) {
                $heading = trim(substr($line, 3));
                $paren = strpos($heading, ' (');
                $current = $paren === false ? $heading : substr($heading, 0, $paren);
                $refs[$current] = [];
            } elseif ($current !== null && str_starts_with(trim($line), '- ')) {
                $refs[$current][] = trim($line);
            }
        }

        return array_map(fn ($bullets) => implode("\n", $bullets), $refs);
    }

    /**
     * @return array<string, int> n-gram => count
     */
    private function ngramCounts(array $tokens, int $n): array
    {
        $counts = [];
        $len = count($tokens);

        if ($len < $n) {
            return $counts;
        }

        for ($i = 0; $i <= $len - $n; $i++) {
            $gram = implode(' ', array_slice($tokens, $i, $n));
            $counts[$gram] = ($counts[$gram] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array{recall: float, precision: float, f1: float}
     */
    private function prf(int $overlap, int $candTotal, int $refTotal): array
    {
        $recall = $refTotal > 0 ? $overlap / $refTotal : 0.0;
        $precision = $candTotal > 0 ? $overlap / $candTotal : 0.0;
        $f1 = ($recall + $precision) > 0
            ? 2 * $recall * $precision / ($recall + $precision)
            : 0.0;

        return [
            'recall' => round($recall, 4),
            'precision' => round($precision, 4),
            'f1' => round($f1, 4),
        ];
    }

    /**
     * LCS length over token arrays (two-row DP).
     *
     * @param  string[]  $a
     * @param  string[]  $b
     */
    private function lcsLength(array $a, array $b): int
    {
        if (count($a) > count($b)) {
            [$a, $b] = [$b, $a];
        }

        $prev = array_fill(0, count($a) + 1, 0);

        foreach ($b as $tb) {
            $curr = [0];
            foreach ($a as $j => $ta) {
                $curr[$j + 1] = $ta === $tb
                    ? $prev[$j] + 1
                    : max($prev[$j + 1], $curr[$j]);
            }
            $prev = $curr;
        }

        return $prev[count($a)];
    }
}
