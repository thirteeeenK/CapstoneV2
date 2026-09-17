<?php

namespace App\Console\Commands;

use App\Models\RecommendationHit;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class RecommendationEvaluateCommand extends Command
{
    protected $signature = 'recommendation:evaluate
        {--export= : Write HR summary + per-session verdict CSV to path (relative to project root)}';

    protected $description = 'Hit Rate@5 of AI recommendations vs default listings from tracked sessions';

    public function handle(): int
    {
        $impressions = RecommendationHit::where('entity_type', RecommendationHit::TYPE_IMPRESSION)->get();

        if ($impressions->isEmpty()) {
            $this->warn('No tracked sessions yet. Visit /dashboard as an onboarded user, then re-run.');

            return self::SUCCESS;
        }

        $tokens = $impressions->pluck('session_token')->unique()->values();
        $sessions = $tokens->count();

        $clicks = RecommendationHit::whereIn('entity_type', [RecommendationHit::TYPE_HOTEL, RecommendationHit::TYPE_ACTIVITY])
            ->where(function ($q) {
                $q->whereNull('rank')->orWhere('rank', '<=', 5);
            })
            ->get()
            ->groupBy('session_token');

        $rate = fn (int $hits) => $sessions > 0 ? round($hits / $sessions, 4) : 0.0;
        $hitCount = fn (string $mode, ?string $type = null) => $tokens->filter(
            fn ($t) => ($clicks[$t] ?? collect())
                ->where('mode', $mode)
                ->when($type, fn ($c) => $c->where('entity_type', $type))
                ->isNotEmpty()
        )->count();

        $rows = [];
        foreach ([RecommendationHit::MODE_AI, RecommendationHit::MODE_DEFAULT] as $mode) {
            $hits = $hitCount($mode);
            $rows[] = [$mode, 'any (HR@5)', $sessions, $hits, $rate($hits)];
            foreach ([RecommendationHit::TYPE_HOTEL, RecommendationHit::TYPE_ACTIVITY] as $type) {
                $th = $hitCount($mode, $type);
                $rows[] = [$mode, $type, $sessions, $th, $rate($th)];
            }
        }

        $table = new Table($this->output);
        $table->setHeaders(['mode', 'click type', 'sessions', 'hit sessions', 'hit rate']);
        $table->setRows($rows);
        $table->render();

        // Click-only record: sessions with zero clicks are not verdicts
        // (a reload without a click is neither HIT nor MISS). Verdict scope is
        // every click type (hotel/activity/nav-exit); HIT = ≥1 AI Top-5 card
        // click; MISS = clicked, but nothing AI.
        $verdictClicks = RecommendationHit::whereIn('entity_type', [RecommendationHit::TYPE_HOTEL, RecommendationHit::TYPE_ACTIVITY, RecommendationHit::TYPE_NAV])
            ->get()
            ->groupBy('session_token');

        $verdicts = [];
        foreach ($impressions->sortBy('id') as $imp) {
            $sessClicks = ($verdictClicks[$imp->session_token] ?? collect())->sortBy('id')->values();
            if ($sessClicks->isEmpty()) {
                continue;
            }
            $hit = $sessClicks
                ->where('mode', RecommendationHit::MODE_AI)
                ->whereIn('entity_type', [RecommendationHit::TYPE_HOTEL, RecommendationHit::TYPE_ACTIVITY])
                ->filter(fn ($c) => $c->rank === null || $c->rank <= 5)
                ->isNotEmpty();
            $first = $sessClicks->first();
            $verdicts[] = [
                substr($imp->session_token, 0, 8),
                $imp->user_id,
                $imp->created_at->format('Y-m-d H:i'),
                $hit ? 'HIT' : 'MISS',
                $first->entity_type === RecommendationHit::TYPE_NAV
                    ? "{$first->mode}/nav exit"
                    : "{$first->mode}/{$first->entity_type} #{$first->entity_id} r{$first->rank}",
            ];
        }

        $vTable = new Table($this->output);
        $vTable->setHeaders(['session', 'user', 'viewed_at', 'verdict', 'first click']);
        $vTable->setRows($verdicts);
        $vTable->render();

        $clickedSessions = count($verdicts);
        $clickedHits = collect($verdicts)->where(3, 'HIT')->count();
        $this->line(sprintf(
            'Click-conditional HR@5: %.4f (%d HIT / %d clicked sessions; %d click-less session(s) excluded)',
            $clickedSessions > 0 ? round($clickedHits / $clickedSessions, 4) : 0.0,
            $clickedHits,
            $clickedSessions,
            $sessions - $clickedSessions
        ));

        $export = $this->option('export');
        if (is_string($export) && $export !== '') {
            $path = $export;
            if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:\\\\/', $path)) {
                $path = str_starts_with($path, 'storage/') ? storage_path(substr($path, 8)) : base_path($path);
            }
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fp = fopen($path, 'w');
            if ($fp === false) {
                $this->error("Cannot open {$path} for writing.");

                return self::FAILURE;
            }
            fputcsv($fp, ['mode', 'click_type', 'sessions', 'hit_sessions', 'hit_rate']);
            foreach ($rows as $r) {
                fputcsv($fp, $r);
            }
            fputcsv($fp, []);
            fputcsv($fp, ['session', 'user_id', 'viewed_at', 'verdict', 'first_click']);
            foreach ($verdicts as $v) {
                fputcsv($fp, $v);
            }
            fclose($fp);
            $this->info("Wrote CSV to {$export} ({$path})");
        }

        return self::SUCCESS;
    }
}
