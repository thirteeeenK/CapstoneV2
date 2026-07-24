@props(['disabled' => false])

<input
    @disabled($disabled)
    {{ $attributes->merge([
        'class' => 'block w-full rounded-lg border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 shadow-none transition-colors duration-150 focus:border-ocean-400 focus:outline-none focus:ring-2 focus:ring-ocean-400/20 disabled:cursor-not-allowed disabled:bg-ink-100 disabled:text-ink-500'
    ]) }}
>
