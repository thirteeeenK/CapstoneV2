<div class="flex flex-wrap gap-2">
    <a href="{{ route('booking.itinerary', [$booking->booking_code, $version]) }}"
        class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-ocean-700 px-3.5 py-2 font-body text-xs font-bold text-white transition hover:bg-ocean-800">
        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">download</span>
        Download itinerary PDF
    </a>
    <a href="{{ route('booking.itinerary', [$booking->booking_code, $version, 'print' => 1]) }}" target="_blank" rel="noopener"
        class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-sand-300 dark:border-slate-600 px-3.5 py-2 font-body text-xs font-bold text-slate-700 dark:text-slate-200 transition hover:bg-sand-100 dark:hover:bg-slate-800">
        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">print</span>
        Print itinerary
    </a>
</div>
