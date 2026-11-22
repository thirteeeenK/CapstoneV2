@php
    $tone = match ($campaign->status) {
        \App\Models\EmailCampaign::STATUS_ACTIVE, \App\Models\EmailCampaign::STATUS_SENT => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        \App\Models\EmailCampaign::STATUS_SCHEDULED, \App\Models\EmailCampaign::STATUS_SENDING => 'bg-sky-50 text-sky-700 border-sky-200',
        \App\Models\EmailCampaign::STATUS_CANCELLED => 'bg-rose-50 text-rose-700 border-rose-200',
        default => 'bg-slate-50 text-slate-600 border-slate-200',
    };
@endphp
<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold {{ $tone }}">{{ $campaign->statusLabel() }}</span>
