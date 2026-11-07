@props(['leg', 'to' => null])

@php
    // Same maneuver icons as resources/js/route-directions.js.
    $icons = [
        'depart' => 'near_me', 'arrive' => 'flag', 'left' => 'turn_left', 'right' => 'turn_right',
        'sharp_left' => 'turn_sharp_left', 'sharp_right' => 'turn_sharp_right', 'slight_left' => 'turn_slight_left', 'slight_right' => 'turn_slight_right',
        'straight' => 'straight', 'uturn' => 'u_turn_left', 'keep_left' => 'fork_left', 'keep_right' => 'fork_right',
        'roundabout' => 'roundabout_right', 'roundabout_exit' => 'roundabout_right',
    ];
    $kind = $leg['geometry_kind'] ?? null;
    $routed = ! empty($leg['geometry']) && ! in_array($kind, ['straight_line', 'none'], true);
    $steps = $leg['steps'] ?? null;
    $distances = app(\App\Services\DistanceService::class);
@endphp

@if ($routed)
    @if (! is_array($steps))
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Turn-by-turn directions weren't saved with this itinerary.</p>
    @elseif ($steps === [])
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Written directions aren't available for this leg.</p>
    @else
        <details class="mt-1">
            <summary class="min-h-11 py-2 cursor-pointer text-sm font-bold text-ocean-700 dark:text-ocean-300">Directions from {{ $leg['from'] }}</summary>
            <ol aria-label="Directions from {{ $leg['from'] }}{{ $to ? ' to ' . $to : '' }}"
                class="divide-y divide-sand-100 dark:divide-slate-800 rounded-xl border border-sand-200 dark:border-slate-700">
                @foreach ($steps as $step)
                    <li class="flex items-start gap-2.5 px-3 py-2 text-sm">
                        <span class="material-symbols-outlined mt-px shrink-0 text-[18px] text-ocean-700 dark:text-ocean-300" aria-hidden="true">{{ $icons[$step['maneuver']] ?? 'straight' }}</span>
                        <span class="flex-1 text-slate-700 dark:text-slate-300">{{ $step['instruction'] }}</span>
                        @if ($step['maneuver'] !== 'arrive' && (float) $step['distance_m'] > 0)
                            <span class="shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $distances->format((float) $step['distance_m'] / 1000) }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if (collect($steps)->contains(fn ($step) => $step['street'] === null && $step['maneuver'] !== 'arrive'))
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Some paths here have no name on OpenStreetMap, so those steps show only the turn.</p>
            @endif
        </details>
    @endif
@endif
