{{-- Shared Package Preview Modal — driven by the global Alpine `preview` store.
     Used on the package catalog and the chat widget. --}}
<div x-data x-show="$store.preview.package" x-transition.opacity @keydown.escape.window="$store.preview.closePackage()"
    class="fixed inset-0 z-[110] flex items-center justify-center p-3 sm:p-6 bg-slate-950/80 backdrop-blur-md overflow-y-auto"
    x-cloak style="display: none;">

    <div @click.away="$store.preview.closePackage()" @click.stop
        class="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl relative my-auto">

        {{-- Modal Header --}}
        <div
            class="relative bg-slate-900 text-white p-5 sm:p-7 shrink-0 overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <button @click="$store.preview.closePackage()"
                aria-label="Close Package Details"
                class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors cursor-pointer z-10">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>

            <div class="flex flex-wrap items-center gap-2 mb-2 pr-10">
                <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 text-[10px] font-bold uppercase tracking-wider border border-amber-400/30 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px]">card_travel</span>
                    <span x-text="$store.preview.package?.type || 'Tour Package'"></span>
                </span>
                <template x-if="$store.preview.package?.destination_name">
                    <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-slate-200 text-[10px] font-medium flex items-center gap-1 border border-white/10">
                        <span class="material-symbols-outlined text-[12px] text-amber-400">location_on</span>
                        <span x-text="$store.preview.package.destination_name"></span>
                    </span>
                </template>
            </div>

            <h2 class="text-xl sm:text-2xl font-black text-white font-headline tracking-tight leading-snug" x-text="$store.preview.package?.name"></h2>

            <div class="mt-2.5 flex items-baseline gap-2">
                <span class="text-xl sm:text-2xl font-black text-emerald-400 font-headline"
                    x-text="$store.preview.package?.price"></span>
                <span class="text-xs text-slate-400 font-medium">/ person</span>
                <span x-show="$store.preview.package?.price_change" x-cloak
                    class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[10px] font-extrabold border"
                    :class="$store.preview.package?.price_change?.dir === 'up' ? 'bg-red-50 text-red-600 border-red-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200'"
                    :title="$store.preview.package?.price_change ? 'Was ₱' + Number($store.preview.package.price_change.old).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' on ' + $store.preview.package.price_change.date : ''"
                    :aria-label="$store.preview.package?.price_change ? ($store.preview.package.price_change.dir === 'up' ? 'Price increased from ₱' : 'Price decreased from ₱') + Number($store.preview.package.price_change.old).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' on ' + $store.preview.package.price_change.date : ''">
                    <span class="material-symbols-outlined text-[13px] leading-none" x-text="$store.preview.package?.price_change ? ($store.preview.package.price_change.dir === 'up' ? 'trending_up' : 'trending_down') : ''"></span>
                    <span class="sr-only" x-text="$store.preview.package?.price_change ? ($store.preview.package.price_change.dir === 'up' ? 'Price up' : 'Price down') : ''"></span>
                </span>
            </div>
        </div>

        {{-- Modal Scrollable Body --}}
        <div class="p-4 sm:p-7 space-y-5 flex-1 overflow-y-auto overscroll-contain text-xs sm:text-sm text-slate-700">
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
                <span class="font-bold text-slate-800 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px] text-amber-600">schedule</span>
                    <span x-text="$store.preview.package?.days + ' Days / ' + $store.preview.package?.nights + ' Nights'"></span>
                </span>
                <span>•</span>
                <span class="font-bold text-slate-800 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px] text-amber-600">group</span>
                    <span x-text="'Min ' + $store.preview.package?.min_pax + ' Passengers'"></span>
                </span>
                <template x-if="$store.preview.package?.valid_from">
                    <span class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full"
                        x-text="'Valid: ' + $store.preview.package.valid_from + ($store.preview.package.valid_to ? ' → ' + $store.preview.package.valid_to : '')"></span>
                </template>
            </div>

            {{-- Image Showcase --}}
            <template x-if="$store.preview.package?.images && $store.preview.package.images.length > 0">
                <div class="space-y-2.5">
                    <div class="relative h-48 sm:h-64 rounded-2xl overflow-hidden bg-slate-900 group border border-slate-200/80 shadow-xs">
                        <img :src="$store.preview.package.images[$store.preview.pkgImgIndex]"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            onerror="this.style.display='none'">
                        <div class="absolute bottom-2.5 right-2.5 bg-slate-950/75 backdrop-blur-md text-white text-[11px] font-bold px-2.5 py-0.5 rounded-lg border border-white/20">
                            <span x-text="$store.preview.pkgImgIndex + 1"></span> / <span x-text="$store.preview.package.images.length"></span>
                        </div>
                    </div>

                    <template x-if="$store.preview.package.images.length > 1">
                        <div class="flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <template x-for="(img, idx) in $store.preview.package.images" :key="idx">
                                <button @click="$store.preview.pkgImgIndex = idx"
                                    :class="$store.preview.pkgImgIndex === idx ? 'ring-2 ring-amber-600 scale-105 opacity-100' : 'opacity-60 hover:opacity-100'"
                                    class="w-14 h-11 sm:w-16 sm:h-12 rounded-xl overflow-hidden border border-slate-200 shrink-0 transition-all cursor-pointer">
                                    <img :src="img" class="w-full h-full object-cover">
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Inclusions --}}
            <template x-if="$store.preview.package?.generic_inclusions && $store.preview.package.generic_inclusions.length > 0">
                <div class="space-y-2">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600">checklist</span>
                        <span>Complete Package Inclusions</span>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="(inc, idx) in $store.preview.package.generic_inclusions" :key="idx">
                            <div
                                class="flex items-center gap-2 text-xs text-slate-700 bg-emerald-50/40 px-3 py-2.5 rounded-xl border border-emerald-200/60">
                                <span
                                    class="material-symbols-outlined text-[16px] text-emerald-600 shrink-0">check_circle</span>
                                <span x-text="inc"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Linked stay, experiences & extras --}}
            <template x-if="$store.preview.package && ($store.preview.package.hotels?.length || $store.preview.package.rooms?.length || $store.preview.package.activities?.length || $store.preview.package.add_ons?.length)">
                <div class="space-y-2">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">link</span>
                        <span>Stay, Experiences & Extras</span>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="hotel in ($store.preview.package.hotels || [])" :key="'h'+hotel.id">
                            <div class="flex items-center gap-2 text-xs text-slate-700 bg-sky-50/60 px-3 py-2.5 rounded-xl border border-sky-200/60">
                                <span class="material-symbols-outlined text-[16px] text-sky-600 shrink-0">hotel</span>
                                <span x-text="hotel.name"></span>
                            </div>
                        </template>
                        <template x-for="room in ($store.preview.package.rooms || [])" :key="'r'+room.id">
                            <div class="flex items-center gap-2 text-xs text-slate-700 bg-sky-50/60 px-3 py-2.5 rounded-xl border border-sky-200/60">
                                <span class="material-symbols-outlined text-[16px] text-sky-600 shrink-0">bed</span>
                                <span x-text="room.name"></span>
                            </div>
                        </template>
                        <template x-for="activity in ($store.preview.package.activities || [])" :key="'a'+activity.id">
                            <div class="flex items-center gap-2 text-xs text-slate-700 bg-violet-50/60 px-3 py-2.5 rounded-xl border border-violet-200/60">
                                <span class="material-symbols-outlined text-[16px] text-violet-600 shrink-0">kayaking</span>
                                <span x-text="activity.name"></span>
                            </div>
                        </template>
                        <template x-for="addon in ($store.preview.package.add_ons || [])" :key="'x'+addon.id">
                            <div class="flex items-center gap-2 text-xs text-slate-700 bg-amber-50/60 px-3 py-2.5 rounded-xl border border-amber-200/60">
                                <span class="material-symbols-outlined text-[16px] text-amber-600 shrink-0">add_circle</span>
                                <span x-text="addon.name"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

        </div>

        {{-- Pinned Modal Footer --}}
        <div
            class="bg-white p-3.5 sm:p-5 border-t border-slate-200/80 flex items-center gap-2.5 shrink-0 shadow-xs">
            <button @click="$store.preview.closePackage()"
                class="px-4 sm:px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-100 transition-colors cursor-pointer shrink-0">
                Close
            </button>

            <button type="button" @click="$store.preview.addPackageToCart()"
                class="flex-1 py-2.5 px-4 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[17px]">shopping_cart</span>
                <span>Add Package to Trip Basket</span>
            </button>
        </div>

    </div>
</div>
