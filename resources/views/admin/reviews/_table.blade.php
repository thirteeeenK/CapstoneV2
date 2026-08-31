@php
    $sentimentMeta = $sentimentMeta ?? [
        'positive' => ['label' => 'Positive', 'icon' => 'sentiment_satisfied', 'bar' => 'bg-emerald-500'],
        'neutral' => ['label' => 'Neutral', 'icon' => 'sentiment_neutral', 'bar' => 'bg-amber-400'],
        'negative' => ['label' => 'Negative', 'icon' => 'sentiment_dissatisfied', 'bar' => 'bg-rose-500'],
        'pending' => ['label' => 'Pending', 'icon' => 'hourglass_top', 'bar' => 'bg-slate-300'],
    ];
@endphp

<div class="overflow-x-auto" data-total="{{ $reviews->total() }}">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400 font-bold">
            <tr>
                <th class="px-6 py-3 w-12">#</th>
                <th class="px-4 py-3">Guest</th>
                <th class="px-4 py-3">Listing</th>
                <th class="px-4 py-3">Rating</th>
                <th class="px-4 py-3">Sentiment</th>
                <th class="px-4 py-3">Comment</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($reviews as $i => $review)
                @php
                    $listingLabel = $review->reviewable instanceof \App\Models\RoomType
                        ? trim($review->reviewable->room_name . ' · ' . optional($review->reviewable->hotel)->hotel_name, ' ·')
                        : ($review->reviewable?->hotel_name ?? $review->reviewable?->room_name ?? $review->reviewable?->activity_name ?? $review->reviewable?->name ?? '—');
                @endphp
                <tr class="hover:bg-ocean-50/30 transition group">
                    <td class="px-6 py-3.5 text-[11px] font-bold text-slate-400">
                        {{ ($reviews->currentPage() - 1) * $reviews->perPage() + $i + 1 }}
                    </td>
                    <td class="px-6 py-3.5">
                        <p class="text-xs font-bold text-slate-900">{{ $review->reviewer_alias }}</p>
                        <p class="text-[10px] text-slate-400">{{ $review->created_at?->format('M j, Y · g:i A') }}</p>
                    </td>
                    <td class="px-4 py-3.5">
                        <p class="text-xs font-semibold text-slate-700 truncate max-w-[180px]">
                            {{ $listingLabel }}
                        </p>
                    </td>
                    <td class="px-4 py-3.5">
                        <span class="flex items-center gap-0.5 text-amber-400">
                            @for ($star = 1; $star <= 5; $star++)
                                <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' {{ $star <= (int)$review->rating ? 1 : 0 }}">star</span>
                            @endfor
                        </span>
                    </td>
                    <td class="px-4 py-3.5">
                        @php
                            $m = $sentimentMeta[$review->sentiment] ?? $sentimentMeta['pending'];
                        @endphp
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold border {{ $review->sentiment === 'positive' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : ($review->sentiment === 'negative' ? 'bg-rose-50 text-rose-700 border-rose-100' : ($review->sentiment === 'pending' ? 'bg-slate-100 text-slate-500 border-slate-200' : 'bg-amber-50 text-amber-700 border-amber-100')) }}">
                            <span class="material-symbols-outlined text-[12px]">{{ $m['icon'] }}</span>
                            {{ $m['label'] }}
                            <span class="ml-0.5">{{ number_format((float)($review->sentiment_score ?? 0), 2) }}</span>
                        </span>
                    </td>
                    <td class="px-4 py-3.5">
                        <p class="text-[11px] text-slate-600 line-clamp-2 group-hover:line-clamp-none max-w-[260px]">{{ $review->comment }}</p>
                        @if(!empty($review->images) && is_array($review->images))
                            <div class="flex gap-1.5 mt-2">
                                @foreach(array_slice($review->images,0,3) as $img)
                                    @php $url = \App\Concerns\ResolvesImages::resolveImg($img); @endphp
                                    <a href="{{ $url }}" target="_blank" class="aspect-[4/3] w-14 rounded-lg overflow-hidden border border-slate-200 bg-white flex items-center justify-center p-0.5 hover:border-ocean-200 hover:shadow-sm transition">
                                        <img src="{{ $url }}" alt="review photo" class="w-full h-full object-contain rounded-md bg-sand-50/60" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                        <span style="display:none" class="w-full h-full items-center justify-center text-[8px] font-bold text-slate-400 text-center leading-tight">No image</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
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
                            <form method="POST" action="{{ route('admin.reviews.toggle-featured', $review->id) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[10px] font-bold border transition cursor-pointer {{ $review->is_featured ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-slate-50 text-slate-500 border-slate-200 hover:bg-slate-100' }}"
                                    title="{{ $review->is_featured ? 'Remove from landing page' : 'Feature on landing page' }}">
                                    <span class="material-symbols-outlined text-[11px] align-middle" style="font-variation-settings: 'FILL' {{ $review->is_featured ? 1 : 0 }};">star</span>
                                    {{ $review->is_featured ? 'Unfeature' : 'Feature' }}
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
                    <td colspan="8" class="px-6 py-12 text-center text-slate-400 text-xs">
                        No reviews match the current filters.
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
