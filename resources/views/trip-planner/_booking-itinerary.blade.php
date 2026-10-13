@foreach ($booking->trip_itineraries ?? [] as $version => $itinerary)
    <section class="my-6 rounded-xl border border-ocean-200 dark:border-ocean-900 bg-white dark:bg-slate-900 p-5 sm:p-7">
        <p class="text-xs uppercase tracking-widest font-bold text-ocean-700 dark:text-ocean-300">SunnyTrips · Saved trip plan</p>
        <h2 class="mt-2 font-headline text-xl font-bold">Your saved itinerary</h2>
        <p class="mt-2 text-sm text-slate-500">{{ $itinerary['title'] }} · {{ $itinerary['start_date'] }} to {{ $itinerary['end_date'] }}</p>
        <p class="mt-3 text-sm text-slate-500">This is the plan saved at checkout. Your booking’s current item availability and status determine which rooms and activities are confirmed.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('booking.itinerary', [$booking->booking_code, $version]) }}" class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2.5 text-sm font-bold text-white">Download itinerary PDF</a>
            <a href="{{ route('booking.itinerary', [$booking->booking_code, $version, 'print' => 1]) }}" target="_blank" rel="noopener" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Print itinerary</a>
        </div>
        @foreach ($itinerary['days'] as $day)
            <div class="mt-6 border-t border-sand-200 dark:border-slate-700 pt-4">
                <h3 class="font-headline font-bold">Day {{ $day['day'] }} · {{ $day['label'] }}</h3>
                @if ($day['hotel'])<p class="mt-1 text-sm text-slate-500">Hotel base: {{ $day['hotel']['name'] }}</p>@endif
                @foreach ($day['hotel']['rooms'] ?? [] as $room)
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $room['quantity'] }} × {{ $room['name'] }} · {{ $room['pax'] }} guests · {{ $room['check_in'] }} to {{ $room['check_out'] }} · {{ in_array($room['plan_item_id'], $itinerary['booked_plan_item_ids'] ?? [], true) ? 'Included in booking request' : 'Planning choice only' }}</p>
                @endforeach
                <p class="mt-1 text-xs text-ocean-700 dark:text-ocean-300">{{ number_format($day['distance_km'], 2) }} km · {{ $day['travel_minutes'] }} min {{ $itinerary['mode'] }}, including return</p>
                @foreach ($day['events'] as $event)<p class="mt-2 text-sm">{{ $event['time'] }} · {{ $event['name'] }}</p>@endforeach
                @foreach ($day['stops'] as $stop)
                    @php
                        $booked = in_array($stop['plan_item_id'], $itinerary['booked_plan_item_ids'] ?? [], true);
                        $bookingItem = $booking->items->first(fn ($item) => ($item->item_snapshot['trip_plan_item_id'] ?? null) === $stop['plan_item_id'] && ($item->item_snapshot['trip_itinerary_id'] ?? null) === ($itinerary['itinerary_id'] ?? null));
                        $excluded = $bookingItem && $bookingItem->availability_status === 'unavailable';
                    @endphp
                    <article class="mt-4 border-l-2 border-ocean-300 pl-4">
                        <h4 class="font-semibold">{{ $loop->iteration }}. {{ $stop['start'] }}–{{ $stop['end'] }} · {{ $stop['name'] }}</h4>
                        <p class="mt-1 text-xs {{ $excluded ? 'text-rose-700' : 'text-slate-500' }}">{{ $excluded ? 'Excluded from booking by admin' : ($booked ? 'Included in this booking request · see current booking status' : 'Planning choice only · not purchased in this booking') }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ number_format($stop['travel']['distance_km'], 2) }} km · {{ $stop['travel']['duration_minutes'] }} min from {{ $stop['travel']['from'] }}</p>
                        <details class="mt-2"><summary class="min-h-11 py-2 cursor-pointer text-sm font-bold text-ocean-700 dark:text-ocean-300">Why this visit is scheduled here</summary><p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $stop['reason'] }}</p></details>
                    </article>
                @endforeach
                @if (empty($day['stops']))<p class="mt-2 text-sm text-slate-500">No scheduled visits; time for arrival, departure or rest.</p>@endif
            </div>
        @endforeach
        <details class="mt-6 border-t border-sand-200 dark:border-slate-700 pt-4"><summary class="min-h-11 py-2 cursor-pointer font-headline font-bold">Strategic report</summary>@foreach ($itinerary['report'] as $paragraph)<p class="mt-3 text-sm text-slate-600 dark:text-slate-400">{{ $paragraph }}</p>@endforeach</details>
    </section>
@endforeach
