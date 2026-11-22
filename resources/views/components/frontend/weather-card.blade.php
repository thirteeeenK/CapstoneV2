@props(['summary' => null, 'showDates' => false])

<div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-900 shadow-xs p-4 sm:p-5">
    @if (!$summary)
        <div class="flex items-center gap-3 text-slate-400 dark:text-slate-500 py-2">
            <span class="text-xl">🏝️</span>
            <p class="text-xs">Weather forecast unavailable right now.</p>
        </div>
    @else
        @php
            $current = $summary['current'] ?? null;
            $icon = $current['icon'] ?? null;
            $today = $summary['daily'][0] ?? null;
            $outlook = $summary['outlook'] ?? null;
            $outlookStyles = [
                'good' => ['box' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-100', 'icon' => 'wb_sunny'],
                'okay' => ['box' => 'bg-amber-50 dark:bg-amber-950/50 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-100', 'icon' => 'partly_cloudy_day'],
                'poor' => ['box' => 'bg-rose-50 dark:bg-rose-950/50 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-100', 'icon' => 'thunderstorm'],
            ];
            $outlookStyle = $outlookStyles[$outlook['level'] ?? ''] ?? null;
        @endphp
        <div class="flex items-center justify-between gap-2 mb-3">
            <p class="font-label text-[10px] uppercase font-bold tracking-[0.15em] text-ocean-700 dark:text-ocean-300 flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">partly_cloudy_day</span>
                5-day weather forecast
            </p>
            <p class="text-[10px] text-slate-400 dark:text-slate-500">OpenWeather{{ !empty($summary['stale']) ? ' · cached' : '' }}</p>
        </div>

        @if ($outlookStyle)
            <div class="mb-3.5 rounded-xl border px-3 py-2.5 {{ $outlookStyle['box'] }}">
                <p class="flex items-center gap-1.5 text-xs font-bold">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $outlookStyle['icon'] }}</span>
                    Trip outlook: {{ $outlook['label'] }}
                </p>
                @if (!empty($outlook['reasons'][0]))
                    <p class="text-[11px] mt-0.5 opacity-80">{{ $outlook['reasons'][0] }}.</p>
                @endif
                @if (!empty($outlook['driest_date']))
                    <p class="text-[11px] mt-0.5 opacity-80">Driest day: {{ \Illuminate\Support\Carbon::parse($outlook['driest_date'])->format('D, M j') }}.</p>
                @endif
            </div>
        @endif

        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                @if ($icon)
                    <img src="https://openweathermap.org/img/wn/{{ $icon }}@2x.png"
                         alt="{{ $current['description'] ?? '' }}"
                         class="h-10 w-10 object-contain">
                @endif
                <div>
                    <p class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500">Now (nearest forecast)</p>
                    <p class="font-headline text-2xl font-black text-slate-900 dark:text-slate-100 leading-none mt-0.5">
                        {{ round($current['temp'] ?? 0) }}°C
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 capitalize font-medium mt-0.5">{{ $current['description'] ?? '' }}</p>
                </div>
            </div>
            @if (isset($current['pop']))
                <span class="rounded-full bg-ocean-50 dark:bg-ocean-900/50 border border-ocean-100/80 dark:border-ocean-800 px-2.5 py-1 text-[11px] font-bold text-ocean-700 dark:text-ocean-100">
                    ☔ {{ round($current['pop'] * 100) }}% rain
                </span>
            @endif
        </div>

        <div class="mt-3.5 grid grid-cols-3 gap-2 border-t border-slate-100 dark:border-slate-800 pt-3 text-center text-xs">
            <div class="bg-slate-50 dark:bg-slate-800 p-2 rounded-lg border border-slate-100 dark:border-slate-800">
                <p class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider">Feels like</p>
                <p class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ round($current['feels_like'] ?? 0) }}°C</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800 p-2 rounded-lg border border-slate-100 dark:border-slate-800">
                <p class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider">Humidity</p>
                <p class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ $current['humidity'] ?? '—' }}%</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800 p-2 rounded-lg border border-slate-100 dark:border-slate-800">
                <p class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider">Today H / L</p>
                <p class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ round($today['day_max'] ?? $current['temp_max'] ?? 0) }}° / {{ round($today['day_min'] ?? $current['temp_min'] ?? $current['temp']) }}°</p>
            </div>
        </div>

        @if (!empty($summary['advice']))
            <ul class="mt-3 space-y-1">
                @foreach ($summary['advice'] as $tip)
                    <li class="flex items-start gap-1.5 text-[11px] text-slate-600 dark:text-slate-300">
                        <span class="material-symbols-outlined text-[14px] text-ocean-600 dark:text-ocean-300 mt-px" aria-hidden="true">tips_and_updates</span>
                        <span>{{ $tip }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (isset($summary['daily']) && count($summary['daily']) > 1)
            <div class="mt-3.5 grid grid-cols-5 gap-1.5 border-t border-slate-100 dark:border-slate-800 pt-3">
                @foreach (array_slice($summary['daily'], 0, 5) as $day)
                    <div class="flex flex-col items-center gap-0.5 bg-slate-50/70 dark:bg-slate-800/70 p-1.5 rounded-lg border border-slate-100 dark:border-slate-800 text-center">
                        <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                            {{ \Illuminate\Support\Carbon::parse($day['time'])->timezone('Asia/Manila')->format($showDates ? 'D, M j' : 'D') }}
                        </p>
                        @if ($day['icon'])
                            <img src="https://openweathermap.org/img/wn/{{ $day['icon'] }}.png"
                                 alt="{{ $day['description'] ?? '' }}" class="h-6 w-6">
                        @endif
                        <p class="text-[10px] font-bold text-slate-800 dark:text-slate-200">
                            {{ round($day['day_max'] ?? $day['temp']) }}°<span class="font-medium text-slate-400 dark:text-slate-500">/{{ round($day['day_min'] ?? $day['temp']) }}°</span>
                        </p>
                        @isset($day['day_pop'])
                            <p class="text-[9px] font-semibold {{ $day['day_pop'] >= 0.6 ? 'text-rose-600 dark:text-rose-400' : ($day['day_pop'] >= 0.3 ? 'text-amber-600 dark:text-amber-400' : 'text-ocean-600 dark:text-ocean-300') }}">☔ {{ round($day['day_pop'] * 100) }}%</p>
                        @endisset
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
