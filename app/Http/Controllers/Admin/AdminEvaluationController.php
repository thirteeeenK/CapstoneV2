<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelModel;
use App\Models\RecommendationHit;
use App\Models\ReviewSummary;
use App\Services\SentimentEvaluationService;
use App\Services\SummaryEvaluationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;

class AdminEvaluationController extends Controller
{
    /**
     * Single AI performance page: Hit Rate@5 + sentiment F1 + ROUGE + offline chatbot eval.
     * All sources are read-only. Chatbot section reads the last CLI run JSON, never re-runs.
     */
    public function index(
        SentimentEvaluationService $sentiment,
        SummaryEvaluationService $summary
    ): View {
        return view('admin.evaluation.index', [
            'hitrate' => $this->hitrateData(),
            'sentiment' => $sentiment->metrics(),
            'rouge' => $this->rougeData($summary),
            'chatbot' => $this->chatbotData(),
        ]);
    }

    /**
     * @return array{sessions: int, rows: array, verdicts: array, clickedSessions: int, clickedHits: int, conditionalRate: float, clickless: int}
     */
    protected function hitrateData(): array
    {
        $impressions = RecommendationHit::where('entity_type', RecommendationHit::TYPE_IMPRESSION)->get();

        if ($impressions->isEmpty()) {
            return [
                'sessions' => 0,
                'rows' => [],
                'verdicts' => [],
                'clickedSessions' => 0,
                'clickedHits' => 0,
                'conditionalRate' => 0.0,
                'clickless' => 0,
            ];
        }

        $tokens = $impressions->pluck('session_token')->unique()->values();
        $sessions = $tokens->count();

        $clicks = RecommendationHit::whereIn('entity_type', [RecommendationHit::TYPE_HOTEL, RecommendationHit::TYPE_ACTIVITY])
            ->where(function ($q) {
                $q->whereNull('rank')->orWhere('rank', '<=', 5);
            })
            ->get()
            ->groupBy('session_token');

        $rate = fn (int $hits): float => $sessions > 0 ? round($hits / $sessions, 4) : 0.0;
        $hitCount = fn (string $mode, ?string $type = null): int => $tokens->filter(
            fn ($t) => ($clicks[$t] ?? collect())
                ->where('mode', $mode)
                ->when($type, fn ($c) => $c->where('entity_type', $type))
                ->isNotEmpty()
        )->count();

        $rows = [];
        foreach ([RecommendationHit::MODE_AI, RecommendationHit::MODE_DEFAULT] as $mode) {
            $hits = $hitCount($mode);
            $rows[] = ['mode' => $mode, 'click_type' => 'any (HR@5)', 'sessions' => $sessions, 'hit_sessions' => $hits, 'hit_rate' => $rate($hits)];
            foreach ([RecommendationHit::TYPE_HOTEL, RecommendationHit::TYPE_ACTIVITY] as $type) {
                $th = $hitCount($mode, $type);
                $rows[] = ['mode' => $mode, 'click_type' => $type, 'sessions' => $sessions, 'hit_sessions' => $th, 'hit_rate' => $rate($th)];
            }
        }

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
                'session' => substr((string) $imp->session_token, 0, 8),
                'user_id' => $imp->user_id,
                'viewed_at' => $imp->created_at->format('Y-m-d H:i'),
                'verdict' => $hit ? 'HIT' : 'MISS',
                'first_click' => $first->entity_type === RecommendationHit::TYPE_NAV
                    ? "{$first->mode}/nav exit"
                    : "{$first->mode}/{$first->entity_type} #{$first->entity_id} r{$first->rank}",
            ];
        }

        $clickedSessions = count($verdicts);
        $clickedHits = collect($verdicts)->where('verdict', 'HIT')->count();

        return [
            'sessions' => $sessions,
            'rows' => $rows,
            'verdicts' => array_slice($verdicts, 0, 100),
            'verdictTotal' => $clickedSessions,
            'clickedSessions' => $clickedSessions,
            'clickedHits' => $clickedHits,
            'conditionalRate' => $clickedSessions > 0 ? round($clickedHits / $clickedSessions, 4) : 0.0,
            'clickless' => $sessions - $clickedSessions,
        ];
    }

    /**
     * @return array{available: bool, reason?: string, rows: array, macro: array|null, skipped: array}
     */
    protected function rougeData(SummaryEvaluationService $svc): array
    {
        $refPath = base_path('Evaluations/human_labeled_reviews.md');

        if (! is_file($refPath)) {
            return ['available' => false, 'reason' => 'Reference file missing: Evaluations/human_labeled_reviews.md', 'rows' => [], 'macro' => null, 'skipped' => []];
        }

        $refs = $svc->parseReferencesMd($refPath);

        if ($refs === []) {
            return ['available' => false, 'reason' => 'No `## Hotel` sections parsed from references file.', 'rows' => [], 'macro' => null, 'skipped' => []];
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
                'hotel' => $hotelName,
                'r1' => $score['r1']['f1'],
                'r2' => $score['r2']['f1'],
                'rl' => $score['rl']['f1'],
            ];
        }

        return [
            'available' => true,
            'rows' => $rows,
            'macro' => $pairs === [] ? null : $svc->macroF1($pairs),
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{hasBaseline: bool, baseline: array|null, latest: array|null, latestFile: string|null, latestAt: string|null}
     */
    protected function chatbotData(): array
    {
        $baselinePath = storage_path('app/eval/eval_baseline.json');
        $baseline = File::exists($baselinePath)
            ? json_decode((string) File::get($baselinePath), true)
            : null;

        $latestFile = null;
        $latestAt = null;
        $latest = null;
        $files = glob(storage_path('app/eval/chatbot-eval-*.json')) ?: [];
        if ($files !== []) {
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $latestFile = basename($files[0]);
            $latestAt = date('Y-m-d H:i', (int) filemtime($files[0]));
            $decoded = json_decode((string) File::get($files[0]), true);
            $latest = $decoded['metrics'] ?? $decoded;
        }

        return [
            'hasBaseline' => is_array($baseline),
            'baseline' => is_array($baseline) ? $baseline : null,
            'latest' => is_array($latest) ? $latest : null,
            'latestFile' => $latestFile,
            'latestAt' => $latestAt,
        ];
    }
}
