{{--
    Day-by-day itinerary body shared by the itinerary PDF and the booking voucher.
    Expects $itinerary (a saved snapshot). Shows the overview, the weather/distance
    decisions, one schedule table per day, unscheduled picks, the AI report and a
    turn-by-turn directions appendix.
--}}
@php
    $distances = app(\App\Services\DistanceService::class);
    $mode = $itinerary['mode'] ?? 'driving';
    $days = $itinerary['days'] ?? [];
    $marksBooking = isset($itinerary['booked_plan_item_ids']);
    $routeBasis = fn (array $travel) => match ($travel['geometry_kind'] ?? null) {
        'road' => 'Mapped road/path estimate',
        'road_with_connector' => 'Mapped road/path estimate; the last stretch to the exact spot is not mapped',
        'straight_line' => 'Straight-line guide only — not a verified road or boat route; arrange a transfer',
        'none' => 'No mapped route between these points',
        default => 'Route basis not recorded for this version',
    };
    $stepDistance = fn (array $step) => ($step['maneuver'] ?? null) !== 'arrive' && (float) ($step['distance_m'] ?? 0) > 0 ? ' · '.$distances->format((float) $step['distance_m'] / 1000) : '';
    $duration = fn (int $minutes) => $minutes >= 60 ? intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '') : $minutes.' min';
    $toneRank = ['green' => 0, 'blue' => 1, 'amber' => 2, 'red' => 3];
    $weatherTone = function (array $weather): array {
        $chance = $weather['rain_probability'] ?? null;
        $rain = $weather['rain_mm_per_hour'] ?? null;
        $wind = $weather['wind_m_per_second'] ?? null;

        return match (true) {
            (bool) ($weather['provisional'] ?? false) => ['Provisional', 'amber'],
            (bool) ($weather['blocked'] ?? false) => ['Unsafe outdoors', 'red'],
            ($chance !== null && $chance >= 0.6) || ($rain !== null && $rain >= 2) || ($wind !== null && $wind >= 8) => ['Rain or wind risk', 'amber'],
            ($chance !== null && $chance >= 0.3) || ($rain !== null && $rain > 0.2) => ['Showers possible', 'blue'],
            default => ['Good conditions', 'green'],
        };
    };
    $weatherDetail = function (array $weather): string {
        $parts = [];
        if (($weather['rain_probability'] ?? null) !== null) { $parts[] = 'Rain chance '.round($weather['rain_probability'] * 100).'%'; }
        if (($weather['rain_mm_per_hour'] ?? null) !== null && $weather['rain_mm_per_hour'] > 0) { $parts[] = number_format($weather['rain_mm_per_hour'], 1).' mm/h'; }
        if (($weather['wind_m_per_second'] ?? null) !== null) { $parts[] = 'Wind '.number_format($weather['wind_m_per_second'], 1).' m/s'; }

        return implode(' · ', $parts);
    };
    $allStops = collect($days)->flatMap(fn ($day) => $day['stops'] ?? []);
    $provisionalStops = $allStops->filter(fn ($stop) => $stop['weather']['provisional'] ?? false)->count();
    $approximateStops = $allStops->filter(fn ($stop) => ! empty($stop['travel_estimated']))->count();
    $totalKm = (float) collect($days)->sum('distance_km');
    $totalMinutes = (int) collect($days)->sum('travel_minutes');
    $longestDay = collect($days)->sortByDesc('distance_km')->first();
    $forecast = $itinerary['forecast'] ?? null;
    $unscheduled = $itinerary['unscheduled'] ?? [];
    $weatherHeld = collect($unscheduled)->filter(fn ($choice) => preg_match('/forecast|rain|wind|weather/i', (string) ($choice['reason'] ?? '')))->count();
    $directionLegs = collect($days)->flatMap(fn ($day) => collect($day['stops'] ?? [])
        ->filter(fn ($stop) => ! empty($stop['travel']['steps']) && ! in_array($stop['travel']['geometry_kind'] ?? null, ['straight_line', 'none'], true))
        ->map(fn ($stop) => ['day' => $day['day'], 'stop' => $stop]))->values();
@endphp

{{-- Trip overview --}}
<div class="section">
    <div class="section-title">Trip overview</div>
    <table class="data">
        <thead><tr><th>Day</th><th>Date</th><th>Hotel base</th><th class="amount">Visits</th><th class="amount">Distance</th><th class="amount">Mapped travel</th><th>Weather outlook</th></tr></thead>
        <tbody>
            @foreach ($days as $day)
                @php
                    $tones = collect($day['stops'] ?? [])->map(fn ($stop) => $weatherTone($stop['weather'] ?? []));
                    $worst = $tones->sortByDesc(fn ($tone) => $toneRank[$tone[1]])->first();
                @endphp
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="strong nowrap">Day {{ $day['day'] }}</td>
                    <td>{{ $day['label'] }}</td>
                    <td>{{ $day['hotel']['name'] ?? 'No hotel base assigned' }}</td>
                    <td class="amount">{{ count($day['stops'] ?? []) }}</td>
                    <td class="amount"><span class="km">{{ number_format((float) $day['distance_km'], 2) }} km</span></td>
                    <td class="amount">{{ $duration((int) $day['travel_minutes']) }}</td>
                    <td>@if ($worst)<span class="chip chip-{{ $worst[1] }}">{{ $worst[0] }}</span>@else<span class="muted">Free day</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- How weather and distance shaped the plan --}}
<div class="section">
    <div class="section-title">How weather and distance shaped this plan</div>
    <div class="notice {{ ($forecast['stale'] ?? false) || $provisionalStops > 0 ? 'notice-amber' : '' }}">
        <ul>
            <li class="{{ ($forecast['stale'] ?? false) || $provisionalStops > 0 ? 'warn' : '' }}">
                @if ($forecast && ($forecast['fetched_at'] ?? null))Forecast checked {{ \Illuminate\Support\Carbon::parse($forecast['fetched_at'])->timezone('Asia/Manila')->format('M j, Y g:i A') }}{{ ($forecast['stale'] ?? false) ? ' from a saved copy that may be out of date' : '' }}.@else Forecast check time was not recorded for this version.@endif
                {{ $provisionalStops > 0 ? $provisionalStops.' visit'.($provisionalStops === 1 ? ' is' : 's are').' beyond the reliable forecast range and marked provisional.' : '' }} Recheck the weather closer to departure.
            </li>
            <li>Each visit was placed in the open time slot with the lowest rain and wind risk, then the least extra travel from the previous stop. The reason is printed under every visit as “Why this slot”.</li>
            <li>Total mapped travel: <strong>{{ number_format($totalKm, 2) }} km</strong> and <strong>{{ $duration($totalMinutes) }}</strong> by {{ $mode }} across {{ count($days) }} {{ \Illuminate\Support\Str::plural('day', count($days)) }}, including returns to your hotel.@if ($longestDay && $longestDay['distance_km'] > 0) The longest day is Day {{ $longestDay['day'] }} ({{ number_format((float) $longestDay['distance_km'], 2) }} km).@endif</li>
            @if ($weatherHeld > 0)<li class="warn">{{ $weatherHeld }} {{ \Illuminate\Support\Str::plural('pick', $weatherHeld) }} could not be scheduled because of the forecast; see “Other picks to arrange yourself”.</li>@endif
            @if ($approximateStops > 0)<li class="warn">{{ $approximateStops }} {{ \Illuminate\Support\Str::plural('visit', $approximateStops) }} {{ $approximateStops === 1 ? 'has' : 'have' }} no exact meeting point yet, so travel is measured from the destination centre. Confirm with the operator.</li>@endif
        </ul>
    </div>
    <p class="small muted" style="margin-top: 5px;">Weather labels: <span class="chip chip-green">Good conditions</span> <span class="chip chip-blue">Showers possible</span> <span class="chip chip-amber">Rain or wind risk / Provisional</span> · Distances follow {{ $mode }} routes; all times are Asia/Manila.</p>
</div>

{{-- Day-by-day schedule --}}
<div class="section">
    <div class="section-title">Day-by-day schedule</div>
    @foreach ($days as $day)
        @php
            $events = $day['events'] ?? [];
            $dayPlan = [];
            foreach (array_filter($events, fn ($event) => empty($event['departure'])) as $event) { $dayPlan[] = ['event' => true, 'data' => $event]; }
            foreach ($day['stops'] ?? [] as $stop) { $dayPlan[] = ['event' => false, 'data' => $stop]; }
            foreach (array_filter($events, fn ($event) => ! empty($event['departure'])) as $event) { $dayPlan[] = ['event' => true, 'data' => $event]; }
        @endphp
        <div class="day">
            <table class="day-band"><tr>
                <td class="day-no">Day {{ $day['day'] }} · {{ $day['label'] }}</td>
                <td class="day-meta">{{ number_format((float) $day['distance_km'], 2) }} km · {{ $duration((int) $day['travel_minutes']) }} mapped travel, including return</td>
            </tr></table>
            <div class="day-sub">
                <strong>Stay:</strong> {{ $day['hotel']['name'] ?? 'No hotel base assigned' }}
                @foreach ($day['hotel']['rooms'] ?? [] as $room)
                    <br>Room: {{ $room['quantity'] }} × {{ $room['name'] }} · {{ $room['pax'] }} guests · {{ $room['check_in'] }} to {{ $room['check_out'] }}@if ($marksBooking) · {{ in_array($room['plan_item_id'] ?? null, $itinerary['booked_plan_item_ids'], true) ? 'Included in booking' : 'Planning choice only' }}@endif
                @endforeach
            </div>
            @if ($dayPlan === [] || empty($day['stops']))
                @foreach ($dayPlan as $entry)<p class="muted" style="padding: 5px 9px;"><em>{{ $entry['data']['name'] }}</em></p>@endforeach
                <p style="padding: 6px 9px;">No visits are scheduled for this day. Keep it open for spontaneous activities, your own plans, or simply to rest.</p>
            @else
                <table class="schedule">
                    <thead><tr><th>Time</th><th style="width: 41%;">Visit &amp; why this slot</th><th style="width: 22%;">Getting there</th><th style="width: 22%;">Weather</th></tr></thead>
                    <tbody>
                        @foreach ($dayPlan as $i => $entry)
                            @if ($entry['event'])
                                <tr class="event"><td colspan="4">{{ $i + 1 }}. {{ $entry['data']['name'] }}</td></tr>
                            @else
                                @php($stop = $entry['data'])
                                @php($travel = $stop['travel'] ?? [])
                                @php($weather = $stop['weather'] ?? [])
                                @php($tone = $weatherTone($weather))
                                <tr>
                                    <td class="time">{{ $stop['start'] }}–{{ $stop['end'] }}</td>
                                    <td>
                                        <div class="stop-name">{{ $i + 1 }}. {{ $stop['name'] }}</div>
                                        @if ($marksBooking)
                                            @php($included = in_array($stop['plan_item_id'] ?? null, $itinerary['booked_plan_item_ids'], true))
                                            <span class="chip {{ $included ? 'chip-green' : 'chip-slate' }}">{{ $included ? 'Included in booking' : 'Planning choice only' }}</span>
                                        @endif
                                        <div class="why"><strong>Why this slot:</strong> {{ $stop['reason'] ?? '' }}</div>
                                        @if (! empty($stop['travel_estimated']))<div class="why warn">Location approximate: the exact meeting point is not set, so travel is measured from the destination centre. Confirm it with the operator.</div>@endif
                                    </td>
                                    <td>
                                        @if (($travel['distance_km'] ?? null) !== null && ($travel['duration_minutes'] ?? null) !== null)
                                            <span class="km">{{ number_format((float) $travel['distance_km'], 2) }} km</span> · {{ $travel['duration_minutes'] }} min {{ $mode }}
                                            <div class="small muted">from {{ $travel['from'] ?? 'previous stop' }}</div>
                                        @else
                                            <span class="muted">Distance and travel time from {{ $travel['from'] ?? 'the previous stop' }} are unavailable</span>
                                        @endif
                                        <div class="small {{ in_array($travel['geometry_kind'] ?? null, ['straight_line', 'none'], true) ? 'warn' : 'muted' }}">{{ $routeBasis($travel) }}</div>
                                    </td>
                                    <td>
                                        <span class="chip chip-{{ $tone[1] }}">{{ $tone[0] }}</span>
                                        <div class="{{ ($weather['provisional'] ?? false) ? 'warn' : '' }}" style="margin-top: 3px;">{{ ($weather['provisional'] ?? false) ? 'Provisional forecast: ' : '' }}{{ $weather['description'] ?? 'Forecast not recorded' }}</div>
                                        @if ($weatherDetail($weather) !== '')<div class="small muted">{{ $weatherDetail($weather) }}</div>@endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</div>

@if ($unscheduled !== [])
    <div class="section">
        <div class="section-title">Other picks to arrange yourself</div>
        <p class="section-note">We could not fit these onto a specific day automatically, so they are not plotted on the map. You can still add them to your trip and arrange the timing yourself.</p>
        <table class="data">
            <thead><tr><th style="width: 32%;">Pick</th><th>Why it was not scheduled</th></tr></thead>
            <tbody>@foreach ($unscheduled as $choice)<tr class="{{ $loop->even ? 'alt' : '' }}"><td class="strong">{{ $choice['name'] }}</td><td>{{ $choice['reason'] }}</td></tr>@endforeach</tbody>
        </table>
    </div>
@endif

@if (! empty($itinerary['report']))
    <div class="section report">
        <div class="section-title">Strategic report</div>
        @foreach ((array) $itinerary['report'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
    </div>
@endif

@if ($directionLegs->isNotEmpty())
    <div class="page-break"></div>
    <div class="section">
        <div class="section-title">Appendix · Turn-by-turn directions</div>
        <p class="section-note">Written directions for each mapped road leg, in schedule order. Road/path estimates do not include live traffic.</p>
        @foreach ($directionLegs as $leg)
            <div class="directions-block">
                <strong>Day {{ $leg['day'] }} · Directions from {{ $leg['stop']['travel']['from'] ?? 'previous stop' }} to {{ $leg['stop']['name'] }}</strong>
                <span class="muted">({{ number_format((float) ($leg['stop']['travel']['distance_km'] ?? 0), 2) }} km)</span>
                <ol>@foreach ($leg['stop']['travel']['steps'] as $step)<li>{{ $step['instruction'] }}{{ $stepDistance($step) }}</li>@endforeach</ol>
            </div>
        @endforeach
    </div>
@endif
