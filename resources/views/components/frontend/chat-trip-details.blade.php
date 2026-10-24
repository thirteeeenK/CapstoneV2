<template x-if="msg.trip_details_form && msg.trip_details_form.id === activeTripFormId && handoffStatus !== 'active'">
    <details class="mt-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700 font-body" :open="msg.trip_details_form.expanded"
        x-data="{ form: msg.trip_details_form,
            values: { destination_id: '', pax: '', days: '', check_in_date: '', check_out_date: '', dates_undecided: false, include_accommodation: true, max_price: '', budget_basis: 'unknown', budget_period: '', budget_coverage: '', preferences: [], notes: '', ...Object.fromEntries(Object.entries(msg.trip_details_form.values).filter(([key]) => ['destination_id','pax','days','check_in_date','check_out_date','dates_undecided','include_accommodation','max_price','budget_basis','budget_period','budget_coverage','preferences','notes'].includes(key))) },
            activityPreferences: [['adventure', 'Adventure'], ['relaxing', 'Relaxing'], ['indoor', 'Indoor activities']],
            stayPreferences: [['beachfront', 'Beachfront stay'], ['budget-friendly', 'Budget-friendly'], ['family-friendly', 'Family-friendly']],
            init() {
                // A legacy group-per-night amount is shown as Whole group + Per night.
                if (this.values.budget_basis === 'per_night') { this.values.budget_basis = 'trip_total'; this.values.budget_period = 'per_night'; }
                if (this.form.needs_duration && !this.values.days && this.values.check_in_date && this.values.check_out_date) {
                    this.values.days = window.tripDuration?.tripDays(this.values.check_in_date, this.values.check_out_date) || '';
                }
            },
            get departure() { return window.tripDuration?.tripEndDate(this.values.check_in_date, Number(this.values.days)) || ''; },
            get budgetPreview() {
                const amount = Number(this.values.max_price), pax = Number(this.values.pax), days = Number(this.values.days);
                if (!amount) return '';
                const peso = (value) => '₱' + value.toLocaleString('en-PH', { maximumFractionDigits: 2 });
                const unit = this.values.budget_period === 'per_night' ? ' per night' : ' for the whole trip';
                if (this.values.budget_basis === 'per_person') return pax ? `${peso(amount)} per traveler${unit} = ${peso(amount * pax)} for ${pax} travelers` : 'Add the number of travelers to calculate the group total.';
                if (this.values.budget_basis === 'trip_total' && this.values.budget_period === 'per_night' && days > 1) return `${peso(amount)} per night × ${days - 1} nights = ${peso(amount * (days - 1))} for the stay`;
                return '';
            } }"
        x-effect="if (!values.include_accommodation) { values.preferences = values.preferences.filter(value => !stayPreferences.some(([key]) => key === value)); if (values.budget_coverage === 'lodging') values.budget_coverage = ''; if (values.budget_period === 'per_night') values.budget_period = ''; }">
        <summary class="min-h-11 cursor-pointer py-2 text-sm font-bold" x-text="form.has_itinerary ? 'Edit trip details' : 'Trip details — enter or edit together'"></summary>
        <p class="mb-3 text-xs text-slate-600 dark:text-slate-300">Use the details below, or type your answers in the chat.</p>
        <form @submit.prevent="await submitTripDetails(form, values); $nextTick(() => { const invalid = $el.querySelector('[aria-invalid=true]'); invalid?.focus(); })" class="space-y-3" :aria-busy="sending">
            <fieldset :disabled="sending" class="space-y-3">
                <legend class="sr-only">Trip details</legend>
                <div>
                    <label :for="form.id + '-destination'" class="block text-xs font-bold mb-1">Destination</label>
                    <select :id="form.id + '-destination'" x-model="values.destination_id" required class="w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800"
                        :aria-invalid="Boolean(controlErrors['trip_details.destination_id'])" :aria-describedby="form.id + '-errors'">
                        <option value="">Choose a destination</option>
                        <template x-for="destination in form.destinations" :key="destination.id"><option :value="destination.id" :selected="Number(values.destination_id) === destination.id" x-text="destination.name"></option></template>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label :for="form.id + '-pax'" class="block text-xs font-bold mb-1">Number of travelers</label><input :id="form.id + '-pax'" type="number" min="1" max="20" step="1" x-model="values.pax" class="w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.pax'])" :aria-describedby="form.id + '-pax-hint ' + form.id + '-errors'">
                        <p :id="form.id + '-pax-hint'" x-show="!values.pax" class="mt-1 text-[11px] text-slate-500">Add travelers to calculate your estimate.</p></div>
                    <div x-show="form.needs_duration"><label :for="form.id + '-days'" class="block text-xs font-bold mb-1">Trip duration (days)</label><input :id="form.id + '-days'" type="number" min="1" max="30" step="1" x-model="values.days" :required="form.needs_duration" :disabled="!form.needs_duration || sending" class="w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.days'])" :aria-describedby="form.id + '-duration ' + form.id + '-errors'">
                        <p :id="form.id + '-duration'" aria-live="polite" class="mt-1 text-[11px] text-slate-600 dark:text-slate-300" x-text="window.tripDuration?.durationLabel(Number(values.days)) || ''"></p></div>
                </div>
                <fieldset x-show="form.needs_dates" class="space-y-2">
                    <legend class="text-xs font-bold">Travel dates</legend>
                    <div class="flex flex-wrap gap-4 text-xs">
                        <label class="flex min-h-11 items-center gap-2"><input type="radio" :name="form.id + '-dates-mode'" :value="false" :checked="!values.dates_undecided" @change="values.dates_undecided = false">Choose dates</label>
                        <label class="flex min-h-11 items-center gap-2"><input type="radio" :name="form.id + '-dates-mode'" :value="true" :checked="values.dates_undecided" @change="values.dates_undecided = true">Decide later</label>
                    </div>
                    <div x-show="!values.dates_undecided" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><label :for="form.id + '-arrival'" class="block text-xs mb-1">Arrival</label><input :id="form.id + '-arrival'" type="date" :min="form.minimum_arrival" x-model="values.check_in_date" :required="form.needs_dates && !values.dates_undecided" :disabled="values.dates_undecided || sending" class="w-full min-w-0 min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.check_in_date'])" :aria-describedby="form.id + '-lead ' + form.id + '-errors'"></div>
                        <template x-if="form.needs_duration">
                            <div><p class="block text-xs mb-1">Departure</p><p class="min-h-11 flex items-center rounded-lg border border-dashed border-slate-300 px-3 text-sm dark:border-slate-600" aria-live="polite" x-text="departure || 'Set the arrival and trip duration'"></p></div>
                        </template>
                        <template x-if="!form.needs_duration">
                            <div><label :for="form.id + '-departure'" class="block text-xs mb-1">Departure</label><input :id="form.id + '-departure'" type="date" x-model="values.check_out_date" :required="form.needs_dates && !values.dates_undecided" :min="values.check_in_date" :disabled="values.dates_undecided || sending" class="w-full min-w-0 min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.check_out_date'])" :aria-describedby="form.id + '-errors'"></div>
                        </template>
                    </div>
                    <p :id="form.id + '-lead'" x-show="!values.dates_undecided" class="text-[11px] text-slate-500">Earliest arrival: <span x-text="form.minimum_arrival"></span> (two calendar days ahead, Philippine time). The departure follows from the trip duration.</p>
                    <p x-show="!values.dates_undecided && form.needs_duration && Number(values.days) === 1" class="text-[11px] text-coral-700 dark:text-coral-300">A dated trip needs at least 2 days (1 night). Keep the dates undecided for a one-day activity plan.</p>
                    <p x-show="values.dates_undecided" class="text-[11px] text-slate-500">Your duration, travelers, budget and preferences are kept. Availability and weather are checked once dates are chosen.</p>
                </fieldset>
                <label x-show="form.needs_duration" class="flex min-h-11 items-start gap-2 text-xs"><input type="checkbox" x-model="values.include_accommodation" class="mt-0.5">
                    <span><span class="font-bold">Include accommodation</span> <span class="block text-slate-500" x-text="Number(values.days) === 1 ? 'A one-day trip has no overnight stay.' : 'Untick if you already have a place to stay; the plan will cover activities only.'"></span></span></label>
                <fieldset class="space-y-2">
                    <legend class="text-xs font-bold">Budget in PHP (optional)</legend>
                    <label :for="form.id + '-budget'" class="block text-xs">Amount</label><input :id="form.id + '-budget'" type="number" min="1" max="10000000" step="0.01" x-model="values.max_price" placeholder="No budget limit" class="w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.max_price'])" :aria-describedby="form.id + '-budget-preview ' + form.id + '-errors'">
                    <div x-show="values.max_price" class="space-y-2">
                        <label class="block text-xs">The amount is for<select x-model="values.budget_basis" class="mt-1 w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800"><option value="unknown">Not sure yet</option><option value="trip_total">The whole group</option><option value="per_person">Each traveler</option><option value="per_activity" x-show="!form.needs_duration">Each activity</option></select></label>
                        <label x-show="values.include_accommodation && (!form.needs_duration || Number(values.days) > 1)" class="block text-xs">Covering<select x-model="values.budget_period" class="mt-1 w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800"><option value="">Not sure yet</option><option value="total">The whole trip</option><option value="per_night">Each night</option></select></label>
                        <label class="block text-xs">Spent on<select x-model="values.budget_coverage" class="mt-1 w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800"><option value="">Not sure yet</option><option value="all_inclusive" x-show="values.include_accommodation">Accommodation and selected activities</option><option value="lodging" x-show="values.include_accommodation">Accommodation only</option><option value="activities">Activities only</option></select></label>
                        <p :id="form.id + '-budget-preview'" aria-live="polite" class="text-[11px] text-slate-600 dark:text-slate-300" x-text="budgetPreview"></p>
                        <p class="text-[11px] text-slate-500">Flights, meals and transfers are not included unless they appear in the quote.</p>
                    </div>
                </fieldset>
                <details class="border-t border-slate-200 pt-2 dark:border-slate-700">
                    <summary class="min-h-11 cursor-pointer py-2 text-xs font-bold">Preferences (optional)</summary>
                    <p class="text-xs text-slate-500">These guide matching where the catalog has relevant options.</p>
                    <p class="mt-2 text-xs font-bold">Activities you enjoy</p>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="[key, label] in activityPreferences" :key="key"><label class="flex min-h-11 items-center gap-2 text-xs"><input type="checkbox" :value="key" x-model="values.preferences"><span x-text="label"></span></label></template>
                    </div>
                    <div x-show="values.include_accommodation">
                        <p class="mt-2 text-xs font-bold">Stay preferences</p>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="[key, label] in stayPreferences" :key="key"><label class="flex min-h-11 items-center gap-2 text-xs"><input type="checkbox" :value="key" x-model="values.preferences"><span x-text="label"></span></label></template>
                        </div>
                    </div>
                    <label :for="form.id + '-notes'" class="mt-2 block text-xs font-bold mb-1">Other preferences or things to avoid</label><textarea :id="form.id + '-notes'" x-model="values.notes" maxlength="300" rows="2" placeholder="e.g. no early mornings, avoid snorkeling" class="w-full rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.notes'])" :aria-describedby="form.id + '-errors'"></textarea>
                </details>
                <div :id="form.id + '-errors'" role="alert" class="text-xs text-coral-700 dark:text-coral-300"><template x-for="(error, key) in controlErrors" :key="key"><p x-text="error"></p></template></div>
                <button type="submit" :disabled="sending" class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                    x-text="form.has_itinerary ? 'Update itinerary' : (form.needs_duration ? 'Create my itinerary' : 'Search with these details')"></button>
            </fieldset>
        </form>
    </details>
</template>
