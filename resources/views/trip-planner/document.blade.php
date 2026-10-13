<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $itinerary['title'] }} — SunnyTrips</title>
    <style>
        @page { margin: 20mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #183440; font-size: 10px; line-height: 1.6; margin: 0; }
        .brand { font-size: 13px; font-weight: bold; color: #006b91; letter-spacing: 2px; }
        h1 { font-size: 25px; line-height: 1.25; margin: 12px 0; } h2 { font-size: 16px; margin: 0 0 5px; } h3 { font-size: 12px; margin: 0; }
        .muted { color: #5c707b; } .masthead { border-bottom: 3px solid #006b91; padding-bottom: 14px; margin-bottom: 18px; }
        .day { margin-top: 20px; page-break-inside: avoid; } .new-page { page-break-before: always; } .day-heading { background: #edf6f8; border-left: 3px solid #006b91; padding: 9px 12px; page-break-after: avoid; }
        .stop { padding: 12px 0; border-bottom: 1px solid #d7e3e6; page-break-inside: avoid; }
        .reason { margin-top: 5px; } .event { margin: 8px 0; } .report { margin-top: 24px; } .report h2 { page-break-after: avoid; }
        .footer { margin-top: 20px; font-size: 8px; color: #5c707b; border-top: 1px solid #d7e3e6; padding-top: 8px; }
        .print-controls { margin: 14px 0; } button { padding: 10px 16px; background: #006b91; border: 0; border-radius: 5px; color: white; cursor: pointer; }
        @media screen { body { max-width: 800px; padding: 25px; margin: auto; font-size: 13px; } }
        @media print { .print-controls { display: none; } }
    </style>
</head>
<body>
    @if (request()->boolean('print'))<div class="print-controls"><button onclick="window.print()">Print itinerary</button></div>@endif
    <div class="masthead"><div class="brand">SUNNYTRIPS</div><h1>{{ $itinerary['title'] }}</h1><p>{{ $itinerary['destination'] }} · {{ $itinerary['start_date'] }} to {{ $itinerary['end_date'] }} · {{ $itinerary['pax'] }} travelers</p><p class="muted">{{ $reference }} · {{ ucfirst($itinerary['mode']) }} routes · All times in Asia/Manila</p></div>
    <p class="muted">A suggested travel plan based on your selected hotels, rooms and activities, mapped routes and the available forecast. Feel free to follow it as it is or adjust it to your own pace. Confirm meeting points, schedules and operating conditions with providers before departure.</p>
    @foreach ($itinerary['days'] as $day)
        <section class="day {{ $loop->index > 0 && count($itinerary['days'][$loop->index - 1]['stops']) >= 2 ? 'new-page' : '' }}">
            <div class="day-heading"><h2>Day {{ $day['day'] }} · {{ $day['label'] }}</h2><div>Hotel: {{ $day['hotel']['name'] ?? 'No hotel base assigned' }} · {{ number_format($day['distance_km'], 2) }} km · {{ $day['travel_minutes'] }} min travel, including return</div></div>
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
                        <p class="muted">{{ number_format($stop['travel']['distance_km'], 2) }} km · {{ $stop['travel']['duration_minutes'] }} min {{ $itinerary['mode'] }} from {{ $stop['travel']['from'] }}</p>
                        @if (isset($itinerary['booked_plan_item_ids']))<p>{{ in_array($stop['plan_item_id'], $itinerary['booked_plan_item_ids'], true) ? 'Included in booking request; current availability and booking status apply.' : 'Planning choice only; not purchased in this booking.' }}</p>@endif
                        <p>{{ $stop['weather']['provisional'] ? 'Provisional forecast: ' : 'Weather: ' }}{{ $stop['weather']['description'] }}</p>
                        <div class="reason"><strong>Why this slot:</strong> {{ $stop['reason'] }}</div>
                    </article>
                @endif
            @endforeach
            @if (empty($day['stops']))<p>No visits are scheduled for this day. Keep it open for spontaneous activities, your own plans, or simply to rest.</p>@endif
        </section>
    @endforeach
    @if (! empty($itinerary['unscheduled']))<section class="report"><h2>Other picks to arrange yourself</h2><p class="muted">We could not fit these onto a specific day automatically, so they are not plotted on the map. You can still add them to your trip and arrange the timing yourself.</p>@foreach ($itinerary['unscheduled'] as $choice)<p><strong>{{ $choice['name'] }}</strong> · {{ $choice['reason'] }}</p>@endforeach</section>@endif
    <section class="report"><h2>Strategic report</h2>@foreach ($itinerary['report'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</section>
    <div class="footer">SunnyTrips · Generated {{ $itinerary['generated_at'] }} · Routing graph {{ $itinerary['graph_version'] ?? 'not recorded' }}<br>Road/path estimates do not include live traffic, boat fares or operator safety clearance. This document does not replace your booking confirmation.</div>
</body></html>
