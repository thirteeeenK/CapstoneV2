@props([
    'categoryRules' => null,
    'maxGuests' => 10,
    'leadName' => Auth::user()->name ?? '',
    'leadEmail' => Auth::user()->email ?? '',
    'leadPhone' => Auth::user()->phone_number ?? (Auth::user()->phone ?? ''),
])

@php
    if (!$categoryRules) {
        $categoryRules = \App\Models\PassengerCategoryRule::where('is_active', true)->get();
    }
@endphp

<div x-data="guestManifestBuilder({ 
        leadName: '{{ addslashes($leadName) }}', 
        leadEmail: '{{ addslashes($leadEmail) }}', 
        leadPhone: '{{ addslashes($leadPhone) }}',
        maxGuests: {{ (int)$maxGuests }},
        rules: {{ json_encode($categoryRules) }}
     })"
     x-init="initManifest()"
     class="space-y-6 font-body">

    {{-- SECTION 1: Lead Guest Contact Information --}}
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-5">
        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center font-bold">
                <span class="material-symbols-outlined text-xl">contact_mail</span>
            </div>
            <div>
                <h3 class="text-base sm:text-lg font-bold text-slate-900 font-headline">Lead Traveler Information</h3>
                <p class="text-xs text-slate-500">Primary contact for booking vouchers, status updates, and vouchers.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Full Name *</label>
                <input type="text" 
                       name="contact_name" 
                       x-model="leadName" 
                       required 
                       placeholder="e.g. Juan Dela Cruz" 
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-slate-50/50">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Email Address *</label>
                <input type="email" 
                       name="contact_email" 
                       x-model="leadEmail" 
                       required 
                       placeholder="e.g. juan@example.com" 
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-slate-50/50">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Mobile Phone Number *</label>
                <input type="tel" 
                       name="contact_phone" 
                       x-model="leadPhone" 
                       required 
                       placeholder="e.g. 0917 123 4567" 
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-slate-50/50">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Special Instructions / Requests</label>
                <input type="text" 
                       name="special_requests" 
                       placeholder="e.g. Non-smoking room, extra towels, early check-in" 
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-slate-50/50">
            </div>
        </div>
    </div>

    {{-- SECTION 2: Dynamic Accompanying Guests Array Builder --}}
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-5">
        
        {{-- Header --}}
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-xl">badge</span>
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-slate-900 font-headline">Passenger & Guest Manifest</h3>
                    <p class="text-xs text-slate-500">Provide full names and passenger categories for all guests joining this travel booking.</p>
                </div>
            </div>
        </div>

        {{-- Dynamic Guest List Array --}}
        <div class="space-y-4">
            <template x-for="(guest, index) in guests" :key="index">
                <div class="bg-slate-50/80 p-4 sm:p-5 rounded-2xl border border-slate-200/70 relative transition duration-200 hover:border-slate-300">
                    
                    {{-- Row Header & Actions --}}
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-sky-600">person</span>
                            <span x-text="guest.is_lead ? 'Lead Traveler' : 'Accompanying Guest ' + (index + 1)"></span>
                        </span>

                        <template x-if="!guest.is_lead">
                            <button type="button" 
                                    @click="removeGuest(index)" 
                                    class="text-slate-400 hover:text-rose-600 text-xs font-semibold flex items-center gap-1 p-1 rounded-md hover:bg-rose-50 transition"
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
                                <template x-for="rule in rules" :key="rule.category_name">
                                    <option :value="rule.category_name" 
                                            x-text="rule.display_label + (rule.adjustment_type === 'discount' ? ' (-₱' + Number(rule.amount).toFixed(0) + ' Discount)' : (rule.adjustment_type === 'surcharge' ? ' (+₱' + Number(rule.amount).toFixed(0) + ' Surcharge)' : ''))">
                                    </option>
                                </template>
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
                    Max room capacity: <strong class="text-slate-800" x-text="maxGuests + ' Guests'"></strong>
                </span>
            </template>
        </div>

        {{-- Hidden JSON Field to Submit Array to Backend --}}
        <input type="hidden" name="guest_manifest" :value="JSON.stringify(guests)">
    </div>
</div>

<script>
function guestManifestBuilder(config) {
    return {
        leadName: config.leadName || '',
        leadEmail: config.leadEmail || '',
        leadPhone: config.leadPhone || '',
        maxGuests: config.maxGuests || 10,
        rules: config.rules || [],
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
