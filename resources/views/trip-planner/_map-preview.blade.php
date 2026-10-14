<section x-show="previewPlaces.length" x-cloak
         aria-labelledby="planner-map-preview-heading"
         @click.stop @dblclick.stop @wheel.stop @pointerdown.stop
         @keydown.escape.window="if (previewPlaces.length && !editing && !deleting && !$store.preview.room && !$store.preview.activity && !$store.preview.package) { $event.preventDefault(); dismissMapPreview(); }"
         class="absolute bottom-6 left-3 right-3 z-10 max-h-[calc(100%-4rem)] overflow-y-auto overscroll-contain rounded-xl bg-white p-4 text-ink-900 shadow-lg dark:bg-slate-900 dark:text-slate-100 sm:left-auto sm:w-80 lg:bottom-8 lg:right-5 lg:w-96">
    <div class="flex items-start gap-3">
        <div x-show="previewKey" class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-lg bg-sand-100 text-ocean-700 dark:bg-slate-800 dark:text-ocean-300 sm:h-20 sm:w-20">
            <template x-for="image in previewImages()" :key="image.key + ':' + image.url">
                <img x-show="!previewImageFailed" :src="image.url" x-on:error="markPreviewImageFailed(image)" alt="" width="80" height="80" class="h-full w-full object-cover">
            </template>
            <svg x-show="!previewListing()?.image || previewImageFailed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-7 w-7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
        </div>
        <div class="min-w-0 flex-1">
            <h2 id="planner-map-preview-heading" x-ref="previewHeading" tabindex="-1" class="break-words font-headline text-base font-bold leading-snug focus-visible:outline-ocean-600">
                <button type="button" x-show="previewListing()?.type === 'hotel'" @click="viewHotelRooms(previewListing(), true)" :disabled="busy" class="min-h-6 text-left hover:text-ocean-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600 dark:hover:text-ocean-300" x-text="previewPlace()?.name"></button>
                <span x-show="previewListing()?.type !== 'hotel'" x-text="previewPlace()?.name || 'Choose a place at this pin'"></span>
            </h2>
            <p x-show="previewKey" class="mt-1 text-xs text-slate-600 dark:text-slate-300" x-text="(previewPlace()?.type === 'hotel' ? 'Hotel' : 'Activity') + ' · ' + (destination()?.name || '')"></p>
        </div>
        <button type="button" @click="dismissMapPreview()" aria-label="Close map preview" class="-mr-2 -mt-2 inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-sand-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600 dark:text-slate-300 dark:hover:bg-slate-800">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6"/></svg>
        </button>
    </div>

    <div x-show="previewPlaces.length > 1" class="mt-3 flex flex-col gap-1" aria-label="Places sharing this pin">
        <template x-for="place in previewPlaces" :key="placeKey(place)">
            <button type="button" @click="selectMapPlace(place)" :disabled="busy" :aria-pressed="previewKey === placeKey(place)" :class="previewKey === placeKey(place) ? 'bg-ocean-50 text-ocean-800 dark:bg-ocean-900 dark:text-ocean-100' : 'hover:bg-sand-100 dark:hover:bg-slate-800'" class="min-h-11 rounded-lg px-3 py-2 text-left text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600 disabled:opacity-50" x-text="place.name"></button>
        </template>
    </div>

    <div x-show="previewKey" class="mt-3 space-y-3 text-sm">
        <p x-show="previewListing()?.image_is_fallback && !previewImageFailed" class="text-xs text-slate-600 dark:text-slate-300">Illustrative image; no uploaded photo available.</p>
        <p x-show="previewImageFailed" class="text-xs text-slate-600 dark:text-slate-300">Photo unavailable.</p>
        <p x-show="previewListing()?.description" class="line-clamp-3 leading-relaxed text-slate-600 dark:text-slate-300" x-text="descriptionText(previewListing())"></p>
        <div x-show="previewListing()?.type === 'hotel'" class="space-y-1">
            <p class="font-semibold" x-text="(previewListing()?.rooms?.length || 0) + ' listed room type(s)'"></p>
            <p x-show="hotelFromPrice(previewListing()) !== null" class="text-ocean-800 dark:text-ocean-200" x-text="'From ' + peso(hotelFromPrice(previewListing())) + ' / night, before guest adjustments'"></p>
            <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">Add this hotel as a planning base. Choose a room to reserve accommodation; availability is checked when you save a room.</p>
        </div>
        <div x-show="previewListing()?.type === 'activity'" class="space-y-1">
            <p x-show="previewListing()?.duration" class="text-slate-600 dark:text-slate-300" x-text="'Duration: ' + (previewListing()?.duration || '')"></p>
            <p x-show="previewListing()?.price" class="font-semibold" x-text="previewListing()?.price"></p>
            <p x-show="plan && !selected(previewListing())" class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">Adds 1 traveler. Let the planner choose a day, or edit the choice afterward.</p>
        </div>
        <p x-show="!previewListing()" class="text-slate-600 dark:text-slate-300">This place is no longer listed. Its saved itinerary information is still available.</p>
        <p x-show="previewError" x-text="previewError" role="alert" class="text-rose-700 dark:text-rose-300"></p>

        <div x-show="previewListing()" class="flex flex-wrap gap-2">
            <button type="button" @click="addMapChoice()" :disabled="busy || !plan" class="min-h-11 flex-1 rounded-lg bg-ocean-700 px-3 py-2 font-bold text-white hover:bg-ocean-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ocean-600 disabled:opacity-50" x-text="busy ? 'Saving…' : (selected(previewListing()) ? (previewListing()?.type === 'hotel' ? 'Edit stay' : 'Edit choice') : 'Add to My Trip')"></button>
            <button type="button" x-show="previewListing()?.type === 'hotel'" @click="viewHotelRooms(previewListing(), true)" :disabled="busy" class="min-h-11 rounded-lg border border-sand-300 px-3 py-2 font-bold text-ocean-700 hover:bg-ocean-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600 disabled:opacity-50 dark:border-slate-600 dark:text-ocean-300 dark:hover:bg-slate-800">View rooms</button>
            <button type="button" x-show="previewListing()?.type === 'activity'" @click="$store.preview.openActivityById(previewListing().id, 'planner')" :disabled="busy || $store.preview.loadingActivity" class="min-h-11 rounded-lg border border-sand-300 px-3 py-2 font-bold text-ocean-700 hover:bg-ocean-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600 disabled:opacity-50 dark:border-slate-600 dark:text-ocean-300 dark:hover:bg-slate-800">Full details</button>
        </div>
        <div x-show="!plan && previewListing()" class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">
            <p>Save your trip dates to add this place.</p>
            <button type="button" @click="revealTripForm()" class="min-h-11 py-2 font-bold text-ocean-700 underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600 dark:text-ocean-300">Set trip dates</button>
        </div>
        <button type="button" x-show="previewError && previewListing()?.type === 'activity'" @click="edit(previewListing())" :disabled="busy" class="min-h-11 py-2 font-bold text-ocean-700 underline underline-offset-4 dark:text-ocean-300">Adjust travelers or date</button>
    </div>
    <p x-text="previewStatus" role="status" class="sr-only"></p>
</section>
