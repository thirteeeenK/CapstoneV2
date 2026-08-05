@php
    $retryAfter = null;
    if (isset($exception) && method_exists($exception, 'getHeaders')) {
        $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
    }
@endphp

<x-error-page
    code="429"
    subtitle="Too Many Requests"
    title="Easy, sailor — slow down"
    message="You're sending requests faster than the tide can carry them. Give it a few seconds, then try again."
    icon="timer"
    theme="ocean"
    :retry-after="$retryAfter ? (int) $retryAfter : null"
    :actions="[['countdown'], ['home']]"
/>
