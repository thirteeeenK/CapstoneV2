@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'mb-5 flex items-center gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700']) }}>
        <span class="material-symbols-outlined text-emerald-500 shrink-0" style="font-size:18px">info</span>
        <span>{{ $status }}</span>
    </div>
@endif
