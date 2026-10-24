<x-frontend.layout title="Trip Planner — SunnyTrips">
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <style>.planner-map-icon{display:grid;place-items:center}.planner-map-number{box-sizing:border-box;display:grid;place-items:center;width:32px;height:32px;border-radius:50%;background:#006b91;color:white;border:2px solid white;font:700 14px 'DM Sans',sans-serif;box-shadow:0 2px 8px #0003}.planner-map-pending{background:#fdfaf4;color:#b45309;border:2px dashed #b45309;box-shadow:none}</style>
    @endpush
    <div x-data="tripPlanner({ destinations: @js($destinations), plans: @js($plans), initialPlan: @js($initialPlan), destination: @js($initialDestination), emailPreferences: @js($emailPreferences), datePolicy: @js($datePolicy), base: @js(route('trip-planner.index')), csrf: @js(csrf_token()) })"
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
                            <optgroup label="Itinerary (From Chatbot)"><template x-for="saved in savedPlans.filter(item => item.source === 'chatbot')" :key="saved.id"><option :value="saved.id" x-text="savedPlanLabel(saved)"></option></template></optgroup>
                            <optgroup label="My other trips"><template x-for="saved in savedPlans.filter(item => item.source !== 'chatbot')" :key="saved.id"><option :value="saved.id" x-text="savedPlanLabel(saved)"></option></template></optgroup>
                        </select>
                        <button type="button" x-show="hasMorePlans" @click="loadMorePlans()" :disabled="busy" class="min-h-11 px-3 text-sm font-bold text-ocean-700 dark:text-ocean-300">Load more trips</button>
                        <button type="button" @click="newTrip()" :disabled="busy" class="min-h-11 px-3 text-sm font-bold text-ocean-700 dark:text-ocean-300">New trip</button>
                        <button x-ref="deleteTrigger" type="button" x-show="plan" x-cloak @click="deleting = true; $nextTick(() => $refs.deleteCancel.focus())" :disabled="busy" class="min-h-11 px-3 text-sm font-bold text-rose-700 dark:text-rose-300">Delete saved trip</button>
                    </div>
                    <form x-ref="tripForm" x-show="!plan" @submit.prevent="create()" class="mt-5 space-y-4 scroll-mt-8">
                        <div><label for="planner-destination" class="block text-sm font-semibold mb-1">Destination</label>
                            <select id="planner-destination" x-model="destinationId" @change="chooseDestination($event.target.value)" required class="w-full rounded-lg border-sand-300 dark:bg-slate-800">
                                <option value="">Choose an island, or click its map marker</option>
                                <template x-for="destination in destinations" :key="destination.id"><option :value="destination.id" x-text="destination.name"></option></template>
                            </select>
                        </div>
                        <div><label for="trip-title" class="block text-sm font-semibold mb-1">Trip name</label><input id="trip-title" x-model="title" maxlength="150" required class="w-full rounded-lg border-sand-300 dark:bg-slate-800"></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label for="trip-start" class="block text-sm font-semibold mb-1">Arrival date</label><input id="trip-start" type="date" x-model="startDate" min="{{ $datePolicy['minimumArrival'] }}" :min="minimumArrival" @focus="refreshMinimumArrival()" aria-describedby="trip-arrival-hint" required class="w-full min-w-0 rounded-lg border-sand-300 dark:bg-slate-800"></div>
                            <div><label for="trip-end" class="block text-sm font-semibold mb-1">Departure date</label><input id="trip-end" type="date" x-model="endDate" :min="startDate" required class="w-full min-w-0 rounded-lg border-sand-300 dark:bg-slate-800"></div>
                        </div>
                        <p id="trip-arrival-hint" class="text-xs leading-relaxed text-slate-600 dark:text-slate-300" x-text="'Choose an arrival on or after ' + minimumArrival + ' (two calendar days ahead in Philippine time).'"></p>
                        <div>
                            <label for="trip-mode" class="block text-sm font-semibold mb-1">Getting around</label>
                            <select id="trip-mode" x-model="mode" class="w-full rounded-lg border-sand-300 dark:bg-slate-800">
                                <option value="walking">On foot — short hops around town</option>
                                <option value="driving">By vehicle — tricycle, van or car</option>
                            </select>
                            <p class="text-xs text-slate-500 mt-1">Island stops are often several kilometres apart. Pick a vehicle when your choices are spread out, otherwise the planner runs out of daylight and leaves them unscheduled.</p>
                        </div>
                        <button type="submit" :disabled="busy || !destinationId" class="min-h-11 rounded-lg bg-ocean-700 px-5 py-3 font-bold text-white disabled:opacity-50">Save trip &amp; choose places</button>
                    </form>
                    <div x-show="plan" x-cloak class="mt-5 border-l-4 border-ocean-600 pl-4">
                        <h2 class="font-headline text-xl font-bold" x-text="title"></h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-text="startDate + ' → ' + endDate + ' · ' + (mode === 'driving' ? 'getting around by vehicle' : 'getting around on foot')"></p>
                        <p class="mt-2 text-xs text-slate-500">Your planner saves each choice.</p>
                    </div>
                    @include('trip-planner._trip-details')
                </div>
                <div class="px-5 sm:px-8 pt-5">
                    <section x-show="destinationId" x-cloak aria-label="Destination weather forecast" :aria-busy="weatherLoading" class="mb-5">
                        <p x-show="weatherLoading" role="status" class="text-sm text-slate-500 dark:text-slate-400 py-3">Loading destination forecast…</p>
                        <p x-show="weatherError" x-text="weatherError" role="status" class="text-sm text-slate-500 dark:text-slate-400 py-3"></p>
                        <div x-html="weatherHtml"></div>
                    </section>
                    <p x-show="error" x-cloak x-text="error" role="alert" class="mb-4 rounded-lg bg-rose-50 text-rose-800 p-3 text-sm"></p>
                    <p x-show="notice" x-cloak x-text="notice" role="status" class="mb-4 rounded-lg bg-ocean-50 text-ocean-900 p-3 text-sm"></p>
                    <p id="trip-booking-date-hint" x-show="plan && arrivalTooSoon(startDate)" x-cloak x-text="arrivalHint()" role="status" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"></p>
                    <p x-show="busy" x-cloak role="status" class="mb-3 text-sm text-ocean-700 dark:text-ocean-300">Saving or checking routes and weather…</p>
                    <nav aria-label="Trip planner sections" class="flex gap-1 overflow-x-auto border-b border-sand-200 dark:border-slate-700">
                        <template x-for="section in ['hotels', 'rooms', 'activities', 'selections', 'itinerary']" :key="section">
                            <button type="button" @click="setSection(section)" :disabled="busy" :aria-current="tab === section ? 'page' : null" :class="tab === section ? 'border-ocean-700 text-ocean-700 dark:text-ocean-300' : 'border-transparent text-slate-500'" class="min-h-11 shrink-0 border-b-2 px-3 py-3 text-sm font-bold capitalize disabled:opacity-50" x-text="section === 'selections' ? 'My choices (' + selections.length + ')' : section"></button>
                        </template>
                    </nav>
                </div>
                <div class="p-5 sm:p-8">
                    <div x-show="!['itinerary', 'selections'].includes(tab)">
                        <div x-show="tab === 'rooms'" class="mb-5">
                            <h2 x-ref="roomsHeading" tabindex="-1" class="font-headline font-bold scroll-mt-8 focus-visible:outline-ocean-600" x-text="activeHotel() ? 'Rooms at ' + activeHotel().name : 'Select a hotel first'"></h2>
                            <p x-show="activeHotel()?.description" class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300 line-clamp-3" x-text="descriptionText(activeHotel())"></p>
                            <p class="mt-1 text-sm text-slate-500">Rooms inherit your hotel stay dates. Guest counts and room quantities can be adjusted below.</p>
                            <p x-show="activeHotel() && !activeHotel().rooms.length" class="mt-2 text-sm text-slate-600 dark:text-slate-300">No room types are currently listed for this hotel.</p>
                            <button type="button" @click="setSection('hotels')" :disabled="busy" class="min-h-11 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300 disabled:opacity-50">Choose a hotel</button>
                        </div>
                        <label for="listing-search" class="sr-only">Search places</label><input id="listing-search" x-model="search" @input.debounce.250ms="searchListings()" :disabled="busy" placeholder="Search places or hotels" type="search" class="w-full rounded-lg border-sand-300 dark:bg-slate-800 mb-5">
                        <p x-show="!destinationId" class="text-sm text-slate-500">Choose a destination to explore its listings.</p>
                        <p x-show="destinationId && listings().length === 0 && !(tab === 'rooms' && activeHotel() && !activeHotel().rooms.length)" class="text-sm text-slate-500" x-text="tab === 'rooms' && activeHotel() ? 'No rooms match your search.' : 'No matching listings are available in this category.'"></p>
                        <template x-for="listing in listings()" :key="listing.type + ':' + listing.id">
                            <article class="flex gap-4 border-b border-sand-200 dark:border-slate-700 py-5 first:pt-0">
                                <img x-show="listing.image" :src="listing.image" alt="" class="hidden sm:block w-24 h-24 shrink-0 rounded-lg object-cover" loading="lazy">
                                <div class="min-w-0 flex-1">
                                    <p x-show="listing.hotel_name" class="text-xs text-ocean-700 dark:text-ocean-300 font-semibold" x-text="listing.hotel_name"></p>
                                    <h3 class="font-headline font-bold mt-1" x-text="listing.name"></h3>
                                    <p class="mt-1 text-sm text-slate-500 line-clamp-2" x-text="listing.description"></p>
                                    <p x-show="listing.price != null" class="mt-2 text-sm font-semibold" x-text="listing.type === 'room' ? '₱' + Number(listing.price).toLocaleString() + ' / night, before guest adjustments' : listing.price"></p>
                                    <p x-show="listing.lat == null || listing.lng == null" class="mt-2 text-xs text-amber-700 dark:text-amber-300">Entrance or meeting point needs verification before scheduling.</p>
                                    <p x-show="listing.location_precision === 'approximate'" class="mt-2 text-xs text-amber-700 dark:text-amber-300">Approximate location — pinned at the destination centre until the exact meeting point is added, so travel times will be estimates.</p>
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
                                    <p x-show="item.item_type !== 'hotel'" class="mt-1 text-sm text-slate-500" x-text="travelersLabel(item)"></p>
                                    <button type="button" x-show="canApplyPartySize(item)" @click="applyPartySize(item)" :disabled="busy" class="min-h-11 text-sm font-semibold text-ocean-700 dark:text-ocean-300">Apply party size</button>
                                </div>
                                <button type="button" @click="editSelection(item)" :disabled="busy" class="min-h-11 px-2 text-sm text-ocean-700 dark:text-ocean-300 font-semibold" :aria-label="'Edit ' + selectionName(item)">Edit</button>
                                <button type="button" @click="remove(item)" :disabled="busy" class="min-h-11 px-2 text-sm text-rose-700 dark:text-rose-300" :aria-label="'Remove ' + selectionName(item)">Remove</button>
                            </div>
                        </template>
                        <section x-show="selections.length" aria-label="Trip estimate" class="mt-6 rounded-lg bg-sand-100 dark:bg-slate-800 p-4 text-sm">
                            <h3 class="font-headline font-bold">Trip estimate</h3>
                            <p class="mt-2 flex justify-between gap-3"><span>Rooms</span><span x-text="peso(tripEstimate().rooms)"></span></p>
                            <p class="mt-1 flex justify-between gap-3"><span>Activities</span><span x-text="peso(tripEstimate().activities)"></span></p>
                            <p class="mt-2 pt-2 border-t border-sand-200 dark:border-slate-700 flex justify-between gap-3 font-bold"><span>Estimated total</span><span x-text="peso(tripEstimate().total)"></span></p>
                            <p x-show="budgetComparison()" class="mt-2" :class="budgetComparison()?.remaining < 0 ? 'text-amber-800 dark:text-amber-300' : 'text-ocean-800 dark:text-ocean-300'"
                               x-text="budgetComparison() ? 'Your chat budget' + (budgetComparison().scope === 'trip' ? '' : ' for ' + budgetComparison().scope) + ' is ' + peso(budgetComparison().budget) + ' — ' + (budgetComparison().remaining < 0 ? peso(-budgetComparison().remaining) + ' over.' : peso(budgetComparison().remaining) + ' left.') : ''"></p>
                            <p x-show="tripEstimate().sleeps !== null && tripEstimate().sleeps < tripEstimate().party" class="mt-2 text-amber-800 dark:text-amber-300"
                               x-text="'On some nights your rooms sleep ' + tripEstimate().sleeps + ', fewer than your party of ' + tripEstimate().party + '. Add a room or more guests per room.'"></p>
                            <p class="mt-2 text-xs text-slate-500">Estimate from listed rates. Transport, meals and fees are not included; the guest list at checkout confirms the final price.</p>
                        </section>
                    </div>
                    <div x-show="tab === 'itinerary'" x-cloak>
                        <template x-if="draftItinerary"><div class="space-y-4">
                            @include('components.frontend.chat-itinerary-summary', ['suggestionExpression' => 'draftItinerary.snapshot'])
                            <h2 class="font-headline text-xl font-bold">Itinerary (From Chatbot)</h2>
                            <p class="text-sm font-bold" x-text="plan?.start_date ? 'Draft — schedule not checked' : 'Draft — dates not selected'"></p>
                            <p class="text-sm text-slate-600 dark:text-slate-300" x-text="draftItinerary.snapshot.notice"></p>
                            <p class="text-sm text-slate-600 dark:text-slate-300" x-text="draftItinerary.snapshot.weather_notice"></p>
                            <p x-show="plan?.context?.pax_assumed" class="text-sm">Traveler count was not provided; this draft uses 2 travelers. Confirm it before building a schedule.</p>
                            <template x-for="day in draftItinerary.snapshot.days" :key="day.day"><section class="border-t border-sand-200 pt-3 dark:border-slate-700">
                                <h3 class="font-headline font-bold" x-text="'Day ' + day.day + (day.date ? ' · ' + day.date : '')"></h3>
                                <template x-for="choice in draftItinerary.snapshot.choices.filter(item => item.day === day.day)" :key="choice.type + ':' + choice.id"><p class="mt-2 text-sm" x-text="choice.name"></p></template>
                                <p x-show="!draftItinerary.snapshot.choices.some(item => item.day === day.day)" class="mt-2 text-sm text-slate-500">Open day — arrange activities at your own pace.</p>
                            </section></template>
                            <h3 class="font-headline font-bold">Saved choices</h3>
                            <template x-for="choice in draftItinerary.snapshot.choices" :key="choice.type + ':' + choice.id"><div class="flex flex-wrap items-center justify-between gap-2 border-b border-sand-200 py-2 dark:border-slate-700">
                                <div><p class="text-sm" x-text="choice.name"></p><p class="text-xs text-slate-500" x-text="choice.available ? (choice.day ? 'Suggested for Day ' + choice.day : 'Timing not assigned') : 'Unavailable — choose an alternative before scheduling'"></p></div>
                                <p x-show="choice.weather_dependent === true" class="text-xs text-slate-500">Weather-dependent</p>
                                <button type="button" @click="updateDraft(choice.type + ':' + choice.id)" :disabled="busy" class="min-h-11 px-3 text-sm font-bold text-coral-700 dark:text-coral-300" :aria-label="'Remove ' + choice.name">Remove</button>
                            </div></template>
                            <p class="text-xs text-slate-500">Saving does not reserve rooms or activities. Estimates may change before booking.</p>
                            <div class="flex flex-wrap gap-3"><a :href="documentUrl()" class="min-h-11 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300">Download draft PDF</a><a :href="documentUrl(true)" target="_blank" rel="noopener" class="min-h-11 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300">Print draft</a><button type="button" @click="email()" :disabled="busy" class="min-h-11 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300">Email draft</button></div>
                        </div></template>
                        <template x-if="itinerary"><div>
                            <h2 class="font-headline text-xl font-bold">Your day-by-day Suggested Itinerary</h2>
                            <p class="mt-1 text-sm text-slate-500">This is a suggested plan. Weather, travel times and activity availability may change. Recheck conditions and confirm details with providers before departure.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a :href="documentUrl()" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Download PDF</a>
                                <a :href="documentUrl(true)" target="_blank" rel="noopener" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Print view</a>
                                <button type="button" @click="email()" :disabled="busy" class="min-h-11 rounded-lg border border-sand-300 px-4 py-2.5 text-sm font-bold">Email itinerary</button>
                            <button type="button" @click="transfer()" :disabled="busy || arrivalTooSoon(startDate)" aria-describedby="trip-booking-date-hint" class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">Add to Trip Basket</button>
                                <a href="{{ route('cart.index') }}" class="min-h-11 px-3 py-2.5 text-sm font-bold text-ocean-700 dark:text-ocean-300">Open Trip Basket →</a>
                            </div>
                            <div aria-label="What Add to Trip Basket includes" class="mt-3 rounded-lg bg-sand-100 dark:bg-slate-800 p-3 text-sm">
                                <p class="font-semibold">Add to Trip Basket includes</p>
                                <p class="mt-1 text-slate-600 dark:text-slate-300" x-text="basketPreview().added.join(', ') || 'Nothing bookable yet — add a room or an activity that fits on a day.'"></p>
                                <p x-show="basketPreview().skipped.length" class="mt-2 text-amber-800 dark:text-amber-300" x-text="'Not added because they are not on a day: ' + basketPreview().skipped.join(', ')"></p>
                                <p class="mt-2 text-xs text-slate-500">Hotel bases are planning choices and are not booked.</p>
                            </div>
                            <template x-for="(day, index) in itinerary.snapshot.days" :key="day.date">
                                <section class="mt-7 border-t border-sand-200 dark:border-slate-700 pt-5">
                                    <button type="button" @click="selectDay(index)" class="w-full text-left min-h-11"><h3 class="font-headline text-lg font-bold" x-text="'Day ' + day.day + ' · ' + day.label"></h3><p class="text-xs text-ocean-700 dark:text-ocean-300 mt-1" x-text="Number(day.distance_km).toFixed(2) + ' km · ' + day.travel_minutes + ' min ' + (mode === 'driving' ? 'by vehicle' : 'on foot') + ', including return'"></p></button>
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
                                                        <p class="text-xs text-slate-500 mt-2" x-text="item.stop.travel.distance_km != null ? Number(item.stop.travel.distance_km).toFixed(2) + ' km · ' + item.stop.travel.duration_minutes + ' min from ' + item.stop.travel.from : 'Travel time from ' + item.stop.travel.from + ' is not available'"></p>
                                                        <p x-show="item.stop.travel.geometry_kind === 'straight_line'" class="text-xs text-amber-700 dark:text-amber-300 mt-1">Shown as a straight line on the map — no mapped road connects these two stops, so arrange a boat or transfer.</p>
                                                        <p x-show="item.stop.travel_estimated" class="text-xs text-amber-700 dark:text-amber-300 mt-1">Estimated travel time — the exact meeting point isn't set yet, so it's measured from the destination centre. Confirm the location with the operator.</p>
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
                                <p class="mt-1 text-sm text-slate-500">We couldn't fit these onto a specific day. They still appear on the map as hollow pins, but they won't be added to your Trip Basket. Each one says what stopped it and what to change — your scheduled days are unaffected.</p>
                                <template x-for="item in itinerary.snapshot.unscheduled" :key="item.plan_item_id">
                                    <div class="mt-4 rounded-lg bg-sand-100 dark:bg-slate-800 p-3">
                                        <p class="font-semibold text-sm" x-text="item.name"></p>
                                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-text="item.reason"></p>
                                    </div>
                                </template>
                            </div>
                            <details class="mt-6 border-t border-sand-200 dark:border-slate-700 pt-4" open><summary class="min-h-11 py-2 font-headline font-bold cursor-pointer">Strategic report</summary><template x-for="(paragraph, key) in itinerary.snapshot.report" :key="key"><p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400" x-text="paragraph"></p></template></details>
                        </div></template>
                        <p x-show="!itinerary && !draftItinerary" class="text-sm text-slate-500">Add your choices, then generate a schedule to see the day-by-day plan and its reasoning.</p>
                    </div>
                    <div x-show="plan" x-cloak class="mt-6 border-t border-sand-200 dark:border-slate-700 pt-5 flex flex-wrap items-center gap-3">
                        <button type="button" @click="generate()" :disabled="busy || !selections.length || !plan?.start_date" class="min-h-11 rounded-lg bg-ocean-700 px-5 py-3 font-bold text-white disabled:opacity-50" x-text="draftItinerary ? 'Check dates and build schedule' : (itinerary ? 'Generate again' : 'Generate itinerary')"></button>
                        <p class="text-xs text-slate-500">Uses your choices, mapped routes and available weather data.</p>
                    </div>
                    <details class="mt-6 border-t border-sand-200 dark:border-slate-700 pt-4"><summary class="min-h-11 py-2 font-semibold cursor-pointer">Email preferences</summary><form @submit.prevent="saveEmailPreferences()" class="mt-3 space-y-3"><label class="flex gap-3 items-start text-sm"><input type="checkbox" x-model="reminders" class="mt-1">Send trip reminders, departure information and review invitations</label><label class="flex gap-3 items-start text-sm"><input type="checkbox" x-model="marketing" class="mt-1">Send planning follow-ups and destination suggestions (optional)</label><button type="submit" :disabled="busy" class="min-h-11 rounded-lg border border-ocean-700 px-4 py-2 text-sm font-bold text-ocean-700 dark:text-ocean-300">Save email preferences</button></form></details>
                </div>
            </section>
            <aside id="planner-map" aria-label="Trip map" class="scroll-mt-16 lg:sticky lg:top-0 lg:self-start">
                <p x-show="mapError" x-cloak x-text="mapError" role="status" class="p-3 bg-amber-50 text-amber-900 text-sm"></p>
                <div class="relative isolate">
                    <div x-ref="map" tabindex="-1" class="h-80 sm:h-96 lg:h-[calc(100dvh-3rem)] z-0" aria-label="Destination and itinerary route map"></div>
                    @include('trip-planner._map-preview')
                </div>
                <div class="p-3 bg-white dark:bg-slate-950 border-t border-sand-200 dark:border-slate-700">
                    <p x-show="routeNotice" x-cloak x-text="routeNotice" role="status" class="mb-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 p-2.5 text-xs text-amber-900 dark:text-amber-200"></p>
                    <h2 class="text-xs font-bold uppercase tracking-widest text-ocean-700 dark:text-ocean-300">Map key</h2>
                    <ul class="mt-2 space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <li class="flex items-center gap-2"><svg viewBox="0 0 40 8" class="h-2 w-10 shrink-0 text-ocean-600 dark:text-ocean-300" aria-hidden="true"><line x1="1" y1="4" x2="39" y2="4" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg><span>A mapped road or trail route. Each day has its own colour.</span></li>
                        <li class="flex items-center gap-2"><svg viewBox="0 0 40 8" class="h-2 w-10 shrink-0 text-ocean-600 dark:text-ocean-300" aria-hidden="true"><line x1="1" y1="4" x2="39" y2="4" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="3 9"/></svg><span>A straight-line estimate: no mapped road connects those stops, so a boat or arranged transfer is needed.</span></li>
                        <li class="flex items-center gap-2"><svg viewBox="0 0 40 8" class="h-2 w-10 shrink-0 text-ocean-600 dark:text-ocean-300" aria-hidden="true"><line x1="1" y1="4" x2="39" y2="4" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="1 6"/></svg><span>A connector bridging the gap between the mapped road and the exact spot.</span></li>
                        <li class="flex items-center gap-2"><svg viewBox="0 0 40 8" class="h-2 w-10 shrink-0 text-ocean-600 dark:text-ocean-300" aria-hidden="true"><line x1="1" y1="4" x2="39" y2="4" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="6 6"/></svg><span>The return trip to your hotel.</span></li>
                        <li class="flex items-center gap-2"><span aria-hidden="true" class="grid h-5 w-5 shrink-0 place-items-center rounded-full border-2 border-dashed border-amber-700 bg-sand-50 text-[10px] font-bold text-amber-700">?</span><span>A pick we could not fit onto a day. Its grey line just points back to your hotel.</span></li>
                    </ul>
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">All scheduled days appear on the map. D1·2 means Day 1, item 2 in your itinerary. Events at the same location share a pin. Select a day, or a route line, to highlight it.</p>
                </div>
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
                        <p x-show="editing?.type === 'room' && editing?.max_pax" class="text-xs text-slate-500 mt-1" x-text="'This room holds up to ' + editing?.max_pax + ' guests (' + editing?.base_occupancy + ' included in the base rate).'"></p>
                    </div>
                    <div x-show="editing?.type === 'activity'">
                        <label class="block text-sm mb-1">Travelers joining this activity</label>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="adjustActivityPax(-1)" :disabled="Number(optionPax) <= 1" aria-label="Fewer travelers" class="min-h-11 min-w-11 rounded-lg border border-sand-300 dark:border-slate-600 text-xl font-bold disabled:opacity-40">−</button>
                            <span class="w-10 text-center text-lg font-bold" x-text="optionPax" aria-live="polite"></span>
                            <button type="button" @click="adjustActivityPax(1)" :disabled="Number(optionPax) >= activityLimit(editing)" aria-label="More travelers" class="min-h-11 min-w-11 rounded-lg border border-sand-300 dark:border-slate-600 text-xl font-bold disabled:opacity-40">+</button>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" x-text="activityLimit(editing) === 1 ? 'This activity currently allows 1 traveler per choice.' : 'Not everyone has to join — choose up to ' + activityLimit(editing) + ' travelers.'"></p>
                        <p x-show="Number(optionPax) > activityLimit(editing)" role="status" class="mt-2 text-sm text-amber-800 dark:text-amber-300">The saved traveler count exceeds the current limit. Reduce it before saving.</p>
                    </div>
                    <div x-show="editing?.type === 'room'"><label for="choice-qty" class="block text-sm mb-1">Number of rooms</label><input id="choice-qty" type="number" x-model.number="optionQuantity" min="1" max="20" class="w-full rounded-lg border-sand-300 dark:bg-slate-800"></div>
                    <div x-show="editing?.type === 'room'" class="rounded-lg bg-sand-100 dark:bg-slate-800 p-3 text-sm">
                        <p class="flex justify-between gap-3"><span>Nightly rate</span><span class="font-bold" x-text="peso(roomNightlyRate(editing, optionPax))"></span></p>
                        <p x-show="Number(optionPax) > (editing?.base_occupancy || 2) && (editing?.extra_person_fee || 0) > 0" class="mt-1 text-xs text-slate-500" x-text="'Includes ' + peso(editing?.extra_person_fee) + ' × ' + (Number(optionPax) - editing?.base_occupancy) + ' extra guest(s) over the base of ' + editing?.base_occupancy + '.'"></p>
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
