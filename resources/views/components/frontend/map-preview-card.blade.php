@props(['marker' => null, 'compact' => false])
@php
    $m = $marker ?? [];
    $type = $m['type'] ?? 'hotel';
    $img = $m['cover_image'] ?? $m['image'] ?? ($m['images'][0] ?? 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80');
@endphp
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="relative h-40 overflow-hidden bg-slate-100">
        <img src="{{ $img }}" alt="{{ $m['name'] ?? '' }}" class="h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent"></div>
        @if ($type === 'destination' && isset($m['weather']['icon']))
            <span class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-1 text-[11px] font-bold text-slate-700">
                <img src="https://openweathermap.org/img/wn/{{ $m['weather']['icon'] }}.png" class="h-5 w-5" alt="">
                {{ round($m['weather']['temp'] ?? 0) }}°C
            </span>
        @endif
        @if (!empty($m['cheapest_price']))
            <span data-cheapest="{{ $m['cheapest_price'] }}" class="absolute bottom-2 right-2 rounded-lg bg-slate-900/85 px-2 py-1 text-xs font-extrabold text-emerald-300">from ₱{{ number_format($m['cheapest_price'], 2) }}</span>
        @elseif (!empty($m['rate']))
            <span data-rate="{{ $m['rate'] }}" class="absolute bottom-2 right-2 rounded-lg bg-slate-900/85 px-2 py-1 text-xs font-extrabold text-emerald-300">{{ \App\Concerns\ResolvesImages::formatRate($m['rate']) }}</span>
        @endif
    </div>
    <div class="space-y-2 p-4">
        <p class="text-[11px] font-extrabold uppercase tracking-widest text-sky-600">{{ $type === 'destination' ? 'Destination' : ($type === 'hotel' ? 'Sanctuary Stay' : 'Experience') }}</p>
        <h3 class="font-headline text-base font-black text-slate-900 line-clamp-1">{{ $m['name'] ?? '' }}</h3>
        @if (!empty($m['subtitle']))
            <p class="text-xs text-slate-500">{{ $m['subtitle'] }}</p>
        @endif
        <div class="flex flex-wrap gap-1.5">
            @if ($type === 'destination')
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ $m['hotel_count'] ?? 0 }} stays · {{ $m['activity_count'] ?? 0 }} experiences</span>
            @endif
            @if (!empty($m['distance_label']))
                <span class="rounded-full bg-ocean-50 px-2.5 py-1 text-[11px] font-bold text-ocean-700">{{ $m['distance_label'] }} away</span>
            @endif
            @if (!empty($m['rating']))
                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">★ {{ number_format($m['rating'], 1) }} ({{ $m['review_count'] ?? 0 }})</span>
            @endif
        </div>
        @if (!empty($m['vibe_tags']))
            <div class="flex flex-wrap gap-1">
                @foreach (array_slice($m['vibe_tags'], 0, 3) as $tag)
                    <span class="rounded-md bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600">#{{ $tag }}</span>
                @endforeach
            </div>
        @endif
        <div class="flex gap-2 pt-1">
            @if ($type === 'destination')
                <a href="{{ $m['url'] ?? '#' }}" class="flex-1 rounded-xl bg-sky-600 px-3 py-2 text-center text-xs font-bold text-white hover:bg-sky-700">Explore Hotels</a>
                <a href="{{ route('explore') }}?focus=destination:{{ $m['id'] ?? '' }}" class="flex-1 rounded-xl bg-slate-100 px-3 py-2 text-center text-xs font-bold text-slate-700 hover:bg-slate-200">Open in Explorer</a>
            @else
                @if (!empty($m['url']))
                    <a href="{{ $m['url'] }}" class="flex-1 rounded-xl bg-slate-900 px-3 py-2 text-center text-xs font-bold text-white hover:bg-slate-800">View Details</a>
                @endif
                <button type="button" data-preview-type="{{ $type }}" data-preview-id="{{ $m['id'] ?? '' }}" class="js-map-preview flex-1 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200">Quick Preview</button>
            @endif
        </div>
    </div>
</div>
