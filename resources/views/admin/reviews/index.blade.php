@extends('layouts.admin')

@section('title', 'Review Analytics | SunnyTrips Admin')

@section('content')
    @php
        $sentimentMeta = [
            'positive' => ['label' => 'Positive', 'icon' => 'sentiment_satisfied', 'bar' => 'bg-emerald-500'],
            'neutral' => ['label' => 'Neutral', 'icon' => 'sentiment_neutral', 'bar' => 'bg-amber-400'],
            'negative' => ['label' => 'Negative', 'icon' => 'sentiment_dissatisfied', 'bar' => 'bg-rose-500'],
        ];
        $positivePct = $sentimentTotal > 0 ? ($sentimentCounts['positive'] / $sentimentTotal) : 0;
        $neutralPct = $sentimentTotal > 0 ? ($sentimentCounts['neutral'] / $sentimentTotal) : 0;
        $negativePct = $sentimentTotal > 0 ? ($sentimentCounts['negative'] / $sentimentTotal) : 0;
        $donut = 'conic-gradient(
            #10b981 0deg,
            #10b981 ' . round($positivePct * 360) . 'deg,
            #fbbf24 ' . round($positivePct * 360) . 'deg,
            #fbbf24 ' . round(($positivePct + $neutralPct) * 360) . 'deg,
            #f43f5e ' . round(($positivePct + $neutralPct) * 360) . 'deg,
            #f43f5e 360deg
        )';
    @endphp

    <div class="pb-12 font-body">
        {{-- Header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Review Analytics & Sentiment</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">AI-assisted DSS metrics across all verified guest feedback.</p>
            </div>
            <div class="flex items-center gap-2 text-xs font-bold">
                <span class="px-3 py-1.5 rounded-xl bg-teal-50 text-teal-700 border border-teal-200">{{ $totalReviews }} reviews</span>
                <span class="px-3 py-1.5 rounded-xl bg-ocean-50 text-ocean-700 border border-ocean-200">★ {{ number_format($avgRating, 2) }} avg</span>
            </div>
        </div>

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Sentiment donut --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-ocean-50 text-ocean-600 border border-ocean-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">donut_small</span>
                    </span>
                    Sentiment Distribution
                </h2>
                <div class="flex items-center gap-6">
                    <div class="w-36 h-36 rounded-full shrink-0 shadow-inner ring-8 ring-slate-50" style="background: {{ $donut }};"></div>
                    <div class="space-y-3">
                        @foreach ($sentimentMeta as $key => $m)
                            <div class="flex items-center gap-2.5">
                                <span class="w-3 h-3 rounded-full {{ $m['bar'] }}"></span>
                                <span class="text-xs font-bold text-slate-700 w-16">{{ $m['label'] }}</span>
                                <span class="text-xs font-black text-slate-900">{{ $sentimentCounts[$key] }}</span>
                                <span class="text-[10px] text-slate-400 font-bold">
                                    {{ $sentimentTotal > 0 ? round(($sentimentCounts[$key] / $sentimentTotal) * 100) : 0 }}%
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Needs improvement alert --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 p-6 lg:col-span-2">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">notification_important</span>
                    </span>
                    Needs Improvement Alerts
                </h2>

                @if ($needsImprovement->isEmpty())
                    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6 text-center text-xs text-slate-500">
                        No listings flagged. Every entity currently sits under the 15% negative-sentiment threshold.
                    </div>
                @else
                    <div class="space-y-2.5">
                        @foreach ($needsImprovement as $alert)
                            <div class="flex items-center justify-between gap-3 p-3.5 rounded-2xl bg-rose-50/60 border border-rose-100">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-900 truncate">{{ $alert['label'] }}</p>
                                    <p class="text-[10px] text-slate-500">{{ $alert['total_reviews'] }} reviews · {{ number_format($alert['average_rating'], 1) }} ★ avg</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg bg-rose-600 text-white text-[10px] font-black shrink-0">
                                    {{ round($alert['negative_percentage']) }}% negative
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Leaderboards --}}
            <section class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">workspace_premium</span>
                    </span>
                    Top Rated Listings
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach (['hotels' => 'Hotels', 'rooms' => 'Rooms', 'activities' => 'Activities'] as $key => $label)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="font-label text-[9px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-3">{{ $label }}</p>
                            @if (empty($leaderboards[$key]))
                                <p class="text-[11px] text-slate-400">No reviews yet.</p>
                            @else
                                <ol class="space-y-3">
                                    @foreach ($leaderboards[$key] as $i => $item)
                                        <li class="flex items-center gap-2.5">
                                            <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 text-[10px] font-black flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[11px] font-bold text-slate-800 truncate">{{ $item['label'] }}</p>
                                                <p class="text-[10px] text-slate-400">{{ $item['review_count'] }} reviews</p>
                                            </div>
                                            <span class="text-xs font-black text-ocean-700 shrink-0">{{ number_format($item['average_rating'], 1) }} ★</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Keyword cloud --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-sand-100 text-slate-600 border border-sand-200 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">cloud</span>
                    </span>
                    Keyword Cloud
                </h2>
                @if (empty($keywordCloud))
                    <p class="text-[11px] text-slate-400">No AI keywords extracted yet.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @php
                            $maxKw = max($keywordCloud) ?: 1;
                        @endphp
                        @foreach ($keywordCloud as $kw => $count)
                            @php
                                $size = 11 + round(($count / $maxKw) * 7);
                                $tint = ['text-slate-500', 'text-slate-700', 'text-teal-700', 'text-ocean-700', 'text-slate-900'][min(4, floor(($count / $maxKw) * 4))];
                            @endphp
                            <span class="px-2.5 py-1 rounded-lg bg-sand-50 border border-sand-200 font-bold" style="font-size: {{ $size }}px;">
                                <span class="{{ $tint }}">{{ $kw }} <span class="text-slate-400 font-black">×{{ $count }}</span></span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Recent reviews table --}}
        <section class="mt-6 bg-white rounded-3xl border border-slate-200/80 overflow-hidden">
            <header class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                <h2 class="font-headline text-sm font-bold text-slate-900">All Reviews</h2>
                <span class="font-label text-[10px] uppercase font-bold tracking-[0.15em] text-slate-400">
                    {{ $reviews->total() }} total
                </span>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 font-bold">
                        <tr>
                            <th class="px-6 py-3">Guest</th>
                            <th class="px-4 py-3">Listing</th>
                            <th class="px-4 py-3">Rating</th>
                            <th class="px-4 py-3">Sentiment</th>
                            <th class="px-4 py-3">Comment</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($reviews as $review)
                            <tr>
                                <td class="px-6 py-3.5">
                                    <p class="text-xs font-bold text-slate-900">{{ $review->reviewer_alias }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $review->created_at?->format('M j, Y · g:i A') }}</p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-xs font-semibold text-slate-700 truncate max-w-[180px]">
                                        {{ $review->reviewable instanceof \App\Models\RoomType ? ($review->reviewable->room_name . ' · ' . optional($review->reviewable->hotel)->hotel_name) : ($review->reviewable?->hotel_name ?? $review->reviewable?->room_name ?? $review->reviewable?->activity_name ?? $review->reviewable?->name ?? '—') }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="flex items-center gap-0.5 text-amber-400">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' {{ $i <= (int)$review->rating ? 1 : 0 }}">star</span>
                                        @endfor
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    @php
                                        $m = $sentimentMeta[$review->sentiment] ?? $sentimentMeta['neutral'];
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold border {{ $review->sentiment === 'positive' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : ($review->sentiment === 'negative' ? 'bg-rose-50 text-rose-700 border-rose-100' : 'bg-amber-50 text-amber-700 border-amber-100') }}">
                                        <span class="material-symbols-outlined text-[12px]">{{ $m['icon'] }}</span>
                                        {{ $m['label'] }}
                                        <span class="ml-0.5">{{ number_format((float)($review->sentiment_score ?? 0), 2) }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[11px] text-slate-600 line-clamp-2 max-w-[260px]">{{ $review->comment }}</p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex px-2 py-0.5 rounded-lg text-[10px] font-bold {{ $review->is_published ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                                        {{ $review->is_published ? 'Published' : 'Hidden' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.reviews.toggle-publish', $review->id) }}">
                                            @csrf
                                            <button type="submit"
                                                class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold border {{ $review->is_published ? 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' : 'bg-ocean-50 text-ocean-700 border-ocean-100 hover:bg-ocean-100' }} transition cursor-pointer"
                                                title="{{ $review->is_published ? 'Hide from public' : 'Publish' }}">
                                                {{ $review->is_published ? 'Hide' : 'Publish' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.reviews.destroy', $review->id) }}"
                                            onsubmit="return confirm('Delete review #{{ $review->id }} permanently? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-100 hover:bg-rose-100 transition cursor-pointer">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400 text-xs">
                                    No reviews submitted yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($reviews->hasPages())
                <div class="px-6 py-4 border-t border-slate-200">
                    {{ $reviews->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection