@props([
    'categoryRules' => null,
    'maxGuests' => 10,
    'baseGuests' => 2,
    'initialGuests' => 1,
    'leadName' => Auth::user()->name ?? '',
    'leadEmail' => Auth::user()->email ?? '',
    'leadPhone' => Auth::user()->phone_number ?? (Auth::user()->phone ?? ''),
])

@php
    if (empty($categoryRules) || (is_object($categoryRules) && method_exists($categoryRules, 'isEmpty') && $categoryRules->isEmpty()) || (is_countable($categoryRules) && count($categoryRules) === 0)) {
        $categoryRules = \App\Models\PassengerCategoryRule::where('is_active', true)->get();
    }

    if (empty($categoryRules) || (is_object($categoryRules) && method_exists($categoryRules, 'isEmpty') && $categoryRules->isEmpty()) || (is_countable($categoryRules) && count($categoryRules) === 0)) {
        $categoryRules = collect([
            ['category_name' => 'Adult', 'display_label' => 'Regular Adult', 'adjustment_type' => 'none', 'amount' => 0],
            ['category_name' => 'Senior Citizen', 'display_label' => 'Senior Citizen', 'adjustment_type' => 'discount', 'amount' => 50],
            ['category_name' => 'PWD', 'display_label' => 'PWD (Person with Disability)', 'adjustment_type' => 'discount', 'amount' => 50],
            ['category_name' => 'Student', 'display_label' => 'Student', 'adjustment_type' => 'discount', 'amount' => 50],
            ['category_name' => 'Child', 'display_label' => 'Child (3-17)', 'adjustment_type' => 'discount', 'amount' => 50],
            ['category_name' => 'Infant', 'display_label' => 'Infant (0-2)', 'adjustment_type' => 'discount', 'amount' => 50],
            ['category_name' => 'Foreigner', 'display_label' => 'Foreign Tourist', 'adjustment_type' => 'surcharge', 'amount' => 150],
        ]);
    }
@endphp

<div x-data="guestManifestBuilder({ 
        leadName: '{{ addslashes($leadName) }}', 
        leadEmail: '{{ addslashes($leadEmail) }}', 
        leadPhone: '{{ addslashes($leadPhone) }}',
        maxGuests: {{ (int)$maxGuests }},
        baseGuests: {{ (int)$baseGuests }},
        initialGuests: {{ (int)$initialGuests }},
        rules: {{ json_encode($categoryRules) }}
     })"
     x-init="initManifest()"
     class="space-y-6 font-body">

    {{-- SECTION 1: Lead Guest Contact Information --}}
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4.5 sm:p-7 border border-slate-200/80 shadow-xs space-y-4 sm:space-y-5">
        <div class="flex items-center justify-between gap-2.5 sm:gap-3 border-b border-slate-100 pb-3.5">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center font-bold shrink-0">
                    <span class="material-symbols-outlined text-lg sm:text-xl">contact_mail</span>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 font-headline">Lead Guest Contact Details</h3>
                    <p class="text-[11px] sm:text-xs text-slate-500">We will send your voucher, payment updates, and itinerary here.</p>
                </div>
            </div>
            <span class="text-[11px] font-bold text-sky-700 bg-sky-50 px-2.5 py-0.5 rounded-full border border-sky-100 shrink-0">Primary</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Lead Traveler Full Name *</label>
                <input type="text" 
                       name="contact_name" 
                       x-model="leadName" 
                       required 
                       placeholder="e.g. Juan Dela Cruz" 
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-slate-50/50">
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Email Address *</label>
                <input type="email" 
                       name="contact_email" 
                       x-model="leadEmail" 
                       required 
                       placeholder="e.g. juan@example.com" 
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-slate-50/50">
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Mobile Phone Number *</label>
                <input type="tel" 
                       name="contact_phone" 
                       x-model="leadPhone" 
                       required 
                       placeholder="e.g. 0917 123 4567" 
                       maxlength="11" inputmode="numeric" pattern="[0-9]{11}"
                       title="Enter exactly 11 digits"
                       @input="leadPhone = leadPhone.replace(/\D/g, '').slice(0, 11)" 
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-slate-50/50">
            </div>
        </div>
    </div>

    {{-- SECTION 2: Dynamic Accompanying Guests Array Builder --}}
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4.5 sm:p-7 border border-slate-200/80 shadow-xs space-y-4 sm:space-y-5">
        
        {{-- Header --}}
        <div class="flex items-center justify-between gap-2.5 sm:gap-3 border-b border-slate-100 pb-3.5">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center font-bold shrink-0">
                    <span class="material-symbols-outlined text-lg sm:text-xl">badge</span>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 font-headline">Hotel Guest Manifest</h3>
                    <p class="text-[11px] sm:text-xs text-slate-500">Provide full names and categories for all guests staying in this Hotel and Room.</p>
                </div>
            </div>
        </div>

        {{-- Dynamic Guest List Array --}}
        <div class="space-y-3 sm:space-y-4">
            <template x-for="(guest, index) in guests" :key="index">
                <div class="bg-slate-50/80 p-3.5 sm:p-4 rounded-xl sm:rounded-2xl border border-slate-200/70 relative transition duration-200 hover:border-slate-300">
                    
                    {{-- Row Header & Actions --}}
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-sky-600">person</span>
                            <span x-text="guest.is_lead ? 'Lead Traveler' : 'Accompanying Guest ' + (index + 1)"></span>
                        </span>

                        <template x-if="!guest.is_lead">
                            <button type="button" 
                                    @click="removeGuest(index)" 
                                    class="text-slate-400 hover:text-rose-600 text-xs font-semibold flex items-center gap-1 p-1 rounded-md hover:bg-rose-50 transition cursor-pointer"
                                    title="Remove this guest">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                <span>Remove</span>
                            </button>
                        </template>
                    </div>

                    {{-- Form Fields --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        {{-- Name --}}
                        <div class="sm:col-span-1">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Full Name *</label>
                            <input type="text" 
                                   x-model="guest.full_name" 
                                   required 
                                   placeholder="Full Name as in ID" 
                                   class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                        </div>

                        {{-- Dynamic DB Category Select --}}
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Passenger Category *</label>
                            <select x-model="guest.category" 
                                    @change="notifyManifestChange()"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                @foreach($categoryRules as $rule)
                                    @php
                                        $rObj = is_array($rule) ? (object)$rule : $rule;
                                        $rName = $rObj->category_name;
                                        $rLabel = $rObj->display_label;
                                        $rType = $rObj->adjustment_type ?? 'none';
                                        $rAmt = (float)($rObj->amount ?? 0);
                                        
                                        $labelText = $rLabel;
                                        if ($rType === 'discount' && $rAmt > 0) {
                                            $labelText .= ' (-₱' . number_format($rAmt, 0) . ' Discount)';
                                        } elseif ($rType === 'surcharge' && $rAmt > 0) {
                                            $labelText .= ' (+₱' . number_format($rAmt, 0) . ' Surcharge)';
                                        }
                                    @endphp
                                    <option value="{{ $rName }}">{{ $labelText }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Special Notes --}}
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Dietary / Notes</label>
                            <input type="text" 
                                   x-model="guest.special_notes" 
                                   placeholder="e.g. Senior ID #, Vegetarian" 
                                   class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-700 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- + Add Accompanying Guest Action Button (Restricted by maxGuests capacity) --}}
        <div class="pt-2 flex items-center justify-between">
            <button type="button" 
                    @click="addGuest()" 
                    :disabled="guests.length >= maxGuests"
                    :class="guests.length >= maxGuests ? 'opacity-50 cursor-not-allowed bg-slate-100 text-slate-400 border-slate-200' : 'bg-sky-50 hover:bg-sky-100 text-sky-700 border-sky-200 hover:border-sky-300 cursor-pointer'"
                    class="px-5 py-2.5 rounded-xl border font-bold text-xs flex items-center justify-center gap-2 shadow-2xs transition">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                <span x-text="guests.length >= maxGuests ? 'Max Occupancy Limit Reached (' + maxGuests + ' Guests)' : '+ Add Accompanying Guest'"></span>
            </button>
            <template x-if="maxGuests && maxGuests < 99">
                <span class="text-[11px] font-semibold text-slate-500">
                    Base capacity: <strong class="text-slate-800" x-text="baseGuests + ' Guest' + (baseGuests > 1 ? 's' : '')"></strong>
                    · Max capacity: <strong class="text-slate-800" x-text="maxGuests + ' Guest' + (maxGuests > 1 ? 's' : '')"></strong>
                </span>
            </template>
        </div>

        {{-- Hidden JSON Field to Submit Array to Backend --}}
        <input type="hidden" name="guest_manifest" :value="JSON.stringify(guests)">
    </div>
</div>

<script>
function guestManifestBuilder(config) {
    const defaultRules = [
        { category_name: 'Adult', display_label: 'Regular Adult', adjustment_type: 'none', amount: 0 },
        { category_name: 'Senior Citizen', display_label: 'Senior Citizen', adjustment_type: 'discount', amount: 50 },
        { category_name: 'PWD', display_label: 'PWD (Person with Disability)', adjustment_type: 'discount', amount: 50 },
        { category_name: 'Student', display_label: 'Student', adjustment_type: 'discount', amount: 50 },
        { category_name: 'Child', display_label: 'Child (3-17)', adjustment_type: 'discount', amount: 50 },
        { category_name: 'Infant', display_label: 'Infant (0-2)', adjustment_type: 'discount', amount: 50 },
        { category_name: 'Foreigner', display_label: 'Foreign Tourist', adjustment_type: 'surcharge', amount: 150 }
    ];

    return {
        leadName: config.leadName || '',
        leadEmail: config.leadEmail || '',
        leadPhone: config.leadPhone || '',
        maxGuests: config.maxGuests || 10,
        baseGuests: config.baseGuests || 2,
        initialGuests: Math.min(config.maxGuests || 10, Math.max(1, config.initialGuests || 1)),
        rules: (config.rules && config.rules.length > 0) ? config.rules : defaultRules,
        guests: [],

        initManifest() {
            this.guests = [
                {
                    full_name: this.leadName,
                    category: 'Adult',
                    is_lead: true,
                    special_notes: 'Lead Traveler'
                }
            ];

            const needed = this.initialGuests;
            for (let i = 1; i < needed; i++) {
                this.guests.push({
                    full_name: '',
                    category: 'Adult',
                    is_lead: false,
                    special_notes: ''
                });
            }

            this.$watch('leadName', value => {
                if (this.guests.length > 0) {
                    this.guests[0].full_name = value;
                }
            });

            this.notifyManifestChange();
        },

        addGuest() {
            if (this.guests.length >= this.maxGuests) {
                alert('Maximum capacity of ' + this.maxGuests + ' guests reached for this reservation.');
                return;
            }
            this.guests.push({
                full_name: '',
                category: 'Adult',
                is_lead: false,
                special_notes: ''
            });
            this.notifyManifestChange();
        },

        removeGuest(index) {
            if (index > 0 && index < this.guests.length) {
                this.guests.splice(index, 1);
                this.notifyManifestChange();
            }
        },

        notifyManifestChange() {
            let totalDiscount = 0;
            let totalSurcharge = 0;
            let breakdown = [];

            const rulesMap = {};
            this.rules.forEach(r => {
                rulesMap[r.category_name] = r;
            });

            this.guests.forEach((g, idx) => {
                const rule = rulesMap[g.category];
                if (rule && rule.adjustment_type !== 'none') {
                    const amt = Number(rule.amount) || 0;
                    const guestName = g.full_name && g.full_name.trim() ? g.full_name.trim() : (g.is_lead ? 'Lead Guest' : 'Guest ' + (idx + 1));

                    if (rule.adjustment_type === 'discount') {
                        totalDiscount += amt;
                        breakdown.push({
                            type: 'discount',
                            category: rule.category_name,
                            label: rule.display_label + ' Discount (' + guestName + ')',
                            amount: amt
                        });
                    } else if (rule.adjustment_type === 'surcharge') {
                        totalSurcharge += amt;
                        breakdown.push({
                            type: 'surcharge',
                            category: rule.category_name,
                            label: rule.display_label + ' Surcharge (' + guestName + ')',
                            amount: amt
                        });
                    }
                }
            });

            window.dispatchEvent(new CustomEvent('manifest-pricing-updated', {
                detail: {
                    key: 'main_room',
                    guest_count: this.guests.length,
                    totalDiscount: totalDiscount,
                    totalSurcharge: totalSurcharge,
                    breakdown: breakdown,
                    guests: this.guests
                }
            }));
        }
    };
}
</script>
