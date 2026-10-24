<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $itinerary['title'] }} — SunnyTrips draft</title>
<style>body { font-family: 'DejaVu Sans', sans-serif; color: #183440; font-size: 12px; line-height: 1.6; padding: 24px; } h1 { font-size: 24px; } section { margin-top: 20px; } @media print { button { display: none; } }</style></head>
<body>
@if(request()->boolean('print'))<button type="button" onclick="window.print()">Print draft</button>@endif
<h1>{{ $itinerary['title'] }}</h1>
<div style="border: 2px solid #183440; padding: 10px 14px; margin: 12px 0;"><span style="display:block;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#5c707b">{{ $referenceLabel ?? 'Plan reference (not a booking)' }}</span><span style="display:block;font-size:20px;font-weight:bold;letter-spacing:2px">{{ $referenceCode ?? $reference }}</span><span style="color:#8a4b00">Draft — routes, timings and weather are not checked yet. Build a checked schedule in the Trip Planner before relying on it.</span></div>
<p>Itinerary (From Chatbot) · Draft · {{ $reference }}</p>
<p>{{ $itinerary['destination'] }} · {{ $itinerary['duration_days'] }} days · {{ $itinerary['pax'] ?? 'Unspecified' }} travelers</p>
<p>{{ $itinerary['start_date'] ?? 'Dates to be decided' }} @if($itinerary['end_date']) → {{ $itinerary['end_date'] }} @endif</p>
<p>{{ $itinerary['notice'] }}</p><p>{{ $itinerary['weather_notice'] }}</p>
@if(!empty($itinerary['accommodation']))
@php($stay = $itinerary['accommodation'])
<section><h2>Your stay</h2>
<p>{{ $stay['hotel_name'] }} — {{ $stay['room_name'] }}</p>
<p>Check-in: {{ $stay['check_in_date'] ?? 'Dates pending' }} · Check-out: {{ $stay['check_out_date'] ?? 'Dates pending' }} · {{ $stay['nights'] }} nights</p>
<p>Capacity: {{ $stay['capacity'] ?? 'Unspecified' }} guests per room · {{ $stay['quantity'] }} {{ $stay['quantity'] === 1 ? 'room' : 'rooms' }}</p>
<p>{{ $stay['pax'] ?? $itinerary['pax'] ?? 'Unspecified' }} travelers for this stay</p>
<p>@if($stay['nightly_rate'] !== null) ₱{{ number_format($stay['nightly_rate'], 2) }} per room/night · Estimated stay: ₱{{ number_format($stay['total'], 2) }} @else Price estimate needs updating @endif</p>
<p>{{ $stay['availability_status'] === 'unavailable' ? 'This room is unavailable. Choose an alternative.' : 'Confirm prices and availability before booking.' }}</p>
</section>
@endif
@if(!empty($itinerary['accommodation_notice']))<p>{{ $itinerary['accommodation_notice'] }}</p>@endif
@foreach($itinerary['days'] as $day)
<section><h2>Day {{ $day['day'] }} @if(!empty($day['date'])) · {{ $day['date'] }} @endif</h2>
@forelse($itinerary['choices'] as $choice)
@if($choice['day'] === $day['day'])<p>{{ $choice['name'] }}@if(empty($choice['available'])) — availability needs review @endif</p>@endif
@empty<p>Open day.</p>@endforelse
</section>
@endforeach
<section><h2>All saved choices</h2>@foreach($itinerary['choices'] as $choice)<p>{{ $choice['name'] }} · {{ $choice['day'] ? 'Day '.$choice['day'] : 'Unassigned' }} @if(empty($choice['available'])) · Unavailable for scheduling @endif</p>@endforeach</section>
<p>Saving a plan does not reserve rooms or activities. Prices and availability must be checked before booking.</p>
</body></html>
