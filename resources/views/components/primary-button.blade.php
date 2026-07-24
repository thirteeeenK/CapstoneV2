<button {{ $attributes->merge([
    'type'  => 'submit',
    'class' => 'inline-flex items-center justify-center gap-2 rounded-lg bg-ocean-500 px-5 py-3 text-sm font-semibold text-white tracking-wide transition-all duration-150 hover:bg-ocean-600 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1 active:bg-ocean-700 disabled:cursor-not-allowed disabled:opacity-60'
]) }}>
    {{ $slot }}
</button>
