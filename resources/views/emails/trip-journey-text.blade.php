SUNNYTRIPS

{{ $payload['title'] }}

Hi {{ $payload['name'] }},
{{ $payload['message'] }}
@if(!empty($payload['reference']))Booking {{ $payload['reference'] }}@endif

@foreach($payload['schedule'] as $day)
{{ $day['label'] }} · {{ $day['hotel'] }}
@foreach($day['visits'] as $visit){{ $visit }}
@endforeach
@endforeach
{{ $payload['action'] }}: {{ $payload['url'] }}

Current booking status and availability apply. Recheck forecasts and travel details before departure.
Manage email preferences: {{ $payload['preferences_url'] }}
