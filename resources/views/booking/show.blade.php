<x-frontend.layout :title="'Booking Confirmation #' . $booking->booking_code . ' — SunnyTrips'">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 font-body">

        {{-- Success Hero Banner --}}
        <div class="bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-800 rounded-3xl p-8 text-white text-center shadow-xl relative overflow-hidden mb-8">
            <div class="w-20 h-20 bg-white/15 backdrop-blur-md rounded-3xl flex items-center justify-center mx-auto mb-4 border border-white/20 shadow-inner">
                <span class="material-symbols-outlined text-4xl text-emerald-200">task_alt</span>
            </div>
            
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/20 text-xs font-bold uppercase tracking-wider text-emerald-100 mb-2">
                Booking Reference Confirmed
            </span>

            <h1 class="text-3xl sm:text-4xl font-black tracking-tight font-headline">
                {{ $booking->booking_code }}
            </h1>
            <p class="text-sm text-emerald-100 mt-2 max-w-xl mx-auto font-medium">
                Thank you for booking with SunnyTrips! Your travel itinerary reservation has been generated and saved.
            </p>

            <div class="mt-6 flex items-center justify-center gap-3">
                <button onclick="window.print()" class="px-5 py-2.5 rounded-xl bg-white text-slate-900 font-bold text-xs hover:bg-slate-100 transition flex items-center gap-1.5 shadow-md cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">print</span>
                    <span>Print Itinerary Voucher</span>
                </button>
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">home</span>
                    <span>Return to Dashboard</span>
                </a>
            </div>
        </div>

        <div class="space-y-6">

            {{-- 1. Lead Guest & Payment Details Card --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sky-600 text-base">person</span>
                        <span>Lead Guest Details</span>
                    </h3>
                    <div class="space-y-1 text-sm font-semibold text-slate-900">
                        <p class="text-base font-extrabold">{{ $booking->contact_name }}</p>
                        <p class="text-slate-600 text-xs flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">mail</span>
                            {{ $booking->contact_email }}
                        </p>
                        <p class="text-slate-600 text-xs flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">call</span>
                            {{ $booking->contact_phone }}
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sky-600 text-base">payments</span>
                        <span>Payment & Status</span>
                    </h3>
                    <div class="space-y-1 text-xs font-medium text-slate-700">
                        <p><span class="text-slate-400">Payment Method:</span> <strong class="text-slate-900">{{ $booking->payment_method }}</strong></p>
                        <p><span class="text-slate-400">Payment Reference:</span> <strong class="text-slate-900">{{ $booking->payment_reference }}</strong></p>
                        <div class="pt-1 flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-md text-[11px] font-extrabold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                {{ strtoupper($booking->payment_status) }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-md text-[11px] font-extrabold uppercase tracking-wider bg-sky-50 text-sky-700 border border-sky-200">
                                {{ strtoupper($booking->status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Passenger Manifest Registered Guests List --}}
            @if(!empty($booking->guest_manifest) && is_array($booking->guest_manifest) && count($booking->guest_manifest) > 0)
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-4">
                    <h3 class="text-base font-bold text-slate-900 font-headline flex items-center gap-2 border-b border-slate-100 pb-3">
                        <span class="material-symbols-outlined text-amber-500">groups</span>
                        <span>Registered Passenger Manifest ({{ count($booking->guest_manifest) }} {{ Str::plural('Guest', count($booking->guest_manifest)) }})</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($booking->guest_manifest as $idx => $guest)
                            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/70 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-white border text-sky-600 font-bold text-xs flex items-center justify-center">
                                        {{ $idx + 1 }}
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900">{{ $guest['full_name'] ?? 'Guest' }}</h4>
                                        <p class="text-[10px] text-slate-500">{{ $guest['special_notes'] ?? 'No special requests' }}</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-white border border-slate-200 text-slate-700">
                                    {{ $guest['category'] ?? 'Adult' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- 3. Booked Items Breakdown --}}
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-4">
                <h3 class="text-base font-bold text-slate-900 font-headline flex items-center gap-2 border-b border-slate-100 pb-3">
                    <span class="material-symbols-outlined text-sky-600">luggage</span>
                    <span>Booked Reserved Items</span>
                </h3>

                <div class="divide-y divide-slate-100">
                    @foreach($booking->items as $item)
                        <div class="py-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-xl">
                                        {{ $item->item_type === 'room' ? 'hotel' : ($item->item_type === 'activity' ? 'explore' : 'card_travel') }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    @if($item->hotel_name)
                                        <p class="text-[10px] font-bold text-sky-700 uppercase tracking-wider mb-0.5">{{ $item->hotel_name }}</p>
                                    @endif
                                    <h4 class="text-sm font-bold text-slate-900 truncate">{{ $item->item_title }}</h4>
                                    <p class="text-xs text-slate-500">
                                        @if($item->check_in_date && $item->check_out_date)
                                            {{ date('M j, Y', strtotime($item->check_in_date)) }} – {{ date('M j, Y', strtotime($item->check_out_date)) }} ({{ $item->nights }} {{ Str::plural('Night', $item->nights) }})
                                        @else
                                            {{ $item->item_subtitle }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="text-xs text-slate-400 block">Qty: {{ $item->quantity }}</span>
                                <span class="text-sm font-black text-slate-900">₱{{ number_format($item->subtotal, 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Total Calculation Summary --}}
                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200/80 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Subtotal</span>
                        <span>₱{{ number_format($booking->total_amount, 2) }}</span>
                    </div>

                    @php
                        $rulesMap = \App\Models\PassengerCategoryRule::getActiveRulesMap();
                    @endphp

                    @if(!empty($booking->guest_manifest) && is_array($booking->guest_manifest))
                        @foreach($booking->guest_manifest as $guest)
                            @php
                                $cat = $guest['category'] ?? 'Adult';
                                $rule = $rulesMap[$cat] ?? null;
                            @endphp
                            @if($rule && $rule->adjustment_type === 'discount' && (float)$rule->amount > 0)
                                <div class="flex items-center justify-between text-emerald-700 font-semibold py-0.5">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">percent</span>
                                        <span>{{ $rule->display_label }} Discount ({{ $guest['full_name'] ?? 'Guest' }})</span>
                                    </span>
                                    <span>-₱{{ number_format((float)$rule->amount, 2) }}</span>
                                </div>
                            @elseif($rule && $rule->adjustment_type === 'surcharge' && (float)$rule->amount > 0)
                                <div class="flex items-center justify-between text-amber-800 font-semibold py-0.5">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">public</span>
                                        <span>{{ $rule->display_label }} Surcharge ({{ $guest['full_name'] ?? 'Guest' }})</span>
                                    </span>
                                    <span>+₱{{ number_format((float)$rule->amount, 2) }}</span>
                                </div>
                            @endif
                        @endforeach
                    @else
                        @if($booking->discount_amount > 0)
                            <div class="flex items-center justify-between text-emerald-700 font-semibold">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">percent</span>
                                    <span>Passenger Discount</span>
                                </span>
                                <span>-₱{{ number_format($booking->discount_amount, 2) }}</span>
                            </div>
                        @endif
                        @if($booking->tax_amount > 0)
                            <div class="flex items-center justify-between text-amber-800 font-semibold">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">public</span>
                                    <span>Foreign Tourist Surcharge</span>
                                </span>
                                <span>+₱{{ number_format($booking->tax_amount, 2) }}</span>
                            </div>
                        @endif
                    @endif

                    <div class="flex items-center justify-between text-base font-black text-slate-900 pt-3 border-t border-slate-200">
                        <span>Total Paid Net Amount</span>
                        <span class="text-lg text-sky-900">₱{{ number_format($booking->net_amount, 2) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-frontend.layout>
