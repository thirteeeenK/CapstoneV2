@php
    $initialItems = $cartItems->map(function ($item) {
        $totalRooms = 10;
        $bookedCount = 0;
        $availableCount = 10;
        $notice = null;

        if ($item->item_type === 'room' && $item->itemable) {
            $room = $item->itemable;
            $totalRooms = (int) ($room->total_rooms ?: ($room->total_number_of_rooms ?: 2));
            $checkIn = $item->check_in_date ? \Illuminate\Support\Carbon::parse($item->check_in_date) : null;
            $checkOut = $item->check_out_date ? \Illuminate\Support\Carbon::parse($item->check_out_date) : null;

            if ($checkIn && $checkOut) {
                $bookedCount = (int) \App\Models\BookingItem::where('item_type', 'room')
                    ->where('item_id', $room->id)
                    ->whereHas('booking', fn($q) => $q->whereIn('status', \App\Models\Booking::HOLD_STATUSES))
                    ->where(function ($q) use ($checkIn, $checkOut) {
                        $q->whereBetween('check_in_date', [$checkIn, $checkOut->copy()->subDay()])
                          ->orWhereBetween('check_out_date', [$checkIn->copy()->addDay(), $checkOut]);
                    })
                    ->sum('quantity');
            }
            $availableCount = max(0, $totalRooms - $bookedCount);
            if ($bookedCount > 0) {
                $notice = "{$availableCount} of {$totalRooms} rooms available for these dates ({$bookedCount} " . ($bookedCount === 1 ? 'room is' : 'rooms are') . " currently pending admin approval or reserved)";
            } else {
                $notice = "{$totalRooms} of {$totalRooms} rooms available in resort";
            }
        }

        return [
            'id' => $item->id,
            'item_type' => $item->item_type,
            'item_id' => $item->item_id,
            'quantity' => (int) $item->quantity,
            'selected_pax' => (int) ($item->selected_pax ?: 1),
            'is_selected' => (bool) $item->is_selected,
            'title' => $item->item_title,
            'subtitle' => $item->item_subtitle,
            'hotel_name' => $item->hotel_name,
            'location_name' => $item->location_name,
            'date_details' => $item->date_details,
            'image' => $item->item_image,
            'unit_rate' => (float) $item->unit_rate,
            'subtotal' => (float) $item->subtotal,
            'formatted_unit_rate' => '₱' . number_format($item->unit_rate, 2),
            'formatted_subtotal' => '₱' . number_format($item->subtotal, 2),
            'max_qty' => $availableCount,
            'total_rooms' => $totalRooms,
            'booked_count' => $bookedCount,
            'available_notice' => $notice,
            'base_occupancy' => ($item->item_type === 'room' && $item->itemable) ? (int) $item->itemable->base_occupancy : null,
            'max_occupancy' => ($item->item_type === 'room' && $item->itemable) ? (int) $item->itemable->max_occupancy : null,
            'bed_configuration' => ($item->item_type === 'room' && $item->itemable) ? $item->itemable->bed_configuration : null,
            'room_name' => ($item->item_type === 'room' && $item->itemable) ? $item->itemable->room_name : $item->item_title,
        ];
    })->values();
@endphp

<x-frontend.layout title="Trip Basket | SunnyTrips">
    <div x-data="cartPageManager({{ json_encode($initialItems) }})" class="min-h-screen bg-sand-50 font-body">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">

            {{-- Breadcrumb & Title --}}
            <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <nav class="flex items-center gap-1.5 text-sm text-ink-400 mb-3" aria-label="Breadcrumb">
                        <a href="/" class="hover:text-ocean-600 transition">Home</a>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-ink-600 font-medium">Trip Basket</span>
                    </nav>
                    <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight text-ink-900">
                        Your Trip Basket
                    </h1>
                    <p class="text-sm text-ink-500 mt-2">
                        <span x-text="totalCount + ' ' + (totalCount === 1 ? 'item' : 'items')"></span>
                        in your basket — <span x-text="selectedCount"></span> selected for booking.
                    </p>
                </div>

                <a href="{{ route('destinations.index') }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-ocean-700 hover:text-ocean-900 transition group">
                    <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Continue exploring</span>
                </a>
            </div>

            {{-- Empty Basket State --}}
            <div x-show="items.length === 0" x-cloak
                 class="bg-white rounded-3xl shadow-sm shadow-ocean-900/5 p-12 sm:p-16 text-center max-w-xl mx-auto my-12">
                <svg viewBox="0 0 96 96" fill="none" aria-hidden="true" class="w-28 h-28 mx-auto mb-6">
                    <circle cx="48" cy="48" r="45" class="stroke-sand-200" stroke-width="1.5"/>
                    <circle cx="48" cy="41" r="8" class="fill-ocean-200"/>
                    <path d="M48 26v-3M48 56v-3M31 41h-3M68 41h-3M36 29l-2-2M62 53l-2-2M60 29l2-2M34 53l2-2"
                          class="stroke-ocean-300" stroke-width="2" stroke-linecap="round"/>
                    <path d="M23 68c7-5.5 14-5.5 21 0s14 5.5 21 0" class="stroke-ocean-400" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M30 76c5.5-4.3 11-4.3 16.5 0s11 4.3 16.5 0" class="stroke-ocean-200" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <h2 class="font-display text-2xl font-bold text-ink-900 mb-2">Your basket is currently empty</h2>
                <p class="text-sm text-ink-500 mb-8 max-w-sm mx-auto leading-relaxed">Start curating your dream vacation package by browsing our accommodations, activities, and tour packages.</p>

                <div class="flex flex-wrap justify-center gap-3">
                    <a href="{{ route('rooms.index') }}"
                       class="px-6 py-3 rounded-full bg-ocean-600 hover:bg-ocean-500 text-white font-semibold text-sm shadow-sm shadow-ocean-600/25 transition cursor-pointer">
                        Explore Rooms
                    </a>
                    <a href="{{ route('activities.index') }}"
                       class="px-6 py-3 rounded-full bg-ink-900 hover:bg-ink-700 text-white font-semibold text-sm transition cursor-pointer">
                        Explore Activities
                    </a>
                </div>
            </div>

            {{-- Cart Items Content Grid --}}
            <div x-show="items.length > 0" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

                {{-- Left 2 Columns: Item Cards --}}
                <div class="lg:col-span-2 space-y-4">
                    <template x-for="item in items" :key="item.id">
                        <div class="bg-white rounded-2xl ring-1 ring-sand-200 p-5 sm:p-6 flex flex-col sm:flex-row gap-5 transition-all duration-200 hover:ring-ocean-200 hover:shadow-md hover:shadow-ocean-900/5"
                             :class="item.is_selected ? '' : 'opacity-60'">

                            {{-- Checkbox --}}
                            <div class="pt-0.5">
                                <input type="checkbox"
                                       :checked="item.is_selected"
                                       @change="toggleSelect(item.id)"
                                       :aria-label="'Select ' + item.title"
                                       class="w-5 h-5 rounded accent-ocean-600 text-ocean-600 border-sand-300 focus:ring-ocean-500 cursor-pointer">
                            </div>

                            {{-- Image --}}
                            <img :src="item.image" :alt="item.title"
                                 class="w-full sm:w-36 h-48 sm:h-32 rounded-xl object-cover ring-1 ring-sand-200 shrink-0">

                            {{-- Details --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="inline-flex items-center rounded-full bg-ocean-50 text-ocean-800 border border-ocean-100 px-2.5 py-0.5 text-[11px] font-semibold tracking-wide capitalize"
                                          x-text="item.item_type">
                                    </span>
                                    <template x-if="item.location_name">
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-ink-500">
                                            <span class="material-symbols-outlined text-sm text-ocean-600">location_on</span>
                                            <span x-text="item.location_name"></span>
                                        </span>
                                    </template>
                                </div>

                                <template x-if="item.hotel_name">
                                    <div class="flex items-center gap-1.5 text-xs font-semibold text-ocean-700 mb-1">
                                        <span class="material-symbols-outlined text-sm text-ocean-600">hotel</span>
                                        <span x-text="item.hotel_name"></span>
                                    </div>
                                </template>

                                <h3 class="text-base font-semibold text-ink-900 truncate" x-text="item.room_name || item.title"></h3>

                                {{-- Feature Badges & Specs --}}
                                <div class="flex flex-wrap gap-1.5 mt-2.5">
                                    <template x-if="item.item_type === 'room' && item.base_occupancy">
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-600 bg-sand-100 px-2.5 py-1 rounded-full border border-sand-200">
                                            <span class="material-symbols-outlined text-[13px] text-ink-400">group</span>
                                            <span x-text="'Base ' + item.base_occupancy + ' • Max ' + item.max_occupancy + ' Pax'"></span>
                                        </span>
                                    </template>

                                    <template x-if="item.bed_configuration">
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-600 bg-sand-100 px-2.5 py-1 rounded-full border border-sand-200">
                                            <span class="material-symbols-outlined text-[13px] text-ink-400">king_bed</span>
                                            <span x-text="item.bed_configuration"></span>
                                        </span>
                                    </template>

                                    <template x-if="item.date_details">
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ocean-800 bg-ocean-50 px-2.5 py-1 rounded-full border border-ocean-100">
                                            <span class="material-symbols-outlined text-[13px] text-ocean-600">calendar_month</span>
                                            <span x-text="item.date_details"></span>
                                        </span>
                                    </template>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                                    {{-- AJAX Quantity Stepper --}}
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-medium text-ink-500">Qty</span>
                                        <div class="flex items-center rounded-full border border-sand-200 bg-white p-1">
                                            <button type="button"
                                                    @click="updateQty(item.id, -1)"
                                                    :disabled="item.quantity <= 1"
                                                    class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                                                <span class="text-xs font-bold">-</span>
                                            </button>
                                            <span class="w-7 text-center text-xs font-semibold text-ink-900" x-text="item.quantity"></span>
                                            <button type="button"
                                                    @click="updateQty(item.id, 1)"
                                                    :disabled="item.quantity >= item.max_qty"
                                                    class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                                                <span class="text-xs font-bold">+</span>
                                            </button>
                                        </div>
                                         <template x-if="item.item_type === 'room'">
                                             <div class="flex flex-col gap-0.5">
                                                 <span class="text-[11px] font-medium text-ink-500" x-text="item.available_notice"></span>
                                                 <template x-if="item.booked_count > 0">
                                                     <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-800 bg-amber-50 border border-amber-200/80 px-2 py-0.5 rounded-full w-fit">
                                                         <span class="material-symbols-outlined text-[12px] text-amber-600">info</span>
                                                         <span>Note: <span x-text="item.booked_count"></span> room<span x-text="item.booked_count > 1 ? 's are' : ' is'"></span> pending admin approval or active holds for these dates.</span>
                                                     </span>
                                                 </template>
                                             </div>
                                         </template>
                                    </div>

                                    {{-- Subtotal --}}
                                    <div class="text-right">
                                        <template x-if="item.quantity > 1">
                                            <span class="block text-xs text-ink-400" x-text="item.formatted_unit_rate + ' each'"></span>
                                        </template>
                                        <span class="block text-lg font-bold text-ink-900" x-text="item.formatted_subtotal"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Remove Action --}}
                            <div class="self-start sm:self-center">
                                <button type="button"
                                        @click="removeItem(item.id)"
                                        :aria-label="'Remove ' + item.title"
                                        class="w-9 h-9 rounded-full flex items-center justify-center text-ink-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Right 1 Column: Summary Card --}}
                <div class="lg:sticky lg:top-8">
                    <div class="bg-white rounded-2xl shadow-md shadow-ocean-900/5 p-6 sm:p-7 space-y-5">

                        <h2 class="font-display text-lg font-bold text-ink-900">Booking Summary</h2>

                        <div class="space-y-2.5 text-sm">
                            <div class="flex justify-between text-ink-500">
                                <span>Selected items</span>
                                <span class="font-semibold text-ink-700" x-text="selectedCount + ' of ' + totalCount"></span>
                            </div>
                            <div class="flex justify-between text-ink-500">
                                <span>Subtotal</span>
                                <span class="font-semibold text-ink-700" x-text="formattedSelectedSubtotal"></span>
                            </div>
                            <div class="flex justify-between items-center text-ink-500">
                                <span>Service & booking fee</span>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 px-2.5 py-0.5 text-xs font-semibold">
                                    Waived (Promo)
                                </span>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-sand-200 flex justify-between items-baseline">
                            <span class="font-display text-sm font-bold text-ink-900">Estimated total</span>
                            <span class="font-display text-2xl font-bold text-ocean-700" x-text="formattedSelectedSubtotal"></span>
                        </div>

                        <a href="{{ route('checkout.index') }}"
                           class="w-full py-3.5 rounded-full bg-ocean-600 hover:bg-ocean-500 text-white text-sm font-semibold shadow-sm shadow-ocean-600/25 transition flex items-center justify-center gap-2 cursor-pointer">
                            <span>Proceed to Booking Checkout</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>

                        <p class="text-xs text-ink-400 text-center leading-relaxed">You'll add your guest details and confirm your stay at checkout.</p>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script>
    function cartPageManager(initialItems) {
        return {
            items: initialItems || [],

            get selectedItems() {
                return this.items.filter(i => i.is_selected);
            },

            get selectedCount() {
                return this.selectedItems.length;
            },

            get totalCount() {
                return this.items.length;
            },

            get selectedSubtotal() {
                return this.selectedItems.reduce((sum, i) => sum + Number(i.subtotal), 0);
            },

            get formattedSelectedSubtotal() {
                return '₱' + this.selectedSubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            async updateQty(itemId, delta) {
                const item = this.items.find(i => i.id === itemId);
                if (!item) return;

                const targetQty = item.quantity + delta;
                if (targetQty < 1 || targetQty > item.max_qty) return;

                // Immediate optimistic UI update
                const prevQty = item.quantity;
                const prevSubtotal = item.subtotal;

                item.quantity = targetQty;
                item.subtotal = item.unit_rate * targetQty;
                item.formatted_subtotal = '₱' + item.subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('/cart/update/' + itemId, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ quantity: targetQty })
                    });

                    const data = await res.json();
                    if (data.success && data.items) {
                        this.syncWithBackend(data.items);
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    } else {
                        item.quantity = prevQty;
                        item.subtotal = prevSubtotal;
                        item.formatted_subtotal = '₱' + prevSubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        alert(data.message || 'Could not update item quantity.');
                    }
                } catch (err) {
                    console.error('Error updating quantity:', err);
                    item.quantity = prevQty;
                    item.subtotal = prevSubtotal;
                    item.formatted_subtotal = '₱' + prevSubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            },

            async toggleSelect(itemId) {
                const item = this.items.find(i => i.id === itemId);
                if (!item) return;

                item.is_selected = !item.is_selected;

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('/cart/toggle/' + itemId, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    if (data.success && data.items) {
                        this.syncWithBackend(data.items);
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    }
                } catch (err) {
                    console.error('Error toggling selection:', err);
                }
            },

            async removeItem(itemId) {
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('/cart/remove/' + itemId, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.items = this.items.filter(i => i.id !== itemId);
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    }
                } catch (err) {
                    console.error('Error removing item:', err);
                }
            },

            syncWithBackend(backendItems) {
                backendItems.forEach(bItem => {
                    const local = this.items.find(i => i.id === bItem.id);
                    if (local) {
                        local.quantity = bItem.quantity;
                        local.subtotal = bItem.subtotal;
                        local.unit_rate = bItem.unit_rate;
                        local.formatted_subtotal = bItem.formatted_subtotal;
                        local.is_selected = bItem.is_selected;
                    }
                });
            }
        };
    }
    </script>
</x-frontend.layout>