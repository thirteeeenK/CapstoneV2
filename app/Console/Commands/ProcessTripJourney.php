<?php

namespace App\Console\Commands;

use App\Services\TripJourneyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('trips:process-journey')]
#[Description('Complete fulfilled trips and queue eligible trip journey emails for every booking source')]
class ProcessTripJourney extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TripJourneyService $journey): int
    {
        if (! config('services.trip_journey.enabled')) {
            $this->warn('TRIP_JOURNEY_ENABLED is off, so the scheduler skips this command. A manual run still queues messages.');
        }
        if (config('queue.default') === 'sync') {
            $this->warn('QUEUE_CONNECTION is sync: journey emails are sent during this run instead of by a queue worker.');
        }

        $queued = $journey->process();
        $report = $journey->diagnostics();
        $this->info('Queued '.$queued.' eligible journey messages.');
        $this->table(['Checked bookings', 'Auto-completed', 'Queued by kind'], [[
            $report['bookings'], $report['completed'],
            collect($report['queued'])->filter()->map(fn (int $count, string $kind) => $kind.': '.$count)->join(', ') ?: 'none',
        ]]);
        foreach (['undated' => 'Secured bookings with no service date (date-confirmation follow-up only)', 'inferred_grouping' => 'Bookings split into trip windows by dates alone (no recorded provenance) — review the grouping', 'no_recipient' => 'Bookings with no reachable recipient'] as $key => $label) {
            if ($report[$key] !== []) {
                $this->line($label.': '.implode(', ', array_slice($report[$key], 0, 25)).(count($report[$key]) > 25 ? ' …' : ''));
            }
        }
        $outcomes = DB::table('trip_journey_messages')->where('created_at', '>=', now()->subDay())->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status');
        if ($outcomes->isNotEmpty()) {
            $this->line('Last 24 hours: '.$outcomes->map(fn ($total, $status) => $status.' '.$total)->join(', ').'. "sent" means the mail provider accepted the message, not that it reached the inbox.');
        }

        return self::SUCCESS;
    }
}
