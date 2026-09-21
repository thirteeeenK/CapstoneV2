<?php

namespace App\Console\Commands;

use App\Jobs\ProcessReviewSentimentJob;
use App\Jobs\UpdateEntityReviewSummaryJob;
use App\Models\Review;
use App\Models\ReviewSummary;
use Illuminate\Console\Command;

class SentimentRunAiCommand extends Command
{
    protected $signature = 'sentiment:run-ai {--all : Run even if ground_truth is null} {--limit=0 : Max rows to process (0 = all)} {--sync : Run synchronously inline instead of dispatching}';

    protected $description = 'Run Gemini sentiment on pending eval reviews (writes ONLY reviews.sentiment — never ground_truth)';

    public function handle(): int
    {
        $query = Review::where('sentiment', 'pending');

        if (! $this->option('all')) {
            $query->whereNotNull('ground_truth_sentiment');
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $ids = $query->orderBy('id')->pluck('id');

        if ($ids->isEmpty()) {
            $this->warn('No pending reviews matched. Label ground truth first, or use --all.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $count = 0;

        foreach ($ids as $id) {
            if ($sync) {
                dispatch_sync(new ProcessReviewSentimentJob($id));
            } else {
                dispatch(new ProcessReviewSentimentJob($id));
            }
            $count++;
        }

        $mode = $sync ? 'dispatched synchronously' : 'dispatched';
        $this->info("{$mode} {$count} job(s). ".($sync ? '' : 'Run queue worker if queue is async, or re-run with --sync.'));

        // Batch rebuild platform overall summary (1 Gemini call, offline fallback covers missing key).
        // Platform uses latest 100, bypasses threshold — sync runs inline, async queues after per-review jobs.
        $platformJob = new UpdateEntityReviewSummaryJob(ReviewSummary::PLATFORM_OVERALL_TYPE, null, 'SunnyTrips Overall Platform', true);
        if ($sync) {
            dispatch_sync($platformJob);
            $this->info('Platform summary rebuilt (latest 100, force).');
        } else {
            dispatch($platformJob);
            $this->info('Platform summary rebuild queued (latest 100, force) — runs after sentiment jobs.');
        }
        if (! $sync) {
            $this->line('Tip: php artisan sentiment:run-ai --sync  runs inline without a worker (needs GEMINI_API_KEY).');
        }

        return self::SUCCESS;
    }
}
