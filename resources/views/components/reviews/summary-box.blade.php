@props([
    'summary' => null,      // App\Models\ReviewSummary|null
    'title' => 'Guest Reviews & Sentiment',
    'fallbackCount' => 0,   // shown when summary is null
])

@php
    $has = $summary !== null && $summary->total_reviews > 0;
    $pct = fn($v) => round((float) $v, 0);
    $bullets = [];
    if ($has && $summary->ai_summary_text) {
        foreach (preg_split('/\r?\n/', $summary->ai_summary_text) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $bullets[] = preg_replace('/^-\s*/', '', $line);
            }
        }
    }
@endphp

@if ($has)
    <section class="bg-white rounded-3xl border border-sand-200/80 shadow-sm overflow-hidden">
        <header class="px-6 sm:px-8 pt-6 pb-4 border-b border-sand-100">
            <h2 class="font-headline text-base font-bold text-slate-900 flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-sand-100 text-slate-600 border border-sand-200 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">reviews</span>
                </span>
                {{ $title }}
            </h2>
            <p class="font-label text-[10px] uppercase font-bold tracking-[0.15em] text-slate-400 mt-1">
                AI-assisted · updated {{ $summary->last_analyzed_at?->format('M j, Y') ?? 'recently' }}
            </p>
        </header>

        <div class="px-6 sm:px-8 py-6">
            {{-- Rating headline --}}
            <div class="flex items-center gap-4 flex-wrap">
                <p class="font-headline text-4xl font-black text-slate-900">{{ number_format((float) $summary->average_rating, 1) }}</p>
                <div>
                    <div class="flex items-center gap-0.5 text-amber-400" aria-label="{{ $summary->average_rating }} out of 5 stars">
                        @for ($i = 1; $i <= 5; $i++)
                            <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' {{ $i <= round((float)$summary->average_rating) ? 1 : 0 }}">star</span>
                        @endfor
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $summary->total_reviews }} verified {{ Str::plural('review', $summary->total_reviews) }}</p>
                </div>
            </div>

            {{-- Sentiment split --}}
            <div class="mt-5 flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-100 text-[11px] font-bold">
                    <span class="material-symbols-outlined text-[14px]">sentiment_satisfied</span>
                    {{ $pct($summary->positive_percentage) }}% Positive
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 text-amber-800 border border-amber-100 text-[11px] font-bold">
                    <span class="material-symbols-outlined text-[14px]">sentiment_neutral</span>
                    {{ $pct($summary->neutral_percentage) }}% Neutral
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-rose-50 text-rose-800 border border-rose-100 text-[11px] font-bold">
                    <span class="material-symbols-outlined text-[14px]">sentiment_dissatisfied</span>
                    {{ $pct($summary->negative_percentage) }}% Negative
                </span>
            </div>

            {{-- AI consensus --}}
            @if (count($bullets))
                <div class="mt-5 rounded-2xl bg-ocean-50/60 border border-ocean-100 p-4">
                    <p class="font-label text-[9px] uppercase font-bold tracking-[0.2em] text-ocean-600 mb-2.5 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px]">smart_toy</span>
                        AI Summary Consensus
                    </p>
                    <ul class="space-y-2">
                        @foreach ($bullets as $bullet)
                            <li class="text-xs text-slate-700 leading-relaxed flex items-start gap-2">
                                <span class="mt-0.5 w-1.5 h-1.5 rounded-full bg-ocean-400 shrink-0"></span>
                                <span>{{ $bullet }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@elseif ($fallbackCount > 0)
    <section class="bg-white rounded-3xl border border-sand-200/80 shadow-sm px-6 sm:px-8 py-6">
        <h2 class="font-headline text-base font-bold text-slate-900 flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-sand-50 text-slate-600 border border-sand-200 flex items-center justify-center">
                <span class="material-symbols-outlined text-[18px]">reviews</span>
            </span>
            {{ $title }}
        </h2>
        <p class="text-xs text-slate-500 mt-3">
            {{ $fallbackCount }} verified {{ Str::plural('guest review', $fallbackCount) }}. The AI summary is being generated.
        </p>
    </section>
@endif