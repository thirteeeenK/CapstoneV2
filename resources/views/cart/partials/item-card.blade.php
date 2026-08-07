<div class="bg-white rounded-2xl ring-1 ring-sand-200 p-5 sm:p-6 flex flex-col sm:flex-row gap-5 transition-all duration-200 hover:ring-ocean-200 hover:shadow-md hover:shadow-ocean-900/5"
     :class="item.is_selected ? '' : 'opacity-60'">

    {{-- Checkbox --}}
    <div class="pt-0.5">
        <input type="checkbox"
               :checked="item.is_selected"
               @change="toggleSelect(item.id)"
               :aria-label="'Select ' + item.title"
               class="w-5 h-5 rounded accent-ocean-600 text-ocean-600 border-sand-300 focus:ring-ocean-500 cursor-pointer">
    </div>

    {{-- Image --}}
    <img :src="item.image" :alt="item.title"
         class="w-full sm:w-36 h-48 sm:h-32 rounded-xl object-cover ring-1 ring-sand-200 shrink-0">

    {{-- Details --}}
    <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="inline-flex items-center rounded-full bg-ocean-50 text-ocean-800 border border-ocean-100 px-2.5 py-0.5 text-[11px] font-semibold tracking-wide capitalize"
                  x-text="item.item_type">
            </span>
            <template x-if="item.location_name">
                <span class="inline-flex items-center gap-1 text-xs font-medium text-ink-500">
                    <span class="material-symbols-outlined text-sm text-ocean-600">location_on</span>
                    <span x-text="item.location_name"></span>
                </span>
            </template>
        </div>

        <template x-if="item.hotel_name">
            <div class="flex items-center gap-1.5 text-xs font-semibold text-ocean-700 mb-1">
                <span class="material-symbols-outlined text-sm text-ocean-600">hotel</span>
                <span x-text="item.hotel_name"></span>
            </div>
        </template>

        <h3 class="text-base font-semibold text-ink-900 truncate" x-text="item.room_name || item.title"></h3>

        {{-- Feature Badges & Specs --}}
        <div class="flex flex-wrap gap-1.5 mt-2.5">
            <template x-if="item.item_type === 'room' && item.base_occupancy">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-600 bg-sand-100 px-2.5 py-1 rounded-full border border-sand-200">
                    <span class="material-symbols-outlined text-[13px] text-ink-400">group</span>
                    <span x-text="'Base ' + item.base_occupancy + ' • Max ' + item.max_occupancy + ' Pax'"></span>
                </span>
            </template>

            <template x-if="item.bed_configuration">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-600 bg-sand-100 px-2.5 py-1 rounded-full border border-sand-200">
                    <span class="material-symbols-outlined text-[13px] text-ink-400">king_bed</span>
                    <span x-text="item.bed_configuration"></span>
                </span>
            </template>

            <template x-if="item.date_details">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ocean-800 bg-ocean-50 px-2.5 py-1 rounded-full border border-ocean-100">
                    <span class="material-symbols-outlined text-[13px] text-ocean-600">calendar_month</span>
                    <span x-text="item.date_details"></span>
                </span>
            </template>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            {{-- Steppers: Pax for Activities/Transfers/Addons/Packages, Qty for Rooms --}}
            <template x-if="item.item_type === 'addon' || item.item_type === 'activity' || item.item_type === 'package'">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-xs font-medium text-ink-500">Pax</span>
                    <div class="flex items-center rounded-full border border-sand-200 bg-white p-1">
                        <button type="button"
                                @click="updatePax(item.id, -1)"
                                :disabled="item.selected_pax <= 1"
                                class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                            <span class="text-xs font-bold">-</span>
                        </button>
                        <span class="w-7 text-center text-xs font-semibold text-ink-900" x-text="item.selected_pax"></span>
                        <button type="button"
                                @click="updatePax(item.id, 1)"
                                class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 transition cursor-pointer">
                            <span class="text-xs font-bold">+</span>
                        </button>
                    </div>
                    <template x-if="item.item_type === 'package'">
                        <span class="text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200/80 px-2 py-0.5 rounded-full">Min 2 Pax</span>
                    </template>
                </div>
            </template>

            <template x-if="item.item_type !== 'addon' && item.item_type !== 'activity' && item.item_type !== 'package'">
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-ink-500">Qty</span>
                    <div class="flex items-center rounded-full border border-sand-200 bg-white p-1">
                        <button type="button"
                                @click="updateQty(item.id, -1)"
                                :disabled="item.quantity <= 1"
                                class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                            <span class="text-xs font-bold">-</span>
                        </button>
                        <span class="w-7 text-center text-xs font-semibold text-ink-900" x-text="item.quantity"></span>
                        <button type="button"
                                @click="updateQty(item.id, 1)"
                                :disabled="item.quantity >= item.max_qty"
                                class="w-7 h-7 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                            <span class="text-xs font-bold">+</span>
                        </button>
                    </div>
                    <template x-if="item.item_type === 'room'">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[11px] font-medium text-ink-500" x-text="item.available_notice"></span>
                            <template x-if="item.booked_count > 0">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-800 bg-amber-50 border border-amber-200/80 px-2 py-0.5 rounded-full w-fit">
                                    <span class="material-symbols-outlined text-[12px] text-amber-600">info</span>
                                    <span>Note: <span x-text="item.booked_count"></span> room<span x-text="item.booked_count > 1 ? 's are' : ' is'"></span> pending admin approval or active holds for these dates.</span>
                                </span>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Subtotal & Rate Display --}}
            <div class="text-right">
                <template x-if="item.item_type === 'addon' || item.item_type === 'activity' || item.item_type === 'package'">
                    <span class="block text-xs text-ink-500 font-medium" x-text="item.formatted_unit_rate + ' / pax'"></span>
                </template>
                <template x-if="item.item_type !== 'addon' && item.item_type !== 'activity' && item.item_type !== 'package' && item.quantity > 1">
                    <span class="block text-xs text-ink-400" x-text="item.formatted_unit_rate + ' each'"></span>
                </template>
                <span class="block text-lg font-bold text-ink-900" x-text="item.formatted_subtotal"></span>
            </div>
        </div>
    </div>

    {{-- Remove Action --}}
    <div class="self-start sm:self-center">
        <button type="button"
                @click="removeItem(item.id)"
                :aria-label="'Remove ' + item.title"
                class="w-9 h-9 rounded-full flex items-center justify-center text-rose-600 bg-rose-50 border border-rose-100 hover:bg-rose-100 hover:text-rose-700 transition cursor-pointer shadow-2xs">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>
    </div>
</div>
