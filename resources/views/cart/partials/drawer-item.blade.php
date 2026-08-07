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
