<?php

namespace App\Console\Commands;

use App\Services\SentimentEvaluationService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class SentimentEvaluateCommand extends Command
{
    protected $signature = 'sentiment:evaluate {--export= : Write matrix+metrics CSV to path (relative to project root)}';

    protected $description = 'Print 3x3 confusion matrix (ground_truth vs AI sentiment) + per-class P/R/F1 + accuracy/macroF1/weightedF1';

    public function handle(SentimentEvaluationService $svc): int
    {
        $metrics = $svc->metrics();
        $matrix = $metrics['matrix'];
        $totals = $metrics['totals'];
        $n = $totals['n'];
        $pending = $metrics['pending'];

        if ($n === 0) {
            $this->warn('No labeled eval reviews yet (ground_truth_sentiment IS NULL or AI still pending).');
            if ($pending > 0) {
                $this->line("{$pending} labeled row(s) still have sentiment='pending' — run: php artisan sentiment:run-ai --sync");
            }
            $this->line('Run: sentiment:export-template -> label -> sentiment:import-ground-truth -> sentiment:run-ai -> sentiment:evaluate');

            return self::SUCCESS;
        }

        $this->info("Eval set: N={$n} (pending AI: {$pending})");

        // Matrix table
        $table = new Table($this->output);
        $table->setHeaders(['actual \\ predicted', 'negative', 'neutral', 'positive', 'actual total']);
        foreach (SentimentEvaluationService::LABELS as $a) {
            $table->addRow([
                $a,
                $matrix[$a]['negative'],
                $matrix[$a]['neutral'],
                $matrix[$a]['positive'],
                $totals['actual'][$a],
            ]);
        }
        $table->addRow(['pred total', $totals['predicted']['negative'], $totals['predicted']['neutral'], $totals['predicted']['positive'], $n]);
        $table->render();

        // Per-class metrics
        $mTable = new Table($this->output);
        $mTable->setHeaders(['class', 'support', 'TP', 'FP', 'FN', 'precision', 'recall', 'F1']);
        foreach (SentimentEvaluationService::LABELS as $c) {
            $pc = $metrics['perClass'][$c];
            $mTable->addRow([$c, $pc['support'], $pc['tp'], $pc['fp'], $pc['fn'], $pc['precision'], $pc['recall'], $pc['f1']]);
        }
        $mTable->render();

        $this->line(sprintf('Accuracy: %.4f  Macro-F1: %.4f  Weighted-F1: %.4f', $metrics['accuracy'], $metrics['macroF1'], $metrics['weightedF1']));

        if ($pending > 0) {
            $this->warn("Note: {$pending} labeled row(s) excluded (AI pending) — re-run after sentiment:run-ai.");
        }

        $export = $this->option('export');
        if (is_string($export) && $export !== '') {
            $path = $this->resolvePath($export);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fp = fopen($path, 'w');
            if ($fp === false) {
                $this->error("Cannot open {$path} for writing.");

                return self::FAILURE;
            }
            fputcsv($fp, ['actual \\ predicted', 'negative', 'neutral', 'positive', 'actual_total']);
            foreach (SentimentEvaluationService::LABELS as $a) {
                fputcsv($fp, [$a, $matrix[$a]['negative'], $matrix[$a]['neutral'], $matrix[$a]['positive'], $totals['actual'][$a]]);
            }
            fputcsv($fp, ['pred_total', $totals['predicted']['negative'], $totals['predicted']['neutral'], $totals['predicted']['positive'], $n]);
            fputcsv($fp, []);
            fputcsv($fp, ['class', 'support', 'tp', 'fp', 'fn', 'precision', 'recall', 'f1']);
            foreach (SentimentEvaluationService::LABELS as $c) {
                $pc = $metrics['perClass'][$c];
                fputcsv($fp, [$c, $pc['support'], $pc['tp'], $pc['fp'], $pc['fn'], $pc['precision'], $pc['recall'], $pc['f1']]);
            }
            fputcsv($fp, []);
            fputcsv($fp, ['accuracy', $metrics['accuracy'], 'macroF1', $metrics['macroF1'], 'weightedF1', $metrics['weightedF1']]);
            fclose($fp);
            $this->info("Wrote CSV to {$export} ({$path})");
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path)) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return storage_path(substr($path, 8));
        }

        return base_path($path);
    }
}
