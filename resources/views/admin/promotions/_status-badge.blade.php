{{-- A campaign's current state: active, scheduled, paused, expired or limit reached. --}}
@php
    [$tone, $icon] = match ($promotion->status()) {
        \App\Models\Promotion::STATUS_ACTIVE => ['border-emerald-200 bg-emerald-50 text-emerald-700', 'play_circle'],
        \App\Models\Promotion::STATUS_SCHEDULED => ['border-sky-200 bg-sky-50 text-sky-700', 'schedule'],
        \App\Models\Promotion::STATUS_EXPIRED => ['border-rose-200 bg-rose-50 text-rose-700', 'event_busy'],
        \App\Models\Promotion::STATUS_LIMIT_REACHED => ['border-amber-200 bg-amber-50 text-amber-700', 'block'],
        default => ['border-slate-200 bg-slate-50 text-slate-500', 'pause_circle'],
    };
@endphp
<span class="inline-flex h-8 items-center gap-1 whitespace-nowrap rounded-lg border px-2 text-[10px] font-extrabold uppercase tracking-wider {{ $tone }}">
    <span class="material-symbols-outlined text-[14px] leading-none" aria-hidden="true">{{ $icon }}</span>
    {{ $promotion->statusLabel() }}
</span>
