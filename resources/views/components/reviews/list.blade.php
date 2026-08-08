@props([
    'reviews' => [],        // Collection of App\Models\Review (published)
    'title' => 'Recent Guest Reviews',
    'limit' => 5,
])

@php
    $items = collect($reviews)->take($limit);
    $sentimentIcon = [
        'positive' => 'sentiment_satisfied',
        'neutral' => 'sentiment_neutral',
        'negative' => 'sentiment_dissatisfied',
    ];
    $sentimentTint = [
        'positive' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
        'neutral' => 'bg-amber-50 text-amber-700 border-amber-100',
        'negative' => 'bg-rose-50 text-rose-700 border-rose-100',
    ];
@endphp

@if ($items->isNotEmpty())
    <section class="space-y-2.5">
        @foreach ($items as $review)
            <article class="bg-white rounded-xl border border-slate-200/80 shadow-2xs px-4 py-3 sm:px-5 sm:py-3.5 hover:border-slate-300 transition-all">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-ocean-50 border border-ocean-100 text-ocean-700 font-headline font-black text-[10px] flex items-center justify-center shrink-0">
                            {{ mb_substr(explode(' ', $review->reviewer_alias)[0] ?? 'G', 0, 1) }}
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="text-xs sm:text-sm font-bold text-slate-900 font-headline leading-tight">{{ $review->reviewer_alias }}</p>
                            <span class="text-[10px] text-slate-400 flex items-center gap-0.5 font-medium">
                                <span class="material-symbols-outlined text-[11px] text-emerald-600">verified</span>
                                Verified · {{ $review->created_at?->format('M j, Y') }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="flex items-center gap-0.5 text-amber-400">
                            @for ($i = 1; $i <= 5; $i++)
                                <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' {{ $i <= (int) $review->rating ? 1 : 0 }}">star</span>
                            @endfor
                        </span>
                        <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-md text-[9px] font-bold border {{ $sentimentTint[$review->sentiment] ?? $sentimentTint['neutral'] }}">
                            <span class="material-symbols-outlined text-[11px]">{{ $sentimentIcon[$review->sentiment] ?? 'sentiment_neutral' }}</span>
                            {{ ucfirst($review->sentiment) }}
                        </span>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed mt-2">{{ $review->comment }}</p>

                @if (is_array($review->extracted_keywords) && count($review->extracted_keywords))
                    <div class="flex items-center gap-1 flex-wrap mt-2">
                        @foreach (array_slice($review->extracted_keywords, 0, 5) as $tag)
                            <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200/60 text-slate-600 text-[9px] font-medium">#{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    </section>
@endif