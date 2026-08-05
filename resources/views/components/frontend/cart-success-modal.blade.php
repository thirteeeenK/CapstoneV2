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
         class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[100]"></div>

    {{-- Modal Card --}}
    <div x-show="isOpen" 
         x-cloak 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4" 
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200 transform" 
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4" 
         class="fixed inset-0 flex items-center justify-center p-4 z-[101]">

        <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden font-body relative">
            
            {{-- Close Button --}}
            <button @click="closeModal()" class="absolute top-4 right-4 z-10 p-2 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 transition cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>

            {{-- Top Hero Header --}}
            <div class="p-6 bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-800 text-white text-center relative overflow-hidden">
                <div class="w-16 h-16 bg-white/15 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-3 border border-white/20 shadow-inner">
                    <span class="material-symbols-outlined text-3xl text-emerald-200">check_circle</span>
                </div>
                <h3 class="text-xl font-extrabold tracking-tight">Added to Trip Basket!</h3>
                <p class="text-xs text-emerald-100 mt-1 font-medium">Your travel itinerary has been updated successfully.</p>
            </div>

            {{-- Item Details Body --}}
            <div class="p-6 space-y-4 bg-white">
                <template x-if="itemData">
                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80 flex gap-4 items-center">
                        <img :src="itemData.image" :alt="itemData.title" class="w-16 h-16 rounded-xl object-cover border border-slate-200 shrink-0">
                        
                        <div class="flex-1 min-w-0">
                            <template x-if="itemData.hotel_name">
                                <div class="flex items-center gap-1 text-[11px] font-extrabold text-sky-900 mb-0.5">
                                    <span class="material-symbols-outlined text-[13px] text-amber-500">hotel</span>
                                    <span class="truncate" x-text="itemData.hotel_name"></span>
                                </div>
                            </template>
                            
                            <h4 class="text-sm font-bold text-slate-900 truncate" x-text="itemData.title"></h4>
                            <p class="text-xs text-sky-700 font-medium mt-0.5 truncate" x-text="itemData.subtitle"></p>
                            
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-xs text-slate-500 font-medium" x-text="'Qty: ' + (itemData.quantity || 1)"></span>
                                <span class="text-sm font-black text-slate-900" x-text="itemData.formatted_subtotal"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Quick Actions --}}
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <button @click="closeModal()" 
                            class="w-full py-3 px-4 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-100 transition text-center cursor-pointer">
                        Keep Exploring
                    </button>
                    
                    <button @click="viewBasket()" 
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/30 transition text-center flex items-center justify-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">shopping_basket</span>
                        <span>View Basket</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
