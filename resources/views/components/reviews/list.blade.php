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
    <section class="space-y-3">
        @foreach ($items as $review)
            <article class="bg-white rounded-3xl border border-sand-200/80 shadow-sm p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-ocean-50 border border-ocean-100 text-ocean-700 font-headline font-black text-xs flex items-center justify-center">
                            {{ mb_substr(explode(' ', $review->reviewer_alias)[0] ?? 'G', 0, 1) }}
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900 font-headline">{{ $review->reviewer_alias }}</p>
                            <p class="text-[11px] text-slate-400 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">verified</span>
                                Verified booking · {{ $review->created_at?->format('M j, Y') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="flex items-center gap-0.5 text-amber-400">
                            @for ($i = 1; $i <= 5; $i++)
                                <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' {{ $i <= (int) $review->rating ? 1 : 0 }}">star</span>
                            @endfor
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold border {{ $sentimentTint[$review->sentiment] ?? $sentimentTint['neutral'] }}">
                            <span class="material-symbols-outlined text-[12px]">{{ $sentimentIcon[$review->sentiment] ?? 'sentiment_neutral' }}</span>
                            {{ ucfirst($review->sentiment) }}
                        </span>
                    </div>
                </div>

                <p class="text-sm text-slate-600 leading-relaxed mt-4">{{ $review->comment }}</p>

                @if (is_array($review->extracted_keywords) && count($review->extracted_keywords))
                    <div class="mt-4 flex items-center gap-1.5 flex-wrap">
                        @foreach (array_slice($review->extracted_keywords, 0, 5) as $tag)
                            <span class="px-2.5 py-1 rounded-lg bg-sand-100 border border-sand-200 text-slate-600 text-[10px] font-bold">#{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    </section>
@endif