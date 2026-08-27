<div class="p-3.5 sm:p-5 flex gap-3 transition-opacity duration-200"
     :class="item.is_selected ? '' : 'opacity-50'">

    {{-- Select Checkbox --}}
    <div class="pt-1 shrink-0">
        <input type="checkbox"
               :checked="item.is_selected"
               @change="toggleSelect(item.id)"
               :aria-label="'Select ' + item.title"
               class="w-4 h-4 rounded text-ocean-600 accent-ocean-600 border-sand-300 focus:ring-ocean-500 cursor-pointer">
    </div>

    {{-- Image --}}
    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-slate-100 border border-sand-200 shrink-0">
        <img :src="item.image" :alt="item.title" class="w-full h-full object-cover" onerror="this.style.display='none'">
    </div>

    {{-- Content --}}
    <div class="flex-1 min-w-0 flex flex-col justify-between">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <template x-if="item.hotel_name">
                    <div class="flex items-center gap-1 text-[11px] font-semibold text-ocean-700 mb-0.5">
                        <span class="material-symbols-outlined text-[13px] text-ocean-600 shrink-0">hotel</span>
                        <span class="truncate" x-text="item.hotel_name"></span>
                    </div>
                </template>
                <h4 class="text-xs sm:text-sm font-bold text-ink-900 leading-snug truncate" x-text="item.title"></h4>
                <p class="text-[11px] text-ink-500 mt-0.5 leading-tight truncate" x-text="item.subtitle"></p>
            </div>
            <button @click="removeItem(item.id)"
                    :aria-label="'Remove ' + item.title"
                    class="shrink-0 p-1.5 rounded-full text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
        </div>

        <div class="flex items-center justify-between gap-2 mt-2 pt-1 border-t border-sand-100">
            {{-- Pax Stepper for Addons/Transfers/Activities/Packages --}}
            <template x-if="item.item_type === 'addon' || item.item_type === 'activity' || item.item_type === 'package'">
                <div class="flex items-center rounded-xl border border-sand-200 bg-white p-0.5 shadow-2xs">
                    <button @click="updatePax(item.id, (item.selected_pax || 1) - 1)"
                            :disabled="item.selected_pax <= 1"
                            :aria-label="'Decrease ' + item.title + ' passenger count'"
                            class="w-6 h-6 rounded-lg flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer font-black text-xs">-</button>
                    <span class="w-12 text-center text-[11px] font-bold text-ink-900" x-text="item.selected_pax + ' pax'"></span>
                    <button @click="updatePax(item.id, (item.selected_pax || 1) + 1)"
                            :aria-label="'Increase ' + item.title + ' passenger count'"
                            class="w-6 h-6 rounded-lg flex items-center justify-center text-ink-600 hover:bg-sand-100 transition cursor-pointer font-black text-xs">+</button>
                </div>
            </template>

            {{-- Quantity Stepper for Rooms --}}
            <template x-if="item.item_type !== 'addon' && item.item_type !== 'activity' && item.item_type !== 'package'">
                <div class="flex items-center rounded-xl border border-sand-200 bg-white p-0.5 shadow-2xs">
                    <button @click="updateQty(item.id, item.quantity - 1)"
                            :disabled="item.quantity <= 1"
                            :aria-label="'Decrease ' + item.title + ' quantity'"
                            class="w-6 h-6 rounded-lg flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer font-black text-xs">-</button>
                    <span class="w-8 text-center text-xs font-bold text-ink-900" x-text="item.quantity"></span>
                    <button @click="updateQty(item.id, item.quantity + 1)"
                            :aria-label="'Increase ' + item.title + ' quantity'"
                            class="w-6 h-6 rounded-lg flex items-center justify-center text-ink-600 hover:bg-sand-100 transition cursor-pointer font-black text-xs">+</button>
                </div>
            </template>

            {{-- Price --}}
            <div class="text-right shrink-0">
                <template x-if="item.item_type === 'addon' || item.item_type === 'activity' || item.item_type === 'package'">
                    <span class="block text-[10px] text-ink-400 font-medium" x-text="item.formatted_unit_rate + ' / pax'"></span>
                </template>
                <template x-if="item.item_type !== 'addon' && item.item_type !== 'activity' && item.item_type !== 'package' && item.quantity > 1">
                    <span class="block text-[10px] text-ink-400 font-medium" x-text="item.quantity + ' × ' + item.formatted_unit_rate"></span>
                </template>
                <span class="block text-xs sm:text-sm font-extrabold text-ink-900 font-headline" x-text="item.formatted_subtotal"></span>
            </div>
        </div>
    </div>
</div>
