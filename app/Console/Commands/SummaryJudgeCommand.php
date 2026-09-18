<?php

namespace App\Console\Commands;

use App\Services\SummaryEvaluationService;
use App\Services\SummaryJudgeService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class SummaryJudgeCommand extends Command
{
    protected $signature = 'summary:judge
        {--references=Evaluations/human_labeled_reviews.md : Hotel list source (## headings, relative to project root)}
        {--export= : Write per-hotel judge CSV to path (relative to project root)}';

    protected $description = 'LLM-as-Judge (strong model, reference-free): faithfulness/coverage/conciseness 1-5 of stored AI hotel summaries vs source reviews';

    public function handle(SummaryJudgeService $judge, SummaryEvaluationService $rouge): int
    {
        $refPath = (string) $this->option('references');
        if (! str_starts_with($refPath, '/') && ! preg_match('/^[A-Za-z]:\\\\/', $refPath)) {
            $refPath = base_path($refPath);
        }

        if (! is_file($refPath)) {
            $this->error("References file not found: {$refPath}");

            return self::FAILURE;
        }

        $hotels = array_keys($rouge->parseReferencesMd($refPath));
        if ($hotels === []) {
            $this->error('No `## Hotel` sections parsed from references file.');

            return self::SUCCESS;
        }

        $rows = [];
        $scored = [];
        $skipped = [];

        foreach ($hotels as $hotelName) {
            $this->line("Judging: {$hotelName} ...");
            $result = $judge->scoreHotel($hotelName);

            if (isset($result['error'])) {
                $skipped[] = "{$hotelName} ({$result['error']})";

                continue;
            }

            $scored[] = $result;
            $rows[] = [$result['hotel'], $result['faithfulness'], $result['coverage'], $result['conciseness'], $result['mean']];
        }

        if ($rows === []) {
            $this->warn('No hotels scored. Check skipped list below.');
            foreach ($skipped as $s) {
                $this->line("  - {$s}");
            }

            return self::SUCCESS;
        }

        $table = new Table($this->output);
        $table->setHeaders(['hotel', 'faithfulness', 'coverage', 'conciseness', 'mean']);
        $table->setRows($rows);
        $table->render();

        $macro = $judge->macroMeans($scored);
        $this->line(sprintf(
            'Macro over %d hotel(s) [model %s]: faithfulness=%.2f  coverage=%.2f  conciseness=%.2f  mean=%.4f',
            $macro['n'], $scored[0]['model'], $macro['faithfulness'], $macro['coverage'], $macro['conciseness'], $macro['mean']
        ));

        $this->line('Reasonings:');
        foreach ($scored as $s) {
            $this->line("  - {$s['hotel']}: {$s['reasoning']}");
        }

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
            fputcsv($fp, ['hotel', 'faithfulness', 'coverage', 'conciseness', 'mean', 'model', 'reasoning']);
            foreach ($scored as $s) {
                fputcsv($fp, [$s['hotel'], $s['faithfulness'], $s['coverage'], $s['conciseness'], $s['mean'], $s['model'], $s['reasoning']]);
            }
            fputcsv($fp, []);
            fputcsv($fp, ['macro_faithfulness', $macro['faithfulness'], 'macro_coverage', $macro['coverage'], 'macro_conciseness', $macro['conciseness'], 'macro_mean', $macro['mean'], 'n', $macro['n']]);
            fclose($fp);
            $this->info("Wrote CSV to {$export} ({$path})");
        }

        return self::SUCCESS;
    }
}
