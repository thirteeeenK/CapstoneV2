<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $itinerary['title'] }} — SunnyTrips</title>
    <style>
        @page { margin: 20mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #183440; font-size: 10px; line-height: 1.6; margin: 0; }
        .brand { font-size: 13px; font-weight: bold; color: #006b91; letter-spacing: 2px; }
        h1 { font-size: 25px; line-height: 1.25; margin: 12px 0; } h2 { font-size: 16px; margin: 0 0 5px; } h3 { font-size: 12px; margin: 0; }
        .muted { color: #5c707b; } .masthead { border-bottom: 3px solid #006b91; padding-bottom: 14px; margin-bottom: 18px; }
        .reference { border: 2px solid #183440; padding: 10px 14px; margin: 12px 0; page-break-inside: avoid; }
        .reference .label { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #5c707b; }
        .reference .code { display: block; font-size: 22px; font-weight: bold; letter-spacing: 2px; font-family: 'DejaVu Sans Mono', monospace; }
        .day { margin-top: 20px; page-break-inside: avoid; } .new-page { page-break-before: always; } .day-heading { background: #edf6f8; border-left: 3px solid #006b91; padding: 9px 12px; page-break-after: avoid; }
        .stop { padding: 12px 0; border-bottom: 1px solid #d7e3e6; page-break-inside: avoid; }
        .reason { margin-top: 5px; } .event { margin: 8px 0; } .report { margin-top: 24px; } .report h2 { page-break-after: avoid; }
        .warn { color: #8a4b00; }
        table.prices { width: 100%; border-collapse: collapse; margin-top: 8px; page-break-inside: avoid; }
        table.prices th, table.prices td { text-align: left; padding: 5px 6px; border-bottom: 1px solid #d7e3e6; vertical-align: top; }
        table.prices td.amount, table.prices th.amount { text-align: right; white-space: nowrap; }
        table.prices tr.total td { font-weight: bold; border-top: 2px solid #183440; }
        .footer { margin-top: 20px; font-size: 8px; color: #5c707b; border-top: 1px solid #d7e3e6; padding-top: 8px; }
        .print-controls { margin: 14px 0; } button { padding: 10px 16px; background: #006b91; border: 0; border-radius: 5px; color: white; cursor: pointer; }
        @media screen { body { max-width: 800px; padding: 25px; margin: auto; font-size: 13px; } }
        @media print { .print-controls { display: none; } }
    </style>
</head>
<body>
    @php
        $peso = fn ($amount) => '₱'.number_format((float) $amount, 2);
        $routeBasis = fn (array $travel) => match ($travel['geometry_kind'] ?? null) {
            'road' => 'Mapped road/path estimate',
            'road_with_connector' => 'Mapped road/path estimate; the last stretch to the exact spot is not mapped',
            'straight_line' => 'Straight-line guide only — not a verified road or boat route; arrange a transfer',
            'none' => 'No mapped route between these points',
            default => 'Route basis not recorded for this version',
        };
        $booked = isset($booking) && $booking;
        $bookedItems = $booked ? $booking->items->filter(fn ($item) => (isset($itinerary['itinerary_id']) && ($item->item_snapshot['trip_itinerary_id'] ?? null) === $itinerary['itinerary_id'])
            || in_array($item->item_snapshot['trip_plan_item_id'] ?? null, $itinerary['booked_plan_item_ids'] ?? [], true))->values() : collect();
        $forecast = $itinerary['forecast'] ?? null;
        $provisionalStops = collect($itinerary['days'])->flatMap(fn ($day) => $day['stops'])->filter(fn ($stop) => $stop['weather']['provisional'] ?? false)->count();
    @endphp
    @if (request()->boolean('print'))<div class="print-controls"><button onclick="window.print()">Print itinerary</button></div>@endif
    <div class="masthead"><div class="brand">SUNNYTRIPS</div><h1>{{ $itinerary['title'] }}</h1>
        <div class="reference"><span class="label">{{ $referenceLabel ?? 'Reference' }}</span><span class="code">{{ $referenceCode ?? $reference }}</span>
            <span class="muted">{{ $booked ? 'Booking status: '.str_replace('_', ' ', $booking->status).' · Payment: '.str_replace('_', ' ', $booking->payment_status) : 'Checked schedule · saved plan version, not a reservation' }}</span></div>
        <p>{{ $itinerary['destination'] }} · {{ $itinerary['start_date'] }} to {{ $itinerary['end_date'] }} · {{ $itinerary['pax'] }} travelers</p><p class="muted">{{ $reference }} · {{ ucfirst($itinerary['mode']) }} routes · All times in Asia/Manila</p></div>
    <p class="muted">A suggested travel plan based on your selected hotels, rooms and activities, mapped routes and the available forecast. Feel free to follow it as it is or adjust it to your own pace. Confirm meeting points, schedules and operating conditions with providers before departure.</p>
    <p class="{{ ($forecast['stale'] ?? false) || $provisionalStops > 0 ? 'warn' : 'muted' }}">
        @if ($forecast && $forecast['fetched_at'])Forecast checked {{ \Illuminate\Support\Carbon::parse($forecast['fetched_at'])->timezone('Asia/Manila')->format('M j, Y g:i A') }}{{ $forecast['stale'] ? ' from a saved copy that may be out of date' : '' }}. @else Forecast check time was not recorded for this version. @endif
        {{ $provisionalStops > 0 ? $provisionalStops.' visit'.($provisionalStops === 1 ? ' is' : 's are').' beyond the reliable forecast range and marked provisional.' : '' }} Recheck the weather closer to departure.
    </p>

    <section class="report">
        @if ($booked)
            <h2>Booked services and prices</h2>
            <p class="muted">Stored prices from booking {{ $booking->booking_code }}. Only the services listed here belong to the booking; other plan choices are not purchased.</p>
            <table class="prices"><thead><tr><th>Service</th><th>Dates · quantity</th><th>Status</th><th class="amount">Amount</th></tr></thead><tbody>
                @foreach ($bookedItems as $item)
                    <tr><td>{{ $item->item_title }}@if ($item->hotel_name)<br><span class="muted">{{ $item->hotel_name }}</span>@endif</td>
                        <td>{{ $item->check_in_date?->format('M j, Y') ?? 'Date to be arranged' }}{{ $item->check_out_date ? ' – '.$item->check_out_date->format('M j, Y') : '' }}<br><span class="muted">{{ $item->quantity }} × · {{ $item->selected_pax }} guests</span></td>
                        <td>{{ $item->availability_status === 'unavailable' ? 'Excluded at review — not charged' : 'Included in booking' }}</td>
                        <td class="amount">{{ $item->availability_status === 'unavailable' ? '—' : $peso($item->subtotal) }}@if ((float) $item->promotion_discount_amount > 0)<br><span class="muted">Lucky −{{ $peso($item->promotion_discount_amount) }}</span>@endif</td></tr>
                @endforeach
            </tbody></table>
            <table class="prices"><tbody>
                <tr><td>Booking subtotal (all services in {{ $booking->booking_code }})</td><td class="amount">{{ $peso($booking->total_amount) }}</td></tr>
                @if ((float) $booking->discount_amount > 0)<tr><td>Passenger discounts</td><td class="amount">−{{ $peso($booking->discount_amount) }}</td></tr>@endif
                @if ((float) $booking->tax_amount > 0)<tr><td>Passenger surcharges</td><td class="amount">+{{ $peso($booking->tax_amount) }}</td></tr>@endif
                @if ((float) $booking->promotion_discount_amount > 0)<tr><td>Lucky discount</td><td class="amount">−{{ $peso($booking->promotion_discount_amount) }}</td></tr>@endif
                @if ((float) $booking->admin_discount_amount > 0)<tr><td>Admin discount</td><td class="amount">−{{ $peso($booking->admin_discount_amount) }}</td></tr>@endif
                @if ((float) $booking->admin_surcharge_amount > 0)<tr><td>Additional amount</td><td class="amount">+{{ $peso($booking->admin_surcharge_amount) }}</td></tr>@endif
                <tr class="total"><td>Booking total (PHP) · {{ $booking->payment_status === 'paid' ? 'paid in full' : ($booking->payment_status === 'partial' ? 'partially paid; a balance remains' : ($booking->payment_status === 'refunded' ? 'refunded' : 'not yet paid')) }}</td><td class="amount">{{ $peso($booking->net_amount) }}</td></tr>
            </tbody></table>
        @elseif (! empty($itinerary['pricing']['lines']))
            <h2>Estimated prices</h2>
            <p class="muted">Estimates in PHP as of {{ \Illuminate\Support\Carbon::parse($itinerary['pricing']['estimated_at'])->timezone('Asia/Manila')->format('M j, Y g:i A') }}. Current prices and availability are checked again at checkout and approval.</p>
            <table class="prices"><thead><tr><th>Choice</th><th>Details</th><th class="amount">Estimate</th></tr></thead><tbody>
                @foreach ($itinerary['pricing']['lines'] as $line)
                    <tr><td>{{ $line['name'] }}</td><td>{{ $line['detail'] }}</td><td class="amount">{{ $peso($line['subtotal']) }}</td></tr>
                @endforeach
                <tr class="total"><td colspan="2">Estimated total (PHP)</td><td class="amount">{{ $peso($itinerary['pricing']['total']) }}</td></tr>
            </tbody></table>
            <p class="muted">{{ $itinerary['pricing']['exclusions'] }}</p>
        @else
            <h2>Prices</h2>
            <p class="muted">Estimate unavailable: this saved version was created before prices were recorded with itineraries. Today’s prices are not shown as historical prices.</p>
        @endif
    </section>

    @foreach ($itinerary['days'] as $day)
        <section class="day {{ $loop->index > 0 && count($itinerary['days'][$loop->index - 1]['stops']) >= 2 ? 'new-page' : '' }}">
            <div class="day-heading"><h2>Day {{ $day['day'] }} · {{ $day['label'] }}</h2><div>Hotel: {{ $day['hotel']['name'] ?? 'No hotel base assigned' }} · {{ number_format($day['distance_km'], 2) }} km · {{ $day['travel_minutes'] }} min mapped travel, including return</div></div>
            @foreach ($day['hotel']['rooms'] ?? [] as $room)
                <p class="event">Room: {{ $room['quantity'] }} × {{ $room['name'] }} · {{ $room['pax'] }} guests · {{ $room['check_in'] }} to {{ $room['check_out'] }}@if (isset($itinerary['booked_plan_item_ids'])) · {{ in_array($room['plan_item_id'], $itinerary['booked_plan_item_ids'], true) ? 'Included in booking request' : 'Planning choice only' }}@endif</p>
            @endforeach
            @php
                $arrivalEvents = array_values(array_filter($day['events'], fn ($event) => empty($event['departure'])));
                $departureEvents = array_values(array_filter($day['events'], fn ($event) => ! empty($event['departure'])));
                $dayPlan = [];
                foreach ($arrivalEvents as $event) { $dayPlan[] = ['event' => true, 'data' => $event]; }
                foreach ($day['stops'] as $stop) { $dayPlan[] = ['event' => false, 'data' => $stop]; }
                foreach ($departureEvents as $event) { $dayPlan[] = ['event' => true, 'data' => $event]; }
            @endphp
            @foreach ($dayPlan as $i => $entry)
                @if ($entry['event'])
                    <p class="event">{{ $i + 1 }}. {{ $entry['data']['name'] }}</p>
                @else
                    @php($stop = $entry['data'])
                    <article class="stop"><h3>{{ $i + 1 }}. {{ $stop['start'] }}–{{ $stop['end'] }} · {{ $stop['name'] }}</h3>
                        <p class="muted">@if ($stop['travel']['distance_km'] !== null && $stop['travel']['duration_minutes'] !== null){{ number_format($stop['travel']['distance_km'], 2) }} km · {{ $stop['travel']['duration_minutes'] }} min {{ $itinerary['mode'] }} from {{ $stop['travel']['from'] }}@else Distance and travel time from {{ $stop['travel']['from'] }} are unavailable @endif · {{ $routeBasis($stop['travel']) }}</p>
                        @if (! empty($stop['travel_estimated']))<p class="warn">Location approximate: the exact meeting point is not set, so travel is measured from the destination centre. Confirm it with the operator.</p>@endif
                        @if (isset($itinerary['booked_plan_item_ids']))<p>{{ in_array($stop['plan_item_id'], $itinerary['booked_plan_item_ids'], true) ? 'Included in booking request; current availability and booking status apply.' : 'Planning choice only; not purchased in this booking.' }}</p>@endif
                        <p class="{{ $stop['weather']['provisional'] ? 'warn' : '' }}">{{ $stop['weather']['provisional'] ? 'Provisional forecast: ' : 'Weather: ' }}{{ $stop['weather']['description'] }}</p>
                        <div class="reason"><strong>Why this slot:</strong> {{ $stop['reason'] }}</div>
                    </article>
                @endif
            @endforeach
            @if (empty($day['stops']))<p>No visits are scheduled for this day. Keep it open for spontaneous activities, your own plans, or simply to rest.</p>@endif
        </section>
    @endforeach
    @if (! empty($itinerary['unscheduled']))<section class="report"><h2>Other picks to arrange yourself</h2><p class="muted">We could not fit these onto a specific day automatically, so they are not plotted on the map. You can still add them to your trip and arrange the timing yourself.</p>@foreach ($itinerary['unscheduled'] as $choice)<p><strong>{{ $choice['name'] }}</strong> · {{ $choice['reason'] }}</p>@endforeach</section>@endif
    <section class="report"><h2>Strategic report</h2>@foreach ($itinerary['report'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</section>
    <div class="footer">SunnyTrips · {{ $referenceCode ?? $reference }} · Generated {{ $itinerary['generated_at'] }} · Routing graph {{ $itinerary['graph_version'] ?? 'not recorded' }}<br>Road/path estimates do not include live traffic, boat fares or operator safety clearance. Straight lines on maps are guides, not routes. This document does not replace your booking confirmation.</div>
</body></html>
