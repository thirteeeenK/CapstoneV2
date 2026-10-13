<x-frontend.layout title="Trip Planner — SunnyTrips">
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <style>.planner-map-number{display:grid;place-items:center;width:2rem;height:2rem;border-radius:50%;background:#006b91;color:white;border:2px solid white;font:700 14px 'DM Sans',sans-serif;box-shadow:0 2px 8px #0003}</style>
    @endpush
    <div x-data="tripPlanner({ destinations: @js($destinations), plans: @js($plans), destination: @js($initialDestination), emailPreferences: @js($emailPreferences), base: @js(route('trip-planner.index')), csrf: @js(csrf_token()) })"
         class="mx-auto max-w-screen-2xl bg-sand-50 text-ink-900 dark:bg-slate-900 dark:text-slate-100 font-body">
        <div class="grid lg:grid-cols-2">
            <section class="min-w-0 border-r border-sand-200 dark:border-slate-700">
                <div class="p-5 sm:p-8 bg-white dark:bg-slate-950 border-b border-sand-200 dark:border-slate-700">
                    <p class="text-xs font-bold uppercase tracking-widest text-ocean-700 dark:text-ocean-300">SunnyTrips · Your island, your pace</p>
                    <h1 class="mt-2 font-headline text-2xl sm:text-3xl font-bold tracking-tight">Plan your island days</h1>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Choose your stay and experiences. We’ll arrange them around real routes and the forecast.</p>
                    <a href="#planner-map" class="lg:hidden mt-3 inline-flex min-h-11 items-center text-sm font-bold text-ocean-700 dark:text-ocean-300">View destination map ↓</a>
                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <label for="saved-plan" class="text-sm font-semibold">Saved trips</label>
                        <select id="saved-plan" x-model="savedPlanId" @change="if ($event.target.value) openPlan($event.target.value)" :disabled="busy" class="min-w-0 max-w-full rounded-lg border-sand-300 dark:bg-slate-800 text-sm">
                            <option value="">Open a saved trip</option>
                            <template x-for="saved in savedPlans" :key="saved.id"><option :value="saved.id" x-text="saved.title + ' · ' + saved.start_date.slice(0, 10) + ' → ' + saved.end_date.slice(0, 10)"></option></template>
                        </select>
                        <button type="button" @click="newTrip()" :disabled="busy" class="min-h-11 px-3 text-sm font-bold text-ocean-700 dark:text-ocean-300">New trip</button>
                        <button type="button" x-show="plan" x-cloak @click="deleting = true; $nextTick(() => $refs.deleteCancel.focus())" :disabled="busy" class="min-h-11 px-3 text-sm font-bold text-rose-700 dark:text-rose-300">Delete saved trip</button>
                    </div>
                    <form x-show="!plan" @submit.prevent="create()" class="mt-5 space-y-4">
                        <div><label for="planner-destination" class="block text-sm font-semibold mb-1">Destination</label>
                            <select id="planner-destination" x-model="destinationId" @change="chooseDestination($event.target.value)" required class="w-full rounded-lg border-sand-300 dark:bg-slate-800">
                                <option value="">Choose an island, or click its map marker</option>
                                <template x-for="destination in destinations" :key="destination.id"><option :value="destination.id" x-text="destination.name"></option></template>
                            </select>
                        </div>
                        <div><label for="trip-title" class="block text-sm font-semibold mb-1">Trip name</label><input id="trip-title" x-model="title" maxlength="150" required class="w-full rounded-lg border-sand-300 dark:bg-slate-800"></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label for="trip-start" class="block text-sm font-semibold mb-1">Arrival date</label><input id="trip-start" type="date" x-model="startDate" min="{{ now('Asia/Manila')->toDateString() }}" required class="w-full min-w-0 rounded-lg border-sand-300 dark:bg-slate-800"></div>
                            <div><label for="trip-end" class="block text-sm font-semibold mb-1">Departure date</label><input id="trip-end" type="date" x-model="endDate" :min="startDate" required class="w-full min-w-0 rounded-lg border-sand-300 dark:bg-slate-800"></div>
                            <div><label for="trip-pax" class="block text-sm font-semibold mb-1">Travelers</label><input id="trip-pax" type="number" x-model="pax" min="1" max="20" required class="w-full rounded-lg border-sand-300 dark:bg-slate-800"></div>
                            <div><label for="trip-mode" class="block text-sm font-semibold mb-1">Get around by</label><select id="trip-mode" x-model="mode" class="w-full rounded-lg border-sand-300 dark:bg-slate-800"><option value="walking">Walking</option><option value="driving">Driving</option></select></div>
                        </div>
                        <button type="submit" :disabled="busy || !destinationId" class="min-h-11 rounded-lg bg-ocean-700 px-5 py-3 font-bold text-white disabled:opacity-50">Save trip &amp; choose places</button>
                    </form>
                    <div x-show="plan" x-cloak class="mt-5 border-l-4 border-ocean-600 pl-4">
                        <h2 class="font-headline text-xl font-bold" x-text="title"></h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-text="startDate + ' → ' + endDate + ' · ' + pax + ' travelers · ' + mode"></p>
                        <p class="mt-2 text-xs text-slate-500">Your planner saves each choice. Reservations begin after Add to Cart and checkout.</p>
                    </div>
                </div>
                <div class="px-5 sm:px-8 pt-5">
                    <p x-show="error" x-cloak x-text="error" role="alert" class="mb-4 rounded-lg bg-rose-50 text-rose-800 p-3 text-sm"></p>
                    <p x-show="notice" x-cloak x-text="notice" role="status" class="mb-4 rounded-lg bg-ocean-50 text-ocean-900 p-3 text-sm"></p>
                    <p x-show="busy" x-cloak role="status" class="mb-3 text-sm text-ocean-700 dark:text-ocean-300">Saving or checking routes and weather…</p>
                    <nav aria-label="Trip planner sections" class="flex gap-1 overflow-x-auto border-b border-sand-200 dark:border-slate-700">
                        <template x-for="section in ['hotels', 'rooms', 'activities', 'selections', 'itinerary']" :key="section">
                            <button type="button" @click="tab = section; renderMap()" :disabled="busy" :aria-current="tab === section ? 'page' : null" :class="tab === section ? 'border-ocean-700 text-ocean-700 dark:text-ocean-300' : 'border-transparent text-slate-500'" class="min-h-11 shrink-0 border-b-2 px-3 py-3 text-sm font-bold capitalize disabled:opacity-50" x-text="section === 'selections' ? 'My choices (' + selections.length + ')' : section"></button>
                        </template>
                    </nav>
                </div>
                <div class="p-5 sm:p-8">
                    <div x-show="!['itinerary', 'selections'].includes(tab)">
                        <div x-show="tab === 'rooms'" class="mb-5">
                            <h2 class="font-headline font-bold" x-text="activeHotel() ? 'Rooms at ' + activeHotel().name : 'Select a hotel first'"></h2>
                            <p class="mt-1 text-sm text-slate-500">Rooms inherit your hotel stay dates. Guest counts and room quantities can be adjusted below.</p>
                            <button type="button" @click="tab = 'hotels'; search = ''; renderMap()" class="min-h-11 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300">Choose a hotel</button>
                        </div>
                        <label for="listing-search" class="sr-only">Search places</label><input id="listing-search" x-model="search" @input.debounce.250ms="renderMap()" placeholder="Search places or hotels" type="search" class="w-full rounded-lg border-sand-300 dark:bg-slate-800 mb-5">
                        <p x-show="!destinationId" class="text-sm text-slate-500">Choose a destination to explore its listings.</p>
                        <p x-show="destinationId && listings().length === 0" class="text-sm text-slate-500">No matching listings are available in this category.</p>
                        <template x-for="listing in listings()" :key="listing.type + ':' + listing.id">
                            <article class="flex gap-4 border-b border-sand-200 dark:border-slate-700 py-5 first:pt-0">
                                <img x-show="listing.image" :src="listing.image" alt="" class="hidden sm:block w-24 h-24 shrink-0 rounded-lg object-cover" loading="lazy">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs text-ocean-700 dark:text-ocean-300 font-semibold" x-text="listing.hotel_name || listing.type"></p>
                                    <h3 class="font-headline font-bold mt-1" x-text="listing.name"></h3>
                                    <p class="mt-1 text-sm text-slate-500 line-clamp-2" x-text="listing.description"></p>
                                    <p x-show="listing.price != null" class="mt-2 text-sm font-semibold" x-text="listing.type === 'room' ? '₱' + Number(listing.price).toLocaleString() + ' / night, before guest adjustments' : listing.price"></p>
                                    <p x-show="listing.lat == null || listing.lng == null" class="mt-2 text-xs text-amber-700 dark:text-amber-300">Entrance or meeting point needs verification before scheduling.</p>
                                    <button type="button" @click="listing.type === 'hotel' ? chooseHotel(listing) : edit(listing)" :disabled="busy" class="mt-3 min-h-11 rounded-lg border border-ocean-700 px-4 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300" x-text="listing.type === 'hotel' ? 'Select hotel & view rooms' : (selected(listing) ? 'Edit choice' : 'Add to my trip')"></button>
                                </div>
                            </article>
                        </template>
                    </div>
                    <div x-show="tab === 'selections'" x-cloak>
                        <h2 class="font-headline text-xl font-bold">Your trip choices</h2>
                        <p class="mt-2 text-sm text-slate-500">Rooms and activities are bookable; hotel bases are planning choices. If you change hotels during the trip, edit each hotel's stay dates here. Its rooms update automatically.</p>
                        <p x-show="!selections.length" class="mt-5 text-sm text-slate-500">Add a stay and the places you want to visit.</p>
                        <template x-for="item in selections" :key="item.id">
                            <div class="py-4 border-b border-sand-200 dark:border-slate-700 flex items-start gap-3">
                                <div class="flex-1 min-w-0"><h3 class="font-bold" x-text="selectionName(item)"></h3>
                                    <p class="mt-1 text-sm text-slate-500" x-text="item.item_type + ' · ' + item.options.pax + ' guests' + (item.options.check_in ? ' · ' + item.options.check_in + ' → ' + item.options.check_out : (item.options.date ? ' · ' + item.options.date : ' · flexible date'))"></p>
                                </div>
                                <button type="button" @click="editSelection(item)" :disabled="busy" class="min-h-11 px-2 text-sm text-ocean-700 dark:text-ocean-300 font-semibold" :aria-label="'Edit ' + selectionName(item)">Edit</button>
                                <button type="button" @click="remove(item)" :disabled="busy" class="min-h-11 px-2 text-sm text-rose-700 dark:text-rose-300" :aria-label="'Remove ' + selectionName(item)">Remove</button>
                            </div>
                        </template>
                    </div>
                    <div x-show="tab === 'itinerary'" x-cloak>
                        <template x-if="itinerary"><div>
                            <h2 class="font-headline text-xl font-bold">Your day-by-day itinerary</h2>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a :href="documentUrl()" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Download PDF</a>
                                <a :href="documentUrl(true)" target="_blank" rel="noopener" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Print view</a>
                                <button type="button" @click="email()" :disabled="busy" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Email itinerary</button>
                                <button type="button" @click="transfer()" :disabled="busy || itinerary.snapshot.unscheduled.length > 0" class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" x-text="itinerary.transferred_at ? 'Added to Cart' : 'Add to Cart'"></button>
                                <a href="{{ route('cart.index') }}" class="min-h-11 px-3 py-2.5 text-sm font-bold text-ocean-700 dark:text-ocean-300">Open Trip Basket →</a>
                            </div>
                            <template x-for="(day, index) in itinerary.snapshot.days" :key="day.date">
                                <section class="mt-7 border-t border-sand-200 dark:border-slate-700 pt-5">
                                    <button type="button" @click="selectDay(index)" class="w-full text-left min-h-11"><h3 class="font-headline text-lg font-bold" x-text="'Day ' + day.day + ' · ' + day.label"></h3><p class="text-xs text-ocean-700 dark:text-ocean-300 mt-1" x-text="Number(day.distance_km).toFixed(2) + ' km · ' + day.travel_minutes + ' min ' + mode + ', including return'"></p></button>
                                    <p x-show="day.hotel" class="mt-2 text-sm text-slate-500" x-text="'Base: ' + (day.hotel?.name || '')"></p>
                                    <template x-for="room in day.hotel?.rooms || []" :key="room.plan_item_id"><p class="mt-1 text-sm text-slate-600 dark:text-slate-300" x-text="room.quantity + ' × ' + room.name + ' · ' + room.pax + ' guests · ' + room.check_in + ' to ' + room.check_out"></p></template>
                                    <template x-for="event in day.events" :key="event.name"><p class="mt-3 rounded-lg bg-sand-100 dark:bg-slate-800 p-3 text-sm" x-text="event.time + ' · ' + event.name + (event.assumed ? ' · time assumed; confirm with hotel' : '')"></p></template>
                                    <template x-for="(stop, stopIndex) in day.stops" :key="stop.plan_item_id">
                                        <article class="mt-4 flex gap-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ocean-700 text-white font-bold text-sm" x-text="stopIndex + 1"></span>
                                            <div class="min-w-0 flex-1 border-b border-sand-200 dark:border-slate-700 pb-4">
                                                <button type="button" @click="selectDay(index); focusStop(stop)" class="text-left min-h-11"><p class="text-xs font-semibold text-ocean-700 dark:text-ocean-300" x-text="stop.start + '–' + stop.end"></p><h4 class="font-headline font-bold mt-1" x-text="stop.name"></h4></button>
                                                <p class="text-xs text-slate-500 mt-2" x-text="Number(stop.travel.distance_km).toFixed(2) + ' km · ' + stop.travel.duration_minutes + ' min from ' + stop.travel.from"></p>
                                                <p class="mt-2 text-sm" :class="stop.weather.provisional ? 'text-amber-700 dark:text-amber-300' : 'text-ocean-700 dark:text-ocean-300'" x-text="stop.weather.description"></p>
                                                <details class="mt-3"><summary class="cursor-pointer text-sm font-bold text-ocean-700 dark:text-ocean-300 min-h-11 py-2">Why this time and order?</summary><p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400" x-text="stop.reason"></p></details>
                                            </div>
                                        </article>
                                    </template>
                                    <p x-show="day.stops.length === 0" class="mt-3 text-sm text-slate-500">No scheduled visits. Use this time for arrival, departure or rest.</p>
                                </section>
                            </template>
                            <div x-show="itinerary.snapshot.unscheduled.length" class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
                                <h3 class="font-bold">Choices that need attention</h3><p class="mt-1 text-sm">Edit or remove these choices, then generate again before Add to Cart.</p>
                                <template x-for="item in itinerary.snapshot.unscheduled" :key="item.plan_item_id"><p class="mt-3 text-sm" x-text="item.name + ': ' + item.reason"></p></template>
                            </div>
                            <details class="mt-6 border-t border-sand-200 dark:border-slate-700 pt-4" open><summary class="min-h-11 py-2 font-headline font-bold cursor-pointer">Strategic report</summary><template x-for="(paragraph, key) in itinerary.snapshot.report" :key="key"><p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400" x-text="paragraph"></p></template></details>
                        </div></template>
                        <p x-show="!itinerary" class="text-sm text-slate-500">Add your choices, then generate a schedule to see the day-by-day plan and its reasoning.</p>
                    </div>
                    <div x-show="plan" x-cloak class="mt-6 border-t border-sand-200 dark:border-slate-700 pt-5 flex flex-wrap items-center gap-3">
                        <button type="button" @click="generate()" :disabled="busy || !selections.length" class="min-h-11 rounded-lg bg-ocean-700 px-5 py-3 font-bold text-white disabled:opacity-50" x-text="itinerary ? 'Generate again' : 'Generate itinerary'"></button>
                        <p class="text-xs text-slate-500">Uses your choices, mapped routes and available weather data.</p>
                    </div>
                    <details class="mt-6 border-t border-sand-200 dark:border-slate-700 pt-4"><summary class="min-h-11 py-2 font-semibold cursor-pointer">Email preferences</summary><form @submit.prevent="saveEmailPreferences()" class="mt-3 space-y-3"><label class="flex gap-3 items-start text-sm"><input type="checkbox" x-model="reminders" class="mt-1">Send trip reminders, departure information and review invitations</label><label class="flex gap-3 items-start text-sm"><input type="checkbox" x-model="marketing" class="mt-1">Send planning follow-ups and destination suggestions (optional)</label><button type="submit" :disabled="busy" class="min-h-11 rounded-lg border border-ocean-700 px-4 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300">Save email preferences</button></form></details>
                </div>
            </section>
            <aside id="planner-map" aria-label="Trip map" class="scroll-mt-16 lg:sticky lg:top-0 lg:self-start">
                <p x-show="mapError" x-cloak x-text="mapError" role="status" class="p-3 bg-amber-50 text-amber-900 text-sm"></p>
                <div x-ref="map" class="h-80 sm:h-96 lg:h-[calc(100dvh-3rem)] z-0" aria-label="Destination and itinerary route map"></div>
                <p class="p-3 text-xs text-slate-500 bg-white dark:bg-slate-950">Click a destination to explore. Select a day to show numbered stops and actual mapped routes. The same choices are available in the list.</p>
            </aside>
        </div>
        <div x-show="editing" x-cloak x-trap.inert.noscroll="editing" @keydown.escape.window="editing = null" class="fixed inset-0 z-[90] bg-ink-900/50 flex items-center justify-center p-4">
            <section role="dialog" aria-modal="true" aria-labelledby="planner-choice-heading" class="w-full max-w-md max-h-[85dvh] overflow-y-auto rounded-xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                <div class="flex gap-3 justify-between"><h2 id="planner-choice-heading" x-ref="choiceHeading" tabindex="-1" class="font-headline text-lg font-bold" x-text="editing?.name"></h2><button type="button" @click="editing = null" aria-label="Close choice editor" class="min-h-11 min-w-11 text-xl">×</button></div>
                <form @submit.prevent="saveChoice()" class="mt-4 space-y-4">
                    <p x-show="editing?.type === 'hotel'" class="text-sm text-slate-500">This sets your route base. Choose a room to reserve accommodation.</p>
                    <p x-show="editing?.type === 'room'" class="text-sm text-slate-500" x-text="'Hotel stay: ' + optionIn + ' to ' + optionOut + '. Edit the hotel in My choices to change these dates.'"></p>
                    <label x-show="editing?.type === 'hotel'" class="flex items-center gap-3 text-sm"><input type="checkbox" x-model="customStayDates">Use different dates from the trip</label>
                    <div x-show="editing?.type === 'hotel' && customStayDates" class="grid grid-cols-2 gap-3"><div><label for="choice-in" class="block text-sm mb-1">Check-in</label><input id="choice-in" type="date" x-model="optionIn" :min="startDate" :max="endDate" class="w-full min-w-0 rounded-lg border-sand-300 dark:bg-slate-800"></div><div><label for="choice-out" class="block text-sm mb-1">Check-out</label><input id="choice-out" type="date" x-model="optionOut" :min="optionIn" :max="endDate" class="w-full min-w-0 rounded-lg border-sand-300 dark:bg-slate-800"></div></div>
                    <div x-show="editing?.type === 'activity'"><label for="choice-date" class="block text-sm mb-1">Visit date (optional)</label><input id="choice-date" type="date" x-model="optionDate" :min="startDate" :max="endDate" class="w-full rounded-lg border-sand-300 dark:bg-slate-800"><p class="text-xs text-slate-500 mt-1">Leave blank to let the planner choose the day.</p></div>
                    <div><label for="choice-pax" class="block text-sm mb-1">Guests <span x-show="editing?.type === 'room'">per room</span></label><input id="choice-pax" type="number" x-model="optionPax" min="1" :max="editing?.max_pax || 20" required class="w-full rounded-lg border-sand-300 dark:bg-slate-800"></div>
                    <div x-show="editing?.type === 'room'"><label for="choice-qty" class="block text-sm mb-1">Number of rooms</label><input id="choice-qty" type="number" x-model="optionQuantity" min="1" max="20" class="w-full rounded-lg border-sand-300 dark:bg-slate-800"></div>
                    <p x-show="error" x-text="error" role="alert" class="text-sm text-rose-700 dark:text-rose-300"></p>
                    <button type="submit" :disabled="busy" class="w-full min-h-11 rounded-lg bg-ocean-700 p-3 font-bold text-white disabled:opacity-50">Save to my trip</button>
                </form>
            </section>
        </div>
        <div x-show="deleting" x-cloak x-trap.inert.noscroll="deleting" @keydown.escape.window="deleting = false" class="fixed inset-0 z-[90] bg-ink-900/50 flex items-center justify-center p-4">
            <section role="dialog" aria-modal="true" aria-labelledby="delete-trip-heading" class="w-full max-w-md rounded-xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                <h2 id="delete-trip-heading" class="font-headline text-lg font-bold">Delete this saved trip?</h2>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" x-text="title"></p>
                <p class="mt-2 text-sm text-slate-500">This permanently removes the saved plan and its linked Trip Basket items. Itineraries already saved in bookings remain available.</p>
                <div class="mt-5 flex gap-3"><button x-ref="deleteCancel" type="button" @click="deleting = false" :disabled="busy" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2 font-bold">Cancel</button><button type="button" @click="deletePlan()" :disabled="busy" class="min-h-11 rounded-lg bg-rose-700 px-4 py-2 font-bold text-white">Delete trip permanently</button></div>
                <p x-show="error" x-text="error" role="alert" class="mt-3 text-sm text-rose-700 dark:text-rose-300"></p>
            </section>
        </div>
    </div>
</x-frontend.layout>
