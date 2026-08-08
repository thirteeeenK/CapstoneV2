{{-- Shared Activity Preview Modal — driven by the global Alpine `preview` store.
     Used on the activity catalog, destination page, hotel page, and chat widget. --}}
<div x-show="$store.preview.activity" x-transition.opacity @keydown.escape.window="$store.preview.closeActivity()"
    class="fixed inset-0 z-[110] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
    x-cloak style="display: none;">
    <div @click.away="$store.preview.closeActivity()" @click.stop
        class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full overflow-hidden border border-slate-200/80 my-auto transform transition-all">

        {{-- Modal Header --}}
        <div class="relative bg-slate-900 text-white p-6 sm:p-8 overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-ocean-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <button @click="$store.preview.closeActivity()"
                class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>

            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="px-2.5 py-0.5 rounded-full bg-ocean-500/20 text-ocean-300 text-[10px] font-bold uppercase tracking-wider border border-ocean-400/30">
                    <span class="material-symbols-outlined text-[13px] mr-1 align-middle" x-text="$store.preview.activity?.category_icon || 'explore'"></span>
                    <span x-text="$store.preview.activity?.category || 'Activity'"></span>
                </span>
                <template x-if="$store.preview.activity?.destination_name">
                    <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-slate-200 text-[10px] font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px]">location_on</span>
                        <span x-text="$store.preview.activity.destination_name"></span>
                    </span>
                </template>
            </div>

            <h2 class="text-2xl sm:text-3xl font-black text-white font-headline" x-text="$store.preview.activity?.activity_name"></h2>

            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-headline"
                    x-text="$store.preview.activity?.rate"></span>
                <span class="text-xs text-slate-300 font-medium">/ person</span>
            </div>
        </div>

        {{-- Modal Body --}}
        <div class="p-6 sm:p-8 space-y-6 max-h-[65vh] overflow-y-auto text-xs sm:text-sm text-slate-700">

            {{-- Image Carousel --}}
            <template x-if="$store.preview.activity?.images && $store.preview.activity.images.length > 0">
                <div class="space-y-3">
                    <div class="relative h-64 sm:h-80 rounded-2xl overflow-hidden bg-slate-900 group border border-slate-200/80">
                        <img :src="$store.preview.activity.images[$store.preview.actImgIndex]"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute bottom-3 right-3 bg-slate-950/75 backdrop-blur-md text-white text-xs px-3 py-1 rounded-lg border border-white/20">
                            Photo <span x-text="$store.preview.actImgIndex + 1"></span> of <span x-text="$store.preview.activity.images.length"></span>
                        </div>
                    </div>

                    <template x-if="$store.preview.activity.images.length > 1">
                        <div class="flex items-center gap-2 overflow-x-auto pb-2">
                            <template x-for="(img, idx) in $store.preview.activity.images" :key="idx">
                                <button @click="$store.preview.actImgIndex = idx"
                                    :class="$store.preview.actImgIndex === idx ? 'ring-2 ring-ocean-600 scale-105' : 'opacity-70 hover:opacity-100'"
                                    class="w-16 h-12 rounded-lg overflow-hidden border border-slate-200 shrink-0 transition-all">
                                    <img :src="img" class="w-full h-full object-cover">
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Quick Specs Grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                    <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Duration</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">schedule</span>
                        <span x-text="$store.preview.activity?.duration || 'Flexible'"></span>
                    </span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                    <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Activity Level</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">signal_cellular_alt</span>
                        <span x-text="$store.preview.activity?.activity_level || 'General'"></span>
                    </span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                    <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Capacity</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">group</span>
                        <span x-text="$store.preview.activity?.capacity || 'Standard Group'"></span>
                    </span>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1"
                    x-show="$store.preview.activity?.ideal_for">
                    <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Ideal For</span>
                    <span class="font-bold text-slate-800 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">face</span>
                        <span x-text="$store.preview.activity?.ideal_for"></span>
                    </span>
                </div>
            </div>

            {{-- Pax Selector Control --}}
            <div class="flex items-center justify-between p-3.5 bg-sky-50/80 rounded-2xl border border-sky-200/80">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sky-600">group</span>
                    <div>
                        <span class="text-xs font-bold text-slate-800 block">Number of Participants / Pax</span>
                        <span class="text-[11px] text-slate-500">Manifest entries will be generated for each participant</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-2xs">
                    <button type="button" @click="$store.preview.activityPax = Math.max(1, $store.preview.activityPax - 1)" :disabled="$store.preview.activityPax <= 1" class="text-slate-600 font-bold hover:text-sky-600 disabled:opacity-40 cursor-pointer">-</button>
                    <span class="text-xs font-black text-slate-900 w-6 text-center" x-text="$store.preview.activityPax"></span>
                    <button type="button" @click="$store.preview.activityPax += 1" class="text-slate-600 font-bold hover:text-sky-600 cursor-pointer">+</button>
                </div>
            </div>

            {{-- Description --}}
            <template x-if="$store.preview.activity?.description">
                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Activity Overview</h4>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed bg-slate-50/80 p-4 rounded-2xl border border-slate-200/70"
                        x-text="$store.preview.activity.description"></p>
                </div>
            </template>

            {{-- Requirements --}}
            <template x-if="$store.preview.activity?.requirements">
                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Participant Requirements</h4>
                    <p class="text-xs text-amber-900 bg-amber-50/80 p-3 rounded-xl border border-amber-200/70 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-amber-600">info</span>
                        <span x-text="$store.preview.activity.requirements"></span>
                    </p>
                </div>
            </template>

            {{-- Inclusions & Exclusions --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <template x-if="$store.preview.activity?.inclusions && $store.preview.activity.inclusions.length > 0">
                    <div class="space-y-2 bg-emerald-50/40 p-4 rounded-2xl border border-emerald-100">
                        <h4 class="text-xs font-bold text-emerald-900 uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                            Included
                        </h4>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(inc, idx) in $store.preview.activity.inclusions" :key="idx">
                                <span class="px-2.5 py-1 bg-white text-emerald-800 text-xs font-semibold rounded-lg border border-emerald-200/80 shadow-2xs">
                                    <span x-text="inc"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="$store.preview.activity?.exclusions && $store.preview.activity.exclusions.length > 0">
                    <div class="space-y-2 bg-rose-50/40 p-4 rounded-2xl border border-rose-100">
                        <h4 class="text-xs font-bold text-rose-900 uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-rose-600">cancel</span>
                            Excluded / Add-ons
                        </h4>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(exc, idx) in $store.preview.activity.exclusions" :key="idx">
                                <span class="px-2.5 py-1 bg-white text-rose-800 text-xs font-semibold rounded-lg border border-rose-200/80 shadow-2xs">
                                    <span x-text="exc"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Itinerary Timeline --}}
            <template x-if="$store.preview.activity?.itinerary && $store.preview.activity.itinerary.length > 0">
                <div class="space-y-3 pt-2">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Suggested Itinerary</h4>
                    <div class="space-y-2 relative border-l-2 border-ocean-200 ml-3 pl-4">
                        <template x-for="(step, idx) in $store.preview.activity.itinerary" :key="idx">
                            <div class="relative group">
                                <span class="absolute -left-[23px] top-0.5 w-3 h-3 rounded-full bg-ocean-500 ring-4 ring-white"></span>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex items-center justify-between">
                                    <span class="font-bold text-slate-800 text-xs" x-text="step.title || step"></span>
                                    <span class="text-[11px] font-semibold text-ocean-600 bg-ocean-50 px-2 py-0.5 rounded-md border border-ocean-100"
                                        x-show="step.duration" x-text="step.duration"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Vibe Tags --}}
            <template x-if="$store.preview.activity?.vibe_tags && $store.preview.activity.vibe_tags.length > 0">
                <div class="space-y-2 pt-2">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Atmosphere & Vibe</h4>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(tag, idx) in $store.preview.activity.vibe_tags" :key="idx">
                            <span class="px-2.5 py-1 bg-ocean-50 text-ocean-700 text-xs font-semibold rounded-lg border border-ocean-100">
                                #<span x-text="tag"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </template>

        </div>

        {{-- Modal Footer --}}
        <div class="bg-slate-50 p-4 sm:p-6 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-4">
            <button type="button" @click="$store.preview.closeActivity()"
                class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-200 transition-colors">
                Close Preview
            </button>
            <button type="button" @click="$store.preview.addActivityToCart()"
                class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[18px]">shopping_cart</span>
                <span>Add to Trip Basket</span>
            </button>
        </div>

    </div>
</div>
