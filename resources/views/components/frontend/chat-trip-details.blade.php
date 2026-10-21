<template x-if="msg.trip_details_form && msg.trip_details_form.id === activeTripFormId && handoffStatus !== 'active'">
    <details class="mt-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700 font-body" :open="msg.trip_details_form.expanded"
        x-data="{ form: msg.trip_details_form, values: { destination_id: '', pax: '', days: '', check_in_date: '', check_out_date: '', dates_undecided: false, max_price: '', budget_basis: 'unknown', budget_period: '', budget_coverage: '', preferences: [], notes: '', ...Object.fromEntries(Object.entries(msg.trip_details_form.values).filter(([key]) => ['destination_id','pax','days','check_in_date','check_out_date','dates_undecided','max_price','budget_basis','budget_period','budget_coverage','preferences','notes'].includes(key))) } }">
        <summary class="min-h-11 cursor-pointer py-2 text-sm font-bold">Trip details — enter or edit together</summary>
        <p class="mb-3 text-xs text-slate-600 dark:text-slate-300">Use the details below, or type your answers in the chat.</p>
        <form x-effect="if (!values.dates_undecided &amp;&amp; values.check_in_date &amp;&amp; values.check_out_date) { const nights = (Date.parse(values.check_out_date) - Date.parse(values.check_in_date)) / 86400000; if (nights > 0) values.days = nights + 1; }" @submit.prevent="await submitTripDetails(form, values); $nextTick(() => { const invalid = $el.querySelector('[aria-invalid=true]'); invalid?.focus(); })" class="space-y-3" :aria-busy="sending">
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
                    <div><label :for="form.id + '-pax'" class="block text-xs font-bold mb-1">Travelers</label><input :id="form.id + '-pax'" type="number" min="1" max="20" step="1" x-model="values.pax" class="w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.pax'])" :aria-describedby="form.id + '-errors'"></div>
                    <div x-show="form.needs_duration && values.dates_undecided"><label :for="form.id + '-days'" class="block text-xs font-bold mb-1">Trip length (days)</label><input :id="form.id + '-days'" type="number" min="1" max="30" step="1" x-model="values.days" :required="form.needs_duration && values.dates_undecided" :disabled="!values.dates_undecided || sending" class="w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.days'])" :aria-describedby="form.id + '-errors'"></div>
                </div>
                <fieldset x-show="form.needs_dates" class="space-y-2">
                    <legend class="text-xs font-bold">Travel dates</legend>
                    <label class="flex min-h-11 items-center gap-2 text-xs"><input type="checkbox" x-model="values.dates_undecided">Dates not decided yet</label>
                    <div x-show="!values.dates_undecided" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><label :for="form.id + '-arrival'" class="block text-xs mb-1">Arrival</label><input :id="form.id + '-arrival'" type="date" min="{{ now()->toDateString() }}" x-model="values.check_in_date" :required="form.needs_dates && !values.dates_undecided" :disabled="values.dates_undecided || sending" class="w-full min-w-0 min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.check_in_date'])" :aria-describedby="form.id + '-errors'"></div>
                        <div><label :for="form.id + '-departure'" class="block text-xs mb-1">Departure</label><input :id="form.id + '-departure'" type="date" x-model="values.check_out_date" :required="form.needs_dates && !values.dates_undecided" :min="values.check_in_date" :disabled="values.dates_undecided || sending" class="w-full min-w-0 min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.check_out_date'])" :aria-describedby="form.id + '-errors'"></div>
                    </div>
                </fieldset>
                <p x-show="!values.dates_undecided && values.check_in_date && values.check_out_date" aria-live="polite" class="text-xs text-slate-600 dark:text-slate-300" x-text="(() => { const nights = (Date.parse(values.check_out_date) - Date.parse(values.check_in_date)) / 86400000; return nights > 0 ? `${nights + 1} days, ${nights} nights` : 'Departure must be after arrival.'; })()"></p>
                <fieldset class="space-y-2">
                    <legend class="text-xs font-bold">Budget (optional)</legend>
                    <label :for="form.id + '-budget'" class="block text-xs">Amount in pesos</label><input :id="form.id + '-budget'" type="number" min="1" max="10000000" step="0.01" x-model="values.max_price" placeholder="No budget preference" class="w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.max_price'])" :aria-describedby="form.id + '-errors'">
                    <div x-show="values.max_price" class="space-y-2">
                        <label class="block text-xs">Amount applies to<select x-model="values.budget_basis" class="mt-1 w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800"><option value="unknown">Not sure yet</option><option value="trip_total">Whole group total</option><option value="per_person">Each person</option><option value="per_night">Group per night</option><option value="per_activity">Each activity</option></select></label>
                        <label class="block text-xs">Time period<select x-model="values.budget_period" class="mt-1 w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800"><option value="">Not sure yet</option><option value="total">Whole stay / trip</option><option value="per_night">Per night</option></select></label>
                        <label class="block text-xs">Includes<select x-model="values.budget_coverage" class="mt-1 w-full min-h-11 rounded-lg border-slate-300 text-sm dark:bg-slate-800"><option value="">Not sure yet</option><option value="all_inclusive">Hotel and activities</option><option value="lodging">Accommodation only</option><option value="activities">Activities only</option></select></label>
                    </div>
                </fieldset>
                <details class="border-t border-slate-200 pt-2 dark:border-slate-700">
                    <summary class="min-h-11 cursor-pointer py-2 text-xs font-bold">Preferences (optional) — skip if you like</summary>
                    <p class="text-xs text-slate-500">These guide matching where the catalog has relevant options.</p>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="preference in ['adventure','relaxing','beachfront','budget-friendly','family-friendly','indoor']" :key="preference"><label class="flex min-h-11 items-center gap-2 text-xs"><input type="checkbox" :value="preference" x-model="values.preferences"><span x-text="preference"></span></label></template>
                    </div>
                    <label :for="form.id + '-notes'" class="block text-xs font-bold mb-1">Anything else, including things to avoid</label><textarea :id="form.id + '-notes'" x-model="values.notes" maxlength="300" rows="2" class="w-full rounded-lg border-slate-300 text-sm dark:bg-slate-800" :aria-invalid="Boolean(controlErrors['trip_details.notes'])" :aria-describedby="form.id + '-errors'"></textarea>
                </details>
                <div :id="form.id + '-errors'" role="alert" class="text-xs text-coral-700 dark:text-coral-300"><template x-for="(error, key) in controlErrors" :key="key"><p x-text="error"></p></template></div>
                <button type="submit" :disabled="sending" class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Use these details</button>
            </fieldset>
        </form>
    </details>
</template>
