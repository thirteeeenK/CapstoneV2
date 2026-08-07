@props(['summary' => null])

<div class="rounded-2xl border border-sand-200 bg-white shadow-sm p-5">
    @if (!$summary)
        <div class="flex items-center gap-3 text-ink-400">
            <span class="text-2xl">🏝️</span>
            <p class="text-sm">Weather unavailable right now. Check back soon.</p>
        </div>
    @else
        @php
            $current = $summary['current'] ?? null;
            $icon = $current['icon'] ?? null;
        @endphp
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                @if ($icon)
                    <img src="https://openweathermap.org/img/wn/{{ $icon }}@2x.png"
                         alt="{{ $current['description'] ?? '' }}"
                         class="h-12 w-12 object-contain">
                @endif
                <div>
                    <p class="font-headline text-3xl font-bold text-ink-900">
                        {{ round($current['temp'] ?? 0) }}°C
                    </p>
                    <p class="text-sm text-ink-600 capitalize">{{ $current['description'] ?? '' }}</p>
                </div>
            </div>
            @if (isset($current['pop']))
                <span class="rounded-full bg-ocean-50 px-3 py-1 text-xs font-medium text-ocean-700">
                    ☔ {{ round($current['pop'] * 100) }}% rain
                </span>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2 border-t border-sand-100 pt-4 text-center text-sm">
            <div>
                <p class="text-sand-400">Feels like</p>
                <p class="font-medium text-ink-700">{{ round($current['feels_like'] ?? 0) }}°C</p>
            </div>
            <div>
                <p class="text-sand-400">Humidity</p>
                <p class="font-medium text-ink-700">{{ $current['humidity'] ?? '—' }}%</p>
            </div>
            <div>
                <p class="text-sand-400">High</p>
                <p class="font-medium text-ink-700">{{ round($current['temp_max'] ?? 0) }}°C</p>
            </div>
        </div>

        @if (isset($summary['daily']) && count($summary['daily']) > 1)
            <div class="mt-4 grid grid-cols-5 gap-1 border-t border-sand-100 pt-4">
                @foreach (array_slice($summary['daily'], 0, 5) as $day)
                    <div class="flex flex-col items-center gap-1">
                        <p class="text-xs text-sand-400">
                            {{ \Illuminate\Support\Carbon::parse($day['time'])->format('D, M j') }}
                        </p>
                        @if ($day['icon'])
                            <img src="https://openweathermap.org/img/wn/{{ $day['icon'] }}.png"
                                 alt="" class="h-8 w-8">
                        @endif
                        <p class="text-xs font-medium text-ink-700">
                            {{ round($day['temp']) }}°<span class="text-sand-400">/{{ round($day['temp_min'] ?? $day['temp']) }}°</span>
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>