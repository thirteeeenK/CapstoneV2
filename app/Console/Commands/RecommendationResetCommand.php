<?php

namespace App\Console\Commands;

use App\Models\RecommendationHit;
use Illuminate\Console\Command;

class RecommendationResetCommand extends Command
{
    protected $signature = 'recommendation:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all recommendation hit-tracking rows (impressions + clicks) so tracking starts fresh';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete ALL recommendation_hits rows? This cannot be undone.')) {
            $this->line('Aborted — nothing deleted.');

            return self::SUCCESS;
        }

        $deleted = RecommendationHit::query()->delete();

        $this->info("Deleted {$deleted} recommendation hit row(s).");

        return self::SUCCESS;
    }
}
