@props(['summary' => null])

<div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs p-4 sm:p-5">
    @if (!$summary)
        <div class="flex items-center gap-3 text-slate-400 py-2">
            <span class="text-xl">🏝️</span>
            <p class="text-xs">Weather forecast unavailable right now.</p>
        </div>
    @else
        @php
            $current = $summary['current'] ?? null;
            $icon = $current['icon'] ?? null;
        @endphp
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                @if ($icon)
                    <img src="https://openweathermap.org/img/wn/{{ $icon }}@2x.png"
                         alt="{{ $current['description'] ?? '' }}"
                         class="h-10 w-10 object-contain">
                @endif
                <div>
                    <p class="font-headline text-2xl font-black text-slate-900 leading-none">
                        {{ round($current['temp'] ?? 0) }}°C
                    </p>
                    <p class="text-xs text-slate-500 capitalize font-medium mt-0.5">{{ $current['description'] ?? '' }}</p>
                </div>
            </div>
            @if (isset($current['pop']))
                <span class="rounded-full bg-ocean-50 border border-ocean-100/80 px-2.5 py-1 text-[11px] font-bold text-ocean-700">
                    ☔ {{ round($current['pop'] * 100) }}% rain
                </span>
            @endif
        </div>

        <div class="mt-3.5 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3 text-center text-xs">
            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider">Feels like</p>
                <p class="font-bold text-slate-800 mt-0.5">{{ round($current['feels_like'] ?? 0) }}°C</p>
            </div>
            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider">Humidity</p>
                <p class="font-bold text-slate-800 mt-0.5">{{ $current['humidity'] ?? '—' }}%</p>
            </div>
            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                <p class="text-slate-400 text-[10px] uppercase font-bold tracking-wider">High / Low</p>
                <p class="font-bold text-slate-800 mt-0.5">{{ round($current['temp_max'] ?? 0) }}° / {{ round($current['temp_min'] ?? $current['temp']) }}°</p>
            </div>
        </div>

        @if (isset($summary['daily']) && count($summary['daily']) > 1)
            <div class="mt-3.5 grid grid-cols-5 gap-1.5 border-t border-slate-100 pt-3">
                @foreach (array_slice($summary['daily'], 0, 5) as $day)
                    <div class="flex flex-col items-center gap-0.5 bg-slate-50/70 p-1.5 rounded-lg border border-slate-100 text-center">
                        <p class="text-[10px] font-semibold text-slate-500">
                            {{ \Illuminate\Support\Carbon::parse($day['time'])->format('D') }}
                        </p>
                        @if ($day['icon'])
                            <img src="https://openweathermap.org/img/wn/{{ $day['icon'] }}.png"
                                 alt="" class="h-6 w-6">
                        @endif
                        <p class="text-[10px] font-bold text-slate-800">
                            {{ round($day['temp']) }}°
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>