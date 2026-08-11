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

            <!-- <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold">
                <span class="material-symbols-outlined text-[16px] text-emerald-600">lock</span>
                <span>Secure 256-Bit SSL Checkout</span>
            </span> -->
        </div>

        {{-- Main 2-Column Grid --}}
        <form id="checkoutForm" @submit.prevent="submitCheckout()" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            @csrf

            {{-- LEFT COLUMN: Forms (2/3) --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- 1. Passenger & Accompanying Guest Manifest (Hotel Room guests) --}}
                @if($hasRoom)
                    <x-frontend.guest-manifest-form :category-rules="$categoryRules" :max-guests="$maxManifestPax"
                        :base-guests="$firstRoomBasePax" :initial-guests="1" :lead-name="Auth::user()->name ?? ''"
                        :lead-email="Auth::user()->email ?? ''"
                        :lead-phone="Auth::user()->phone_number ?? (Auth::user()->phone ?? '')" />
                @else
                    {{-- Standard Lead Contact Details when no Hotel Room present --}}
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6 font-body">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center font-bold">
                                    <span class="material-symbols-outlined text-xl">badge</span>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-bold text-slate-900 font-headline">Lead Guest
                                        Contact Details</h3>
                                    <p class="text-xs text-slate-500">We will send your voucher, payment updates, and
                                        itinerary here.</p>
                                </div>
                            </div>
                            <span
                                class="px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold border border-sky-200">Primary
                                Contact</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">Lead
                                    Traveler Full Name *</label>
                                <input type="text" name="contact_name" value="{{ Auth::user()->name ?? '' }}" required
                                    class="w-full px-4 py-3 rounded-2xl border border-slate-200 text-sm font-semibold text-slate-900 bg-slate-50 focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">Email
                                    Address *</label>
                                <input type="email" name="contact_email" value="{{ Auth::user()->email ?? '' }}" required
                                    placeholder="e.g. name@example.com"
                                    class="w-full px-4 py-3 rounded-2xl border border-slate-200 text-sm font-semibold text-slate-900 bg-slate-50 focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">Mobile
                                    Phone Number *</label>
                                <input type="tel" name="contact_phone"
                                    value="{{ Auth::user()->phone_number ?? (Auth::user()->phone ?? '') }}" required
                                    placeholder="e.g. 09171234567"
                                    class="w-full px-4 py-3 rounded-2xl border border-slate-200 text-sm font-semibold text-slate-900 bg-slate-50 focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition">
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
                        class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-4 font-body">

                        {{-- Header --}}
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <div
                                class="w-10 h-10 rounded-2xl {{ $manifest['type'] === 'package' ? 'bg-amber-50 text-amber-700 border-amber-200' : ($manifest['type'] === 'addon' ? 'bg-violet-50 text-violet-600 border-violet-200' : ($manifest['type'] === 'room' ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-teal-50 text-teal-600 border-teal-200')) }} border flex items-center justify-center font-bold">
                                <span class="material-symbols-outlined text-xl">{{ $manifest['icon'] }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                @if($manifest['type'] === 'package')
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-base font-bold text-slate-900 font-headline">
                                            Package {{ $manifest['package_slot'] }}
                                        </h3>
                                        @if($manifest['is_lead'])
                                            <span
                                                class="text-[11px] font-semibold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-full border border-sky-200">Lead
                                                Traveler</span>
                                        @endif
                                        <span class="text-[11px] text-slate-400 font-medium">of
                                            {{ $manifest['total_pax'] }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5 truncate">
                                        <span class="font-semibold text-amber-800">{{ $manifest['label'] }}</span>
                                        — full name as it appears on ID
                                    </p>
                                @else
                                    <h3 class="text-base font-bold text-slate-900 font-headline">
                                        {{ $manifest['type'] === 'room' ? 'Hotel Guest Manifest' : 'Passenger Manifest' }}
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        <span
                                            class="font-semibold {{ $manifest['type'] === 'addon' ? 'text-violet-700' : ($manifest['type'] === 'room' ? 'text-amber-800' : 'text-teal-700') }}">{{ $manifest['label'] }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        {{-- Passenger rows --}}
                        <div class="space-y-3">
                            <template x-for="(p, i) in passengers" :key="i">
                                <div
                                    class="bg-slate-50/80 p-4 sm:p-5 rounded-2xl border border-slate-200/70 space-y-3 transition duration-200 hover:border-slate-300">
                                    {{-- Row header only for non-packages (rooms/activities have multiple guests per
                                    container) --}}
                                    <template x-if="manifestType !== 'package'">
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
                                    </template>

                                    {{-- For Packages: Single Full Name Field (container title is the identifier) --}}
                                    <template x-if="manifestType === 'package'">
                                        <div>
                                            <label
                                                class="block text-[11px] font-bold text-slate-600 mb-1 uppercase tracking-wider">
                                                Traveler Full Name *
                                            </label>
                                            <input type="text" x-model="p.full_name" @input="notifyManifestChange()"
                                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        </div>
                                    </template>

                                    {{-- For Hotel Rooms / Other: Full Name & Category --}}
                                    <template x-if="manifestType !== 'package'">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label
                                                    class="block text-[11px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Full
                                                    Name *</label>
                                                <input type="text" x-model="p.full_name" @input="notifyManifestChange()"
                                                    :placeholder="i === 0 ? 'Lead traveler name' : 'Full Name as in ID'"
                                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                            </div>
                                            <div>
                                                <label
                                                    class="block text-[11px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Passenger
                                                    Category *</label>
                                                <select x-model="p.category" @change="notifyManifestChange()"
                                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
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
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Add / capacity row --}}
                        <div x-show="manifestType !== 'package' && maxPax > 1"
                            class="pt-2 flex items-center justify-between">
                            <button type="button" @click="addPassenger()" :disabled="passengers.length >= maxPax"
                                :class="passengers.length >= maxPax ? 'opacity-50 cursor-not-allowed bg-slate-100 text-slate-400 border-slate-200' : 'bg-sky-50 hover:bg-sky-100 text-sky-700 border-sky-200 hover:border-sky-300 cursor-pointer'"
                                class="px-5 py-2.5 rounded-xl border font-bold text-xs flex items-center justify-center gap-2 shadow-2xs transition">
                                <span class="material-symbols-outlined text-[18px]">person_add</span>
                                <span
                                    x-text="passengers.length >= maxPax ? 'Max Occupancy Limit Reached (' + maxPax + ' Guests)' : '+ Add Accompanying Guest'"></span>
                            </button>
                            <span class="text-[11px] font-semibold text-slate-500">
                                <template x-if="basePax > 0">
                                    <span>Base capacity: <strong class="text-slate-800" x-text="basePax + ' Pax'"></strong>
                                        · </span>
                                </template>
                                Max capacity: <strong class="text-slate-800" x-text="maxPax + ' Pax'"></strong>
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
                                class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 transition-all duration-200 hover:border-slate-300">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div
                                        class="w-12 h-12 rounded-2xl {{ $isTransfer ? 'bg-violet-50 text-violet-600 border-violet-200' : 'bg-teal-50 text-teal-600 border-teal-200' }} border flex items-center justify-center font-bold shrink-0">
                                        <span
                                            class="material-symbols-outlined text-2xl">{{ $isTransfer ? 'directions_bus' : 'kayaking' }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-base font-bold text-slate-900 font-headline truncate">
                                            {{ $cItem->item_title }}
                                        </h4>
                                        <p class="text-xs text-slate-500 font-medium mt-0.5 truncate">
                                            {{ $cItem->location_name ?: 'Boracay Island' }} •
                                            {{ $isTransfer ? 'Airport to Hotel Roundtrip Transfer' : 'Activity Experience' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:items-end gap-1 shrink-0 w-full sm:w-auto">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full {{ $isTransfer ? 'bg-violet-50 text-violet-800 border-violet-200' : 'bg-teal-50 text-teal-800 border-teal-200' }} border text-xs font-bold w-fit">
                                        <span
                                            class="material-symbols-outlined text-[15px] {{ $isTransfer ? 'text-violet-600' : 'text-teal-600' }}">group</span>
                                        <span>Booked for {{ $pax }} Pax</span>
                                    </span>
                                    <!-- <span class="text-[11px] font-semibold text-slate-500">
                                                        {{ $badgeSummary }}
                                                    </span> -->
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- 3. How Booking Confirmation Works --}}
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-5">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div
                            class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center font-bold">
                            <span class="material-symbols-outlined text-xl">fact_check</span>
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 font-headline">How Booking Works
                            </h3>
                            <p class="text-xs text-slate-500">Your reservation is verified before any payment is taken.
                            </p>
                        </div>
                    </div>

                    <ol class="space-y-3">
                        <li class="flex items-start gap-3">
                            <span
                                class="w-6 h-6 rounded-full bg-sky-600 text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">1</span>
                            <p class="text-xs text-slate-600 font-medium"><strong class="text-slate-900">Submit your
                                    request.</strong> We receive your itinerary and hold your selected items.</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span
                                class="w-6 h-6 rounded-full bg-sky-600 text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">2</span>
                            <p class="text-xs text-slate-600 font-medium"><strong class="text-slate-900">Availability
                                    check.</strong> Our team verifies rooms, activities, and packages for your dates.
                            </p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span
                                class="w-6 h-6 rounded-full bg-sky-600 text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">3</span>
                            <p class="text-xs text-slate-600 font-medium"><strong class="text-slate-900">Pay to
                                    confirm.</strong> Once approved, you get an email with a secure payment link — pay
                                within <strong class="text-slate-900">48 hours</strong> to lock in your booking.</p>
                        </li>
                    </ol>
                </div>

            </div>

            {{-- RIGHT COLUMN: Sticky Itinerary Summary (1/3) --}}
            <div class="space-y-6">
                <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-md sticky top-6 space-y-5">
                    <h3
                        class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 font-headline flex items-center gap-2">
                        <span class="material-symbols-outlined text-sky-600">receipt_long</span>
                        <span>Itinerary Order Breakdown</span>
                    </h3>

                    {{-- Cart Items Snapshot List --}}
                    <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                        @foreach($cartItems as $item)
                            <div
                                class="flex items-center justify-between text-xs py-2 border-b border-slate-100 last:border-0">
                                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                    <img src="{{ $item->item_image }}" alt="{{ $item->item_title }}"
                                        class="w-10 h-10 rounded-lg object-cover border shrink-0">
                                    <div class="truncate">
                                        <h4 class="font-bold text-slate-900 truncate">{{ $item->item_title }}</h4>
                                        <p class="text-[11px] text-slate-500 truncate">
                                            {{ $item->date_details ?: $item->item_subtitle }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="font-bold text-slate-900 shrink-0">₱{{ number_format($roomLineTotals[$item->id] ?? $item->subtotal, 2) }}</span>
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
                                <span class="font-extrabold shrink-0"
                                    x-text="(item.type === 'discount' ? '-₱' : '+₱') + Number(item.amount).toFixed(2)"></span>
                            </div>
                        </template>

                        {{-- Auto-Calculated Room Extra Person Surcharge with Explicit Math Breakdown --}}
                        <div x-show="extraPersonFeeTotal > 0"
                            class="p-3 rounded-xl border border-amber-200/80 bg-amber-50/80 text-amber-950 text-xs transition space-y-2"
                            x-cloak>
                            <div class="flex items-center justify-between font-bold">
                                <span class="flex items-center gap-1.5 text-amber-900">
                                    <span
                                        class="material-symbols-outlined text-[16px] text-amber-600 shrink-0">group_add</span>
                                    <span>Extra Guest Charge</span>
                                </span>
                                <span class="font-black text-amber-900"
                                    x-text="'+₱' + Number(extraPersonFeeTotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                            </div>
                            <div class="space-y-1 pt-1 border-t border-amber-200/60">
                                <template x-for="(item, idx) in extraPersonFeeBreakdownItems" :key="idx">
                                    <div
                                        class="text-[11px] bg-white/90 p-2 rounded-lg border border-amber-200/60 space-y-1 shadow-2xs">
                                        <div class="flex items-center justify-between font-bold text-amber-950">
                                            <span x-text="item.roomName"></span>
                                            <span class="font-extrabold text-amber-900"
                                                x-text="'+' + item.lineTotalStr"></span>
                                        </div>
                                        <div
                                            class="text-[10.5px] text-amber-800 font-medium flex items-center justify-between gap-1 flex-wrap">
                                            <span class="text-amber-700">Computation:</span>
                                            <span
                                                class="font-mono font-bold bg-amber-100/70 px-1.5 py-0.5 rounded text-amber-950"
                                                x-text="item.formula"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between text-sm font-black text-slate-900 pt-2 border-t border-slate-200">
                            <span>Total Net Amount</span>
                            <span class="text-base text-sky-900"
                                x-text="formattedTotalNet">₱{{ number_format($totalAmount, 2) }}</span>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit" :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-50 cursor-wait' : 'hover:from-sky-500 hover:to-sky-600 cursor-pointer shadow-lg shadow-sky-600/30'"
                        class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-sky-600 to-sky-700 text-white font-extrabold text-sm transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined" x-show="!isSubmitting">verified</span>
                        <span class="material-symbols-outlined animate-spin" x-show="isSubmitting"
                            x-cloak>progress_activity</span>
                        <span x-text="isSubmitting ? 'Submitting Booking Request...' : 'Submit Booking Request'"></span>
                    </button>

                    <p class="text-[11px] text-center text-slate-400 font-medium">
                        By confirming, you agree to SunnyTrips terms, room cancellation policies, and traveler
                        guidelines.
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