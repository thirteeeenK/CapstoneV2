<div x-data="cartDrawer()"
     x-init="initDrawer()"
     x-effect="window.dispatchEvent(new CustomEvent('cart-drawer-toggle', { detail: { open: isOpen } }))"
     @open-cart-drawer.window="openDrawer()"
     @cart-updated.window="fetchCartData()"
     class="relative z-50">

    {{-- Backdrop --}}
    <div x-show="isOpen"
         x-cloak
         @click="closeDrawer()"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-ink-900/40 backdrop-blur-sm"></div>

    {{-- Drawer Panel --}}
    <div x-show="isOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         @keydown.escape.window="closeDrawer()"
         role="dialog"
         aria-modal="true"
         aria-label="Trip basket"
         class="fixed inset-y-0 right-0 max-w-full flex justify-end z-50">

        <div class="w-[88vw] sm:w-[420px] max-w-md bg-white flex flex-col font-body shadow-2xl shadow-ocean-900/30 h-full">

            {{-- Header --}}
            <div class="flex items-center justify-between px-4 sm:px-6 py-4 sm:py-5 bg-sand-50/90 border-b border-sand-200/80 shrink-0">
                <div class="flex items-center gap-2 sm:gap-3">
                    <button @click="closeDrawer()"
                            aria-label="Back to page"
                            class="w-9 h-9 rounded-full flex items-center justify-center text-ink-600 hover:text-ink-900 hover:bg-sand-200/80 transition cursor-pointer shrink-0">
                        <span class="material-symbols-outlined text-[22px]">arrow_back</span>
                    </button>
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-ocean-50 border border-ocean-100 flex items-center justify-center text-ocean-600 shrink-0">
                            <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
                        </div>
                        <div>
                            <h2 class="font-headline text-base font-bold tracking-tight text-ink-900 leading-tight">Trip Basket</h2>
                            <p class="text-xs text-ink-500 font-medium" x-text="totalCount + ' item' + (totalCount === 1 ? '' : 's')"></p>
                        </div>
                    </div>
                </div>
                <button @click="closeDrawer()"
                        aria-label="Close basket"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-sand-200/70 transition cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            {{-- Select All Toolbar --}}
            <div x-show="items.length > 0" class="flex items-center justify-between px-4 sm:px-6 py-2.5 bg-sand-100/70 border-b border-sand-200 text-xs font-bold text-ink-700 shrink-0 select-none">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox"
                           :checked="isAllSelected"
                           @change="toggleSelectAll()"
                           class="w-4 h-4 rounded text-ocean-600 accent-ocean-600 border-sand-300 focus:ring-ocean-500 cursor-pointer">
                    <span x-text="isAllSelected ? 'Deselect All' : 'Select All'"></span>
                </label>
                <span class="text-[11px] text-ink-400 font-semibold" x-text="selectedCount + ' of ' + items.length + ' selected'"></span>
            </div>

            {{-- Cart Items List --}}
            <div class="flex-1 overflow-y-auto bg-sand-50/50 divide-y divide-sand-200/80">
                <template x-if="loading">
                    <div class="py-16 text-center">
                        <div class="mx-auto mb-4 w-8 h-8 rounded-full border-2 border-ocean-200 border-t-ocean-600 animate-spin"></div>
                        <p class="text-xs font-medium tracking-wide text-ink-400">Loading your basket…</p>
                    </div>
                </template>

                <template x-if="!loading && items.length === 0">
                    <div class="py-16 text-center px-6 sm:px-8">
                        <svg viewBox="0 0 96 96" fill="none" aria-hidden="true" class="w-20 h-20 mx-auto mb-4">
                            <circle cx="48" cy="48" r="45" class="stroke-sand-200" stroke-width="1.5"/>
                            <circle cx="48" cy="41" r="8" class="fill-ocean-200"/>
                            <path d="M48 26v-3M48 56v-3M31 41h-3M68 41h-3M36 29l-2-2M62 53l-2-2M60 29l2-2M34 53l2-2"
                                  class="stroke-ocean-300" stroke-width="2" stroke-linecap="round"/>
                            <path d="M23 68c7-5.5 14-5.5 21 0s14 5.5 21 0" class="stroke-ocean-400" stroke-width="2.5" stroke-linecap="round"/>
                            <path d="M30 76c5.5-4.3 11-4.3 16.5 0s11 4.3 16.5 0" class="stroke-ocean-200" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                        <h3 class="font-headline text-base font-bold text-ink-900">Your basket is empty</h3>
                        <p class="text-xs text-ink-500 mt-1.5 leading-relaxed max-w-[240px] mx-auto">Rooms, experiences and packages you pick will gather here, ready for booking.</p>
                        <a href="{{ route('destinations.index') }}"
                           class="mt-5 inline-flex items-center gap-1.5 rounded-2xl bg-ocean-600 hover:bg-ocean-500 text-white text-xs font-bold py-2.5 px-5 shadow-xs transition cursor-pointer">
                            <span>Start exploring</span>
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </div>
                </template>

                <template x-for="group in displayGroups" :key="group.id">
                    <div class="bg-gradient-to-r from-ocean-700 to-sky-500">
                        <div class="px-4 sm:px-6 py-3.5 flex items-center gap-3">
                            <input type="checkbox"
                                   :checked="group.is_selected"
                                   @change="toggleGroup(group.id)"
                                   :aria-label="'Select or deselect the ' + group.title"
                                   class="w-4 h-4 rounded bg-white accent-ocean-700 border-white/50 focus:ring-white cursor-pointer shrink-0">
                            <div class="flex-1 min-w-0">
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/20 text-white border border-white/30 px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase">
                                    <span class="material-symbols-outlined text-[12px]" x-text="group.icon || 'casino'"></span>
                                    <span x-text="group.label || 'I\'m Feeling Lucky'"></span>
                                </span>
                                <h4 class="text-xs font-bold text-white leading-snug mt-1 truncate" x-text="group.title"></h4>
                                <p class="text-[11px] text-white/80 truncate">
                                    <span x-text="group.item_count + ' items'"></span>
                                    <span x-text="' • ' + group.formatted_subtotal"></span>
                                </p>
                            </div>
                        </div>
                        <div class="bg-white divide-y divide-sand-200/80">
                            <template x-for="item in group.items" :key="item.id">
                                @include('cart.partials.drawer-item')
                            </template>
                        </div>
                    </div>
                </template>

                <template x-for="item in ungroupedItems" :key="item.id">
                    @include('cart.partials.drawer-item')
                </template>
            </div>

            {{-- Footer Summary --}}
            <div x-show="items.length > 0" class="px-4 sm:px-6 py-4 sm:py-5 bg-white border-t border-sand-200 space-y-3.5 shrink-0 shadow-lg">
                <div class="space-y-1.5">
                    <div class="flex justify-between text-xs text-ink-500">
                        <span>Selected items</span>
                        <span class="font-bold text-ink-700" x-text="selectedCount + ' of ' + items.length"></span>
                    </div>
                    <div class="flex justify-between items-baseline pt-0.5">
                        <span class="font-headline text-sm font-bold text-ink-900">Trip total</span>
                        <span class="font-headline text-xl font-black text-ocean-700" x-text="formattedSubtotal"></span>
                    </div>
                </div>

                <a href="{{ route('checkout.index') }}" @click.prevent="proceedToCheckout()"
                   class="w-full py-3 rounded-2xl bg-gradient-to-r from-ocean-600 to-ocean-700 hover:from-ocean-500 hover:to-ocean-600 text-white text-xs sm:text-sm font-bold shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">verified_user</span>
                    <span>Proceed to Checkout</span>
                </a>

                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('cart.index') }}"
                       class="px-3 py-2.5 rounded-xl border border-sand-300 bg-sand-50 hover:bg-sand-100 text-ink-700 font-bold text-xs transition text-center flex items-center justify-center gap-1 cursor-pointer">
                        <span>Full basket</span>
                        <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                    </a>
                    <button @click="clearCart()"
                            class="px-3 py-2.5 rounded-xl border border-rose-200 text-rose-600 hover:text-rose-700 hover:bg-rose-50 font-bold text-xs transition text-center cursor-pointer">
                        Clear basket
                    </button>
            </div>

        </div>
    </div>
</div>

<script>
function cartDrawer() {
    return {
        isOpen: false,
        loading: false,
        items: [],
        groups: [],
        totalCount: 0,
        selectedCount: 0,
        subtotal: 0,
        formattedSubtotal: '₱0.00',
        get isAllSelected() {
            return this.items.length > 0 && this.items.every(i => i.is_selected);
        },

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
                    formatted_subtotal: '₱' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                };
            });
        },

        get ungroupedItems() {
            return this.items.filter(i => !i.lucky_group_id);
        },

        initDrawer() {
            this.fetchCartData();
        },

        openDrawer() {
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
            window.dispatchEvent(new CustomEvent('cart-drawer-toggle', { detail: { open: true } }));
            this.fetchCartData();
        },

        closeDrawer() {
            this.isOpen = false;
            document.body.style.overflow = '';
            window.dispatchEvent(new CustomEvent('cart-drawer-toggle', { detail: { open: false } }));
        },

        async fetchCartData() {
            this.loading = true;
            try {
                const res = await fetch('{{ route("cart.data") }}', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.items = data.items;
                    this.groups = data.groups || [];
                    this.totalCount = data.total_count;
                    this.selectedCount = data.selected_count;
                    this.subtotal = data.subtotal;
                    this.formattedSubtotal = data.formatted_subtotal;
                }
            } catch (err) {
                console.error('Failed to load cart data:', err);
            } finally {
                this.loading = false;
            }
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
            window.location.href = '{{ route('checkout.index') }}';
        },

        async toggleSelect(id) {
            try {
                const res = await fetch(`/cart/toggle/${id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.items = data.items;
                    this.groups = data.groups || [];
                    this.selectedCount = data.selected_count;
                    this.subtotal = data.subtotal;
                    this.formattedSubtotal = data.formatted_subtotal;
                }
            } catch (err) {
                console.error('Error toggling cart item:', err);
            }
        },

        async toggleSelectAll() {
            const target = !this.isAllSelected;
            // Optimistic update
            this.items.forEach(i => i.is_selected = target);
            try {
                const res = await fetch('{{ route("cart.toggle-all") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ is_selected: target })
                });
                const data = await res.json();
                if (data.success) {
                    this.items = data.items;
                    this.groups = data.groups || [];
                    this.selectedCount = data.selected_count;
                    this.subtotal = data.subtotal;
                    this.formattedSubtotal = data.formatted_subtotal;
                }
            } catch (err) {
                console.error('Error toggling all cart items:', err);
            }
        },

        async toggleGroup(groupId) {
            try {
                const res = await fetch(`/cart/toggle-group/${groupId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.items = data.items;
                    this.groups = data.groups || [];
                    this.selectedCount = data.selected_count;
                    this.subtotal = data.subtotal;
                    this.formattedSubtotal = data.formatted_subtotal;
                } else {
                    alert(data.message || 'Could not update the itinerary selection.');
                }
            } catch (err) {
                console.error('Error toggling itinerary group:', err);
            }
        },

        async updatePax(id, newPax) {
            if (newPax < 1) return;
            try {
                const res = await fetch(`/cart/update/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ selected_pax: newPax })
                });
                const data = await res.json();
                if (data.success) {
                    this.items = data.items;
                    this.groups = data.groups || [];
                    this.totalCount = data.total_count;
                    this.selectedCount = data.selected_count;
                    this.subtotal = data.subtotal;
                    this.formattedSubtotal = data.formatted_subtotal;
                }
            } catch (err) {
                console.error('Error updating pax:', err);
            }
        },

        async updateQty(id, newQty) {
            if (newQty < 1) return this.removeItem(id);
            try {
                const res = await fetch(`/cart/update/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ quantity: newQty })
                });
                const data = await res.json();
                if (data.success) {
                    this.items = data.items;
                    this.groups = data.groups || [];
                    this.totalCount = data.total_count;
                    this.selectedCount = data.selected_count;
                    this.subtotal = data.subtotal;
                    this.formattedSubtotal = data.formatted_subtotal;
                }
            } catch (err) {
                console.error('Error updating quantity:', err);
            }
        },

        async removeItem(id) {
            if (!confirm('Remove this item from your trip basket?')) return;
            try {
                const res = await fetch(`/cart/remove/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.fetchCartData();
                }
            } catch (err) {
                console.error('Error removing item:', err);
            }
        },

        async clearCart() {
            if (!confirm('Are you sure you want to clear all items in your cart?')) return;
            try {
                const res = await fetch('{{ route("cart.clear") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.fetchCartData();
                }
            } catch (err) {
                console.error('Error clearing cart:', err);
            }
        }
    };
}
</script>