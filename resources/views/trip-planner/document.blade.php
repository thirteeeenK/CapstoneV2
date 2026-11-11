<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $itinerary['title'] }} — SunnyTrips</title>
    @include('trip-planner.pdf._styles')
</head>
@php
    $webView = $webView ?? request()->boolean('print');
    $booked = isset($booking) && $booking;
    $code = $referenceCode ?? $reference;
@endphp
<body class="{{ $webView ? 'web' : '' }}">
    @if ($webView)<div class="print-controls"><button type="button" onclick="window.print()">Print itinerary</button></div>@endif
    @unless ($webView)
        <div class="page-footer"><table><tr><td>SunnyTrips · {{ $code }} · {{ $itinerary['title'] }}</td></tr></table></div>
    @endunless
    <div class="sheet">
        @include('trip-planner.pdf._masthead', ['documentTitle' => 'Trip Itinerary', 'documentMeta' => 'Generated '.\Illuminate\Support\Carbon::parse($itinerary['generated_at'])->timezone('Asia/Manila')->format('M j, Y g:i A')])

        <table class="ref-panel"><tr>
            <td>
                <div class="ref-label">{{ $referenceLabel ?? 'Reference' }}</div>
                <div class="ref-code">{{ $code }}</div>
                <div class="muted">{{ $booked ? 'Booking status: '.str_replace('_', ' ', $booking->status).' · Payment: '.str_replace('_', ' ', $booking->payment_status) : 'Checked schedule · saved plan version, not a reservation' }}</div>
            </td>
            <td class="ref-side">
                <div class="strong" style="font-size: 12px;">{{ $itinerary['title'] }}</div>
                <div>{{ $itinerary['destination'] }}</div>
                <div class="muted">{{ \Illuminate\Support\Carbon::parse($itinerary['start_date'])->format('M j') }} – {{ \Illuminate\Support\Carbon::parse($itinerary['end_date'])->format('M j, Y') }} · {{ $itinerary['pax'] }} travelers</div>
            </td>
        </tr></table>
        <p class="small muted" style="margin-top: 5px;">{{ $reference }} · {{ ucfirst($itinerary['mode']) }} routes · All times in Asia/Manila</p>

        <p class="section-note" style="margin-top: 10px;">A suggested travel plan based on your selected hotels, rooms and activities, mapped routes and the available forecast. Feel free to follow it as it is or adjust it to your own pace. Confirm meeting points, schedules and operating conditions with providers before departure.</p>

        @include('trip-planner.pdf._pricing')
        @include('trip-planner.pdf._itinerary')

        <div class="doc-footer">SunnyTrips · {{ $code }} · Generated {{ $itinerary['generated_at'] }} · Routing graph {{ $itinerary['graph_version'] ?? 'not recorded' }}<br>Road/path estimates do not include live traffic, boat fares or operator safety clearance. Straight lines on maps are guides, not routes. This document does not replace your booking confirmation.</div>
    </div>
</body>
</html>
