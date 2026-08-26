<?php

namespace App\Console\Commands;

use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SentimentImportGroundTruthCommand extends Command
{
    protected $signature = 'sentiment:import-ground-truth {file : CSV file with columns id,human_actual (or ground_truth_sentiment) — id,comment,human_actual}';

    protected $description = 'Import human labels into reviews.ground_truth_sentiment (AI never writes here)';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $absolute = $this->resolvePath($file);

        if (! is_file($absolute)) {
            $this->error("File not found: {$absolute} (arg: {$file})");

            return self::FAILURE;
        }

        $fh = fopen($absolute, 'r');
        if ($fh === false) {
            $this->error("Cannot open {$absolute}");

            return self::FAILURE;
        }

        $header = fgetcsv($fh);
        if ($header === false) {
            $this->error('Empty CSV or cannot read header.');

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        // Accept id + human_actual OR ground_truth_sentiment OR actual
        $idIdx = $this->findHeader($header, ['id', 'review_id']);
        $actualIdx = $this->findHeader($header, ['human_actual', 'ground_truth_sentiment', 'actual', 'ground_truth', 'label']);

        if ($idIdx === null || $actualIdx === null) {
            $this->error('CSV must have id + human_actual (or ground_truth_sentiment/actual) columns. Got: '.implode(',', $header));

            return self::FAILURE;
        }

        $allowed = ['positive', 'neutral', 'negative'];
        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($fh)) !== false) {
                if (count($row) <= max($idIdx, $actualIdx)) {
                    $skipped++;

                    continue;
                }

                $id = (int) trim((string) $row[$idIdx]);
                $raw = strtolower(trim((string) $row[$actualIdx]));

                if ($raw === '' || $raw === 'human_actual' || $raw === 'ground_truth_sentiment') {
                    $skipped++;

                    continue;
                }

                if (! in_array($raw, $allowed, true)) {
                    $this->warn("Row id={$id}: invalid label '{$raw}' — skip (use positive|neutral|negative)");
                    $skipped++;

                    continue;
                }

                if ($id <= 0) {
                    $skipped++;

                    continue;
                }

                $review = Review::find($id);
                if (! $review) {
                    $notFound++;

                    continue;
                }

                $review->ground_truth_sentiment = $raw;
                $review->save();
                $updated++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($fh);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        fclose($fh);

        $this->info("Updated {$updated} review(s) ground_truth_sentiment.");
        if ($skipped > 0) {
            $this->line("Skipped {$skipped} row(s) (empty/invalid label).");
        }
        if ($notFound > 0) {
            $this->warn("{$notFound} id(s) not found in reviews.");
        }
        $this->line('Next: php artisan sentiment:run-ai  then  php artisan sentiment:evaluate');

        return self::SUCCESS;
    }

    private function findHeader(array $header, array $candidates): ?int
    {
        foreach ($candidates as $c) {
            $idx = array_search($c, $header, true);
            if ($idx !== false) {
                return $idx;
            }
        }

        return null;
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
