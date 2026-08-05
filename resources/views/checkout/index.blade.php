@php
    $roomsMetadata = [];
    $maxManifestPaxList = [];

    foreach ($cartItems as $cItem) {
        if ($cItem->item_type === 'room' && $cItem->itemable) {
            $room = $cItem->itemable;
            $nights = ($cItem->check_in_date && $cItem->check_out_date) 
                ? max(1, (int)$cItem->check_in_date->diffInDays($cItem->check_out_date)) 
                : 1;
            $roomsMetadata[] = [
                'id' => $cItem->id,
                'room_name' => $cItem->item_title,
                'base_occupancy' => (int)($room->base_occupancy ?: 2),
                'max_occupancy' => (int)($room->max_occupancy ?: ($room->occupancy ?: 4)),
                'extra_person_fee' => (float)($room->extra_person_fee ?: 0.00),
                'selected_pax' => (int)($cItem->selected_pax ?: 2),
                'nights' => $nights,
                'quantity' => max(1, (int)$cItem->quantity),
            ];
            $maxManifestPaxList[] = (int)($room->max_occupancy ?: ($room->occupancy ?: 4)) * max(1, (int)$cItem->quantity);
        } elseif ($cItem->item_type === 'package' && $cItem->itemable) {
            $maxManifestPaxList[] = (int)($cItem->itemable->max_pax ?: 10);
        }
    }

    $maxManifestPax = !empty($maxManifestPaxList) ? min($maxManifestPaxList) : 10;
@endphp

<x-frontend.layout :title="'Checkout & Passenger Manifest — SunnyTrips'">
    <div x-data="checkoutEngine({{ (float)$totalAmount }}, {{ json_encode($roomsMetadata) }})" 
         @manifest-pricing-updated.window="updateManifestPricing($event.detail)"
         class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 font-body">

        {{-- Breadcrumb Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-sky-600">Home</a>
                    <span>/</span>
                    <a href="{{ route('cart.index') }}" class="hover:text-sky-600">Trip Basket</a>
                    <span>/</span>
                    <span class="text-slate-800">Final Checkout</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight font-headline">
                    Finalize Travel Booking
                </h1>
            </div>

            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold">
                <span class="material-symbols-outlined text-[16px] text-emerald-600">lock</span>
                <span>Secure 256-Bit SSL Checkout</span>
            </span>
        </div>

        {{-- Main 2-Column Grid --}}
        <form id="checkoutForm" @submit.prevent="submitCheckout()" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            @csrf

            {{-- LEFT COLUMN: Forms (2/3) --}}
            <div class="lg:col-span-2 space-y-8">
                
                {{-- 1. Passenger & Accompanying Guest Manifest Component --}}
                <x-frontend.guest-manifest-form 
                    :category-rules="$categoryRules"
                    :max-guests="$maxManifestPax"
                    :lead-name="Auth::user()->name ?? ''" 
                    :lead-email="Auth::user()->email ?? ''" 
                    :lead-phone="Auth::user()->phone_number ?? (Auth::user()->phone ?? '')" />

                {{-- 2. How Booking Confirmation Works --}}
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-5">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-xl">fact_check</span>
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 font-headline">How Booking Works</h3>
                            <p class="text-xs text-slate-500">Your reservation is verified before any payment is taken.</p>
                        </div>
                    </div>

                    <ol class="space-y-3">
                        <li class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-full bg-sky-600 text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">1</span>
                            <p class="text-xs text-slate-600 font-medium"><strong class="text-slate-900">Submit your request.</strong> We receive your itinerary and hold your selected items.</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-full bg-sky-600 text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">2</span>
                            <p class="text-xs text-slate-600 font-medium"><strong class="text-slate-900">Availability check.</strong> Our team verifies rooms, activities, and packages for your dates.</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-full bg-sky-600 text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">3</span>
                            <p class="text-xs text-slate-600 font-medium"><strong class="text-slate-900">Pay to confirm.</strong> Once approved, you get an email with a secure payment link — pay within <strong class="text-slate-900">48 hours</strong> to lock in your booking.</p>
                        </li>
                    </ol>
                </div>

            </div>

            {{-- RIGHT COLUMN: Sticky Itinerary Summary (1/3) --}}
            <div class="space-y-6">
                <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-md sticky top-6 space-y-5">
                    <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 font-headline flex items-center gap-2">
                        <span class="material-symbols-outlined text-sky-600">receipt_long</span>
                        <span>Itinerary Order Breakdown</span>
                    </h3>

                    {{-- Cart Items Snapshot List --}}
                    <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                        @foreach($cartItems as $item)
                            <div class="flex items-center justify-between text-xs py-2 border-b border-slate-100 last:border-0">
                                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                    <img src="{{ $item->item_image }}" alt="{{ $item->item_title }}" class="w-10 h-10 rounded-lg object-cover border shrink-0">
                                    <div class="truncate">
                                        <h4 class="font-bold text-slate-900 truncate">{{ $item->item_title }}</h4>
                                        <p class="text-[11px] text-slate-500 truncate">{{ $item->date_details ?: $item->item_subtitle }}</p>
                                    </div>
                                </div>
                                <span class="font-bold text-slate-900 shrink-0">₱{{ number_format($item->subtotal, 2) }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Totals Calculation --}}
                    <div class="bg-slate-50 rounded-2xl p-4 space-y-2 border border-slate-200/60 text-xs">
                        <div class="flex items-center justify-between text-slate-600 font-medium">
                            <span>Subtotal</span>
                            <span>₱{{ number_format($totalAmount, 2) }}</span>
                        </div>

                        {{-- Itemized Stacked Passenger Discounts & Surcharges --}}
                        <template x-for="(item, idx) in pricingBreakdown" :key="idx">
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-xl border text-[11px] font-semibold transition"
                                 :class="item.type === 'discount' ? 'bg-emerald-50/70 border-emerald-200/80 text-emerald-800' : 'bg-amber-50/70 border-amber-200/80 text-amber-900'">
                                <span class="flex items-center gap-1.5 min-w-0 pr-2 truncate">
                                    <span class="material-symbols-outlined text-[15px] shrink-0" 
                                          :class="item.type === 'discount' ? 'text-emerald-600' : 'text-amber-600'" 
                                          x-text="item.type === 'discount' ? 'percent' : 'public'"></span>
                                    <span class="truncate" x-text="item.label"></span>
                                </span>
                                <span class="font-extrabold shrink-0" x-text="(item.type === 'discount' ? '-₱' : '+₱') + Number(item.amount).toFixed(2)"></span>
                            </div>
                        </template>

                        {{-- Auto-Calculated Room Extra Person Surcharge with Explicit Math Breakdown --}}
                        <div x-show="extraPersonFeeTotal > 0" class="p-3 rounded-xl border border-amber-200/80 bg-amber-50/80 text-amber-950 text-xs transition space-y-2" x-cloak>
                            <div class="flex items-center justify-between font-bold">
                                <span class="flex items-center gap-1.5 text-amber-900">
                                    <span class="material-symbols-outlined text-[16px] text-amber-600 shrink-0">group_add</span>
                                    <span>Extra Guest Charge</span>
                                </span>
                                <span class="font-black text-amber-900" x-text="'+₱' + Number(extraPersonFeeTotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                            </div>
                            <div class="space-y-1 pt-1 border-t border-amber-200/60">
                                <template x-for="(item, idx) in extraPersonFeeBreakdownItems" :key="idx">
                                    <div class="text-[11px] bg-white/90 p-2 rounded-lg border border-amber-200/60 space-y-1 shadow-2xs">
                                        <div class="flex items-center justify-between font-bold text-amber-950">
                                            <span x-text="item.roomName"></span>
                                            <span class="font-extrabold text-amber-900" x-text="'+' + item.lineTotalStr"></span>
                                        </div>
                                        <div class="text-[10.5px] text-amber-800 font-medium flex items-center justify-between gap-1 flex-wrap">
                                            <span class="text-amber-700">Computation:</span>
                                            <span class="font-mono font-bold bg-amber-100/70 px-1.5 py-0.5 rounded text-amber-950" x-text="item.formula"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm font-black text-slate-900 pt-2 border-t border-slate-200">
                            <span>Total Net Amount</span>
                            <span class="text-base text-sky-900" x-text="formattedTotalNet">₱{{ number_format($totalAmount, 2) }}</span>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit" 
                            :disabled="isSubmitting"
                            :class="isSubmitting ? 'opacity-50 cursor-wait' : 'hover:from-sky-500 hover:to-sky-600 cursor-pointer shadow-lg shadow-sky-600/30'"
                            class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-sky-600 to-sky-700 text-white font-extrabold text-sm transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined" x-show="!isSubmitting">verified</span>
                        <span class="material-symbols-outlined animate-spin" x-show="isSubmitting" x-cloak>progress_activity</span>
                        <span x-text="isSubmitting ? 'Submitting Booking Request...' : 'Submit Booking Request'"></span>
                    </button>

                    <p class="text-[11px] text-center text-slate-400 font-medium">
                        By confirming, you agree to SunnyTrips terms, room cancellation policies, and traveler guidelines.
                    </p>
                </div>
            </div>

        </form>
    </div>

    <script>
    function checkoutEngine(baseSubtotal, roomsMetadata) {
        return {
            isSubmitting: false,
            baseSubtotal: baseSubtotal || 0,
            roomsMetadata: roomsMetadata || [],
            manifestGuestsCount: 1,
            passengerDiscount: 0,
            foreignerSurcharge: 0,
            pricingBreakdown: [],

            get extraPersonFeeTotal() {
                let fee = 0;
                const totalPax = this.manifestGuestsCount || 1;
                this.roomsMetadata.forEach(r => {
                    const effectivePax = Math.max(r.selected_pax, totalPax);
                    const basePax = r.base_occupancy || 2;
                    if (effectivePax > basePax && r.extra_person_fee > 0) {
                        const extraPaxCount = effectivePax - basePax;
                        fee += (extraPaxCount * r.extra_person_fee * r.nights * r.quantity);
                    }
                });
                return fee;
            },

            get extraPersonFeeBreakdownItems() {
                const totalPax = this.manifestGuestsCount || 1;
                const items = [];
                this.roomsMetadata.forEach(r => {
                    const effectivePax = Math.max(r.selected_pax, totalPax);
                    const basePax = r.base_occupancy || 2;
                    if (effectivePax > basePax && r.extra_person_fee > 0) {
                        const extraPaxCount = effectivePax - basePax;
                        const feePerNight = Number(r.extra_person_fee);
                        const nights = Number(r.nights) || 1;
                        const qty = Number(r.quantity) || 1;
                        const lineTotal = extraPaxCount * feePerNight * nights * qty;
                        
                        const feeStr = '₱' + feePerNight.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const lineTotalStr = '₱' + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        
                        const formula = `${feeStr}/night × ${nights} night${nights > 1 ? 's' : ''} × ${extraPaxCount} extra guest${extraPaxCount > 1 ? 's' : ''}${qty > 1 ? ' (' + qty + ' rooms)' : ''}`;
                        
                        items.push({
                            roomName: r.room_name,
                            extraPaxCount: extraPaxCount,
                            feePerNightStr: feeStr,
                            nights: nights,
                            formula: formula,
                            lineTotalStr: lineTotalStr
                        });
                    }
                });
                return items;
            },

            get netTotal() {
                return Math.max(0, this.baseSubtotal + this.extraPersonFeeTotal - this.passengerDiscount + this.foreignerSurcharge);
            },

            get formattedTotalNet() {
                return '₱' + this.netTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            updateManifestPricing(detail) {
                if (detail) {
                    this.manifestGuestsCount = detail.guests ? detail.guests.length : 1;
                    this.passengerDiscount = detail.totalDiscount || 0;
                    this.foreignerSurcharge = detail.totalSurcharge || 0;
                    this.pricingBreakdown = detail.breakdown || [];
                }
            },

            async submitCheckout() {
                this.isSubmitting = true;
                const form = document.getElementById('checkoutForm');
                const formData = new FormData(form);

                try {
                    const res = await fetch('{{ route('checkout.process') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    if (res.redirected) {
                        window.location.href = res.url;
                        return;
                    }

                    const data = await res.json();
                    if (data.success) {
                        window.location.href = data.redirect_url;
                    } else {
                        alert(data.message || 'Could not process booking order.');
                        this.isSubmitting = false;
                    }
                } catch (err) {
                    console.error('Checkout error:', err);
                    alert('An error occurred while submitting your order. Please try again.');
                    this.isSubmitting = false;
                }
            }
        };
    }
    </script>
</x-frontend.layout>
