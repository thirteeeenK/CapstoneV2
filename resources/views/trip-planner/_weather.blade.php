<div class="space-y-3">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="font-headline text-base font-bold">Weather forecast · {{ $destination->name }}</h2>
        @if (!empty($summary['fetched_at']))
            <p class="text-xs text-slate-500 dark:text-slate-400">Updated {{ \Illuminate\Support\Carbon::parse($summary['fetched_at'])->timezone('Asia/Manila')->format('M j, g:i A') }} PHT</p>
        @endif
    </div>
    @if (!empty($summary['stale']))
        <p role="status" class="text-xs text-amber-700 dark:text-amber-300">Showing a cached forecast because the latest update is unavailable.</p>
    @endif
    @if (!empty($summary['current']['time']))
        <p class="text-xs text-slate-500 dark:text-slate-400">Forecast for {{ \Illuminate\Support\Carbon::parse($summary['current']['time'])->timezone('Asia/Manila')->format('M j, g:i A') }} PHT</p>
    @endif
    <x-frontend.weather-card :summary="!empty($summary['current']) ? $summary : null" :show-dates="true" />
    @if (!empty($summary['slots']))
        @php
            $lastSlot = $summary['slots'][array_key_last($summary['slots'])];
        @endphp
        <p class="text-xs text-slate-500 dark:text-slate-400">Available forecast ends {{ \Illuminate\Support\Carbon::parse($lastSlot['time'])->timezone('Asia/Manila')->format('M j, Y, g:i A') }} PHT. Later trip dates do not have a forecast yet.</p>
    @endif
    <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-400">Forecasts are estimates and may change. Conditions can vary by location, especially at sea. Recheck the forecast and confirm outdoor or water activities with your operator before departure.</p>
</div>
