<x-frontend.layout :title="'My Bookings — SunnyTrips'">
    @php
        $statusMeta = [
            'pending' => ['label' => 'Under Review', 'chip' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-900 dark:text-amber-100 border-amber-200 dark:border-amber-800', 'dot' => 'bg-amber-500', 'icon' => 'hourglass_top', 'tint' => 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 border-amber-100 dark:border-amber-800'],
            'approved' => ['label' => 'Awaiting Payment', 'chip' => 'bg-sky-100 dark:bg-sky-950/60 text-sky-900 dark:text-sky-100 border-sky-200 dark:border-sky-800', 'dot' => 'bg-sky-500', 'icon' => 'schedule_send', 'tint' => 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 border-sky-100 dark:border-sky-800'],
            'paid' => ['label' => 'Confirmed', 'chip' => 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-900 dark:text-emerald-100 border-emerald-200 dark:border-emerald-800', 'dot' => 'bg-emerald-500', 'icon' => 'task_alt', 'tint' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 border-emerald-100 dark:border-emerald-800'],
            'completed' => ['label' => 'Completed', 'chip' => 'bg-teal-100 dark:bg-teal-950/60 text-teal-900 dark:text-teal-100 border-teal-200 dark:border-teal-800', 'dot' => 'bg-teal-500', 'icon' => 'celebration', 'tint' => 'text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-950/60 border-teal-100 dark:border-teal-800'],
            'rejected' => ['label' => 'Declined', 'chip' => 'bg-rose-100 dark:bg-rose-950/60 text-rose-900 dark:text-rose-100 border-rose-200 dark:border-rose-800', 'dot' => 'bg-rose-500', 'icon' => 'cancel', 'tint' => 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 border-rose-100 dark:border-rose-800'],
            'cancelled' => ['label' => 'Cancelled', 'chip' => 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-700', 'dot' => 'bg-slate-400', 'icon' => 'block', 'tint' => 'text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700'],
            'expired' => ['label' => 'Expired', 'chip' => 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-700', 'dot' => 'bg-slate-400', 'icon' => 'schedule', 'tint' => 'text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700'],
            'cancellation_requested' => ['label' => 'Cancellation Pending', 'chip' => 'bg-orange-100 dark:bg-orange-950/60 text-orange-900 dark:text-orange-100 border-orange-200 dark:border-orange-800', 'dot' => 'bg-orange-500', 'icon' => 'hourglass_top', 'tint' => 'text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-950/60 border-orange-100 dark:border-orange-800'],
            'cancellation_denied' => ['label' => 'Cancellation Denied', 'chip' => 'bg-rose-100 dark:bg-rose-950/60 text-rose-900 dark:text-rose-100 border-rose-200 dark:border-rose-800', 'dot' => 'bg-rose-500', 'icon' => 'block', 'tint' => 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 border-rose-100 dark:border-rose-800'],
        ];

        $filters = [
            ['key' => 'all', 'label' => 'All'],
            ['key' => 'active', 'label' => 'In Progress'],
            ['key' => 'paid', 'label' => 'Confirmed'],
            ['key' => 'completed', 'label' => 'Completed'],
            ['key' => 'reviewable', 'label' => 'To Review'],
            ['key' => 'reviewed', 'label' => 'Reviewed'],
            ['key' => 'closed', 'label' => 'Closed'],
        ];

        $activeKeys = ['pending', 'approved', 'cancellation_requested'];
        $closedKeys = ['rejected', 'cancelled', 'expired', 'cancellation_denied'];
    @endphp

    <div class="min-h-screen bg-sand-50/70 dark:bg-slate-800/60 font-body">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

            {{-- Masthead --}}
            <div class="flex items-center justify-between mb-5">
                <p class="font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400 dark:text-slate-500">
                    SunnyTrips · Traveler Portal
                </p>
                <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400 dark:text-slate-500">
                    {{ now()->format('M j, Y') }}
                </p>
            </div>

            <header class="mb-8 animate-fade-up">
                <h1 class="font-headline text-3xl sm:text-4xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                    My Booking Journals
                </h1>
                <p class="font-body text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed mt-2 max-w-xl">
                    Every request, approval, and confirmation lives here — open a journal to track status,
                    complete payment, or rebook.
                </p>
            </header>

            @if ($bookings->isEmpty())
                {{-- Empty state --}}
                <section class="bg-white dark:bg-slate-900 rounded-[2rem] border border-sand-200/80 dark:border-slate-700/80 shadow-sm px-6 sm:px-10 py-16 text-center animate-fade-up">
                    <div class="w-20 h-20 rounded-3xl bg-sand-100 dark:bg-slate-800 border border-sand-200 dark:border-slate-700 flex items-center justify-center mx-auto mb-6">
                        <span class="material-symbols-outlined text-4xl text-slate-400 dark:text-slate-500">auto_stories</span>
                    </div>
                    <h2 class="font-headline text-lg font-bold text-slate-900 dark:text-slate-100">No journals yet</h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-md mx-auto leading-relaxed">
                        Your booking history will appear here once you submit your first itinerary.
                        Everything is verified by our crew before any payment is taken.
                    </p>
                    <div class="mt-7 flex items-center justify-center gap-3 flex-wrap">
                        <a href="{{ route('dashboard') }}" class="px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition flex items-center gap-1.5 shadow-lg shadow-slate-900/20 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">explore</span>
                            <span>Discover Your Next Escape</span>
                        </a>
                        <a href="{{ route('rooms.index') }}" class="px-5 py-3 rounded-2xl bg-white dark:bg-slate-900 border border-sand-200 dark:border-slate-700 text-slate-700 font-bold text-xs hover:bg-sand-50 dark:hover:bg-slate-700 transition flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">king_bed</span>
                            <span>Browse Rooms</span>
                        </a>
                    </div>
                </section>
            @else
                {{-- Filter chips --}}
                <div x-data="{ filter: 'all' }" class="animate-fade-up">
                    <div class="flex items-center gap-2 flex-wrap mb-6">
                        @foreach ($filters as $f)
                            <button @click="filter = '{{ $f['key'] }}'"
                                    :class="filter === '{{ $f['key'] }}' ? 'bg-slate-900 text-white border-slate-900 shadow-md' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-sand-200 dark:border-slate-700 hover:bg-sand-100 dark:hover:bg-slate-700'"
                                    class="px-4 py-2 rounded-full border text-[11px] font-bold transition cursor-pointer">
                                {{ $f['label'] }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Booking cards --}}
                    <div class="space-y-4">
                        @foreach ($bookings as $bookingList)
                            @php
                                $bookingList->loadMissing('reviews');
                                $meta = $statusMeta[$bookingList->status] ?? $statusMeta['pending'];
                                $firstItem = $bookingList->items->first();
                                $itemCount = $bookingList->items->count();
                                $checkIn = $bookingList->items->pluck('check_in_date')->filter()->min();
                                $bucket = in_array($bookingList->status, $activeKeys, true) ? 'active'
                                    : ($bookingList->status === 'paid' ? 'paid'
                                    : ($bookingList->status === 'completed' ? 'completed' : 'closed'));

                                $reviewBucket = null;
                                if ($bookingList->status === 'completed') {
                                    $reviewableTypes = ['room', 'activity', 'package'];
                                    $reviewableItems = $bookingList->items->filter(fn($it) => in_array($it->item_type, $reviewableTypes, true));
                                    $hasAnyUnreviewed = $reviewableItems->count() > 0
                                        && $reviewableItems->some(fn($it) => !$bookingList->reviews->contains('booking_item_id', $it->id));
                                    $reviewBucket = $hasAnyUnreviewed ? 'reviewable' : 'reviewed';
                                    $bucket = 'completed';
                                }
                            @endphp

                            <a href="{{ route('booking.show', $bookingList->booking_code) }}"
                               x-show="filter === 'all' || filter === '{{ $bucket }}' || filter === '{{ $reviewBucket }}'"
                               x-transition:enter="transition ease-out duration-200"
                               x-transition:enter-start="opacity-0 translate-y-2"
                               x-transition:enter-end="opacity-100 translate-y-0"
                               class="group block bg-white dark:bg-slate-900 rounded-3xl border border-sand-200 dark:border-slate-700/80 shadow-sm hover:shadow-lg hover:border-sand-300 transition-all duration-200 p-5 sm:p-6 flex items-center gap-4 sm:gap-6 cursor-pointer">

                                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl {{ $meta['tint'] }} border flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                    <span class="material-symbols-outlined text-2xl">{{ $meta['icon'] }}</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2.5 flex-wrap">
                                        <h2 class="font-headline text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 truncate">
                                            {{ $bookingList->booking_code }}
                                        </h2>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {{ $meta['chip'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $meta['dot'] }}"></span>
                                            {{ $meta['label'] }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 truncate">
                                        {{ $firstItem ? $firstItem->item_title : 'Itinerary' }}
                                        @if ($itemCount > 1)
                                            <span class="text-slate-400">+ {{ $itemCount - 1 }} more {{ Str::plural('item', $itemCount - 1) }}</span>
                                        @endif
                                        @if ($checkIn)
                                            <span class="text-slate-400">·</span>
                                            <span class="text-slate-500">{{ date('M j, Y', strtotime($checkIn)) }}</span>
                                        @endif
                                    </p>
                                    <p class="font-label text-[9px] uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500 font-bold mt-1.5">
                                        Journaled {{ $bookingList->created_at->format('M j, Y') }}
                                    </p>
                                </div>

                                <div class="text-right shrink-0">
                                    @if (in_array($bookingList->status, ['pending', 'approved', 'paid'], true))
                                        <p class="font-headline text-base sm:text-lg font-black text-slate-900 dark:text-slate-100">
                                            ₱{{ number_format($bookingList->net_amount, 2) }}
                                        </p>
                                        <p class="font-label text-[9px] uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500 font-bold mt-0.5">
                                            {{ $bookingList->status === 'pending' ? 'Estimated' : ($bookingList->status === 'approved' ? 'Due' : 'Paid') }}
                                        </p>
                                    @endif
                                    <span class="material-symbols-outlined text-slate-300 dark:text-slate-600 group-hover:text-slate-600 dark:group-hover:text-slate-300 group-hover:translate-x-0.5 transition-all text-xl mt-1 inline-block">
                                        arrow_forward
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Colophon --}}
            <p class="text-center font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400 dark:text-slate-500 pt-8">
                SunnyTrips · Curated island escapes, verified before payment
            </p>
        </div>
    </div>
</x-frontend.layout>
