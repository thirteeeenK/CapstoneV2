{{--
    Brand masthead for SunnyTrips documents. Expects $documentTitle and $documentMeta.
    The mark is inline SVG: dompdf draws it as vectors, whereas PNGs need the GD extension.
--}}
@php
    $rays = collect(range(0, 7))->map(function ($i) {
        $angle = deg2rad($i * 45);
        return sprintf('<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f"/>', 20 + cos($angle) * 12.5, 20 + sin($angle) * 12.5, 20 + cos($angle) * 18, 20 + sin($angle) * 18);
    })->join('');
    $mark = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40">'
        .'<g stroke="#f59e0b" stroke-width="3" stroke-linecap="round">'.$rays.'</g>'
        .'<circle cx="20" cy="20" r="9.5" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5"/>'
        .'<path d="M4 31 Q12 27 20 31 T36 31" fill="none" stroke="#0a78a8" stroke-width="2.6" stroke-linecap="round"/></svg>';
    $logo = 'data:image/svg+xml;base64,'.base64_encode($mark);
@endphp
<table class="masthead">
    <tr>
        <td style="width: 42px;"><img class="logo" src="{{ $logo }}" alt=""></td>
        <td><div class="wordmark">SunnyTrips</div><div class="tagline">Philippine travel, planned with care</div></td>
        <td><div class="doc-title">{{ $documentTitle }}</div><div class="doc-meta">{{ $documentMeta }}</div></td>
    </tr>
</table>
<div class="rule"></div>
