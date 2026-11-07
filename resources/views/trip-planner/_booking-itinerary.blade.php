@foreach ($booking->trip_itineraries ?? [] as $version => $itinerary)
    <div class="px-6 sm:px-8 py-6 border-t border-sand-100 dark:border-slate-800 font-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-label text-[10px] uppercase font-bold tracking-[0.15em] text-ocean-700 dark:text-ocean-300">Day-by-day plan</p>
                <h3 class="mt-1 font-headline text-base font-bold text-slate-900 dark:text-slate-100">{{ $itinerary['title'] }}</h3>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $itinerary['start_date'] }} to {{ $itinerary['end_date'] }}</p>
            </div>
            @if (count($booking->trip_itineraries) > 1)
                @include('trip-planner._booking-itinerary-links', ['version' => $version])
            @endif
        </div>
        <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">This is the plan saved at checkout. Your booking’s current item availability and status determine which rooms and activities are confirmed.</p>

        @foreach ($itinerary['days'] as $day)
            <div class="mt-6 border-t border-sand-100 dark:border-slate-800 pt-4">
                <h4 class="font-headline text-sm font-bold text-slate-900 dark:text-slate-100">Day {{ $day['day'] }} · {{ $day['label'] }}</h4>
                @if ($day['hotel'])<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Hotel base: {{ $day['hotel']['name'] }}</p>@endif
                @foreach ($day['hotel']['rooms'] ?? [] as $room)
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ $room['quantity'] }} × {{ $room['name'] }} · {{ $room['pax'] }} guests · {{ $room['check_in'] }} to {{ $room['check_out'] }} · {{ in_array($room['plan_item_id'], $itinerary['booked_plan_item_ids'] ?? [], true) ? 'Included in booking request' : 'Planning choice only' }}</p>
                @endforeach
                <p class="mt-1 text-xs text-ocean-700 dark:text-ocean-300">{{ number_format((float) $day['distance_km'], 2) }} km · {{ $day['travel_minutes'] }} min {{ $itinerary['mode'] }}, including return</p>
                @foreach ($day['events'] as $event)<p class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ $event['time'] }} · {{ $event['name'] }}</p>@endforeach
                @foreach ($day['stops'] as $stop)
                    @php
                        $booked = in_array($stop['plan_item_id'], $itinerary['booked_plan_item_ids'] ?? [], true);
                        $bookingItem = $booking->items->first(fn ($item) => ($item->item_snapshot['trip_plan_item_id'] ?? null) === $stop['plan_item_id'] && ($item->item_snapshot['trip_itinerary_id'] ?? null) === ($itinerary['itinerary_id'] ?? null));
                        $excluded = $bookingItem && $bookingItem->availability_status === 'unavailable';
                        $travel = $stop['travel'];
                    @endphp
                    <article class="mt-4 border-l-2 border-ocean-300 dark:border-ocean-700 pl-4">
                        <h5 class="font-headline text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $loop->iteration }}. {{ $stop['start'] }}–{{ $stop['end'] }} · {{ $stop['name'] }}</h5>
                        <p class="mt-1 text-xs {{ $excluded ? 'text-rose-700 dark:text-rose-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $excluded ? 'Excluded from booking by admin' : ($booked ? 'Included in this booking request · see current booking status' : 'Planning choice only · not purchased in this booking') }}</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            @if ($travel['distance_km'] !== null && $travel['duration_minutes'] !== null)
                                {{ number_format((float) $travel['distance_km'], 2) }} km · {{ $travel['duration_minutes'] }} min from {{ $travel['from'] }}
                            @else
                                Travel time from {{ $travel['from'] }} is not available
                            @endif
                        </p>
                        @if (($travel['geometry_kind'] ?? null) === 'straight_line')
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">No mapped road connects these stops, so arrange a boat or transfer.</p>
                        @endif
                        <x-frontend.route-directions :leg="$travel" :to="$stop['name']" />
                        <details class="mt-1"><summary class="min-h-11 py-2 cursor-pointer text-sm font-bold text-ocean-700 dark:text-ocean-300">Why this visit is scheduled here</summary><p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $stop['reason'] }}</p></details>
                    </article>
                @endforeach
                @if (empty($day['stops']))<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No scheduled visits; time for arrival, departure or rest.</p>@endif
            </div>
        @endforeach
        <details class="mt-6 border-t border-sand-100 dark:border-slate-800 pt-4"><summary class="min-h-11 py-2 cursor-pointer font-headline text-sm font-bold text-slate-900 dark:text-slate-100">Strategic report</summary>@foreach ($itinerary['report'] as $paragraph)<p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $paragraph }}</p>@endforeach</details>
    </div>
@endforeach
