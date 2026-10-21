<form x-show="draftItinerary" x-cloak @submit.prevent="updateDraft()" class="mt-5 space-y-3 border-t border-sand-200 pt-4 dark:border-slate-700" aria-label="Edit chatbot draft">
    <p class="text-sm text-slate-600 dark:text-slate-300">Keep this draft undated, or set dates before building a checked schedule. A hotel base may be needed to calculate routes.</p>
    <label class="block text-sm font-semibold">Trip name<input x-model="title" required maxlength="150" class="mt-1 w-full min-h-11 rounded-lg border-sand-300 dark:bg-slate-800"></label>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label class="block text-sm font-semibold">Arrival<input type="date" x-model="startDate" :min="minimumArrival" class="mt-1 w-full min-w-0 min-h-11 rounded-lg border-sand-300 dark:bg-slate-800"></label>
        <label class="block text-sm font-semibold">Departure<input type="date" x-model="endDate" :min="startDate" class="mt-1 w-full min-w-0 min-h-11 rounded-lg border-sand-300 dark:bg-slate-800"></label>
        <label class="block text-sm font-semibold">Travelers<input type="number" x-model="pax" min="1" max="20" step="1" required class="mt-1 w-full min-h-11 rounded-lg border-sand-300 dark:bg-slate-800"></label>
        <label class="block text-sm font-semibold">Getting around<select x-model="mode" class="mt-1 w-full min-h-11 rounded-lg border-sand-300 dark:bg-slate-800"><option value="walking">Walking</option><option value="driving">By vehicle</option></select></label>
    </div>
    <button type="submit" :disabled="busy" class="min-h-11 rounded-lg border border-ocean-700 px-4 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300">Save draft details</button>
</form>
