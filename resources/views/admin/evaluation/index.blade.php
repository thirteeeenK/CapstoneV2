@extends('layouts.admin')

@section('title', 'AI Evaluation | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">AI Evaluation</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Hit Rate@5, sentiment F1, summary ROUGE, and offline chatbot scores. Read-only — CSV exports stay in CLI.</p>
            </div>
            <span class="px-3 py-1.5 rounded-xl bg-sky-50 text-sky-700 border border-sky-200 text-xs font-bold">{{ $hitrate['sessions'] }} tracked sessions</span>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Hit Rate@5 (click-conditional)</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ number_format($hitrate['conditionalRate'], 4) }}</p>
                <p class="text-[11px] text-slate-500 mt-1">{{ $hitrate['clickedHits'] }} HIT / {{ $hitrate['clickedSessions'] }} clicked · {{ $hitrate['clickless'] }} click-less excluded</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Sentiment macro-F1</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ number_format($sentiment['macroF1'] ?? 0, 4) }}</p>
                <p class="text-[11px] text-slate-500 mt-1">N={{ $sentiment['totals']['n'] ?? 0 }} · acc {{ number_format($sentiment['accuracy'] ?? 0, 4) }} · pending {{ $sentiment['pending'] ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">ROUGE-L macro-F1</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $rouge['macro'] ? number_format($rouge['macro']['rl'], 4) : '—' }}</p>
                <p class="text-[11px] text-slate-500 mt-1">
                    @if($rouge['macro'])
                        R1 {{ number_format($rouge['macro']['r1'], 4) }} · R2 {{ number_format($rouge['macro']['r2'], 4) }} · n={{ $rouge['macro']['n'] }}
                    @else
                        No scored hotels
                    @endif
                </p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Chatbot intent accuracy</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ isset($chatbot['baseline']['intent_accuracy']) ? number_format($chatbot['baseline']['intent_accuracy'], 4) : '—' }}</p>
                <p class="text-[11px] text-slate-500 mt-1">{{ $chatbot['latestFile'] ? $chatbot['latestFile'].' · '.$chatbot['latestAt'] : 'No eval run yet' }}</p>
            </div>
        </div>

        {{-- Hit Rate --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 mb-6">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                <span class="material-symbols-outlined text-sky-600">target</span>
                Hit Rate@5 — AI vs default
            </h2>
            @if($hitrate['sessions'] === 0)
                <p class="text-xs text-slate-400 font-medium">No tracked sessions yet. Visit /dashboard as an onboarded user, then re-check. CLI: <span class="font-mono">php artisan recommendation:evaluate</span></p>
            @else
                <div class="overflow-x-auto mb-6">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                            <tr><th class="py-3 px-4">Mode</th><th class="py-3 px-4">Click type</th><th class="py-3 px-4">Sessions</th><th class="py-3 px-4">Hit sessions</th><th class="py-3 px-4">Hit rate</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($hitrate['rows'] as $row)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 font-bold">{{ $row['mode'] }}</td>
                                    <td class="py-3 px-4">{{ $row['click_type'] }}</td>
                                    <td class="py-3 px-4">{{ $row['sessions'] }}</td>
                                    <td class="py-3 px-4 font-bold">{{ $row['hit_sessions'] }}</td>
                                    <td class="py-3 px-4 font-black">{{ number_format($row['hit_rate'], 4) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Per-session verdicts (latest {{ count($hitrate['verdicts']) }} of {{ $hitrate['verdictTotal'] }})</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                            <tr><th class="py-3 px-4">Session</th><th class="py-3 px-4">User</th><th class="py-3 px-4">Viewed</th><th class="py-3 px-4">Verdict</th><th class="py-3 px-4">First click</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($hitrate['verdicts'] as $v)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 font-mono font-bold">{{ $v['session'] }}</td>
                                    <td class="py-3 px-4">{{ $v['user_id'] }}</td>
                                    <td class="py-3 px-4">{{ $v['viewed_at'] }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase {{ $v['verdict'] === 'HIT' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $v['verdict'] }}</span>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-[11px]">{{ $v['first_click'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-400 font-medium text-xs">Clicked sessions appear here.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Sentiment --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 mb-6">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                <span class="material-symbols-outlined text-sky-600">sentiment_satisfied</span>
                Sentiment F1 — Gemini vs human labels
            </h2>
            @if(($sentiment['totals']['n'] ?? 0) === 0)
                <p class="text-xs text-slate-400 font-medium">No labeled eval reviews yet. CLI: <span class="font-mono">sentiment:export-template → label → sentiment:import-ground-truth → sentiment:run-ai → sentiment:evaluate</span></p>
            @else
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                            <tr><th class="py-3 px-4">Actual \ Predicted</th><th class="py-3 px-4">Negative</th><th class="py-3 px-4">Neutral</th><th class="py-3 px-4">Positive</th><th class="py-3 px-4">Actual total</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach(['negative', 'neutral', 'positive'] as $a)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 font-bold capitalize">{{ $a }}</td>
                                    <td class="py-3 px-4">{{ $sentiment['matrix'][$a]['negative'] }}</td>
                                    <td class="py-3 px-4">{{ $sentiment['matrix'][$a]['neutral'] }}</td>
                                    <td class="py-3 px-4">{{ $sentiment['matrix'][$a]['positive'] }}</td>
                                    <td class="py-3 px-4 font-bold">{{ $sentiment['totals']['actual'][$a] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                            <tr><th class="py-3 px-4">Class</th><th class="py-3 px-4">Support</th><th class="py-3 px-4">Precision</th><th class="py-3 px-4">Recall</th><th class="py-3 px-4">F1</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach(['negative', 'neutral', 'positive'] as $c)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 font-bold capitalize">{{ $c }}</td>
                                    <td class="py-3 px-4">{{ $sentiment['perClass'][$c]['support'] }}</td>
                                    <td class="py-3 px-4">{{ number_format($sentiment['perClass'][$c]['precision'], 4) }}</td>
                                    <td class="py-3 px-4">{{ number_format($sentiment['perClass'][$c]['recall'], 4) }}</td>
                                    <td class="py-3 px-4 font-black">{{ number_format($sentiment['perClass'][$c]['f1'], 4) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(($sentiment['pending'] ?? 0) > 0)
                    <p class="text-[11px] text-amber-700 font-bold mt-3">{{ $sentiment['pending'] }} labeled row(s) still pending AI — run <span class="font-mono">php artisan sentiment:run-ai --sync</span></p>
                @endif
            @endif
        </div>

        {{-- ROUGE --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 mb-6">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                <span class="material-symbols-outlined text-sky-600">summarize</span>
                ROUGE — AI summaries vs blind human references
            </h2>
            @if(!$rouge['available'])
                <p class="text-xs text-slate-400 font-medium">{{ $rouge['reason'] }}</p>
            @elseif($rouge['rows'] === [])
                <p class="text-xs text-slate-400 font-medium">No scored hotels yet — queue worker has not generated summaries. Skipped: {{ count($rouge['skipped']) }}.</p>
                @if(count($rouge['skipped']) > 0)
                    <ul class="mt-2 text-[11px] text-slate-500 list-disc pl-5">
                        @foreach(array_slice($rouge['skipped'], 0, 10) as $s)
                            <li>{{ $s }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <div class="overflow-x-auto mb-3">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                            <tr><th class="py-3 px-4">Hotel</th><th class="py-3 px-4">R1-F1</th><th class="py-3 px-4">R2-F1</th><th class="py-3 px-4">RL-F1</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($rouge['rows'] as $row)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 font-bold">{{ $row['hotel'] }}</td>
                                    <td class="py-3 px-4">{{ number_format($row['r1'], 4) }}</td>
                                    <td class="py-3 px-4">{{ number_format($row['r2'], 4) }}</td>
                                    <td class="py-3 px-4 font-black">{{ number_format($row['rl'], 4) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(count($rouge['skipped']) > 0)
                    <p class="text-[11px] text-slate-400 font-medium">Skipped ({{ count($rouge['skipped']) }}): {{ implode('; ', array_slice($rouge['skipped'], 0, 5)) }}{{ count($rouge['skipped']) > 5 ? '…' : '' }}</p>
                @endif
            @endif
        </div>

        {{-- Chatbot --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                <span class="material-symbols-outlined text-sky-600">smart_toy</span>
                Chatbot offline retrieval eval
            </h2>
            @if(!$chatbot['hasBaseline'] && !$chatbot['latest'])
                <p class="text-xs text-slate-400 font-medium">No baseline yet — run on server: <span class="font-mono">php artisan chatbot:eval --update-baseline</span>. This suite seeds fixtures and cannot run per-request.</p>
            @else
                @php $m = $chatbot['baseline'] ?? $chatbot['latest']; @endphp
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                            <tr><th class="py-3 px-4">Metric</th><th class="py-3 px-4">Value</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach(['cases','intent_accuracy','recall_at_3','recall_at_5','mrr','destination_match_rate','wrong_destination_rate','no_result_rate','forbid_hit_rate','facts_hit_rate','constraint_violation_rate','unsupported_claim_rate','valid_abstention_rate','multi_turn_consistency_rate','latency_p50_ms','latency_p95_ms'] as $key)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 font-mono text-[11px]">{{ $key }}</td>
                                    <td class="py-3 px-4 font-bold">{{ is_numeric($m[$key] ?? null) ? number_format((float) $m[$key], 4) : ($m[$key] ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($chatbot['latestFile'])
                    <p class="text-[11px] text-slate-400 font-medium mt-3">Latest run: {{ $chatbot['latestFile'] }} · {{ $chatbot['latestAt'] }}</p>
                @endif
            @endif
        </div>
    </div>
@endsection
