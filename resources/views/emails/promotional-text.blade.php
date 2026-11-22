{{ $payload['headline'] }}

{{ $payload['greeting'] }}

@foreach($payload['paragraphs'] as $paragraph)
{{ $paragraph }}

@endforeach
{{ $payload['cta_label'] }}: {{ $payload['cta_url'] }}

Prices, availability and Lucky discounts are subject to the terms shown on SunnyTrips at the time you book.
You are receiving this because you opted in to deals and destination ideas from {{ $payload['business'] }}.
@if(!empty($payload['unsubscribe_url']))
Unsubscribe: {{ $payload['unsubscribe_url'] }}
@endif
