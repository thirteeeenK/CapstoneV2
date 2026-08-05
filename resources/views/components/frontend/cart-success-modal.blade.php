<script>
window.cartSuccessModal = function() {
    return {
        isOpen: false,
        itemData: null,

        initModal() {
            window.addEventListener('show-cart-modal', (e) => {
                console.log('Cart modal received event:', e.detail);
                this.openModal(e.detail);
            });
        },

        openModal(detail) {
            this.itemData = (detail && detail.itemData) ? detail.itemData : null;
            this.isOpen = true;
        },

        closeModal() {
            this.isOpen = false;
        },

        viewBasket() {
            this.closeModal();
            window.dispatchEvent(new CustomEvent('open-cart-drawer'));
        }
    };
};
</script>

<div x-data="cartSuccessModal()"
     x-init="initModal()"
     @show-cart-modal.window="openModal($event.detail)"
     class="relative z-[100]">

    {{-- Backdrop --}}
    <div x-show="isOpen"
         x-cloak
         @click="closeModal()"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-ink-900/50 backdrop-blur-sm z-[100]"></div>

    {{-- Modal Card --}}
    <div x-show="isOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         @keydown.escape.window="closeModal()"
         role="dialog"
         aria-modal="true"
         aria-label="Added to trip basket"
         class="fixed inset-0 flex items-center justify-center p-4 z-[101]">

        <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl shadow-ocean-900/10 ring-1 ring-sand-200 overflow-hidden font-body relative">

            {{-- Close Button --}}
            <button @click="closeModal()"
                    aria-label="Close"
                    class="absolute top-4 right-4 z-10 w-9 h-9 rounded-full flex items-center justify-center bg-sand-100 hover:bg-sand-200 text-ink-500 hover:text-ink-800 transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Header --}}
            <div class="p-6 sm:p-7 bg-sand-50 border-b border-sand-200 text-center">
                <div class="w-14 h-14 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="font-display text-lg font-bold tracking-tight text-ink-900">Added to your trip basket</h3>
                <p class="text-xs text-ink-500 mt-1 font-medium">This piece of your journey is ready when you are.</p>
            </div>

            {{-- Item Details Body --}}
            <div class="p-6 space-y-4 bg-white">
                <template x-if="itemData">
                    <div class="bg-sand-50 rounded-2xl ring-1 ring-sand-200 p-4 flex gap-4 items-center">
                        <img :src="itemData.image" :alt="itemData.title" class="w-16 h-16 rounded-xl object-cover ring-1 ring-sand-200 shrink-0">

                        <div class="flex-1 min-w-0">
                            <template x-if="itemData.hotel_name">
                                <div class="flex items-center gap-1 text-xs font-semibold text-ocean-700 mb-0.5">
                                    <span class="material-symbols-outlined text-sm text-ocean-600">hotel</span>
                                    <span class="truncate" x-text="itemData.hotel_name"></span>
                                </div>
                            </template>

                            <h4 class="text-sm font-semibold text-ink-900 truncate" x-text="itemData.title"></h4>
                            <p class="text-xs text-ink-500 mt-0.5 truncate" x-text="itemData.subtitle"></p>

                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-xs text-ink-500 font-medium" x-text="'Qty: ' + (itemData.quantity || 1)"></span>
                                <span class="text-sm font-bold text-ink-900" x-text="itemData.formatted_subtotal"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Quick Actions --}}
                <div class="grid grid-cols-2 gap-3 pt-1">
                    <button @click="closeModal()"
                            class="w-full py-3 rounded-full border border-sand-300 text-ink-700 font-semibold text-xs hover:bg-sand-50 transition text-center cursor-pointer">
                        Keep Exploring
                    </button>

                    <button @click="viewBasket()"
                            class="w-full py-3 rounded-full bg-ocean-600 hover:bg-ocean-500 text-white font-semibold text-xs shadow-sm shadow-ocean-600/25 transition text-center flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span>View Basket</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>