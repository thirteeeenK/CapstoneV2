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
            'min_pax' => ($item->item_type === 'package' && $item->itemable) ? (int) $item->itemable->min_pax : null,
            'is_selected' => (bool) $item->is_selected,
            'is_expired' => $item->isExpired(),
            'check_in_date' => $item->check_in_date ? \Illuminate\Support\Carbon::parse($item->check_in_date)->format('Y-m-d') : null,
            'check_out_date' => $item->check_out_date ? \Illuminate\Support\Carbon::parse($item->check_out_date)->format('Y-m-d') : null,
            'lucky_group_id' => $item->lucky_group_id,
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
    <div x-data="cartPageManager({{ json_encode($initialItems) }}, {{ json_encode($groups) }})" class="min-h-screen bg-sand-50/70 font-body">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 sm:pt-28 pb-10 sm:pb-16">
            @if(session('error'))
                <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 flex items-start gap-3 text-sm text-rose-800 shadow-2xs">
                    <span class="material-symbols-outlined text-rose-600 text-xl shrink-0 mt-0.5">error</span>
                    <span class="font-semibold leading-relaxed">{{ session('error') }}</span>
                </div>
            @endif
            @if(session('success'))
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 flex items-start gap-3 text-sm text-emerald-800 shadow-2xs">
                    <span class="material-symbols-outlined text-emerald-600 text-xl shrink-0 mt-0.5">check_circle</span>
                    <span class="font-semibold leading-relaxed">{{ session('success') }}</span>
                </div>
            @endif

            {{-- Breadcrumb & Title --}}
            <div class="mb-5 sm:mb-8 flex flex-wrap items-end justify-between gap-3 sm:gap-4">
                <div>
                    <nav class="flex items-center gap-1.5 text-xs sm:text-sm text-ink-400 mb-1.5 sm:mb-2" aria-label="Breadcrumb">
                        <a href="/" class="hover:text-ocean-600 transition">Home</a>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-ink-600 font-medium">Trip Basket</span>
                    </nav>
                    <h1 class="font-display text-2xl sm:text-4xl font-bold tracking-tight text-ink-900">
                        Your Trip Basket
                    </h1>
                    <p class="text-xs sm:text-sm text-ink-500 mt-1 sm:mt-1.5">
                        <span x-text="totalCount + ' ' + (totalCount === 1 ? 'item' : 'items')"></span>
                        in your basket — <span x-text="selectedCount"></span> selected for booking.
                    </p>
                </div>

                <a href="{{ route('destinations.index') }}"
                   class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-ocean-700 hover:text-ocean-900 transition group">
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
                <div class="lg:col-span-2 space-y-4 sm:space-y-5">

                    {{-- Select All / Bulk Action Toolbar --}}
                    <div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-sand-200 shadow-xs flex flex-wrap items-center justify-between gap-3 select-none">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox"
                                   :checked="isAllSelected"
                                   @change="toggleSelectAll()"
                                   class="w-4 h-4 sm:w-5 sm:h-5 rounded text-ocean-600 accent-ocean-600 border-sand-300 focus:ring-ocean-500 cursor-pointer">
                            <span class="font-headline font-bold text-xs sm:text-sm text-ink-900" x-text="isAllSelected ? 'Deselect All (' + totalCount + ' items)' : 'Select All (' + totalCount + ' items)'"></span>
                        </label>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold text-ink-500">
                                <span class="text-ocean-700 font-bold" x-text="selectedCount"></span> of <span x-text="totalCount"></span> selected
                            </span>
                            <span class="text-sand-300">•</span>
                            <button type="button" @click="clearCart()" class="text-xs font-bold text-rose-600 hover:text-rose-700 hover:underline cursor-pointer">
                                Clear basket
                            </button>
                        </div>
                    </div>

                    {{-- "I'm Feeling Lucky" Itinerary Groups --}}
                    <template x-for="group in displayGroups" :key="group.id">
                        <section class="overflow-hidden rounded-2xl bg-white ring-1 ring-ocean-200 shadow-sm shadow-ocean-900/5">
                            <div class="bg-gradient-to-r from-ocean-700 to-sky-500 px-5 sm:px-6 py-4 flex flex-wrap items-center gap-4">
                                <input type="checkbox"
                                       :checked="group.is_selected"
                                       @change="toggleGroup(group.id)"
                                       :aria-label="'Select or deselect the ' + group.title"
                                       class="w-5 h-5 rounded bg-white accent-ocean-700 border-white/50 focus:ring-white cursor-pointer">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-white/20 text-white border border-white/30 px-2.5 py-0.5 text-[11px] font-bold tracking-wide uppercase">
                                            <span class="material-symbols-outlined text-[13px]">casino</span>
                                            I'm Feeling Lucky
                                        </span>
                                        <span class="inline-flex items-center gap-1 text-xs text-white/90">
                                            <span class="material-symbols-outlined text-sm">location_on</span>
                                            <span x-text="group.destination_name"></span>
                                        </span>
                                    </div>
                                    <h3 class="font-display text-base sm:text-lg font-bold text-white mt-1.5" x-text="group.title"></h3>
                                    <p class="text-xs text-white/80 mt-0.5">
                                        <span x-text="group.item_count + ' ' + (group.item_count === 1 ? 'item' : 'items')"></span>
                                        <span x-text="group.selected_count === group.item_count ? ' • fully selected' : ''"></span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="block text-[11px] font-semibold text-white/70 uppercase tracking-wide">Itinerary total</span>
                                    <span class="block font-display text-lg font-bold text-white" x-text="group.formatted_subtotal"></span>
                                </div>
                            </div>
                            <div class="divide-y divide-sand-200/80">
                                <template x-for="item in group.items" :key="item.id">
                                    @include('cart.partials.item-card')
                                </template>
                            </div>
                        </section>
                    </template>

                    {{-- Regular (ungrouped) Items --}}
                    <template x-for="item in ungroupedItems" :key="item.id">
                        @include('cart.partials.item-card')
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

                        <a href="{{ route('checkout.index') }}" @click.prevent="proceedToCheckout()"
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
    function cartPageManager(initialItems, initialGroups) {
        return {
            items: initialItems || [],
            groups: initialGroups || [],

            get displayGroups() {
                return this.groups.map(g => {
                    const gItems = this.items.filter(i => i.lucky_group_id === g.id);
                    const selected = gItems.filter(i => i.is_selected);
                    const subtotal = selected.reduce((sum, i) => sum + Number(i.subtotal), 0);
                    return {
                        ...g,
                        items: gItems,
                        is_selected: gItems.length > 0 && selected.length === gItems.length,
                        item_count: gItems.length,
                        selected_count: selected.length,
                        subtotal,
                        formatted_subtotal: '₱' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    };
                });
            },

            get ungroupedItems() {
                return this.items.filter(i => !i.lucky_group_id);
            },

            get selectedItems() {
                return this.items.filter(i => i.is_selected);
            },

            get isAllSelected() {
                return this.items.length > 0 && this.items.every(i => i.is_selected);
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

            proceedToCheckout() {
                const violations = this.items.filter(i =>
                    i.item_type === 'package' && i.is_selected &&
                    Number(i.min_pax) > 1 && Number(i.selected_pax) < Number(i.min_pax)
                );
                if (violations.length) {
                    alert(violations.map(v =>
                        `"${v.title}" requires a minimum of ${v.min_pax} participants. Please increase the number of participants to continue with your booking.`
                    ).join('\n\n'));
                    return;
                }
                const expiredSelected = this.items.filter(i => i.is_selected && i.is_expired);
                if (expiredSelected.length) {
                    alert('Your Trip Basket contains stays with dates that have already passed. Please update the dates or remove the items before checkout.\n\n' + expiredSelected.map(e => `"${e.title}" — ${e.date_details || e.check_in_date + ' to ' + e.check_out_date} is past. Please update or deselect it.`).join('\n'));
                    return;
                }
                window.location.href = '{{ route('checkout.index') }}';
            },

            async updatePax(itemId, delta) {
                const item = this.items.find(i => i.id === itemId);
                if (!item) return;

                const targetPax = Math.max(1, (Number(item.selected_pax) || 1) + delta);
                if (targetPax === item.selected_pax) return;

                const prevPax = item.selected_pax;
                item.selected_pax = targetPax;

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
                        body: JSON.stringify({ selected_pax: targetPax })
                    });

                    const data = await res.json();
                    if (data.success && data.items) {
                        this.syncWithBackend(data);
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    } else {
                        item.selected_pax = prevPax;
                        alert(data.message || 'Could not update passenger count.');
                    }
                } catch (err) {
                    console.error('Error updating pax:', err);
                    item.selected_pax = prevPax;
                }
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
                        this.syncWithBackend(data);
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
                        this.syncWithBackend(data);
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    }
                } catch (err) {
                    console.error('Error toggling selection:', err);
                }
            },

            async toggleGroup(groupId) {
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('/cart/toggle-group/' + groupId, {
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
                        this.syncWithBackend(data);
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    } else {
                        alert(data.message || 'Could not update the itinerary selection.');
                    }
                } catch (err) {
                    console.error('Error toggling itinerary group:', err);
                }
            },

            async toggleSelectAll() {
                const target = !this.isAllSelected;
                this.items.forEach(i => i.is_selected = target);
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('{{ route("cart.toggle-all") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ is_selected: target })
                    });
                    const data = await res.json();
                    if (data.success && data.items) {
                        this.syncWithBackend(data);
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    }
                } catch (err) {
                    console.error('Error toggling all:', err);
                }
            },

            async clearCart() {
                if (!confirm('Are you sure you want to clear your entire basket?')) return;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('/cart/clear', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.items = [];
                        this.groups = [];
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    }
                } catch (err) {
                    console.error('Error clearing cart:', err);
                }
            },

            async removeItem(itemId) {
                if (!confirm('Remove this item from your trip basket?')) return;
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
                        this.groups = this.groups.filter(g => this.items.some(i => i.lucky_group_id === g.id));
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                    }
                } catch (err) {
                    console.error('Error removing item:', err);
                }
            },

            syncWithBackend(data) {
                (data.items || []).forEach(bItem => {
                    const local = this.items.find(i => i.id === bItem.id);
                    if (local) {
                        local.quantity = bItem.quantity;
                        local.selected_pax = bItem.selected_pax;
                        local.subtotal = bItem.subtotal;
                        local.unit_rate = bItem.unit_rate;
                        local.formatted_unit_rate = bItem.formatted_unit_rate;
                        local.formatted_subtotal = bItem.formatted_subtotal;
                        local.is_selected = bItem.is_selected;
                        local.is_expired = bItem.is_expired;
                        local.check_in_date = bItem.check_in_date;
                        local.check_out_date = bItem.check_out_date;
                        local.date_details = bItem.date_details;
                    }
                });
                if (data.groups) {
                    this.groups = data.groups;
                }
            }
        };
    }
    </script>
</x-frontend.layout>