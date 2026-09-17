<?php

namespace App\Console\Commands;

use App\Models\HotelModel;
use App\Models\ReviewSummary;
use App\Services\SummaryEvaluationService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class SummaryEvaluateCommand extends Command
{
    protected $signature = 'summary:evaluate
        {--references=Evaluations/human_labeled_reviews.md : Path to blind human reference MD (relative to project root)}
        {--export= : Write per-hotel ROUGE CSV to path (relative to project root)}';

    protected $description = 'ROUGE-1/2/L (R/P/F1) of stored AI hotel summaries vs blind human references + macro-F1';

    public function handle(SummaryEvaluationService $svc): int
    {
        $refPath = $this->option('references');
        if (! str_starts_with($refPath, '/') && ! preg_match('/^[A-Za-z]:\\\\/', $refPath)) {
            $refPath = base_path($refPath);
        }

        if (! is_file($refPath)) {
            $this->error("References file not found: {$refPath}");

            return self::FAILURE;
        }

        $refs = $svc->parseReferencesMd($refPath);

        if ($refs === []) {
            $this->error('No `## Hotel` sections parsed from references file.');

            return self::FAILURE;
        }

        $rows = [];
        $pairs = [];
        $skipped = [];

        foreach ($refs as $hotelName => $reference) {
            $hotel = HotelModel::where('hotel_name', $hotelName)->first();

            if (! $hotel) {
                $skipped[] = "{$hotelName} (not in hotels table)";

                continue;
            }

            $summary = ReviewSummary::where('summarizable_type', 'hotel')
                ->where('summarizable_id', $hotel->id)
                ->first();

            if (! $summary || trim((string) $summary->ai_summary_text) === '') {
                $skipped[] = "{$hotelName} (no stored AI summary — run queue worker to generate)";

                continue;
            }

            $score = $svc->scorePair($summary->ai_summary_text, $reference);
            $pairs[] = $score;
            $rows[] = [
                $hotelName,
                $score['r1']['f1'], $score['r2']['f1'], $score['rl']['f1'],
                $score['r1']['recall'], $score['r1']['precision'],
                $score['r2']['recall'], $score['r2']['precision'],
                $score['rl']['recall'], $score['rl']['precision'],
            ];
        }

        if ($rows === []) {
            $this->warn('No scored hotels. Check skipped list below.');
            foreach ($skipped as $s) {
                $this->line("  - {$s}");
            }

            return self::SUCCESS;
        }

        $table = new Table($this->output);
        $table->setHeaders(['hotel', 'R1-F1', 'R2-F1', 'RL-F1', 'R1-R', 'R1-P', 'R2-R', 'R2-P', 'RL-R', 'RL-P']);
        $table->setRows($rows);
        $table->render();

        $macro = $svc->macroF1($pairs);
        $this->line(sprintf(
            'Macro-F1 over %d hotel(s): ROUGE-1=%.4f  ROUGE-2=%.4f  ROUGE-L=%.4f',
            $macro['n'], $macro['r1'], $macro['r2'], $macro['rl']
        ));

        foreach ($skipped as $s) {
            $this->warn("Skipped: {$s}");
        }

        $export = $this->option('export');
        if (is_string($export) && $export !== '') {
            $path = str_starts_with($export, 'storage/') ? storage_path(substr($export, 8)) : base_path($export);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fp = fopen($path, 'w');
            if ($fp === false) {
                $this->error("Cannot open {$path} for writing.");

                return self::FAILURE;
            }
            fputcsv($fp, ['hotel', 'r1_f1', 'r2_f1', 'rl_f1', 'r1_r', 'r1_p', 'r2_r', 'r2_p', 'rl_r', 'rl_p']);
            foreach ($rows as $r) {
                fputcsv($fp, $r);
            }
            fputcsv($fp, []);
            fputcsv($fp, ['macro_r1_f1', $macro['r1'], 'macro_r2_f1', $macro['r2'], 'macro_rl_f1', $macro['rl'], 'n', $macro['n']]);
            fclose($fp);
            $this->info("Wrote CSV to {$export} ({$path})");
        }

        return self::SUCCESS;
    }
}
