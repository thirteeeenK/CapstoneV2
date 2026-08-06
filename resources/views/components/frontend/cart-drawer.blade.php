<div x-data="cartDrawer()"
     x-init="initDrawer()"
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
         class="fixed inset-y-0 right-0 max-w-full flex pl-10">

        <div class="w-screen max-w-md bg-white flex flex-col font-body shadow-2xl shadow-ocean-900/10">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 sm:px-6 py-5 bg-sand-50 border-b border-sand-200">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-full bg-ocean-50 border border-ocean-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-ocean-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-display text-base font-bold tracking-tight text-ink-900 leading-tight">Trip Basket</h2>
                        <p class="text-xs text-ink-500 font-medium mt-0.5" x-text="totalCount + ' item' + (totalCount === 1 ? '' : 's')"></p>
                    </div>
                </div>
                <button @click="closeDrawer()"
                        aria-label="Close basket"
                        class="w-9 h-9 rounded-full flex items-center justify-center text-ink-400 hover:text-ink-700 hover:bg-white transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Cart Items List --}}
            <div class="flex-1 overflow-y-auto bg-sand-50 divide-y divide-sand-200/80">
                <template x-if="loading">
                    <div class="py-16 text-center">
                        <div class="mx-auto mb-4 w-8 h-8 rounded-full border-2 border-ocean-200 border-t-ocean-600 animate-spin"></div>
                        <p class="text-xs font-medium tracking-wide text-ink-400">Loading your basket…</p>
                    </div>
                </template>

                <template x-if="!loading && items.length === 0">
                    <div class="py-16 text-center px-8">
                        <svg viewBox="0 0 96 96" fill="none" aria-hidden="true" class="w-24 h-24 mx-auto mb-5">
                            <circle cx="48" cy="48" r="45" class="stroke-sand-200" stroke-width="1.5"/>
                            <circle cx="48" cy="41" r="8" class="fill-ocean-200"/>
                            <path d="M48 26v-3M48 56v-3M31 41h-3M68 41h-3M36 29l-2-2M62 53l-2-2M60 29l2-2M34 53l2-2"
                                  class="stroke-ocean-300" stroke-width="2" stroke-linecap="round"/>
                            <path d="M23 68c7-5.5 14-5.5 21 0s14 5.5 21 0" class="stroke-ocean-400" stroke-width="2.5" stroke-linecap="round"/>
                            <path d="M30 76c5.5-4.3 11-4.3 16.5 0s11 4.3 16.5 0" class="stroke-ocean-200" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                        <h3 class="font-display text-base font-bold text-ink-900">Your basket is empty</h3>
                        <p class="text-sm text-ink-500 mt-1.5 leading-relaxed max-w-[240px] mx-auto">Rooms, experiences and packages you pick here will gather, ready for booking.</p>
                        <a href="{{ route('destinations.index') }}"
                           class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-ocean-600 hover:bg-ocean-500 text-white text-sm font-semibold py-2.5 px-5 shadow-sm shadow-ocean-600/25 transition cursor-pointer">
                            <span>Start exploring</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    </div>
                </template>

                <template x-for="item in items" :key="item.id">
                    <div class="px-5 sm:px-6 py-5 flex gap-4 transition-opacity duration-200"
                         :class="item.is_selected ? '' : 'opacity-50'">

                        {{-- Select --}}
                        <div class="pt-0.5">
                            <input type="checkbox"
                                   :checked="item.is_selected"
                                   @change="toggleSelect(item.id)"
                                   :aria-label="'Select ' + item.title"
                                   class="w-4 h-4 rounded text-ocean-600 accent-ocean-600 border-sand-300 focus:ring-ocean-500 focus:ring-offset-1 cursor-pointer">
                        </div>

                        {{-- Image --}}
                        <img :src="item.image" :alt="item.title" class="w-20 h-20 rounded-xl object-cover shrink-0 ring-1 ring-sand-200">

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <template x-if="item.hotel_name">
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-ocean-700 mb-1">
                                            <span class="material-symbols-outlined text-sm text-ocean-600">hotel</span>
                                            <span class="truncate" x-text="item.hotel_name"></span>
                                        </div>
                                    </template>
                                    <h4 class="text-sm font-semibold text-ink-900 leading-snug truncate" x-text="item.title"></h4>
                                    <p class="text-xs text-ink-500 mt-1 leading-relaxed line-clamp-1" x-text="item.subtitle"></p>
                                </div>
                                <button @click="removeItem(item.id)"
                                        :aria-label="'Remove ' + item.title"
                                        class="shrink-0 p-1.5 rounded-full text-rose-600 bg-rose-50 border border-rose-100 hover:bg-rose-100 hover:text-rose-700 transition cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>

                            <div class="flex items-center justify-between mt-3.5">
                                {{-- Pax Stepper for Addons/Transfers/Activities/Packages --}}
                                <template x-if="item.item_type === 'addon' || item.item_type === 'activity' || item.item_type === 'package'">
                                    <div class="flex items-center rounded-full border border-sand-200 bg-white p-1">
                                        <button @click="updatePax(item.id, (item.selected_pax || 1) - 1)"
                                                :disabled="item.selected_pax <= 1"
                                                :aria-label="'Decrease ' + item.title + ' passenger count'"
                                                class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/>
                                            </svg>
                                        </button>
                                        <span class="w-10 text-center text-xs font-semibold text-ink-900" x-text="item.selected_pax + ' pax'"></span>
                                        <button @click="updatePax(item.id, (item.selected_pax || 1) + 1)"
                                                :aria-label="'Increase ' + item.title + ' passenger count'"
                                                class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 transition cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </template>

                                {{-- Quantity Stepper for Rooms --}}
                                <template x-if="item.item_type !== 'addon' && item.item_type !== 'activity' && item.item_type !== 'package'">
                                    <div class="flex items-center rounded-full border border-sand-200 bg-white p-1">
                                        <button @click="updateQty(item.id, item.quantity - 1)"
                                                :disabled="item.quantity <= 1"
                                                :aria-label="'Decrease ' + item.title + ' quantity'"
                                                class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/>
                                            </svg>
                                        </button>
                                        <span class="w-8 text-center text-sm font-semibold text-ink-900" x-text="item.quantity"></span>
                                        <button @click="updateQty(item.id, item.quantity + 1)"
                                                :aria-label="'Increase ' + item.title + ' quantity'"
                                                class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 transition cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </template>

                                {{-- Price --}}
                                <div class="text-right">
                                    <template x-if="item.item_type === 'addon' || item.item_type === 'activity' || item.item_type === 'package'">
                                        <span class="block text-xs text-ink-500 font-medium" x-text="item.formatted_unit_rate + ' / pax'"></span>
                                    </template>
                                    <template x-if="item.item_type !== 'addon' && item.item_type !== 'activity' && item.item_type !== 'package' && item.quantity > 1">
                                        <span class="block text-xs text-ink-400" x-text="item.quantity + ' × ' + item.formatted_unit_rate"></span>
                                    </template>
                                    <span class="block text-sm font-bold text-ink-900" x-text="item.formatted_subtotal"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer Summary --}}
            <div x-show="items.length > 0" class="px-5 sm:px-6 py-5 bg-sand-50 border-t border-sand-200 space-y-4">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm text-ink-500">
                        <span>Selected items</span>
                        <span class="font-semibold text-ink-700" x-text="selectedCount + ' of ' + items.length"></span>
                    </div>
                    <div class="flex justify-between items-baseline pt-0.5">
                        <span class="font-display text-sm font-bold text-ink-900">Trip total</span>
                        <span class="font-display text-xl font-bold text-ocean-700" x-text="formattedSubtotal"></span>
                    </div>
                </div>

                <a href="{{ route('checkout.index') }}" @click.prevent="proceedToCheckout()"
                   class="w-full py-3.5 rounded-full bg-ocean-600 hover:bg-ocean-500 text-white text-sm font-semibold shadow-sm shadow-ocean-600/25 transition flex items-center justify-center gap-2 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ocean-500 focus-visible:ring-offset-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span>Proceed to Checkout</span>
                </a>

                <div class="grid grid-cols-2 gap-2.5">
                    <a href="{{ route('cart.index') }}"
                       class="px-3 py-2.5 rounded-full border border-sand-300 bg-white text-ink-700 hover:bg-sand-100 font-semibold text-xs transition text-center flex items-center justify-center gap-1.5 cursor-pointer">
                        <span>Full basket view</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                    <button @click="clearCart()"
                            class="px-3 py-2.5 rounded-full border border-transparent text-rose-600 hover:text-rose-700 hover:bg-rose-50 font-semibold text-xs transition text-center cursor-pointer">
                        Clear basket
                    </button>
                </div>
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
        totalCount: 0,
        selectedCount: 0,
        subtotal: 0,
        formattedSubtotal: '₱0.00',

        initDrawer() {
            this.fetchCartData();
        },

        openDrawer() {
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
            this.fetchCartData();
        },

        closeDrawer() {
            this.isOpen = false;
            document.body.style.overflow = '';
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
                    this.selectedCount = data.selected_count;
                    this.subtotal = data.subtotal;
                    this.formattedSubtotal = data.formatted_subtotal;
                }
            } catch (err) {
                console.error('Error toggling cart item:', err);
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