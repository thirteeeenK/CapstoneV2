<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $payload['subject'] }}</title></head>
<body style="margin:0;background:#f3f7f8;color:#183440;font-family:Arial,sans-serif">
    @if(!empty($payload['preheader']))<div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $payload['preheader'] }}</div>@endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:100%;background:white;border-top:4px solid #006b91"><tr><td style="padding:28px">
            <p style="margin:0 0 12px;color:#006b91;font-weight:bold;letter-spacing:2px">SUNNYTRIPS</p>
            @if(!empty($payload['image_url']))
                <img src="{{ $payload['image_url'] }}" alt="" width="544" style="display:block;width:100%;max-width:544px;height:auto;border-radius:6px;margin:0 0 20px">
            @endif
            <h1 style="margin:0 0 16px;font-size:26px;line-height:1.25">{{ $payload['headline'] }}</h1>
            <p style="line-height:1.7">{{ $payload['greeting'] }}</p>
            @foreach($payload['paragraphs'] as $paragraph)
                <p style="line-height:1.7">{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
            <p style="margin:28px 0"><a href="{{ $payload['cta_url'] }}" style="background:#006b91;color:white;text-decoration:none;padding:13px 20px;border-radius:5px;font-weight:bold">{{ $payload['cta_label'] }}</a></p>
            <p style="font-size:12px;color:#5c707b;line-height:1.6">Prices, availability Lucky discounts and promo codes are subject to the terms shown on SunnyTrips at the time you book.</p>
            <p style="font-size:12px;color:#5c707b;line-height:1.6">
                You are receiving this because you opted in to deals and destination ideas from {{ $payload['business'] }}.
                @if(!empty($payload['unsubscribe_url']))
                    <a href="{{ $payload['unsubscribe_url'] }}" style="color:#5c707b">Unsubscribe</a> at any time; trip reminders for your bookings are not affected.
                @endif
            </p>
        </td></tr></table>
    </td></tr></table>
</body></html>
