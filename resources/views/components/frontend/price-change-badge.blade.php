@props(['change'])

@unless(empty($change))
    @php($isUp = $change['dir'] === 'up')
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[10px] font-extrabold border '.($isUp ? 'bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-200 border-red-200 dark:border-red-800' : 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800')]) }}
        title="Was ₱{{ number_format((float) $change['old'], 2) }} on {{ $change['date'] }}"
        aria-label="Price {{ $isUp ? 'increased' : 'decreased' }} from ₱{{ number_format((float) $change['old'], 2) }} on {{ $change['date'] }}">
        <span class="material-symbols-outlined text-[13px] leading-none">{{ $isUp ? 'trending_up' : 'trending_down' }}</span>
        <span class="sr-only">{{ $isUp ? 'Price up' : 'Price down' }}</span>
    </span>
@endunless
