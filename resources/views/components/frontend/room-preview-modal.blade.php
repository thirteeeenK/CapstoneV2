{{-- Shared Room Preview Modal — driven by the global Alpine `preview` store.
     Used on the hotel page, room catalog, and chat widget. --}}
<div x-data x-show="$store.preview.room" x-transition.opacity @keydown.escape.window="$store.preview.closeRoom()"
    class="fixed inset-0 z-[110] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
    x-cloak style="display: none;">
    <div @click.away="$store.preview.closeRoom()" @click.stop
        class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full overflow-hidden border border-slate-200/80 my-auto transform transition-all">

        {{-- Modal Header --}}
        <div class="relative bg-slate-900 text-white p-6 sm:p-8 overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-ocean-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <button @click="$store.preview.closeRoom()"
                class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>

            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="px-2.5 py-0.5 rounded-full bg-ocean-500/20 text-ocean-300 text-[10px] font-bold uppercase tracking-wider border border-ocean-400/30">
                    Room Details & Overview
                </span>
                <template x-if="$store.preview.room?.view_type">
                    <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-slate-200 text-[10px] font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px]">visibility</span>
                        <span x-text="$store.preview.room.view_type"></span>
                    </span>
                </template>
            </div>

            <h2 class="text-2xl sm:text-3xl font-black text-white font-headline" x-text="$store.preview.room?.room_name"></h2>

            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-headline">
                    ₱<span x-text="Number($store.preview.computedNightlyRate).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                </span>
                <span class="text-xs text-slate-300 font-medium">/ night</span>
                <span x-show="($store.preview.room?.max_occupancy || $store.preview.room?.occupancy || 4) > ($store.preview.room?.base_occupancy || 2) && ($store.preview.room?.extra_person_fee || 0) > 0" class="text-[10px] font-bold bg-amber-400 text-slate-950 px-2 py-0.5 rounded-full ml-2" x-cloak>
                    +₱<span x-text="($store.preview.room?.extra_person_fee || 0).toLocaleString('en-US')"></span> / guest Extra Fee
                </span>
            </div>
        </div>

        {{-- Modal Body --}}
        <div class="p-6 sm:p-8 space-y-6 max-h-[65vh] overflow-y-auto">

            {{-- Image Carousel / Selector --}}
            <template x-if="$store.preview.room?.images && $store.preview.room.images.length > 0">
                <div class="space-y-3">
                    <div class="relative h-64 sm:h-80 rounded-2xl overflow-hidden bg-slate-900 group">
                        <img :src="$store.preview.room.images[$store.preview.roomImgIndex]"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            onerror="this.style.display='none'">
                        <div class="absolute bottom-3 right-3 bg-slate-950/70 backdrop-blur-md text-white text-xs px-3 py-1 rounded-lg border border-white/20">
                            Photo <span x-text="$store.preview.roomImgIndex + 1"></span> of <span x-text="$store.preview.room.images.length"></span>
                        </div>
                    </div>

                    <template x-if="$store.preview.room.images.length > 1">
                        <div class="flex items-center gap-2 overflow-x-auto pb-2">
                            <template x-for="(img, idx) in $store.preview.room.images" :key="idx">
                                <button @click="$store.preview.roomImgIndex = idx"
                                    :class="$store.preview.roomImgIndex === idx ? 'ring-2 ring-ocean-600 scale-105' : 'opacity-70 hover:opacity-100'"
                                    class="w-16 h-12 rounded-lg overflow-hidden border border-slate-200 shrink-0 transition-all">
                                    <img :src="img" class="w-full h-full object-cover">
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Specs Quick Grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                    <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Bed Layout</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">bed</span>
                        <span x-text="$store.preview.room?.bed_configuration || 'Standard'"></span>
                    </span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                    <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Base Occupancy</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">person</span>
                        <span x-text="($store.preview.room?.base_occupancy || 2) + ' Guests'"></span>
                    </span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                    <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Max Occupancy</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">group</span>
                        <span x-text="($store.preview.room?.max_occupancy || $store.preview.room?.occupancy || 4) + ' Guests'"></span>
                    </span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1" x-show="$store.preview.room?.room_size">
                    <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Room Size</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">straighten</span>
                        <span x-text="$store.preview.room?.room_size"></span>
                    </span>
                </div>
            </div>

            {{-- Description --}}
            <template x-if="$store.preview.room?.description">
                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Room Description</h4>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed bg-slate-50/70 p-4 rounded-xl border border-slate-200/60"
                        x-text="$store.preview.room.description"></p>
                </div>
            </template>

            {{-- Amenities Badges --}}
            <template x-if="$store.preview.room?.amenities && $store.preview.room.amenities.length > 0">
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Included Amenities</h4>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(amenity, idx) in $store.preview.room.amenities" :key="idx">
                            <span class="px-3 py-1.5 bg-ocean-50 text-ocean-800 rounded-lg text-xs font-semibold border border-ocean-100 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">check_circle</span>
                                <span x-text="amenity"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Room Review Summary (DSS) --}}
            <template x-if="$store.preview.room?.review_summary">
                <div class="space-y-3 rounded-2xl bg-gradient-to-br from-ocean-50/70 to-sand-50/70 border border-ocean-100 p-4">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[15px] text-ocean-600">reviews</span>
                            Guest Reviews & Sentiment
                        </h4>
                        <span class="flex items-center gap-1 text-amber-400">
                            <template x-for="i in 5" :key="i">
                                <span class="material-symbols-outlined text-[14px]" :style="'font-variation-settings: \'FILL\' ' + (i <= Math.round($store.preview.room.review_summary.average_rating) ? 1 : 0)">star</span>
                            </template>
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-600 font-bold">
                        <span x-text="$store.preview.room.review_summary.average_rating.toFixed(1)"></span> / 5.0 ·
                        <span x-text="$store.preview.room.review_summary.total_reviews"></span> verified reviews
                    </p>
                    <p class="text-[11px] text-slate-500" x-text="$store.preview.room.review_summary.ai_summary_text"></p>
                </div>
            </template>

            {{-- Room Recent Reviews --}}
            <template x-if="$store.preview.room?.reviews && $store.preview.room.reviews.length > 0">
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Recent Verified Reviews</h4>
                    <template x-for="(rv, idx) in $store.preview.room.reviews" :key="idx">
                        <div class="bg-white border border-sand-200 rounded-xl p-3.5 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-bold text-slate-900" x-text="rv.reviewer_alias"></p>
                                <span class="flex items-center gap-0.5 text-amber-400">
                                    <template x-for="i in 5" :key="i">
                                        <span class="material-symbols-outlined text-[13px]" :style="'font-variation-settings: \'FILL\' ' + (i <= rv.rating ? 1 : 0)">star</span>
                                    </template>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 leading-relaxed" x-text="rv.comment"></p>
                            <p class="text-[9px] font-label uppercase tracking-[0.15em] text-slate-400 font-bold" x-text="rv.created_at_label"></p>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Date Picker Section (store-driven; keyed by pickerEpoch to reset per room) --}}
            <div class="pt-3 border-t border-slate-200" @date-range-changed.stop="$store.preview.setRoomDates($event.detail)">
                <template x-for="n in [$store.preview.pickerEpoch]" :key="'drp-' + n">
                    <x-frontend.date-range-picker store-room />
                </template>
            </div>

        </div>

        {{-- Modal Footer --}}
        <div class="bg-slate-50 p-4 sm:p-6 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-4">
            <button type="button" @click="$store.preview.closeRoom()"
                class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-200 transition-colors">
                Close Preview
            </button>
            <button type="button"
                @click="$store.preview.addRoomToCart()"
                :disabled="!$store.preview.roomDates.available || !$store.preview.roomDates.checkIn || !$store.preview.roomDates.checkOut"
                :class="(!$store.preview.roomDates.available || !$store.preview.roomDates.checkIn || !$store.preview.roomDates.checkOut) ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 shadow-md shadow-sky-600/20 hover:shadow-lg cursor-pointer'"
                class="w-full sm:w-auto px-6 py-2.5 rounded-xl text-white font-bold text-xs transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[18px]">shopping_cart</span>
                <span x-text="(!$store.preview.roomDates.checkIn || !$store.preview.roomDates.checkOut) ? 'Select Dates to Add' : 'Add to Trip Basket'"></span>
            </button>
        </div>

    </div>
</div>
