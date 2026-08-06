@props([
    'summary' => null,     // App\Models\ReviewSummary|null (platform-wide)
    'reviews' => [],       // Collection of featured App\Models\Review
])

@php
    $items = collect($reviews)->take(3);
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
    $entityLabel = fn($review) => match (true) {
        $review->reviewable instanceof \App\Models\RoomType => $review->reviewable->room_name . ($review->reviewable->hotel ? ' · ' . $review->reviewable->hotel->hotel_name : ''),
        $review->reviewable instanceof \App\Models\HotelModel => $review->reviewable->hotel_name,
        $review->reviewable instanceof \App\Models\ActivityModel => $review->reviewable->activity_name,
        $review->reviewable instanceof \App\Models\Package => $review->reviewable->name,
        default => 'Verified stay',
    };
@endphp

@if ($items->isNotEmpty())
    <section class="py-24 bg-white" id="testimonials">
        <div class="max-w-7xl mx-auto px-8">
            {{-- Header --}}
            <div class="text-center max-w-2xl mx-auto mb-10 reveal-on-scroll">
                <span class="font-label text-primary font-bold tracking-[0.2em] text-xs uppercase mb-3 block">GUEST REVIEWS</span>
                <h2 class="font-headline text-3xl md:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">What Our Guests Say</h2>
                <p class="mt-4 text-slate-500 text-xs md:text-sm leading-relaxed">
                    Honest, verified feedback from completed SunnyTrips journeys — analyzed with AI-assisted sentiment.
                </p>
            </div>

            {{-- Platform summary strip --}}
            @if ($summary && (int) $summary->total_reviews > 0)
                <div class="max-w-3xl mx-auto mb-12 flex flex-wrap items-center justify-center gap-x-8 gap-y-4 px-6 py-4 rounded-2xl bg-ocean-50/60 border border-ocean-100 reveal-on-scroll">
                    <div class="flex items-center gap-2">
                        <span class="font-headline text-2xl font-black text-slate-900">{{ number_format((float) $summary->average_rating, 1) }}</span>
                        <span class="flex items-center gap-0.5 text-amber-400">
                            @for ($i = 1; $i <= 5; $i++)
                                <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' {{ $i <= (int) round((float) $summary->average_rating) ? 1 : 0 }}">star</span>
                            @endfor
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-ocean-600">verified</span>
                        <span class="text-xs font-bold text-slate-700">{{ $summary->total_reviews }} verified reviews</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-100 text-[10px] font-black">{{ round((float) $summary->positive_percentage) }}% positive</span>
                        <span class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 border border-amber-100 text-[10px] font-black">{{ round((float) $summary->neutral_percentage) }}% neutral</span>
                        <span class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-100 text-[10px] font-black">{{ round((float) $summary->negative_percentage) }}% negative</span>
                    </div>
                </div>
            @endif

            {{-- Featured reviews grid --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach ($items as $index => $review)
                    <div class="bg-slate-50 border border-slate-200/60 rounded-2xl p-8 shadow-sm flex flex-col relative reveal-on-scroll" @if ($index > 0) style="transition-delay: {{ $index * 100 }}ms;" @endif>
                        <!-- <span class="absolute top-6 right-6 text-slate-200 text-6xl font-serif leading-none select-none">"</span> -->

                        <div class="flex items-center justify-between gap-2 mb-5">
                            <div class="flex gap-0.5 text-amber-400">
                                @for ($i = 1; $i <= 5; $i++)
                                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' {{ $i <= (int) $review->rating ? 1 : 0 }}">star</span>
                                @endfor
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold border {{ $sentimentTint[$review->sentiment] ?? $sentimentTint['neutral'] }}">
                                <span class="material-symbols-outlined text-[12px]">{{ $sentimentIcon[$review->sentiment] ?? 'sentiment_neutral' }}</span>
                                {{ ucfirst($review->sentiment) }}
                            </span>
                        </div>

                        <p class="text-slate-600 text-xs md:text-sm leading-relaxed italic mb-6 flex-grow">"{{ $review->comment }}"</p>

                        <div class="flex items-center gap-4 border-t border-slate-200/50 pt-4 mt-auto">
                            <div class="w-10 h-10 rounded-full bg-ocean-50 border border-ocean-100 text-ocean-700 font-headline font-black text-xs flex items-center justify-center shrink-0">
                                {{ mb_substr(explode(' ', $review->reviewer_alias)[0] ?? 'G', 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <h5 class="font-headline font-bold text-slate-900 text-xs md:text-sm truncate">{{ $review->reviewer_alias }}</h5>
                                <p class="text-slate-400 text-[10px] uppercase tracking-wider font-semibold truncate">{{ $entityLabel($review) }}</p>
                                <p class="text-slate-400 text-[10px] font-semibold mt-0.5 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[11px]">verified</span>
                                    Verified booking · {{ $review->created_at?->format('M j, Y') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- See all link --}}
            <div class="text-center mt-12 reveal-on-scroll">
                <a href="{{ route('reviews.index') }}"
                   class="inline-flex items-center gap-2 px-8 py-3.5 rounded-2xl bg-ocean-600 hover:bg-ocean-700 text-white font-headline font-bold text-sm transition-colors">
                    See all reviews
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </div>
        </div>
    </section>
@endif