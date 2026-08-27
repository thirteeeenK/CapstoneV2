<div class="bg-white rounded-2xl border border-sand-200/90 p-3.5 sm:p-5 flex gap-3 sm:gap-4 items-start transition-all duration-200 hover:border-ocean-300 hover:shadow-md hover:shadow-ocean-900/5"
     :class="item.is_selected ? '' : 'opacity-60'">

    {{-- Checkbox --}}
    <div class="pt-1 shrink-0">
        <input type="checkbox"
               :checked="item.is_selected"
               @change="toggleSelect(item.id)"
               :aria-label="'Select ' + item.title"
               class="w-4 h-4 sm:w-5 sm:h-5 rounded accent-ocean-600 text-ocean-600 border-sand-300 focus:ring-ocean-500 cursor-pointer">
    </div>

    {{-- Image Thumbnail --}}
    <div class="w-20 h-20 sm:w-28 sm:h-28 md:w-32 md:h-32 rounded-xl sm:rounded-2xl overflow-hidden bg-slate-100 border border-sand-200/80 shrink-0">
        <img :src="item.image" :alt="item.title"
             class="w-full h-full object-cover"
             onerror="this.style.display='none'">
    </div>

    {{-- Details & Content --}}
    <div class="flex-1 min-w-0 flex flex-col justify-between min-h-[5rem] sm:min-h-[7rem]">
        <div>
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                        <span class="inline-flex items-center rounded-md bg-ocean-50 text-ocean-800 border border-ocean-100 px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase"
                              x-text="item.item_type">
                        </span>
                        <template x-if="item.location_name">
                            <span class="inline-flex items-center gap-0.5 text-[11px] font-medium text-ink-500">
                                <span class="material-symbols-outlined text-[13px] text-ocean-600">location_on</span>
                                <span x-text="item.location_name"></span>
                            </span>
                        </template>
                    </div>

                    <template x-if="item.hotel_name">
                        <div class="flex items-center gap-1 text-[11px] font-semibold text-ocean-700 mb-0.5">
                            <span class="material-symbols-outlined text-[13px] text-ocean-600 shrink-0">hotel</span>
                            <span class="truncate" x-text="item.hotel_name"></span>
                        </div>
                    </template>

                    <h3 class="text-xs sm:text-base font-bold text-ink-900 leading-snug line-clamp-2" x-text="item.room_name || item.title"></h3>
                </div>

                {{-- Remove Button --}}
                <button type="button"
                        @click="removeItem(item.id)"
                        :aria-label="'Remove ' + item.title"
                        class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 bg-rose-50/70 hover:bg-rose-100 border border-rose-100 transition cursor-pointer shrink-0">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                </button>
            </div>

            {{-- Feature Badges & Specs --}}
            <div class="flex flex-wrap gap-1 mt-1.5">
                <template x-if="item.item_type === 'room' && item.base_occupancy">
                    <span class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] font-medium text-ink-600 bg-sand-100 px-2 py-0.5 rounded-md border border-sand-200">
                        <span class="material-symbols-outlined text-[12px] text-ink-400">group</span>
                        <span x-text="'Base ' + item.base_occupancy + ' • Max ' + item.max_occupancy + ' Pax'"></span>
                    </span>
                </template>

                <template x-if="item.bed_configuration">
                    <span class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] font-medium text-ink-600 bg-sand-100 px-2 py-0.5 rounded-md border border-sand-200">
                        <span class="material-symbols-outlined text-[12px] text-ink-400">king_bed</span>
                        <span x-text="item.bed_configuration"></span>
                    </span>
                </template>

                <template x-if="item.date_details">
                    <span class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] font-medium text-ocean-800 bg-ocean-50 px-2 py-0.5 rounded-md border border-ocean-100">
                        <span class="material-symbols-outlined text-[12px] text-ocean-600">calendar_month</span>
                        <span x-text="item.date_details"></span>
                    </span>
                </template>
            </div>
        </div>

        {{-- Controls and Price Bar --}}
        <div class="mt-3 pt-2 border-t border-sand-100 flex flex-wrap items-center justify-between gap-2">
            {{-- Steppers: Pax for Activities/Transfers/Addons/Packages, Qty for Rooms --}}
            <template x-if="item.item_type === 'addon' || item.item_type === 'activity' || item.item_type === 'package'">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[11px] font-semibold text-ink-500">Travelers:</span>
                    <div class="flex items-center rounded-lg border border-sand-200 bg-white p-0.5 shadow-2xs">
                        <button type="button"
                                @click="updatePax(item.id, -1)"
                                :disabled="item.selected_pax <= 1"
                                class="w-6 h-6 rounded flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer font-bold text-xs">
                            <span>-</span>
                        </button>
                        <span class="w-10 text-center text-xs font-bold text-ink-900" x-text="item.selected_pax + ' pax'"></span>
                        <button type="button"
                                @click="updatePax(item.id, 1)"
                                class="w-6 h-6 rounded flex items-center justify-center text-ink-600 hover:bg-sand-100 transition cursor-pointer font-bold text-xs">
                            <span>+</span>
                        </button>
                    </div>
                    <template x-if="item.item_type === 'package'">
                        <span class="text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">Min 2 Pax</span>
                    </template>
                </div>
            </template>

            <template x-if="item.item_type !== 'addon' && item.item_type !== 'activity' && item.item_type !== 'package'">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[11px] font-semibold text-ink-500">Rooms:</span>
                    <div class="flex items-center rounded-lg border border-sand-200 bg-white p-0.5 shadow-2xs">
                        <button type="button"
                                @click="updateQty(item.id, -1)"
                                :disabled="item.quantity <= 1"
                                class="w-6 h-6 rounded flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer font-bold text-xs">
                            <span>-</span>
                        </button>
                        <span class="w-8 text-center text-xs font-bold text-ink-900" x-text="item.quantity"></span>
                        <button type="button"
                                @click="updateQty(item.id, 1)"
                                :disabled="item.quantity >= item.max_qty"
                                class="w-6 h-6 rounded flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer font-bold text-xs">
                            <span>+</span>
                        </button>
                    </div>
                    <template x-if="item.item_type === 'room'">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[10px] font-medium text-ink-500" x-text="item.available_notice"></span>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Subtotal & Rate Display --}}
            <div class="text-right shrink-0">
                <template x-if="item.item_type === 'addon' || item.item_type === 'activity' || item.item_type === 'package'">
                    <span class="block text-[10px] text-ink-400 font-medium" x-text="item.formatted_unit_rate + ' / pax'"></span>
                </template>
                <template x-if="item.item_type !== 'addon' && item.item_type !== 'activity' && item.item_type !== 'package' && item.quantity > 1">
                    <span class="block text-[10px] text-ink-400 font-medium" x-text="item.quantity + ' × ' + item.formatted_unit_rate"></span>
                </template>
                <span class="block text-sm sm:text-base font-extrabold text-ink-900 font-headline" x-text="item.formatted_subtotal"></span>
            </div>
        </div>
    </div>
</div>
