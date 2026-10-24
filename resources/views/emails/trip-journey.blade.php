<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f3f7f8;color:#183440;font-family:Arial,sans-serif">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:100%;background:white;border-top:4px solid #006b91"><tr><td style="padding:28px">
            <p style="color:#006b91;font-weight:bold;letter-spacing:2px">SUNNYTRIPS</p><h1 style="font-size:26px;line-height:1.25">{{ $payload['title'] }}</h1>
            <p>Hi {{ $payload['name'] }},</p><p style="line-height:1.7">{{ $payload['message'] }}</p>
            @if(!empty($payload['reference']))<p style="color:#5c707b">Booking {{ $payload['reference'] }}</p>@endif
            @if(!empty($payload['payment_notice']))<p style="background:#fff7e6;border-left:3px solid #b26b00;padding:10px 12px;line-height:1.6">{{ $payload['payment_notice'] }}</p>@endif
            @foreach($payload['schedule'] as $day)<div style="margin-top:18px;border-left:3px solid #006b91;padding-left:12px"><h2 style="font-size:16px">{{ $day['label'] }}</h2>@if($day['hotel'])<p>{{ $day['hotel'] }}</p>@endif @foreach($day['visits'] as $visit)<p style="font-size:14px">{{ $visit }}</p>@endforeach</div>@endforeach
            @if(empty($payload['schedule']))
                @foreach($payload['services'] ?? [] as $service)<div style="margin-top:14px;border-left:3px solid #006b91;padding-left:12px"><p style="margin:0;font-size:13px;color:#5c707b">{{ $service['label'] }}</p><p style="margin:4px 0 0;font-weight:bold">{{ $service['name'] }}</p>@if($service['detail'])<p style="margin:2px 0 0;font-size:14px">{{ $service['detail'] }}</p>@endif</div>@endforeach
            @endif
            @if(!empty($payload['undated']))
                <div style="margin-top:18px"><h2 style="font-size:15px">Date to be arranged</h2>@foreach($payload['undated'] as $name)<p style="margin:4px 0;font-size:14px">{{ $name }}</p>@endforeach</div>
            @endif
            @if(!empty($payload['url']))
                <p style="margin:28px 0"><a href="{{ $payload['url'] }}" style="background:#006b91;color:white;text-decoration:none;padding:13px 20px;border-radius:5px;font-weight:bold">{{ $payload['action'] }}</a></p>
            @endif
            @if(!empty($payload['support']))<p style="line-height:1.6">Questions about this booking? Contact {{ $payload['support'] }}. Quote your booking reference so we can help.</p>@endif
            <p style="font-size:12px;color:#5c707b;line-height:1.6">Your booking’s current status and item availability determine confirmed services. Saved weather forecasts and travel times are estimates; check updates before traveling.</p>
            @if(!empty($payload['preferences_url']))
                <p style="font-size:12px;color:#5c707b">{{ $payload['marketing'] ? 'You opted in to planning follow-ups and destination suggestions.' : 'This is a trip reminder based on your email preferences.' }} <a href="{{ $payload['preferences_url'] }}">Manage email preferences</a>.</p>
            @else
                <p style="font-size:12px;color:#5c707b">You are receiving this service message because this email address is the contact for a SunnyTrips booking.</p>
            @endif
        </td></tr></table>
    </td></tr></table>
</body></html>
