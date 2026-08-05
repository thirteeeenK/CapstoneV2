<div x-data="cartDrawer()" 
     x-init="initDrawer()"
     @open-cart-drawer.window="openDrawer()"
     @cart-updated.window="fetchCartData()"
     class="relative z-50">

    {{-- Slide-over Backdrop --}}
    <div x-show="isOpen" 
         x-cloak 
         @click="closeDrawer()" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" 
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0" 
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>

    {{-- Drawer Panel --}}
    <div x-show="isOpen" 
         x-cloak 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full" 
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform" 
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full" 
         class="fixed inset-y-0 right-0 max-w-full flex pl-10">

        <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col font-body">
            
            {{-- Header --}}
            <div class="p-5 sm:p-6 bg-gradient-to-r from-sky-900 via-sky-800 to-slate-900 text-white flex items-center justify-between border-b border-sky-700/50">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white/10 rounded-xl backdrop-blur-md">
                        <svg class="w-6 h-6 text-sky-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold tracking-tight">Your Travel Basket</h2>
                        <p class="text-xs text-sky-200 font-medium" x-text="totalCount + ' item' + (totalCount === 1 ? '' : 's') + ' selected'"></p>
                    </div>
                </div>
                <button @click="closeDrawer()" class="p-2 rounded-full hover:bg-white/10 text-slate-300 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Cart Items List --}}
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 bg-slate-50/60">
                <template x-if="loading">
                    <div class="py-12 text-center text-slate-400">
                        <div class="inline-block animate-spin w-8 h-8 border-4 border-sky-600 border-t-transparent rounded-full mb-3"></div>
                        <p class="text-xs font-semibold uppercase tracking-wider">Loading your basket...</p>
                    </div>
                </template>

                <template x-if="!loading && items.length === 0">
                    <div class="py-16 text-center">
                        <div class="w-20 h-20 bg-sky-100/80 text-sky-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-sky-200">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-800">Your basket is empty</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">Explore destinations, room stays, and activities to build your dream vacation!</p>
                    </div>
                </template>

                <template x-for="item in items" :key="item.id">
                    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:shadow-md transition-all duration-200 relative group"
                         :class="!item.is_selected ? 'opacity-60 bg-slate-100/50' : ''">
                        
                        <div class="flex gap-3.5 items-start">
                            {{-- Checkbox --}}
                            <div class="pt-1">
                                <input type="checkbox" 
                                       :checked="item.is_selected" 
                                       @change="toggleSelect(item.id)" 
                                       class="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500 cursor-pointer">
                            </div>

                            {{-- Image --}}
                            <img :src="item.image" :alt="item.title" class="w-16 h-16 rounded-xl object-cover border border-slate-100 shrink-0">

                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <template x-if="item.hotel_name">
                                            <div class="flex items-center gap-1 text-[11px] font-extrabold text-sky-900 mb-0.5">
                                                <span class="material-symbols-outlined text-[14px] text-amber-500">hotel</span>
                                                <span x-text="item.hotel_name"></span>
                                            </div>
                                        </template>
                                        <h4 class="text-sm font-bold text-slate-900 truncate leading-snug" x-text="item.title"></h4>
                                    </div>
                                    <button @click="removeItem(item.id)" class="text-slate-400 hover:text-rose-500 transition shrink-0 p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                                
                                <p class="text-xs text-sky-700 font-medium mt-0.5" x-text="item.subtitle"></p>

                                {{-- Quantity Controls & Price --}}
                                <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100">
                                    <div class="flex items-center gap-1.5 bg-slate-100 rounded-lg p-1 border border-slate-200/60">
                                        <button @click="updateQty(item.id, item.quantity - 1)" 
                                                class="w-6 h-6 rounded-md bg-white text-slate-700 hover:bg-slate-200 font-bold text-xs flex items-center justify-center shadow-2xs transition">-</button>
                                        <span class="w-6 text-center text-xs font-bold text-slate-800" x-text="item.quantity"></span>
                                        <button @click="updateQty(item.id, item.quantity + 1)" 
                                                class="w-6 h-6 rounded-md bg-white text-slate-700 hover:bg-slate-200 font-bold text-xs flex items-center justify-center shadow-2xs transition">+</button>
                                    </div>

                                    <div class="text-right">
                                        <span class="text-xs text-slate-400 block" x-show="item.quantity > 1" x-text="item.quantity + 'x ' + item.formatted_unit_rate"></span>
                                        <span class="text-sm font-black text-slate-900" x-text="item.formatted_subtotal"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer Summary --}}
            <div x-show="items.length > 0" class="p-5 sm:p-6 bg-white border-t border-slate-200/80 shadow-lg space-y-4">
                <div class="space-y-2">
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>Selected Items</span>
                        <span class="font-bold text-slate-700" x-text="selectedCount + ' of ' + items.length"></span>
                    </div>
                    <div class="flex justify-between items-baseline pt-1">
                        <span class="text-sm font-bold text-slate-800">Trip Total</span>
                        <span class="text-xl font-black text-sky-700" x-text="formattedSubtotal"></span>
                    </div>
                </div>

                <a href="{{ route('checkout.index') }}" 
                   class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-extrabold text-xs shadow-md shadow-sky-600/30 transition text-center flex items-center justify-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">lock</span>
                    <span>Proceed to Checkout</span>
                </a>

                <div class="grid grid-cols-2 gap-2.5">
                    <button @click="clearCart()" 
                            class="px-3 py-2 rounded-xl border border-slate-300 font-bold text-xs text-slate-600 hover:bg-slate-50 transition text-center cursor-pointer">
                        Clear Basket
                    </button>
                    <a href="{{ route('cart.index') }}" 
                       class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition text-center flex items-center justify-center gap-1 cursor-pointer">
                        <span>Full Basket View</span>
                    </a>
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
            this.fetchCartData();
        },

        closeDrawer() {
            this.isOpen = false;
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
