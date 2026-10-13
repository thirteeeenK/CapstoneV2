<x-frontend.layout title="Trip Planner — SunnyTrips">
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <style>.planner-map-icon{display:grid;place-items:center}.planner-map-number{box-sizing:border-box;display:grid;place-items:center;width:32px;height:32px;border-radius:50%;background:#006b91;color:white;border:2px solid white;font:700 14px 'DM Sans',sans-serif;box-shadow:0 2px 8px #0003}</style>
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
                        <button x-ref="deleteTrigger" type="button" x-show="plan" x-cloak @click="deleting = true; $nextTick(() => $refs.deleteCancel.focus())" :disabled="busy" class="min-h-11 px-3 text-sm font-bold text-rose-700 dark:text-rose-300">Delete saved trip</button>
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
                        </div>
                        <button type="submit" :disabled="busy || !destinationId" class="min-h-11 rounded-lg bg-ocean-700 px-5 py-3 font-bold text-white disabled:opacity-50">Save trip &amp; choose places</button>
                    </form>
                    <div x-show="plan" x-cloak class="mt-5 border-l-4 border-ocean-600 pl-4">
                        <h2 class="font-headline text-xl font-bold" x-text="title"></h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-text="startDate + ' → ' + endDate + ' · getting around on foot'"></p>
                        <p class="mt-2 text-xs text-slate-500">Your planner saves each choice.</p>
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
                                    <p x-show="listing.hotel_name" class="text-xs text-ocean-700 dark:text-ocean-300 font-semibold" x-text="listing.hotel_name"></p>
                                    <h3 class="font-headline font-bold mt-1" x-text="listing.name"></h3>
                                    <p class="mt-1 text-sm text-slate-500 line-clamp-2" x-text="listing.description"></p>
                                    <p x-show="listing.price != null" class="mt-2 text-sm font-semibold" x-text="listing.type === 'room' ? '₱' + Number(listing.price).toLocaleString() + ' / night, before guest adjustments' : listing.price"></p>
                                    <p x-show="listing.lat == null || listing.lng == null" class="mt-2 text-xs text-amber-700 dark:text-amber-300">Entrance or meeting point needs verification before scheduling.</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button type="button" x-show="listing.type === 'activity' || listing.type === 'room'" @click="listing.type === 'room' ? $store.preview.openRoomById(listing.id, 'planner') : $store.preview.openActivityById(listing.id, 'planner')" :disabled="$store.preview.loading || $store.preview.loadingActivity" class="min-h-11 rounded-lg border border-sand-300 dark:border-slate-600 px-4 py-2 text-sm font-bold text-slate-700 dark:text-slate-200">Preview</button>
                                        <button type="button" @click="listing.type === 'hotel' ? chooseHotel(listing) : edit(listing)" :disabled="busy" class="min-h-11 rounded-lg border border-ocean-700 px-4 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300" x-text="listing.type === 'hotel' ? 'Select hotel & view rooms' : (selected(listing) ? 'Edit choice' : 'Add to my trip')"></button>
                                    </div>
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
                                <div class="flex-1 min-w-0"><h3 class="font-bold" x-text="(item.item_type === 'hotel' ? 'Hotel Name: ' : (item.item_type === 'room' ? 'Room: ' : 'Activity: ')) + selectionName(item)"></h3>
                                </div>
                                <button type="button" @click="editSelection(item)" :disabled="busy" class="min-h-11 px-2 text-sm text-ocean-700 dark:text-ocean-300 font-semibold" :aria-label="'Edit ' + selectionName(item)">Edit</button>
                                <button type="button" @click="remove(item)" :disabled="busy" class="min-h-11 px-2 text-sm text-rose-700 dark:text-rose-300" :aria-label="'Remove ' + selectionName(item)">Remove</button>
                            </div>
                        </template>
                    </div>
                    <div x-show="tab === 'itinerary'" x-cloak>
                        <template x-if="itinerary"><div>
                            <h2 class="font-headline text-xl font-bold">Your day-by-day Suggested Itinerary</h2>
                            <p class="mt-1 text-sm text-slate-500">This is a suggested plan — feel free to follow it as it is, or adjust the order and timing to suit your own pace.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a :href="documentUrl()" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Download PDF</a>
                                <a :href="documentUrl(true)" target="_blank" rel="noopener" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Print view</a>
                                <button type="button" @click="email()" :disabled="busy" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Email itinerary</button>
                                <button type="button" @click="transfer()" :disabled="busy" class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" x-text="itinerary.transferred_at ? 'Added to Cart' : 'Add to Cart'"></button>
                                <a href="{{ route('cart.index') }}" class="min-h-11 px-3 py-2.5 text-sm font-bold text-ocean-700 dark:text-ocean-300">Open Trip Basket →</a>
                            </div>
                            <template x-for="(day, index) in itinerary.snapshot.days" :key="day.date">
                                <section class="mt-7 border-t border-sand-200 dark:border-slate-700 pt-5">
                                    <button type="button" @click="selectDay(index)" class="w-full text-left min-h-11"><h3 class="font-headline text-lg font-bold" x-text="'Day ' + day.day + ' · ' + day.label"></h3><p class="text-xs text-ocean-700 dark:text-ocean-300 mt-1" x-text="Number(day.distance_km).toFixed(2) + ' km · ' + day.travel_minutes + ' min ' + mode + ', including return'"></p></button>
                                    <p x-show="day.hotel" class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="'Hotel: ' + (day.hotel?.name || '')"></p>
                                    <template x-for="room in day.hotel?.rooms || []" :key="room.plan_item_id"><p class="mt-1 text-sm text-slate-600 dark:text-slate-300" x-text="'Room: ' + room.quantity + ' × ' + room.name + ' · ' + room.pax + ' guests · ' + room.check_in + ' to ' + room.check_out"></p></template>
                                    <template x-for="item in dayPlan(day)" :key="item.num">
                                        <article class="mt-4 flex gap-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ocean-700 text-white font-bold text-sm" x-text="item.num"></span>
                                            <div class="min-w-0 flex-1 border-b border-sand-200 dark:border-slate-700 pb-4">
                                                <template x-if="item.event">
                                                    <p class="min-h-11 flex items-center font-headline font-bold" x-text="item.label"></p>
                                                </template>
                                                <template x-if="!item.event">
                                                    <div>
                                                        <button type="button" @click="selectDay(index); focusStop(item.stop)" class="text-left min-h-11"><p class="text-xs font-semibold text-ocean-700 dark:text-ocean-300" x-text="item.stop.start + '–' + item.stop.end"></p><h4 class="font-headline font-bold mt-1" x-text="item.stop.name"></h4></button>
                                                        <p class="text-xs text-slate-500 mt-2" x-text="Number(item.stop.travel.distance_km).toFixed(2) + ' km · ' + item.stop.travel.duration_minutes + ' min from ' + item.stop.travel.from"></p>
                                                        <p class="mt-2 text-sm" :class="item.stop.weather.provisional ? 'text-amber-700 dark:text-amber-300' : 'text-ocean-700 dark:text-ocean-300'" x-text="item.stop.weather.description"></p>
                                                        <details class="mt-3"><summary class="cursor-pointer text-sm font-bold text-ocean-700 dark:text-ocean-300 min-h-11 py-2">Why this time and order?</summary><p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400" x-text="item.stop.reason"></p></details>
                                                    </div>
                                                </template>
                                            </div>
                                        </article>
                                    </template>
                                    <p x-show="day.stops.length === 0" class="mt-3 text-sm text-slate-500">No visits are scheduled for this day. Keep it open for spontaneous activities, your own plans, or simply to rest.</p>
                                </section>
                            </template>
                            <div x-show="itinerary.snapshot.unscheduled.length" class="mt-7 border-t border-sand-200 dark:border-slate-700 pt-5">
                                <h3 class="font-headline text-lg font-bold">Other picks to arrange yourself</h3>
                                <p class="mt-1 text-sm text-slate-500">We couldn't fit these onto a specific day automatically, so they aren't plotted on the map. You can still add them to your trip and sort out the timing yourself — your scheduled days are unaffected.</p>
                                <template x-for="item in itinerary.snapshot.unscheduled" :key="item.plan_item_id">
                                    <div class="mt-4 rounded-lg bg-sand-100 dark:bg-slate-800 p-3">
                                        <p class="font-semibold text-sm" x-text="item.name"></p>
                                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-text="item.reason"></p>
                                    </div>
                                </template>
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
                    <div x-show="editing?.type !== 'activity'">
                        <label for="choice-pax" class="block text-sm mb-1">Guests <span x-show="editing?.type === 'room'">per room</span></label>
                        <input id="choice-pax" type="number" x-model.number="optionPax" min="1" :max="editing?.max_pax || 20" required class="w-full rounded-lg border-sand-300 dark:bg-slate-800">
                        <p x-show="editing?.type === 'room' && editing?.max_pax" class="text-xs text-slate-500 mt-1" x-text="'This room holds up to ' + editing.max_pax + ' guests (' + editing.base_occupancy + ' included in the base rate).'"></p>
                    </div>
                    <div x-show="editing?.type === 'activity'">
                        <label class="block text-sm mb-1">Travelers joining this activity</label>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="optionPax = Math.max(1, Number(optionPax) - 1)" :disabled="Number(optionPax) <= 1" aria-label="Fewer travelers" class="min-h-11 min-w-11 rounded-lg border border-sand-300 dark:border-slate-600 text-xl font-bold disabled:opacity-40">−</button>
                            <span class="w-10 text-center text-lg font-bold" x-text="optionPax" aria-live="polite"></span>
                            <button type="button" @click="optionPax = Math.min(editing?.max_pax || 20, Number(optionPax) + 1)" :disabled="Number(optionPax) >= (editing?.max_pax || 20)" aria-label="More travelers" class="min-h-11 min-w-11 rounded-lg border border-sand-300 dark:border-slate-600 text-xl font-bold disabled:opacity-40">+</button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Not everyone has to join — set how many travelers will go (up to <span x-text="editing?.max_pax || 20"></span>).</p>
                    </div>
                    <div x-show="editing?.type === 'room'"><label for="choice-qty" class="block text-sm mb-1">Number of rooms</label><input id="choice-qty" type="number" x-model.number="optionQuantity" min="1" max="20" class="w-full rounded-lg border-sand-300 dark:bg-slate-800"></div>
                    <div x-show="editing?.type === 'room'" class="rounded-lg bg-sand-100 dark:bg-slate-800 p-3 text-sm">
                        <p class="flex justify-between gap-3"><span>Nightly rate</span><span class="font-bold" x-text="peso(roomNightlyRate(editing, optionPax))"></span></p>
                        <p x-show="Number(optionPax) > (editing?.base_occupancy || 2) && (editing?.extra_person_fee || 0) > 0" class="mt-1 text-xs text-slate-500" x-text="'Includes ' + peso(editing.extra_person_fee) + ' × ' + (Number(optionPax) - editing.base_occupancy) + ' extra guest(s) over the base of ' + editing.base_occupancy + '.'"></p>
                        <p class="mt-2 pt-2 border-t border-sand-200 dark:border-slate-700 flex justify-between gap-3"><span x-text="nightsBetween(optionIn, optionOut) + ' night(s) × ' + (optionQuantity || 1) + ' room(s)'"></span><span class="font-bold" x-text="peso(roomTotal(editing, optionPax, optionQuantity, optionIn, optionOut))"></span></p>
                        <p class="mt-1 text-[11px] text-slate-400">Estimate; final guest manifest at checkout may adjust it.</p>
                    </div>
                    <div x-show="editing?.type === 'activity'" class="rounded-lg bg-sand-100 dark:bg-slate-800 p-3 text-sm">
                        <p class="flex justify-between gap-3"><span x-text="editing?.per_person ? (peso(activityUnitRate(editing, optionPax)) + ' × ' + optionPax + ' traveler(s)') : 'Flat group rate'"></span><span class="font-bold" x-text="peso(activityTotal(editing, optionPax))"></span></p>
                        <p class="mt-1 text-[11px] text-slate-400" x-text="editing?.per_person ? 'Priced per traveler.' : 'One flat charge regardless of the number of travelers.'"></p>
                    </div>
                    <p x-show="error" x-text="error" role="alert" class="text-sm text-rose-700 dark:text-rose-300"></p>
                    <button type="submit" :disabled="busy" class="w-full min-h-11 rounded-lg bg-ocean-700 p-3 font-bold text-white disabled:opacity-50">Save to my trip</button>
                </form>
            </section>
        </div>
        <div x-show="deleting" x-cloak x-trap.inert.noscroll="deleting" @keydown.escape.window="deleting = false; $nextTick(() => $refs.deleteTrigger.focus())" class="fixed inset-0 z-[90] bg-ink-900/50 flex items-center justify-center p-4">
            <section role="dialog" aria-modal="true" aria-labelledby="delete-trip-heading" class="w-full max-w-md rounded-xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                <h2 id="delete-trip-heading" class="font-headline text-lg font-bold">Delete this saved trip?</h2>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" x-text="title"></p>
                <p class="mt-2 text-sm text-slate-500">This permanently removes the saved plan and its linked Trip Basket items. Itineraries already saved in bookings remain available.</p>
                <div class="mt-5 flex gap-3"><button x-ref="deleteCancel" type="button" @click="deleting = false; $nextTick(() => $refs.deleteTrigger.focus())" :disabled="busy" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2 font-bold">Cancel</button><button type="button" @click="deletePlan()" :disabled="busy" class="min-h-11 rounded-lg bg-rose-700 px-4 py-2 font-bold text-white">Delete trip permanently</button></div>
                <p x-show="error" x-text="error" role="alert" class="mt-3 text-sm text-rose-700 dark:text-rose-300"></p>
            </section>
        </div>
    </div>
</x-frontend.layout>
