<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('app:check-environment')]
#[Description('Check the app key, database, pgvector and Gemini key before the dev stack starts')]
class CheckEnvironment extends Command
{
    /**
     * Fails on what stops the app from booting or serving requests, so `composer dev`
     * reports it once up front instead of as repeated errors in the log.
     */
    public function handle(): int
    {
        $failed = false;
        if (blank(config('app.key'))) {
            $this->error('APP_KEY is missing. Run: php artisan key:generate');
            $failed = true;
        }

        $connection = config('database.default');
        try {
            DB::connection()->getPdo();
            $this->info("Database ({$connection}) is reachable.");
            if ($connection === 'pgsql' && ! DB::selectOne("select 1 from pg_extension where extname = 'vector'")) {
                $this->error('The pgvector extension is not installed. Run: php artisan migrate');
                $failed = true;
            }
        } catch (Throwable $exception) {
            $host = config("database.connections.{$connection}.host").':'.config("database.connections.{$connection}.port");
            $this->error("Database ({$connection}) at {$host} is not accepting connections. Start PostgreSQL, then try again.");
            $failed = true;
        }

        if (blank(config('services.gemini.api_key'))) {
            $this->warn('GEMINI_API_KEY is not set. Recommendations, embeddings and the chatbot will use fallbacks.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
