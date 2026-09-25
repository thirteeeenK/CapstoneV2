{{-- Shared Activity Preview Modal — driven by the global Alpine `preview` store.
     Used on the activity catalog, destination page, hotel page, and chat widget. --}}
<div x-data x-show="$store.preview.activity" x-transition.opacity @keydown.escape.window="$store.preview.closeActivity()"
    class="fixed inset-0 z-[110] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
    x-cloak style="display: none;">
    <div @click.away="$store.preview.closeActivity()" @click.stop
        class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full max-h-[92vh] flex flex-col overflow-hidden border border-slate-200/80 my-auto transform transition-all">

        {{-- Modal Header --}}
        <div class="relative bg-slate-900 text-white p-5 sm:p-7 shrink-0 overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-ocean-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <button @click="$store.preview.closeActivity()"
                aria-label="Close Preview"
                class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors cursor-pointer z-10">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>

            <div class="flex flex-wrap items-center gap-2 mb-2 pr-10">
                <span class="px-2.5 py-0.5 rounded-full bg-ocean-500/20 text-ocean-300 text-[10px] font-bold uppercase tracking-wider border border-ocean-400/30 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px]" x-text="$store.preview.activity?.category_icon || 'explore'"></span>
                    <span x-text="$store.preview.activity?.category || 'Activity'"></span>
                </span>
                <template x-if="$store.preview.activity?.destination_name">
                    <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-slate-200 text-[10px] font-medium flex items-center gap-1 border border-white/10">
                        <span class="material-symbols-outlined text-[12px] text-sky-400">location_on</span>
                        <span x-text="$store.preview.activity.destination_name"></span>
                    </span>
                </template>
            </div>

            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-white font-headline tracking-tight leading-snug" x-text="$store.preview.activity?.activity_name"></h2>

            <div class="mt-2.5 flex items-baseline gap-2">
                <span class="text-xl sm:text-2xl font-black text-emerald-400 font-headline"
                    x-text="$store.preview.activity?.rate"></span>
                <span class="text-xs text-slate-400 font-medium">/ person</span>
                <span x-show="$store.preview.activity?.price_change" x-cloak
                    class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[10px] font-extrabold border"
                    :class="$store.preview.activity?.price_change?.dir === 'up' ? 'bg-red-50 text-red-600 border-red-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200'"
                    :title="$store.preview.activity?.price_change ? 'Was ₱' + Number($store.preview.activity.price_change.old).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' on ' + $store.preview.activity.price_change.date : ''"
                    :aria-label="$store.preview.activity?.price_change ? ($store.preview.activity.price_change.dir === 'up' ? 'Price increased from ₱' : 'Price decreased from ₱') + Number($store.preview.activity.price_change.old).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' on ' + $store.preview.activity.price_change.date : ''">
                    <span class="material-symbols-outlined text-[13px] leading-none" x-text="$store.preview.activity?.price_change ? ($store.preview.activity.price_change.dir === 'up' ? 'trending_up' : 'trending_down') : ''"></span>
                    <span class="sr-only" x-text="$store.preview.activity?.price_change ? ($store.preview.activity.price_change.dir === 'up' ? 'Price up' : 'Price down') : ''"></span>
                </span>
            </div>
        </div>

        {{-- Modal Scrollable Body --}}
        <div class="p-4 sm:p-7 space-y-5 flex-1 overflow-y-auto overscroll-contain text-xs sm:text-sm text-slate-700">

            {{-- Image Showcase & Gallery --}}
            <template x-if="$store.preview.activity?.images && $store.preview.activity.images.length > 0">
                <div class="space-y-2.5">
                    <div class="relative h-48 sm:h-72 rounded-2xl overflow-hidden bg-slate-900 group border border-slate-200/80 shadow-xs">
                        <img :src="$store.preview.activity.images[$store.preview.actImgIndex]"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            onerror="this.style.display='none'">
                        <div class="absolute bottom-2.5 right-2.5 bg-slate-950/75 backdrop-blur-md text-white text-[11px] font-bold px-2.5 py-0.5 rounded-lg border border-white/20">
                            <span x-text="$store.preview.actImgIndex + 1"></span> / <span x-text="$store.preview.activity.images.length"></span>
                        </div>
                    </div>

                    <template x-if="$store.preview.activity.images.length > 1">
                        <div class="flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <template x-for="(img, idx) in $store.preview.activity.images" :key="idx">
                                <button @click="$store.preview.actImgIndex = idx"
                                    :class="$store.preview.actImgIndex === idx ? 'ring-2 ring-sky-600 scale-105 opacity-100' : 'opacity-60 hover:opacity-100'"
                                    class="w-14 h-11 sm:w-16 sm:h-12 rounded-xl overflow-hidden border border-slate-200 shrink-0 transition-all cursor-pointer">
                                    <img :src="img" class="w-full h-full object-cover">
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Key Specifications 2x2 Grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 text-xs">
                <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-200/70 flex flex-col justify-between">
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">Duration</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1.5 truncate">
                        <span class="material-symbols-outlined text-[15px] text-sky-600 shrink-0">schedule</span>
                        <span class="truncate" x-text="$store.preview.activity?.duration || 'Flexible'"></span>
                    </span>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-200/70 flex flex-col justify-between">
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">Activity Level</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1.5 truncate">
                        <span class="material-symbols-outlined text-[15px] text-sky-600 shrink-0">signal_cellular_alt</span>
                        <span class="truncate" x-text="$store.preview.activity?.activity_level || 'General'"></span>
                    </span>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-200/70 flex flex-col justify-between">
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">Capacity</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1.5 truncate">
                        <span class="material-symbols-outlined text-[15px] text-sky-600 shrink-0">group</span>
                        <span class="truncate" x-text="$store.preview.activity?.capacity || 'Standard Group'"></span>
                    </span>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-200/70 flex flex-col justify-between">
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">Ideal For</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1.5 truncate">
                        <span class="material-symbols-outlined text-[15px] text-sky-600 shrink-0">face</span>
                        <span class="truncate" x-text="$store.preview.activity?.ideal_for || 'All Travelers'"></span>
                    </span>
                </div>
            </div>

            {{-- Interactive Pax Selector Card --}}
            <div class="flex items-center justify-between p-3.5 bg-sky-50/90 rounded-2xl border border-sky-200/80">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[18px]">group</span>
                    </div>
                    <div>
                        <span class="text-xs font-extrabold text-slate-900 block leading-tight">Participants (Pax)</span>
                        <span class="text-[11px] text-slate-500 block leading-tight mt-0.5">Tickets & manifest slots</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 bg-white px-2.5 py-1 rounded-xl border border-slate-200 shadow-2xs">
                    <button type="button" 
                        @click="$store.preview.activityPax = Math.max(1, $store.preview.activityPax - 1)" 
                        :disabled="$store.preview.activityPax <= 1" 
                        class="w-7 h-7 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-600 font-black text-sm disabled:opacity-30 transition-colors cursor-pointer">-</button>
                    <span class="text-xs font-black text-slate-900 w-5 text-center" x-text="$store.preview.activityPax"></span>
                    <button type="button" 
                        @click="$store.preview.activityPax += 1" 
                        class="w-7 h-7 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-600 font-black text-sm transition-colors cursor-pointer">+</button>
                </div>
            </div>

            {{-- Activity Overview Description --}}
            <template x-if="$store.preview.activity?.description">
                <div class="space-y-1.5">
                    <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">article</span>
                        <span>Activity Overview</span>
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed bg-slate-50/70 p-3.5 sm:p-4 rounded-2xl border border-slate-200/70"
                        x-text="$store.preview.activity.description"></p>
                </div>
            </template>

            {{-- Participant Requirements --}}
            <template x-if="$store.preview.activity?.requirements">
                <div class="space-y-1.5">
                    <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-amber-600">warning</span>
                        <span>Participant Requirements</span>
                    </h3>
                    <div class="text-xs text-amber-900 bg-amber-50/80 p-3.5 rounded-2xl border border-amber-200/80 flex items-start gap-2.5 leading-relaxed">
                        <span class="material-symbols-outlined text-[17px] text-amber-600 shrink-0 mt-0.5">info</span>
                        <span x-text="$store.preview.activity.requirements"></span>
                    </div>
                </div>
            </template>

            {{-- Inclusions & Exclusions Side-by-Side Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <template x-if="$store.preview.activity?.inclusions && $store.preview.activity.inclusions.length > 0">
                    <div class="space-y-2 bg-emerald-50/50 p-3.5 sm:p-4 rounded-2xl border border-emerald-200/70">
                        <h3 class="text-xs font-extrabold text-emerald-900 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                            <span>Included</span>
                        </h3>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(inc, idx) in $store.preview.activity.inclusions" :key="idx">
                                <span class="px-2.5 py-1 bg-white text-emerald-800 text-xs font-semibold rounded-xl border border-emerald-200/80 shadow-2xs">
                                    <span x-text="inc"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="$store.preview.activity?.exclusions && $store.preview.activity.exclusions.length > 0">
                    <div class="space-y-2 bg-rose-50/50 p-3.5 sm:p-4 rounded-2xl border border-rose-200/70">
                        <h3 class="text-xs font-extrabold text-rose-900 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-rose-600">cancel</span>
                            <span>Excluded / Add-ons</span>
                        </h3>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(exc, idx) in $store.preview.activity.exclusions" :key="idx">
                                <span class="px-2.5 py-1 bg-white text-rose-800 text-xs font-semibold rounded-xl border border-rose-200/80 shadow-2xs">
                                    <span x-text="exc"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Suggested Itinerary Timeline --}}
            <template x-if="$store.preview.activity?.itinerary && $store.preview.activity.itinerary.length > 0">
                <div class="space-y-2.5 pt-1">
                    <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">timeline</span>
                        <span>Suggested Itinerary</span>
                    </h3>
                    <div class="space-y-2 relative border-l-2 border-sky-200 ml-2.5 pl-3.5">
                        <template x-for="(step, idx) in $store.preview.activity.itinerary" :key="idx">
                            <div class="relative">
                                <span class="absolute -left-[19px] top-2.5 w-2.5 h-2.5 rounded-full bg-sky-500 ring-4 ring-white"></span>
                                <div class="bg-slate-50/90 p-3 rounded-xl border border-slate-200/70 flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-800 text-xs" x-text="step.title || step"></span>
                                    <span class="text-[10px] font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200 shrink-0"
                                        x-show="step.duration" x-text="step.duration"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Atmosphere & Vibe Tags --}}
            <template x-if="$store.preview.activity?.vibe_tags && $store.preview.activity.vibe_tags.length > 0">
                <div class="space-y-2 pt-1">
                    <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">mood</span>
                        <span>Atmosphere & Vibe</span>
                    </h3>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(tag, idx) in $store.preview.activity.vibe_tags" :key="idx">
                            <span class="px-2.5 py-1 bg-ocean-50 text-ocean-700 text-xs font-semibold rounded-xl border border-ocean-200/80">
                                #<span x-text="tag"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </template>

        </div>

        {{-- Pinned Modal Footer with Clear Primary Action --}}
        <div class="bg-white p-3.5 sm:p-5 border-t border-slate-200/80 flex items-center gap-2.5 shrink-0 shadow-xs">
            <button type="button" @click="$store.preview.closeActivity()"
                class="px-4 sm:px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-100 transition-colors cursor-pointer shrink-0">
                Close
            </button>
            <button type="button" @click="$store.preview.addActivityToCart()"
                class="flex-1 py-2.5 px-4 rounded-2xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[17px]">shopping_cart</span>
                <span>Add to Trip Basket</span>
            </button>
        </div>

    </div>
</div>
