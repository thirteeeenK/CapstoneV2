<x-frontend.layout :title="'Journal ' . $booking->booking_code . ' — SunnyTrips'">
    @php
        $status = $booking->status;
        $paidOrDone = in_array($status, ['paid', 'completed'], true);

        $themes = [
            'pending' => [
                'label' => 'Request Under Review',
                'icon' => 'hourglass_top',
                'vars' => '--t-sky1:#fdf6e3; --t-sky2:#fde68a; --t-sky3:#fdba74; --t-sun:#f59e0b; --t-sea1:#0e7490; --t-sea2:#0f5e6b; --t-isle:#134e4a; --t-isle-far:#115e59; --t-palm:#14532d; --t-birds:#92400e;',
                'chip' => 'bg-amber-100 text-amber-900 border-amber-200',
                'dot' => 'bg-amber-500',
                'text' => 'text-amber-600',
            ],
            'approved' => [
                'label' => 'Approved · Awaiting Payment',
                'icon' => 'schedule_send',
                'vars' => '--t-sky1:#eff6ff; --t-sky2:#93c5fd; --t-sky3:#38bdf8; --t-sun:#fbbf24; --t-sea1:#155e75; --t-sea2:#0e4f6b; --t-isle:#064e3b; --t-isle-far:#065f46; --t-palm:#166534; --t-birds:#1e40af;',
                'chip' => 'bg-sky-100 text-sky-900 border-sky-200',
                'dot' => 'bg-sky-500',
                'text' => 'text-sky-600',
            ],
            'paid' => [
                'label' => 'Booking Confirmed',
                'icon' => 'task_alt',
                'vars' => '--t-sky1:#f0fdf4; --t-sky2:#a7f3d0; --t-sky3:#6ee7b7; --t-sun:#fbbf24; --t-sea1:#0f766e; --t-sea2:#115e59; --t-isle:#134e4a; --t-isle-far:#115e59; --t-palm:#166534; --t-birds:#065f46;',
                'chip' => 'bg-emerald-100 text-emerald-900 border-emerald-200',
                'dot' => 'bg-emerald-500',
                'text' => 'text-emerald-600',
            ],
            'completed' => [
                'label' => 'Trip Completed',
                'icon' => 'celebration',
                'vars' => '--t-sky1:#f0fdfa; --t-sky2:#5eead4; --t-sky3:#2dd4bf; --t-sun:#fb923c; --t-sea1:#0f766e; --t-sea2:#115e59; --t-isle:#134e4a; --t-isle-far:#115e59; --t-palm:#166534; --t-birds:#0f766e;',
                'chip' => 'bg-teal-100 text-teal-900 border-teal-200',
                'dot' => 'bg-teal-500',
                'text' => 'text-teal-600',
            ],
            'rejected' => [
                'label' => 'Request Declined',
                'icon' => 'cancel',
                'vars' => '--t-sky1:#fafaf9; --t-sky2:#e7e5e4; --t-sky3:#d6d3d1; --t-sun:#fca5a5; --t-sea1:#78716c; --t-sea2:#57534e; --t-isle:#44403c; --t-isle-far:#57534e; --t-palm:#44403c; --t-birds:#57534e;',
                'chip' => 'bg-rose-100 text-rose-900 border-rose-200',
                'dot' => 'bg-rose-500',
                'text' => 'text-rose-600',
            ],
            'cancelled' => [
                'label' => 'Booking Cancelled',
                'icon' => 'block',
                'vars' => '--t-sky1:#fafaf9; --t-sky2:#e7e5e4; --t-sky3:#d6d3d1; --t-sun:#d6d3d1; --t-sea1:#78716c; --t-sea2:#57534e; --t-isle:#44403c; --t-isle-far:#57534e; --t-palm:#44403c; --t-birds:#57534e;',
                'chip' => 'bg-slate-100 text-slate-800 border-slate-200',
                'dot' => 'bg-slate-400',
                'text' => 'text-slate-500',
            ],
            'expired' => [
                'label' => 'Booking Expired',
                'icon' => 'schedule',
                'vars' => '--t-sky1:#fafaf9; --t-sky2:#e7e5e4; --t-sky3:#d6d3d1; --t-sun:#d6d3d1; --t-sea1:#78716c; --t-sea2:#57534e; --t-isle:#44403c; --t-isle-far:#57534e; --t-palm:#44403c; --t-birds:#57534e;',
                'chip' => 'bg-slate-100 text-slate-800 border-slate-200',
                'dot' => 'bg-slate-400',
                'text' => 'text-slate-500',
            ],
        ];
        $t = $themes[$status];
    @endphp

    <div class="min-h-screen bg-sand-50/70 font-body">

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

            {{-- Journal masthead --}}
            <div class="flex items-center justify-between mb-5">
                <p class="font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400">
                    SunnyTrips · Traveler Journal
                </p>
                <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400">
                    {{ now()->format('M j, Y') }}
                </p>
            </div>

            @if (session('success'))
                <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2 animate-fade-in">
                    <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2 animate-fade-in">
                    <span class="material-symbols-outlined text-[20px] text-rose-600">error</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- ============ HERO: THE ISLAND SCENE ============ --}}
            <section class="relative overflow-hidden rounded-[2rem] shadow-xl shadow-slate-900/5 border border-sand-200/80 animate-fade-up"
                     style="{{ $t['vars'] }}">

                {{-- Dusk seascape illustration --}}
                <svg viewBox="0 0 1200 340" preserveAspectRatio="xMidYMax slice" class="absolute inset-0 w-full h-full">
                    <defs>
                        <linearGradient id="st-sky" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--t-sky1)"/>
                            <stop offset="70%" stop-color="var(--t-sky2)"/>
                            <stop offset="100%" stop-color="var(--t-sky3)"/>
                        </linearGradient>
                        <linearGradient id="st-sea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--t-sea1)"/>
                            <stop offset="100%" stop-color="var(--t-sea2)"/>
                        </linearGradient>
                    </defs>

                    <rect width="1200" height="340" fill="url(#st-sky)"/>

                    <circle cx="955" cy="128" r="58" fill="var(--t-sun)" opacity="0.92"/>
                    <circle cx="955" cy="128" r="78" fill="var(--t-sun)" opacity="0.18"/>

                    <path d="M 178 340 Q 226 168 300 340 Z" fill="var(--t-isle-far)" opacity="0.45"/>
                    <path d="M 168 340 Q 216 158 296 340 Z" fill="var(--t-isle)"/>

                    <path d="M 217 340 Q 209 262 203 206" stroke="#3f2d1d" stroke-width="8" fill="none" stroke-linecap="round"/>
                    <ellipse cx="204" cy="197" rx="36" ry="13" fill="var(--t-palm)" transform="rotate(-16 204 197)"/>
                    <ellipse cx="174" cy="209" rx="31" ry="11" fill="var(--t-palm)" transform="rotate(-48 174 209)"/>
                    <ellipse cx="234" cy="209" rx="31" ry="11" fill="var(--t-palm)" transform="rotate(28 234 209)"/>
                    <path d="M 203 206 q 10 12 22 14" stroke="#3f2d1d" stroke-width="4" fill="none" stroke-linecap="round"/>

                    <path d="M 0 250 Q 160 222 320 250 T 640 250 T 960 250 T 1280 250 V 340 H 0 Z" fill="url(#st-sea)"/>
                    <path d="M 0 276 Q 200 252 400 276 T 800 276 T 1200 276 V 340 H 0 Z" fill="var(--t-sea2)" opacity="0.5"/>

                    <path d="M 590 96 q 11 -13 22 0 q 11 -13 22 0" stroke="var(--t-birds)" stroke-width="3.5" fill="none" stroke-linecap="round" opacity="0.7"/>
                    <path d="M 668 128 q 9 -11 18 0 q 9 -11 18 0" stroke="var(--t-birds)" stroke-width="2.5" fill="none" stroke-linecap="round" opacity="0.6"/>
                </svg>

                {{-- Vignette for text legibility --}}
                <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-black/25 pointer-events-none"></div>

                {{-- Hero content --}}
                <div class="relative z-10 px-6 sm:px-10 pt-16 sm:pt-20 pb-9 text-center text-slate-900">
                    <p class="font-label text-[10px] uppercase font-bold tracking-[0.3em] text-slate-800/70 mb-5">
                        {{ $paidOrDone ? 'Voucher' : 'Journal' }} No.
                        <span class="text-slate-900 font-black">{{ $booking->booking_code }}</span>
                    </p>

                    @if ($status === 'pending')
                        <div class="relative w-20 h-20 mx-auto mb-6">
                            <div class="absolute inset-0 rounded-full bg-amber-400/30 animate-ping"></div>
                            <div class="relative w-20 h-20 rounded-full bg-white/40 backdrop-blur-md border border-white/60 flex items-center justify-center shadow-inner">
                                <span class="material-symbols-outlined text-[34px] text-amber-900">{{ $t['icon'] }}</span>
                            </div>
                        </div>
                        <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 font-headline">
                            Your request is on the captain's desk
                        </h1>
                        <p class="text-sm sm:text-[15px] text-slate-800/80 mt-3 max-w-xl mx-auto font-medium leading-relaxed">
                            We received your itinerary and are verifying availability right now.
                            No payment is required yet — we'll email
                            <strong class="text-slate-900">{{ $booking->contact_email }}</strong>
                            the moment your reservation is approved.
                        </p>
                    @elseif ($status === 'approved')
                        <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-white/40 backdrop-blur-md border border-white/60 flex items-center justify-center shadow-inner rotate-3">
                            <span class="material-symbols-outlined text-[34px] text-sky-900">{{ $t['icon'] }}</span>
                        </div>
                        <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 font-headline">
                            All clear — your dates are reserved
                        </h1>
                        <p class="text-sm sm:text-[15px] text-slate-800/80 mt-3 max-w-xl mx-auto font-medium leading-relaxed">
                            Every item in your journal checked out. Complete payment of
                            <strong class="text-slate-900">₱{{ number_format((float)$booking->net_amount, 2) }}</strong>
                            before
                            <strong class="text-slate-900">{{ $booking->payment_deadline?->format('M j, Y · g:i A') }}</strong>
                            to lock in your reservation.
                        </p>
                    @elseif ($paidOrDone)
                        <div class="mx-auto mb-6 w-32 h-32 -rotate-6 rounded-[1.4rem] border-4 border-double border-white/80 bg-white/20 backdrop-blur-md flex flex-col items-center justify-center shadow-lg">
                            <span class="material-symbols-outlined text-[34px] text-white">{{ $t['icon'] }}</span>
                            <span class="font-headline text-[11px] font-black uppercase tracking-[0.3em] text-white mt-1">
                                {{ $status === 'paid' ? 'Confirmed' : 'Completed' }}
                            </span>
                        </div>
                        <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 font-headline">
                            {{ $status === 'paid' ? 'Your escape is confirmed' : 'A journey well traveled' }}
                        </h1>
                        <p class="text-sm sm:text-[15px] text-slate-800/80 mt-3 max-w-xl mx-auto font-medium leading-relaxed">
                            {{ $status === 'paid'
                                ? 'Payment received — your itinerary is locked in. Keep this voucher handy for check-in.'
                                : 'Your trip has been completed. Thank you for traveling with SunnyTrips — until the next island.' }}
                        </p>
                    @elseif ($status === 'rejected')
                        <div class="mx-auto mb-6 w-32 h-32 -rotate-6 rounded-[1.4rem] border-4 border-double border-white/70 bg-white/10 backdrop-blur-md flex flex-col items-center justify-center shadow-lg">
                            <span class="material-symbols-outlined text-[34px] text-white">{{ $t['icon'] }}</span>
                            <span class="font-headline text-[11px] font-black uppercase tracking-[0.3em] text-white mt-1">Declined</span>
                        </div>
                        <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 font-headline">
                            We couldn't make this one work
                        </h1>
                        <p class="text-sm sm:text-[15px] text-slate-800/70 mt-3 max-w-xl mx-auto font-medium leading-relaxed">
                            {{ $booking->rejection_reason ?: 'The requested items are unavailable for your dates.' }}
                            No payment was taken — your items are back in the basket if you'd like to adjust.
                        </p>
                    @else
                        <div class="mx-auto mb-6 w-32 h-32 -rotate-6 rounded-[1.4rem] border-4 border-double border-white/70 bg-white/10 backdrop-blur-md flex flex-col items-center justify-center shadow-lg">
                            <span class="material-symbols-outlined text-[34px] text-white">{{ $t['icon'] }}</span>
                            <span class="font-headline text-[11px] font-black uppercase tracking-[0.3em] text-white mt-1">
                                {{ ucfirst($status) }}
                            </span>
                        </div>
                        <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 font-headline">
                            {{ $status === 'cancelled' ? 'This journey was cancelled' : 'This booking has expired' }}
                        </h1>
                        <p class="text-sm sm:text-[15px] text-slate-800/70 mt-3 max-w-xl mx-auto font-medium leading-relaxed">
                            @if ($status === 'cancelled')
                                This booking was cancelled{{ $booking->cancellation_reason ? ' — ' . $booking->cancellation_reason : '.' }} No payment was taken.
                            @else
                                Payment wasn't completed within the 48-hour window, so the dates were released. You can rebook anytime — your items are ready to go back in the basket.
                            @endif
                        </p>
                    @endif

                    {{-- Hero actions --}}
                    <div class="mt-8 flex items-center justify-center gap-3 flex-wrap">
                        @if ($status === 'approved')
                            <form action="{{ route('booking.pay.process', $booking->booking_code) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition flex items-center gap-1.5 shadow-lg shadow-slate-900/20 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">lock</span>
                                    <span>Proceed to Payment · ₱{{ number_format((float)$booking->net_amount, 2) }}</span>
                                </button>
                            </form>
                        @elseif (in_array($status, ['rejected', 'cancelled', 'expired'], true))
                            <form action="{{ route('booking.rebook', $booking->booking_code) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition flex items-center gap-1.5 shadow-lg shadow-slate-900/20 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">shopping_cart</span>
                                    <span>Add Back to Trip Basket</span>
                                </button>
                            </form>
                        @endif

                        @if ($status === 'paid')
                            <button onclick="window.print()" class="px-5 py-3 rounded-2xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition flex items-center gap-1.5 shadow-lg shadow-slate-900/20 cursor-pointer">
                                <span class="material-symbols-outlined text-[16px]">print</span>
                                <span>Print Itinerary Voucher</span>
                            </button>
                        @endif

                        @if ($status === 'completed')
                                @php
                                    $reviewableTypes = ['room', 'activity', 'package'];
                                    $hasUnreviewed = $booking->items
                                        ->filter(fn($item) => in_array($item->item_type, $reviewableTypes))
                                        ->some(fn($item) => !$booking->reviews->contains('booking_item_id', $item->id));
                                @endphp
                                <div x-data="{ reviewed: @js(!$hasUnreviewed) }"
                                     x-on:booking-reviews-synced.window="reviewed = true">
                                    <template x-if="!reviewed">
                                        <div>
                                            <x-review-modal :booking-id="$booking->id" />
                                        </div>
                                    </template>
                                    <template x-if="reviewed">
                                        <span class="px-5 py-3 rounded-2xl bg-emerald-100 text-emerald-700 font-bold text-xs flex items-center gap-1.5 border border-emerald-200">
                                            <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                            All Items Reviewed
                                        </span>
                                    </template>
                                </div>
                            @endif

                        <a href="{{ route('dashboard') }}" class="px-5 py-3 rounded-2xl bg-white/60 hover:bg-white/90 backdrop-blur text-slate-900 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">home</span>
                            <span>Return to Dashboard</span>
                        </a>
                    </div>
                </div>
            </section>

            {{-- ============ JOURNAL BODY ============ --}}
            <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- LEFT: itinerary + manifest --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- 1. Expedition Itinerary --}}
                    <section class="bg-white rounded-3xl border border-sand-200/80 shadow-xs overflow-hidden">
                        <header class="px-6 sm:px-8 pt-6 pb-4 border-b border-sand-100 flex items-center justify-between gap-3">
                            <h2 class="font-headline text-base font-bold text-slate-900 flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-xl bg-ocean-50 text-ocean-600 border border-ocean-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[18px]">luggage</span>
                                </span>
                                Expedition Itinerary
                            </h2>
                            <span class="font-label text-[10px] uppercase font-bold tracking-[0.15em] text-slate-400">
                                {{ $booking->items->count() }} {{ Str::plural('item', $booking->items->count()) }}
                            </span>
                        </header>

                        <div class="px-6 sm:px-8 divide-y divide-sand-100">
                            @foreach($booking->items as $item)
                                <div class="py-5 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-4 min-w-0">
                                        <div class="w-12 h-12 rounded-2xl bg-sand-100 text-slate-700 border border-sand-200 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-xl">
                                                {{ $item->item_type === 'room' ? 'hotel' : ($item->item_type === 'activity' ? 'explore' : 'card_travel') }}
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            @if($item->hotel_name)
                                                <p class="font-label text-[9px] font-bold text-ocean-700 uppercase tracking-[0.15em] mb-0.5">{{ $item->hotel_name }}</p>
                                            @endif
                                            <h3 class="text-sm font-bold text-slate-900 truncate font-headline">{{ $item->item_title }}</h3>
                                            <p class="text-xs text-slate-500">
                                                @if($item->check_in_date && $item->check_out_date)
                                                    {{ date('M j, Y', strtotime($item->check_in_date)) }} – {{ date('M j, Y', strtotime($item->check_out_date)) }}
                                                    <span class="text-slate-400">· {{ $item->nights }} {{ Str::plural('night', $item->nights) }}</span>
                                                @else
                                                    {{ $item->item_subtitle }}
                                                @endif
                                            </p>
                                            @if($item->availability_status === 'unavailable')
                                                <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                    <span class="material-symbols-outlined text-[12px]">block</span>
                                                    Not available{{ $item->admin_note ? ' — ' . $item->admin_note : '' }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-[10px] text-slate-400 font-semibold block">Qty × {{ $item->quantity }}</span>
                                        @if($item->availability_status !== 'unavailable')
                                            <span class="text-sm font-black text-slate-900">₱{{ number_format($item->subtotal, 2) }}</span>
                                        @else
                                            <span class="text-xs font-bold text-rose-500">Excluded</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- 2. Ship Manifest --}}
                    @if(!empty($booking->guest_manifest) && is_array($booking->guest_manifest) && count($booking->guest_manifest) > 0)
                        <section class="bg-white rounded-3xl border border-sand-200/80 shadow-xs overflow-hidden">
                            <header class="px-6 sm:px-8 pt-6 pb-4 border-b border-sand-100 flex items-center justify-between gap-3">
                                <h2 class="font-headline text-base font-bold text-slate-900 flex items-center gap-2.5">
                                    <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[18px]">groups</span>
                                    </span>
                                    Ship Manifest
                                </h2>
                                <span class="font-label text-[10px] uppercase font-bold tracking-[0.15em] text-slate-400">
                                    {{ count($booking->guest_manifest) }} {{ Str::plural('guest', count($booking->guest_manifest)) }}
                                </span>
                            </header>

                            <div class="px-6 sm:px-8 py-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($booking->guest_manifest as $idx => $guest)
                                    <div class="bg-sand-50 p-4 rounded-2xl border border-sand-200/70 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-9 h-9 rounded-xl bg-white border border-sand-200 text-slate-700 font-black text-xs flex items-center justify-center font-headline">
                                                {{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}
                                            </div>
                                            <div class="min-w-0">
                                                <h4 class="text-xs font-bold text-slate-900 truncate">{{ $guest['full_name'] ?? 'Guest' }}</h4>
                                                <p class="text-[10px] text-slate-500 truncate">{{ $guest['special_notes'] ?? 'No special requests' }}</p>
                                            </div>
                                        </div>
                                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white border border-sand-200 text-slate-700 whitespace-nowrap">
                                            {{ $guest['category'] ?? 'Adult' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    {{-- 3. Journey stepper (pending / approved) --}}
                    @if (in_array($status, ['pending', 'approved'], true))
                        @php
                            $journey = [
                                ['label' => 'Request received', 'desc' => 'Your journal landed on our desk.', 'state' => 'done'],
                                ['label' => 'Availability check', 'desc' => 'Crew verifies dates, rooms & tours.', 'state' => $status === 'pending' ? 'current' : 'done'],
                                ['label' => 'Approved & notified', 'desc' => 'You\'ll get an email with your payment link.', 'state' => $status === 'pending' ? 'todo' : 'done'],
                                ['label' => 'Pay to confirm', 'desc' => 'Settle the balance within 48 hours.', 'state' => $status === 'pending' ? 'todo' : 'current'],
                            ];
                        @endphp
                        <section class="bg-white rounded-3xl border border-sand-200/80 shadow-xs p-6 sm:p-8">
                            <h2 class="font-headline text-base font-bold text-slate-900 flex items-center gap-2.5 mb-6">
                                <span class="w-8 h-8 rounded-xl bg-ocean-50 text-ocean-600 border border-ocean-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[18px]">explore</span>
                                </span>
                                The Journey Ahead
                            </h2>

                            <ol class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                                @foreach($journey as $step)
                                    <li class="relative">
                                        <div class="flex sm:flex-col items-center sm:items-start gap-3">
                                            <div class="relative shrink-0">
                                                @if($step['state'] === 'done')
                                                    <div class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-md shadow-emerald-500/30">
                                                        <span class="material-symbols-outlined text-[16px]">check</span>
                                                    </div>
                                                @elseif($step['state'] === 'current')
                                                    <div class="relative w-9 h-9">
                                                        <div class="absolute inset-0 rounded-full {{ $status === 'approved' ? 'bg-sky-500' : 'bg-amber-500' }} animate-ping opacity-40"></div>
                                                        <div class="relative w-9 h-9 rounded-full {{ $status === 'approved' ? 'bg-sky-500' : 'bg-amber-500' }} text-white flex items-center justify-center shadow-md">
                                                            <span class="material-symbols-outlined text-[16px]">schedule</span>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="w-9 h-9 rounded-full bg-sand-100 border border-sand-200 text-slate-400 flex items-center justify-center">
                                                        <span class="material-symbols-outlined text-[16px]">circle</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold {{ $step['state'] === 'todo' ? 'text-slate-400' : 'text-slate-900' }}">{{ $step['label'] }}</p>
                                                <p class="text-[10px] text-slate-500 leading-snug mt-0.5">{{ $step['desc'] }}</p>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endif
                </div>

                {{-- RIGHT: ledger rail --}}
                <div class="space-y-6">

                    {{-- Captain's ledger --}}
                    <section class="bg-white rounded-3xl border border-sand-200/80 shadow-xs p-6">
                        <h2 class="font-headline text-sm font-bold text-slate-900 flex items-center gap-2 mb-4">
                            <span class="w-7 h-7 rounded-lg bg-sand-100 text-slate-600 border border-sand-200 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[15px]">menu_book</span>
                            </span>
                            Captain's Ledger
                        </h2>
                        <dl class="space-y-2.5 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-400 font-medium">Status</dt>
                                <dd>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {{ $t['chip'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $t['dot'] }}"></span>
                                        {{ ucfirst($status) }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-400 font-medium">Payment</dt>
                                <dd class="font-bold text-slate-900 uppercase tracking-wider">{{ $booking->payment_status }}</dd>
                            </div>
                            @if ($booking->payment_deadline)
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-slate-400 font-medium">Deadline</dt>
                                    <dd class="font-bold text-slate-900">{{ $booking->payment_deadline->format('M j · g:i A') }}</dd>
                                </div>
                            @endif
                            @if ($booking->gateway_reference)
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-slate-400 font-medium">Reference</dt>
                                    <dd class="font-mono font-bold text-slate-900 text-[11px]">{{ $booking->gateway_reference }}</dd>
                                </div>
                            @endif
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-400 font-medium">Submitted</dt>
                                <dd class="font-bold text-slate-900">{{ $booking->created_at->format('M j, Y') }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Lead traveler --}}
                    <section class="bg-white rounded-3xl border border-sand-200/80 shadow-xs p-6">
                        <h2 class="font-headline text-sm font-bold text-slate-900 flex items-center gap-2 mb-4">
                            <span class="w-7 h-7 rounded-lg bg-sand-100 text-slate-600 border border-sand-200 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[15px]">sailing</span>
                            </span>
                            Lead Traveler
                        </h2>
                        <p class="text-sm font-black text-slate-900 font-headline">{{ $booking->contact_name }}</p>
                        <div class="mt-2 space-y-1.5 text-xs text-slate-600">
                            <p class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[14px] text-slate-400">mail</span>
                                <span class="truncate">{{ $booking->contact_email }}</span>
                            </p>
                            <p class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[14px] text-slate-400">call</span>
                                {{ $booking->contact_phone }}
                            </p>
                        </div>
                        @if ($booking->special_requests)
                            <div class="mt-4 pt-4 border-t border-sand-100">
                                <p class="font-label text-[9px] uppercase font-bold tracking-[0.15em] text-slate-400 mb-1">Special requests</p>
                                <p class="text-xs text-slate-600 leading-relaxed">{{ $booking->special_requests }}</p>
                            </div>
                        @endif
                    </section>

                    {{-- Settlement --}}
                    <section class="bg-white rounded-3xl border border-sand-200/80 shadow-xs p-6">
                        <h2 class="font-headline text-sm font-bold text-slate-900 flex items-center gap-2 mb-4">
                            <span class="w-7 h-7 rounded-lg bg-sand-100 text-slate-600 border border-sand-200 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[15px]">receipt_long</span>
                            </span>
                            Settlement
                        </h2>

                        @php
                            $rulesMap = \App\Models\PassengerCategoryRule::getActiveRulesMap();
                        @endphp

                        <dl class="space-y-2 text-xs">
                            <div class="flex items-center justify-between text-slate-600">
                                <dt>Subtotal</dt>
                                <dd class="font-bold text-slate-900">₱{{ number_format($booking->total_amount, 2) }}</dd>
                            </div>

                            @if(!empty($booking->guest_manifest) && is_array($booking->guest_manifest))
                                @foreach($booking->guest_manifest as $guest)
                                    @php
                                        $cat = $guest['category'] ?? 'Adult';
                                        $rule = $rulesMap[$cat] ?? null;
                                    @endphp
                                    @if($rule && $rule->adjustment_type === 'discount' && (float)$rule->amount > 0)
                                        <div class="flex items-center justify-between text-emerald-700 font-semibold">
                                            <dt class="flex items-center gap-1 min-w-0">
                                                <span class="material-symbols-outlined text-[13px]">percent</span>
                                                <span class="truncate">{{ $rule->display_label }}</span>
                                            </dt>
                                            <dd>-₱{{ number_format((float)$rule->amount, 2) }}</dd>
                                        </div>
                                    @elseif($rule && $rule->adjustment_type === 'surcharge' && (float)$rule->amount > 0)
                                        <div class="flex items-center justify-between text-amber-800 font-semibold">
                                            <dt class="flex items-center gap-1 min-w-0">
                                                <span class="material-symbols-outlined text-[13px]">public</span>
                                                <span class="truncate">{{ $rule->display_label }}</span>
                                            </dt>
                                            <dd>+₱{{ number_format((float)$rule->amount, 2) }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                @if($booking->discount_amount > 0)
                                    <div class="flex items-center justify-between text-emerald-700 font-semibold">
                                        <dt>Passenger Discount</dt>
                                        <dd>-₱{{ number_format($booking->discount_amount, 2) }}</dd>
                                    </div>
                                @endif
                                @if($booking->tax_amount > 0)
                                    <div class="flex items-center justify-between text-amber-800 font-semibold">
                                        <dt>Foreign Tourist Surcharge</dt>
                                        <dd>+₱{{ number_format($booking->tax_amount, 2) }}</dd>
                                    </div>
                                @endif
                            @endif

                            <div class="flex items-center justify-between pt-3 mt-2 border-t-2 border-dashed border-sand-200">
                                <dt class="font-headline text-sm font-bold text-slate-900">
                                    {{ $paidOrDone ? 'Total paid' : 'Total due' }}
                                </dt>
                                <dd class="font-headline text-xl font-black text-ocean-800">₱{{ number_format($booking->net_amount, 2) }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Under-review checklist (pending only) --}}
                    @if ($status === 'pending')
                        <section class="bg-amber-50/70 border border-amber-200/80 rounded-3xl p-6">
                            <h2 class="font-headline text-sm font-bold text-amber-900 flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-white text-amber-600 border border-amber-200 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[15px]">fact_check</span>
                                </span>
                                What we're checking
                            </h2>
                            <ul class="space-y-3 text-xs">
                                <li class="flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[16px] text-amber-600 mt-0.5">calendar_month</span>
                                    <span class="text-amber-900/90 font-medium leading-relaxed">Dates are open for every room, tour & add-on in your journal.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[16px] text-amber-600 mt-0.5">scale</span>
                                    <span class="text-amber-900/90 font-medium leading-relaxed">Pricing matches our current rates, promos & passenger rules.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[16px] text-amber-600 mt-0.5">mark_email_read</span>
                                    <span class="text-amber-900/90 font-medium leading-relaxed">We'll email <strong class="text-amber-950">{{ $booking->contact_email }}</strong> the moment it's approved — usually within the day.</span>
                                </li>
                            </ul>
                        </section>
                    @endif
                </div>
            </div>

            {{-- ============ FOOTNOTES ============ --}}
            <div class="mt-8 space-y-6">

                {{-- Cancel zone --}}
                @if (in_array($status, ['pending', 'approved'], true))
                    <section class="bg-white rounded-3xl border border-sand-200/80 shadow-xs p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-xl">event_busy</span>
                            </div>
                            <div>
                                <h3 class="font-headline text-sm font-bold text-slate-900">Cancel this booking?</h3>
                                <p class="text-xs text-slate-500">No payment has been taken — cancelling releases your reserved dates immediately.</p>
                            </div>
                        </div>
                        <form action="{{ route('booking.cancel', $booking->booking_code) }}" method="POST"
                              onsubmit="return confirm('Cancel booking {{ $booking->booking_code }}? This cannot be undone.')">
                            @csrf
                            <button type="submit" class="px-5 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                                <span>Cancel Booking</span>
                            </button>
                        </form>
                    </section>
                @endif

                {{-- Journal timeline --}}
                @if($booking->history->isNotEmpty())
                    <section class="bg-white rounded-3xl border border-sand-200/80 shadow-xs p-6 sm:p-8">
                        <h2 class="font-headline text-base font-bold text-slate-900 flex items-center gap-2.5 mb-6">
                            <span class="w-8 h-8 rounded-xl bg-ocean-50 text-ocean-600 border border-ocean-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[18px]">history</span>
                            </span>
                            Journal Timeline
                        </h2>
                        <ol class="relative border-l-2 border-dashed border-sand-300 ml-3 space-y-5">
                            @foreach($booking->history as $event)
                                <li class="ml-6">
                                    <span class="absolute -left-[9px] mt-1 w-3.5 h-3.5 rounded-full border-2 border-white {{ $t['dot'] }} shadow"></span>
                                    <p class="text-xs font-bold text-slate-900 font-headline">
                                        {{ ucfirst(str_replace('_', ' ', $event->to_status)) }}
                                        @if($event->from_status)
                                            <span class="text-slate-400 font-medium">(was {{ $event->from_status }})</span>
                                        @endif
                                    </p>
                                    @if($event->note)
                                        <p class="text-[11px] text-slate-500 mt-0.5">{{ $event->note }}</p>
                                    @endif
                                    <p class="font-label text-[9px] uppercase tracking-[0.15em] text-slate-400 font-bold mt-1">
                                        {{ $event->created_at->format('M j, Y · g:i A') }}
                                    </p>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                {{-- Colophon --}}
                <p class="text-center font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400 pt-2">
                    SunnyTrips · Curated island escapes, verified before payment
                </p>
            </div>
        </div>
    </div>
</x-frontend.layout>
