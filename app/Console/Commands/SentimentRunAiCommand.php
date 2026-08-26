<?php

namespace App\Console\Commands;

use App\Jobs\ProcessReviewSentimentJob;
use App\Models\Review;
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
        if (! $sync) {
            $this->line('Tip: php artisan sentiment:run-ai --sync  runs inline without a worker (needs GEMINI_API_KEY).');
        }

        return self::SUCCESS;
    }
}
