@php
    $roomInstances = [];
    $firstRoomMaxPax = 10;
    $roomLineTotals = []; // fee-exclusive per-line totals for display
    $itemManifests = []; // per-item manifests for extra rooms, activities and addons
    $hasRoom = false;

    foreach ($cartItems as $cItem) {
        $pax = max(1, (int) ($cItem->selected_pax ?: 1));

        if ($cItem->item_type === 'room' && $cItem->itemable) {
            $room = $cItem->itemable;
            $nights = ($cItem->check_in_date && $cItem->check_out_date)
                ? max(1, (int) $cItem->check_in_date->diffInDays($cItem->check_out_date))
                : 1;
            $roomMaxOccupancy = (int) ($room->max_occupancy ?: ($room->occupancy ?: 4));
            $baseOccupancy = (int) ($room->base_occupancy ?: 2);
            $extraFee = (float) ($room->extra_person_fee ?: 0.00);
            $qty = max(1, (int) $cItem->quantity);

            $roomLineTotals[$cItem->id] = (float) $room->base_price * $nights * $qty;

            if (!$hasRoom) {
                // First room uses the main Lead Traveler & Hotel Guest Manifest component
                $firstRoomMaxPax = $roomMaxOccupancy;
                $firstRoomBasePax = $baseOccupancy;
                $hasRoom = true;

                $roomInstances[] = [
                    'key' => 'main_room',
                    'label' => $cItem->item_title . ($qty > 1 ? ' (Room 1 of ' . $qty . ')' : ''),
                    'base_occupancy' => $baseOccupancy,
                    'extra_person_fee' => $extraFee,
                    'nights' => $nights,
                    'selected_pax' => $pax,
                ];

                // If this first room item has quantity > 1, add manifests for Room 2..N
                for ($rNum = 2; $rNum <= $qty; $rNum++) {
                    $fieldName = 'room_manifest_' . $cItem->id . '_' . $rNum;
                    $itemManifests[] = [
                        'cart_item_id' => $cItem->id,
                        'type' => 'room',
                        'label' => $cItem->item_title . " (Room {$rNum} of {$qty})",
                        'icon' => 'hotel',
                        'selected_pax' => 1,
                        'base_pax' => $baseOccupancy,
                        'max_pax' => $roomMaxOccupancy,
                        'field_name' => $fieldName,
                    ];
                    $roomInstances[] = [
                        'key' => $fieldName,
                        'label' => $cItem->item_title . " (Room {$rNum} of {$qty})",
                        'base_occupancy' => $baseOccupancy,
                        'extra_person_fee' => $extraFee,
                        'nights' => $nights,
                        'selected_pax' => $pax,
                    ];
                }
            } else {
                // Additional room items in cart get dedicated manifests for each instance
                for ($rNum = 1; $rNum <= $qty; $rNum++) {
                    $fieldName = 'room_manifest_' . $cItem->id . '_' . $rNum;
                    $itemManifests[] = [
                        'cart_item_id' => $cItem->id,
                        'type' => 'room',
                        'label' => $cItem->item_title . ($qty > 1 ? " (Room {$rNum} of {$qty})" : ''),
                        'icon' => 'hotel',
                        'selected_pax' => 1,
                        'base_pax' => $baseOccupancy,
                        'max_pax' => $roomMaxOccupancy,
                        'field_name' => $fieldName,
                    ];
                    $roomInstances[] = [
                        'key' => $fieldName,
                        'label' => $cItem->item_title . ($qty > 1 ? " (Room {$rNum} of {$qty})" : ''),
                        'base_occupancy' => $baseOccupancy,
                        'extra_person_fee' => $extraFee,
                        'nights' => $nights,
                        'selected_pax' => $pax,
                    ];
                }
            }
        } elseif ($cItem->item_type === 'package' && $cItem->itemable) {
            $pax = max(1, (int) ($cItem->selected_pax ?: 1), (int) ($cItem->quantity ?: 1));
            // One separate manifest container per package slot (one per person/pax)
            for ($pkgNum = 1; $pkgNum <= $pax; $pkgNum++) {
                $itemManifests[] = [
                    'cart_item_id' => $cItem->id,
                    'type' => 'package',
                    'label' => $cItem->item_title,
                    'icon' => 'card_travel',
                    'selected_pax' => 1,
                    'base_pax' => 1,
                    'max_pax' => 1,
                    'field_name' => 'package_manifest_' . $cItem->id . '_' . $pkgNum,
                    'initial_name' => $pkgNum === 1 ? (Auth::user()->name ?? '') : '',
                    'package_slot' => $pkgNum,
                    'is_lead' => $pkgNum === 1,
                    'total_pax' => $pax,
                ];
            }
        }
    }

    $maxManifestPax = $firstRoomMaxPax;
    $firstRoomBasePax = $firstRoomBasePax ?? 2;
@endphp

<x-frontend.layout :title="'Checkout & Passenger Manifest — SunnyTrips'">
    <div x-data="checkoutEngine({{ (float) $totalAmount }}, {{ json_encode($roomInstances) }}, {{ json_encode($categoryRules) }})"
        @manifest-pricing-updated.window="updateManifestPricing($event.detail)"
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 sm:pt-28 pb-32 sm:pb-16 font-body">

        {{-- Breadcrumb & Title Header --}}
        <div class="mb-4 sm:mb-6">
            <nav class="flex items-center gap-1.5 text-xs font-semibold text-slate-400 mb-1" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="hover:text-sky-600 transition">Home</a>
                <span>/</span>
                <a href="{{ route('cart.index') }}" class="hover:text-sky-600 transition">Trip Basket</a>
                <span>/</span>
                <span class="text-slate-800">Final Checkout</span>
            </nav>
            <h1 class="text-xl sm:text-3xl font-extrabold text-slate-900 tracking-tight font-headline">
                Finalize Travel Booking
            </h1>
        </div>

        {{-- Main 2-Column Grid --}}
        <form id="checkoutForm" @submit.prevent="submitCheckout()" class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
            @csrf

            {{-- LEFT COLUMN: Forms (2/3) --}}
            <div class="lg:col-span-2 space-y-3.5 sm:space-y-5">

                {{-- 1. Passenger & Accompanying Guest Manifest (Hotel Room guests) --}}
                @if($hasRoom)
                    <x-frontend.guest-manifest-form :category-rules="$categoryRules" :max-guests="$maxManifestPax"
                        :base-guests="$firstRoomBasePax" :initial-guests="1" :lead-name="Auth::user()->name ?? ''"
                        :lead-email="Auth::user()->email ?? ''"
                        :lead-phone="Auth::user()->phone_number ?? (Auth::user()->phone ?? '')" />
                @else
                    {{-- Standard Lead Contact Details when no Hotel Room present --}}
                    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 border border-slate-200/80 shadow-xs space-y-3.5 sm:space-y-4 font-body">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2.5 sm:gap-3">
                                <div
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg sm:rounded-xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center font-bold shrink-0">
                                    <span class="material-symbols-outlined text-base sm:text-lg">badge</span>
                                </div>
                                <div>
                                    <h3 class="text-sm sm:text-base font-bold text-slate-900 font-headline">Lead Guest Contact Details</h3>
                                    <p class="text-[11px] sm:text-xs text-slate-500">We will send your voucher, payment updates, and itinerary here.</p>
                                </div>
                            </div>
                            <span
                                class="px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 text-[10px] sm:text-[11px] font-bold border border-sky-200 shrink-0">Primary</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3.5">
                            <div class="sm:col-span-2">
                                <label class="block text-[10.5px] font-bold text-slate-500 mb-1 uppercase tracking-wider">Lead Traveler Full Name *</label>
                                <input type="text" name="contact_name" value="{{ Auth::user()->name ?? '' }}" required
                                    class="w-full px-3.5 py-2 sm:py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 bg-slate-50/50 focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-[10.5px] font-bold text-slate-500 mb-1 uppercase tracking-wider">Email Address *</label>
                                <input type="email" name="contact_email" value="{{ Auth::user()->email ?? '' }}" required
                                    placeholder="e.g. name@example.com"
                                    class="w-full px-3.5 py-2 sm:py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 bg-slate-50/50 focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-[10.5px] font-bold text-slate-500 mb-1 uppercase tracking-wider">Mobile Phone Number *</label>
                                <input type="tel" name="contact_phone"
                                    value="{{ Auth::user()->phone_number ?? (Auth::user()->phone ?? '') }}" required
                                    placeholder="e.g. 09171234567"
                                    class="w-full px-3.5 py-2 sm:py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 bg-slate-50/50 focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition">
                            </div>
                        </div>
                    </div>
                @endif

                {{-- 2. Per-Item Passenger Manifests (Packages, Rooms, Activities & Transfers) --}}
                @foreach($itemManifests as $manifest)
                    <div x-data="itemManifestBuilder({
                                    manifestType: '{{ $manifest['type'] }}',
                                    itemLabel: '{{ addslashes($manifest['label']) }}',
                                    fieldName: '{{ $manifest['field_name'] }}',
                                    selectedPax: {{ (int) $manifest['selected_pax'] }},
                                    basePax: {{ (int) ($manifest['base_pax'] ?? 0) }},
                                    maxPax: {{ (int) $manifest['max_pax'] }},
                                    initialName: '{{ addslashes($manifest['initial_name'] ?? '') }}',
                                    rules: {{ json_encode($categoryRules) }}
                                 })"
                        class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 border border-slate-200/80 shadow-xs space-y-3 sm:space-y-4 font-body">

                        {{-- Header --}}
                        <div class="flex items-center gap-2.5 sm:gap-3 border-b border-slate-100 pb-3">
                            <div
                                class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg sm:rounded-xl {{ $manifest['type'] === 'package' ? 'bg-amber-50 text-amber-700 border-amber-200' : ($manifest['type'] === 'addon' ? 'bg-violet-50 text-violet-600 border-violet-200' : ($manifest['type'] === 'room' ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-teal-50 text-teal-600 border-teal-200')) }} border flex items-center justify-center font-bold shrink-0">
                                <span class="material-symbols-outlined text-base sm:text-lg">{{ $manifest['icon'] }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                @if($manifest['type'] === 'package')
                                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                                        <h3 class="text-sm sm:text-base font-bold text-slate-900 font-headline">
                                            Package {{ $manifest['package_slot'] }}
                                        </h3>
                                        @if($manifest['is_lead'])
                                            <span
                                                class="text-[10px] font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-full border border-sky-200">Lead</span>
                                        @endif
                                        <span class="text-[10px] text-slate-400 font-semibold">of
                                            {{ $manifest['total_pax'] }}</span>
                                    </div>
                                    <p class="text-[11px] sm:text-xs text-slate-500 mt-0.5 truncate">
                                        <span class="font-semibold text-amber-800">{{ $manifest['label'] }}</span>
                                        — full name as on ID
                                    </p>
                                @else
                                    <h3 class="text-sm sm:text-base font-bold text-slate-900 font-headline">
                                        {{ $manifest['type'] === 'room' ? 'Hotel Guest Manifest' : 'Passenger Manifest' }}
                                    </h3>
                                    <p class="text-[11px] sm:text-xs text-slate-500">
                                        <span
                                            class="font-semibold {{ $manifest['type'] === 'addon' ? 'text-violet-700' : ($manifest['type'] === 'room' ? 'text-amber-800' : 'text-teal-700') }}">{{ $manifest['label'] }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        {{-- Passenger rows --}}
                        <div class="space-y-2 sm:space-y-3">
                            <template x-for="(p, i) in passengers" :key="i">
                                <div>
                                    {{-- For Packages: Clean Direct Full Name Field --}}
                                    <template x-if="manifestType === 'package'">
                                        <div>
                                            <label
                                                class="block text-[10.5px] font-bold text-slate-500 mb-1 uppercase tracking-wider">
                                                Traveler Full Name *
                                            </label>
                                            <input type="text" x-model="p.full_name" @input="notifyManifestChange()"
                                                placeholder="Full name as on valid ID"
                                                class="w-full px-3.5 py-2 sm:py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 bg-slate-50/50 focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition">
                                        </div>
                                    </template>

                                    {{-- For Hotel Rooms / Multi-Guest Items: Grouped Box with Category --}}
                                    <template x-if="manifestType !== 'package'">
                                        <div class="bg-slate-50/80 p-3 sm:p-4 rounded-xl sm:rounded-2xl border border-slate-200/70 space-y-2 transition duration-200 hover:border-slate-300">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                                    <span
                                                        class="material-symbols-outlined text-[16px] text-sky-600">person</span>
                                                    <span
                                                        x-text="i === 0 ? 'Lead Traveler' : 'Accompanying Guest ' + (i + 1)"></span>
                                                </span>
                                                <button type="button" x-show="i > 0" @click="removePassenger(i)"
                                                    class="text-slate-400 hover:text-rose-600 text-xs font-semibold flex items-center gap-1 p-1 rounded-md hover:bg-rose-50 transition cursor-pointer"
                                                    title="Remove this guest">
                                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                                    <span>Remove</span>
                                                </button>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                                                <div>
                                                    <label
                                                        class="block text-[10.5px] font-bold text-slate-500 mb-1 uppercase tracking-wider">Full Name *</label>
                                                    <input type="text" x-model="p.full_name" @input="notifyManifestChange()"
                                                        :placeholder="i === 0 ? 'Lead traveler name' : 'Full Name as in ID'"
                                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-[10.5px] font-bold text-slate-500 mb-1 uppercase tracking-wider">Passenger Category *</label>
                                                    <select x-model="p.category" @change="notifyManifestChange()"
                                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                                        @foreach($categoryRules as $rule)
                                                            @php
                                                                $rObj = is_array($rule) ? (object) $rule : $rule;
                                                                $rName = $rObj->category_name;
                                                                $rLabel = $rObj->display_label;
                                                                $rType = $rObj->adjustment_type ?? 'none';
                                                                $rAmt = (float) ($rObj->amount ?? 0);

                                                                $labelText = $rLabel;
                                                                if ($rType === 'discount' && $rAmt > 0) {
                                                                    $labelText .= ' (-₱' . number_format($rAmt, 0) . ' Discount)';
                                                                } elseif ($rType === 'surcharge' && $rAmt > 0) {
                                                                    $labelText .= ' (+₱' . number_format($rAmt, 0) . ' Surcharge)';
                                                                }
                                                            @endphp
                                                            <option value="{{ $rName }}">{{ $labelText }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Add / capacity row --}}
                        <div x-show="manifestType !== 'package' && maxPax > 1"
                            class="pt-1 flex items-center justify-between">
                            <button type="button" @click="addPassenger()" :disabled="passengers.length >= maxPax"
                                :class="passengers.length >= maxPax ? 'opacity-50 cursor-not-allowed bg-slate-100 text-slate-400 border-slate-200' : 'bg-sky-50 hover:bg-sky-100 text-sky-700 border-sky-200 hover:border-sky-300 cursor-pointer'"
                                class="px-4 py-2 rounded-xl border font-bold text-xs flex items-center justify-center gap-2 shadow-2xs transition">
                                <span class="material-symbols-outlined text-[16px]">person_add</span>
                                <span
                                    x-text="passengers.length >= maxPax ? 'Max Occupancy Limit Reached (' + maxPax + ' Guests)' : '+ Add Accompanying Guest'"></span>
                            </button>
                            <span class="text-[10.5px] font-semibold text-slate-500">
                                <template x-if="basePax > 0">
                                    <span>Base: <strong class="text-slate-800" x-text="basePax + ' Pax'"></strong> · </span>
                                </template>
                                Max: <strong class="text-slate-800" x-text="maxPax + ' Pax'"></strong>
                            </span>
                        </div>

                        <input type="hidden" :name="fieldName" :value="JSON.stringify(passengers)">
                    </div>
                @endforeach

                {{-- 2. Simplified Passenger Summary for Activities & Airport Transfers --}}
                @php
                    $nonRoomItems = $cartItems->filter(fn($ci) => in_array($ci->item_type, ['activity', 'addon']));
                @endphp

                @if($nonRoomItems->isNotEmpty())
                    <div class="space-y-4 font-body">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sky-600 text-lg">confirmation_number</span>
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">
                                Activities & Transfers Passenger Summary
                            </h3>
                        </div>

                        @foreach($nonRoomItems as $cItem)
                            @php
                                $pax = max(1, (int) ($cItem->selected_pax ?: 1));
                                $isTransfer = $cItem->item_type === 'addon';
                                $badgeSummary = $isTransfer ? "Transfer for {$pax} Pax" : "Participants: {$pax}";
                            @endphp

                            <div
                                class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-5 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 transition-all duration-200 hover:border-slate-300">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div
                                        class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl sm:rounded-2xl {{ $isTransfer ? 'bg-violet-50 text-violet-600 border-violet-200' : 'bg-teal-50 text-teal-600 border-teal-200' }} border flex items-center justify-center font-bold shrink-0">
                                        <span
                                            class="material-symbols-outlined text-xl sm:text-2xl">{{ $isTransfer ? 'directions_bus' : 'kayaking' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-sm sm:text-base font-bold text-slate-900 font-headline truncate">
                                            {{ $cItem->item_title }}
                                        </h4>
                                        <p class="text-[11px] sm:text-xs text-slate-500 font-medium mt-0.5 truncate">
                                            {{ $cItem->location_name ?: 'Boracay Island' }} •
                                            {{ $isTransfer ? 'Airport to Hotel Roundtrip Transfer' : 'Activity Experience' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:items-end gap-1 shrink-0 w-full sm:w-auto">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-full {{ $isTransfer ? 'bg-violet-50 text-violet-800 border-violet-200' : 'bg-teal-50 text-teal-800 border-teal-200' }} border text-[11px] sm:text-xs font-bold w-fit">
                                        <span
                                            class="material-symbols-outlined text-[14px] sm:text-[15px] {{ $isTransfer ? 'text-violet-600' : 'text-teal-600' }}">group</span>
                                        <span>Booked for {{ $pax }} Pax</span>
                                    </span>
                                    <span class="text-[10px] font-medium text-slate-500">{{ $badgeSummary }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- 3. How Booking Confirmation Works --}}
                <div class="bg-white rounded-2xl sm:rounded-3xl p-3.5 sm:p-5 border border-slate-200/80 shadow-xs space-y-2.5 sm:space-y-3 font-body">
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-2.5">
                        <div
                            class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg sm:rounded-xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center font-bold shrink-0">
                            <span class="material-symbols-outlined text-base">fact_check</span>
                        </div>
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 font-headline">How Booking Works</h3>
                            <p class="text-[10.5px] sm:text-xs text-slate-500">Your reservation is verified before payment.</p>
                        </div>
                    </div>

                    <ol class="space-y-2 text-xs">
                        <li class="flex items-start gap-2">
                            <span
                                class="w-5 h-5 min-w-[20px] rounded-full bg-sky-600 text-white text-[10px] font-black flex items-center justify-center shrink-0 mt-0.5">1</span>
                            <p class="text-[11px] sm:text-xs text-slate-600"><strong class="text-slate-900">Submit request:</strong> We hold your selected items.</p>
                        </li>
                        <li class="flex items-start gap-2">
                            <span
                                class="w-5 h-5 min-w-[20px] rounded-full bg-sky-600 text-white text-[10px] font-black flex items-center justify-center shrink-0 mt-0.5">2</span>
                            <p class="text-[11px] sm:text-xs text-slate-600"><strong class="text-slate-900">Availability check:</strong> Our team verifies room/activity dates.</p>
                        </li>
                        <li class="flex items-start gap-2">
                            <span
                                class="w-5 h-5 min-w-[20px] rounded-full bg-sky-600 text-white text-[10px] font-black flex items-center justify-center shrink-0 mt-0.5">3</span>
                            <p class="text-[11px] sm:text-xs text-slate-600"><strong class="text-slate-900">Pay to confirm:</strong> Pay within <strong class="text-slate-900">48 hours</strong> via email link.</p>
                        </li>
                    </ol>
                </div>

            </div>

            {{-- RIGHT COLUMN: Sticky Itinerary Summary (1/3) --}}
            <div class="space-y-3.5 sm:space-y-5">
                <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-5 border border-slate-200/80 shadow-md lg:sticky lg:top-28 space-y-3.5 sm:space-y-4 font-body">
                    <h3
                        class="text-xs sm:text-sm font-bold text-slate-900 border-b border-slate-100 pb-2.5 font-headline flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sky-600 text-base">receipt_long</span>
                        <span>Itinerary Order Breakdown</span>
                    </h3>

                    {{-- Cart Items Snapshot List --}}
                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                        @foreach($cartItems as $item)
                            <div
                                class="flex items-center justify-between text-xs py-1.5 border-b border-slate-100 last:border-0">
                                <div class="flex items-center gap-2 min-w-0 pr-2">
                                    <img src="{{ $item->item_image }}" alt="{{ $item->item_title }}"
                                        class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg object-cover border border-slate-200/80 shrink-0"
                                        onerror="this.style.display='none'">
                                    <div class="truncate">
                                        <h4 class="font-bold text-slate-900 truncate text-[11px] sm:text-xs">{{ $item->item_title }}</h4>
                                        <p class="text-[10px] sm:text-[11px] text-slate-500 truncate">
                                            {{ $item->date_details ?: $item->item_subtitle }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="font-extrabold text-slate-900 shrink-0 text-xs">₱{{ number_format($roomLineTotals[$item->id] ?? $item->subtotal, 2) }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Totals Calculation --}}
                    <div class="bg-slate-50 rounded-xl p-3 space-y-1.5 border border-slate-200/60 text-xs">
                        <div class="flex items-center justify-between text-slate-600 font-medium text-[11.5px]">
                            <span>Subtotal</span>
                            <span class="font-bold text-slate-800">₱{{ number_format($totalAmount, 2) }}</span>
                        </div>

                        {{-- Itemized Stacked Passenger Discounts & Surcharges --}}
                        <template x-for="(item, idx) in pricingBreakdown" :key="idx">
                            <div class="flex items-center justify-between py-1 px-2 rounded-lg border text-[10.5px] font-semibold transition"
                                :class="item.type === 'discount' ? 'bg-emerald-50/70 border-emerald-200/80 text-emerald-800' : 'bg-amber-50/70 border-amber-200/80 text-amber-900'">
                                <span class="flex items-center gap-1.5 min-w-0 pr-2 truncate">
                                    <span class="material-symbols-outlined text-[13px] shrink-0"
                                        :class="item.type === 'discount' ? 'text-emerald-600' : 'text-amber-600'"
                                        x-text="item.type === 'discount' ? 'percent' : 'public'"></span>
                                    <span class="truncate" x-text="item.label"></span>
                                </span>
                                <span class="font-extrabold shrink-0"
                                    x-text="(item.type === 'discount' ? '-₱' : '+₱') + Number(item.amount).toFixed(2)"></span>
                            </div>
                        </template>

                        {{-- Auto-Calculated Room Extra Person Surcharge with Explicit Math Breakdown --}}
                        <div x-show="extraPersonFeeTotal > 0"
                            class="p-2 rounded-lg border border-amber-200/80 bg-amber-50/80 text-amber-950 text-xs transition space-y-1.5"
                            x-cloak>
                            <div class="flex items-center justify-between font-bold text-[11px]">
                                <span class="flex items-center gap-1 text-amber-900">
                                    <span
                                        class="material-symbols-outlined text-[14px] text-amber-600 shrink-0">group_add</span>
                                    <span>Extra Guest Charge</span>
                                </span>
                                <span class="font-black text-amber-900"
                                    x-text="'+₱' + Number(extraPersonFeeTotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                            </div>
                            <div class="space-y-1 pt-1 border-t border-amber-200/60">
                                <template x-for="(item, idx) in extraPersonFeeBreakdownItems" :key="idx">
                                    <div
                                        class="text-[10px] bg-white/90 p-1.5 rounded border border-amber-200/60 space-y-0.5 shadow-2xs">
                                        <div class="flex items-center justify-between font-bold text-amber-950">
                                            <span x-text="item.roomName"></span>
                                            <span class="font-extrabold text-amber-900"
                                                x-text="'+' + item.lineTotalStr"></span>
                                        </div>
                                        <div
                                            class="text-[9.5px] text-amber-800 font-medium flex items-center justify-between gap-1 flex-wrap">
                                            <span class="text-amber-700">Computation:</span>
                                            <span
                                                class="font-mono font-bold bg-amber-100/70 px-1 py-0.2 rounded text-amber-950"
                                                x-text="item.formula"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between text-xs sm:text-sm font-black text-slate-900 pt-1.5 border-t border-slate-200">
                            <span>Total Net Amount</span>
                            <span class="text-sm sm:text-base font-headline font-black text-sky-900"
                                x-text="formattedTotalNet">₱{{ number_format($totalAmount, 2) }}</span>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit" :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-50 cursor-wait' : 'hover:from-sky-500 hover:to-sky-600 cursor-pointer shadow-md shadow-sky-600/25'"
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 text-white font-extrabold text-xs sm:text-sm transition-all flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]" x-show="!isSubmitting">verified</span>
                        <span class="material-symbols-outlined text-[16px] animate-spin" x-show="isSubmitting"
                            x-cloak>progress_activity</span>
                        <span x-text="isSubmitting ? 'Submitting Booking Request...' : 'Submit Booking Request'"></span>
                    </button>

                    <p class="text-[10px] text-center text-slate-400 font-medium leading-tight">
                        By confirming, you agree to SunnyTrips terms, cancellation policies, and traveler guidelines.
                    </p>
                </div>
            </div>

        </form>
    </div>

    <script>
        function checkoutEngine(baseSubtotal, roomInstances, categoryRules) {
            return {
                isSubmitting: false,
                baseSubtotal: baseSubtotal || 0,
                roomInstances: roomInstances || [],
                categoryRules: categoryRules || [],
                manifestGuestsMap: {},
                manifestPassengersMap: {},
                mainRoomDiscount: 0,
                mainRoomSurcharge: 0,
                mainRoomBreakdown: [],
                passengerDiscount: 0,
                foreignerSurcharge: 0,
                pricingBreakdown: [],

                get extraPersonFeeTotal() {
                    let fee = 0;
                    this.roomInstances.forEach(inst => {
                        const count = Math.max(this.manifestGuestsMap[inst.key] || 1, inst.selected_pax || 1);
                        if (count > inst.base_occupancy && inst.extra_person_fee > 0) {
                            const extraPaxCount = count - inst.base_occupancy;
                            fee += (extraPaxCount * inst.extra_person_fee * inst.nights);
                        }
                    });
                    return fee;
                },

                get extraPersonFeeBreakdownItems() {
                    const items = [];
                    this.roomInstances.forEach(inst => {
                        const count = Math.max(this.manifestGuestsMap[inst.key] || 1, inst.selected_pax || 1);
                        if (count > inst.base_occupancy && inst.extra_person_fee > 0) {
                            const extraPaxCount = count - inst.base_occupancy;
                            const feePerNight = Number(inst.extra_person_fee);
                            const nights = Number(inst.nights) || 1;
                            const lineTotal = extraPaxCount * feePerNight * nights;

                            const feeStr = '₱' + feePerNight.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const lineTotalStr = '₱' + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const formula = `${feeStr}/night × ${nights} night${nights > 1 ? 's' : ''} × ${extraPaxCount} extra guest${extraPaxCount > 1 ? 's' : ''}`;

                            items.push({
                                roomName: inst.label,
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
                    if (!detail) return;

                    if (detail.key) {
                        this.manifestGuestsMap[detail.key] = detail.guest_count || (detail.guests ? detail.guests.length : 1);
                        if (detail.passengers || detail.guests) {
                            this.manifestPassengersMap[detail.key] = detail.passengers || detail.guests;
                        }
                    }

                    if (detail.key === 'main_room') {
                        this.mainRoomDiscount = detail.totalDiscount || 0;
                        this.mainRoomSurcharge = detail.totalSurcharge || 0;
                        this.mainRoomBreakdown = detail.breakdown || [];
                    }

                    this.recalculateAllCategoryDiscounts();
                },

                recalculateAllCategoryDiscounts() {
                    let totalDiscount = this.mainRoomDiscount || 0;
                    let totalSurcharge = this.mainRoomSurcharge || 0;
                    let breakdown = [...(this.mainRoomBreakdown || [])];

                    const rulesMap = {};
                    (this.categoryRules || []).forEach(r => {
                        const name = r.category_name || r.name;
                        rulesMap[name] = r;
                    });

                    Object.keys(this.manifestPassengersMap).forEach(key => {
                        if (key === 'main_room') return; // already in mainRoomBreakdown
                        const passList = this.manifestPassengersMap[key] || [];
                        passList.forEach((p, idx) => {
                            const rule = rulesMap[p.category];
                            if (rule && rule.adjustment_type !== 'none') {
                                const amt = Number(rule.amount) || 0;
                                const passName = p.full_name && p.full_name.trim() ? p.full_name.trim() : 'Passenger ' + (idx + 1);

                                if (rule.adjustment_type === 'discount') {
                                    totalDiscount += amt;
                                    breakdown.push({
                                        type: 'discount',
                                        category: rule.category_name,
                                        label: rule.display_label + ' Discount (' + passName + ')',
                                        amount: amt
                                    });
                                } else if (rule.adjustment_type === 'surcharge') {
                                    totalSurcharge += amt;
                                    breakdown.push({
                                        type: 'surcharge',
                                        category: rule.category_name,
                                        label: rule.display_label + ' Surcharge (' + passName + ')',
                                        amount: amt
                                    });
                                }
                            }
                        });
                    });

                    this.passengerDiscount = totalDiscount;
                    this.foreignerSurcharge = totalSurcharge;
                    this.pricingBreakdown = breakdown;
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

        function itemManifestBuilder(config) {
            return {
                manifestType: config.manifestType || 'room',
                fieldName: config.fieldName || 'item_manifest',
                basePax: config.basePax || 0,
                maxPax: config.maxPax || 20,
                passengers: [],
                rules: config.rules || [],

                init() {
                    const count = Math.min(config.selectedPax || 1, this.maxPax);
                    for (let i = 0; i < count; i++) {
                        this.passengers.push({
                            full_name: (i === 0 && config.initialName) ? config.initialName : '',
                            category: 'Adult',
                            is_lead: i === 0,
                        });
                    }
                    this.notifyManifestChange();
                },

                addPassenger() {
                    if (this.passengers.length >= this.maxPax) return;
                    this.passengers.push({ full_name: '', category: 'Adult', is_lead: false });
                    this.notifyManifestChange();
                },

                removePassenger(index) {
                    if (index > 0 && index < this.passengers.length) {
                        this.passengers.splice(index, 1);
                        this.notifyManifestChange();
                    }
                },

                notifyManifestChange() {
                    window.dispatchEvent(new CustomEvent('manifest-pricing-updated', {
                        detail: {
                            key: this.fieldName,
                            guest_count: this.passengers.length,
                            passengers: this.passengers
                        }
                    }));
                }
            };
        }
    </script>
</x-frontend.layout>