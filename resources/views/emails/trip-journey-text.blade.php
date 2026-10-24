SUNNYTRIPS

{{ $payload['title'] }}

Hi {{ $payload['name'] }},
{{ $payload['message'] }}
@if(!empty($payload['reference']))Booking {{ $payload['reference'] }}@endif

@if(!empty($payload['payment_notice'])){{ $payload['payment_notice'] }}

@endif
@foreach($payload['schedule'] as $day)
{{ $day['label'] }} · {{ $day['hotel'] }}
@foreach($day['visits'] as $visit){{ $visit }}
@endforeach
@endforeach
@if(empty($payload['schedule']))
@foreach($payload['services'] ?? [] as $service)
{{ $service['label'] }} · {{ $service['name'] }}{{ $service['detail'] ? ' · '.$service['detail'] : '' }}
@endforeach
@endif
@if(!empty($payload['undated']))
Date to be arranged:
@foreach($payload['undated'] as $name){{ $name }}
@endforeach
@endif
@if(!empty($payload['url'])){{ $payload['action'] }}: {{ $payload['url'] }}@endif

@if(!empty($payload['support']))Questions about this booking? Contact {{ $payload['support'] }}. Quote your booking reference so we can help.
@endif
Current booking status and availability apply. Recheck forecasts and travel details before departure.
@if(!empty($payload['preferences_url']))Manage email preferences: {{ $payload['preferences_url'] }}
@else
You are receiving this service message because this email address is the contact for a SunnyTrips booking.
@endif
