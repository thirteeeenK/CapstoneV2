{{-- Compact forecast outlook for generated itineraries (ItineraryInsightsService::weatherFor shape). --}}
@props(['weather' => null])

@php
    $styles = [
        'good' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-100',
        'okay' => 'bg-amber-50 dark:bg-amber-950/50 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-100',
        'poor' => 'bg-rose-50 dark:bg-rose-950/50 border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-100',
    ];
@endphp

@if ($weather)
    <div {{ $attributes->merge(['class' => 'rounded-2xl border p-3 '.($styles[$weather['level']] ?? 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100')]) }}>
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                @if ($weather['icon'])
                    <img src="https://openweathermap.org/img/wn/{{ $weather['icon'] }}.png" alt="" class="h-8 w-8 shrink-0">
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-bold">Weather outlook: {{ $weather['label'] }}</p>
                    <p class="text-[11px] opacity-80 capitalize truncate">{{ $weather['temp'] !== null ? round($weather['temp']).'°C · ' : '' }}{{ $weather['description'] }}{{ $weather['stale'] ? ' · cached forecast' : '' }}</p>
                </div>
            </div>
        </div>
        @if ($weather['reason'])
            <p class="mt-1.5 text-[11px] opacity-80">{{ $weather['reason'] }}.</p>
        @endif
        @if (count($weather['days']) > 1)
            <div class="mt-2 grid grid-cols-5 gap-1 text-center">
                @foreach ($weather['days'] as $day)
                    <div class="rounded-lg bg-white/70 dark:bg-slate-900/60 px-1 py-1">
                        <p class="text-[10px] font-semibold">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D') }}</p>
                        <p class="text-[10px] font-bold">{{ $day['max'] !== null ? round($day['max']).'°' : '—' }}</p>
                        <p class="text-[9px] opacity-80">☔ {{ round($day['pop'] * 100) }}%</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@else
    <p {{ $attributes->merge(['class' => 'text-[11px] text-slate-500 dark:text-slate-400']) }}>Weather forecast unavailable right now — check again closer to your trip.</p>
@endif
