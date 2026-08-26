<?php

namespace App\Console\Commands;

use App\Models\Review;
use Illuminate\Console\Command;

class SentimentExportTemplateCommand extends Command
{
    protected $signature = 'sentiment:export-template {--path=sentiment_eval_100_template.csv : Output CSV path (relative to project root)} {--all : Export all reviews with null ground truth, not just pending AI}';

    protected $description = 'Export blind template CSV (id,comment) for human ground-truth labeling — AI predictions stay hidden';

    public function handle(): int
    {
        $path = (string) $this->option('path');
        $absolute = $this->resolvePath($path);

        $query = Review::query()
            ->select(['id', 'comment'])
            ->orderBy('id');

        if ($this->option('all')) {
            $query->whereNull('ground_truth_sentiment');
        } else {
            $query->where('sentiment', 'pending')->whereNull('ground_truth_sentiment');
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->warn('No rows matched. Try --all or check that SentimentEvalSeeder has been run.');

            return self::SUCCESS;
        }

        $dir = dirname($absolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($absolute, 'w');
        if ($fp === false) {
            $this->error("Cannot open {$absolute} for writing.");

            return self::FAILURE;
        }

        fputcsv($fp, ['id', 'comment', 'human_actual', 'notes']);
        foreach ($rows as $r) {
            fputcsv($fp, [$r->id, $r->comment, '', 'blind: read comment only; set positive|neutral|negative']);
        }
        fclose($fp);

        $this->info("Wrote {$rows->count()} rows to {$path} (absolute: {$absolute}).");
        $this->line('Next: label human_actual (70 pos / 20 neu / 10 neg intent) then run: php artisan sentiment:import-ground-truth '.$path);

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
