// One colour per itinerary day so overlapping days stay readable. Brand palette
// only, and no purple, per the SunnyTrips colour rules.
const dayColors = ['#0a78a8', '#e85e37', '#0f766e', '#085e85', '#b45309', '#15803d', '#be123c'];

const hotelSymbol = '<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18V6m18 12v-7a2 2 0 0 0-2-2h-7v7M3 16h18M3 9h6v7"/><path d="M5 11h2"/></svg>';

export default (config) => {
    // Leaflet relies on object identity when attaching/removing event listeners.
    // Keep its mutable instances outside Alpine's deep reactive proxies.
    let map = null;
    let layers = null;
    let resizeObserver = null;
    let pageShowHandler = null;
    let previewTrigger = null;
    let previewSession = 0;
    let dateTimer = null;
    let dateRefreshHandler = null;
    const clockStartedAt = Date.now();
    const serverClock = Date.parse(config.datePolicy?.serverNow || '');
    const previewMarkers = new Map();

    return {
    destinations: config.destinations, savedPlans: config.plans, destinationId: Number(config.destination || 0),
    catalog: { hotels: [], activities: [] }, plan: null, selections: [], itinerary: null, draftItinerary: null,
    plansPage: 1, hasMorePlans: config.plans.length >= 20,
    weatherHtml: '', weatherLoading: false, weatherError: '', weatherSequence: 0,
    tab: 'hotels', busy: false, error: '', notice: '', mapError: '', routeNotice: '', search: '', activeDay: 0, editing: null,
    title: '', startDate: '', endDate: '', pax: 2, mode: 'walking', optionIn: '', optionOut: '', optionDate: '', optionPax: 2, optionQuantity: 1,
    viewportSignature: '', loadSequence: 0, activeHotelId: 0, savedPlanId: '', deleting: false, customStayDates: false,
    previewPlaces: [], previewKey: '', previewError: '', previewStatus: '', previewImageFailed: false,
    itineraryMapContext: false, catalogLoading: false,
    reminders: config.emailPreferences.reminders, marketing: config.emailPreferences.marketing,
    minimumArrival: config.datePolicy?.minimumArrival || '',
    async init() {
        this.refreshMinimumArrival();
        if (config.datePolicy) {
            dateRefreshHandler = () => this.refreshMinimumArrival();
            dateTimer = window.setInterval(dateRefreshHandler, 30000);
            window.addEventListener('focus', dateRefreshHandler);
            document.addEventListener('visibilitychange', dateRefreshHandler);
        }
        try {
            if (!window.L) {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    script.onload = resolve; script.onerror = reject; document.head.append(script);
                });
            }
            const container = this.$refs.map;
            if (container.sunnyTripsResizeFrame) cancelAnimationFrame(container.sunnyTripsResizeFrame);
            container.sunnyTripsResizeObserver?.disconnect();
            if (container.sunnyTripsPageShowHandler) window.removeEventListener('pageshow', container.sunnyTripsPageShowHandler);
            if (container.sunnyTripsMap) {
                container.sunnyTripsMap.stop();
                container.sunnyTripsMap.remove();
            }
            map = L.map(container, { scrollWheelZoom: false }).setView([11.967, 121.925], 13);
            container.sunnyTripsMap = map;
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 })
                .on('tileerror', () => { this.mapError = 'Map tiles are unavailable. You can still use the destination and listing controls.'; }).addTo(map);
            layers = L.featureGroup().addTo(map);
            map.on?.('click', () => this.dismissMapPreview(false));
            resizeObserver = new ResizeObserver(() => this.scheduleMapResize());
            resizeObserver.observe(container);
            container.sunnyTripsResizeObserver = resizeObserver;
            pageShowHandler = () => {
                this.refreshMinimumArrival();
                const restoredDestinationId = Number(this.$root.querySelector('#planner-destination')?.value || 0);
                if (!this.plan && restoredDestinationId && restoredDestinationId !== Number(this.destinationId)) {
                    this.chooseDestination(restoredDestinationId);
                } else {
                    this.scheduleMapResize();
                }
            };
            container.sunnyTripsPageShowHandler = pageShowHandler;
            window.addEventListener('pageshow', pageShowHandler);
        } catch { this.mapError = 'The map could not load. You can still plan from the listings.'; }
        if (config.initialPlan) await this.openPlan(config.initialPlan);
        else if (this.destinationId) await this.chooseDestination(this.destinationId);
        else this.renderMap();
    },
    async api(path, method = 'GET', data) {
        const response = await fetch(config.base + path, {
            method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf },
            ...(data ? { body: JSON.stringify(data) } : {}),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'The request failed. Please retry.');
        return body;
    },
    destination() { return this.destinations.find(item => Number(item.id) === Number(this.destinationId)); },
    async chooseDestination(id) {
        if (this.busy || this.plan) return;
        this.closeMapPreview(false); this.itineraryMapContext = false; this.activeHotelId = 0; this.search = ''; this.tab = 'hotels';
        this.catalog = { hotels: [], activities: [] };
        this.destinationId = Number(id); this.error = ''; this.title = 'Trip to ' + (this.destination()?.name || 'the islands');
        this.loadWeather(this.destinationId);
        const sequence = ++this.loadSequence;
        this.catalogLoading = Boolean(this.destinationId); this.renderMap();
        if (!this.destinationId) return;
        try {
            const body = await this.api('/catalog/' + id);
            if (sequence !== this.loadSequence) return;
            this.catalog = body.data; this.renderMap();
        } catch (error) { if (sequence === this.loadSequence) { console.error('Trip planner destination failed', error); this.error = error.message; } }
        finally { if (sequence === this.loadSequence) this.catalogLoading = false; }
    },
    async run(action) {
        if (this.busy) return;
        this.busy = true; this.error = ''; this.notice = '';
        try { await action(); } catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    async loadWeather(id) {
        const sequence = ++this.weatherSequence;
        this.weatherHtml = ''; this.weatherError = '';
        this.weatherLoading = Boolean(Number(id));
        if (!Number(id)) return;
        try {
            const response = await fetch(config.base + '/weather/' + Number(id), { headers: { Accept: 'text/html' } });
            if (!response.ok) throw new Error('Forecast unavailable. Please try again later.');
            const html = await response.text();
            if (sequence === this.weatherSequence) this.weatherHtml = html;
        } catch {
            if (sequence === this.weatherSequence) this.weatherError = 'Forecast unavailable. Please try again later.';
        } finally {
            if (sequence === this.weatherSequence) this.weatherLoading = false;
        }
    },
    async create() {
        this.refreshMinimumArrival();
        if (this.minimumArrival && this.startDate && this.startDate < this.minimumArrival) {
            this.error = this.arrivalHint(); return;
        }
        await this.run(async () => {
            this.plan = (await this.api('/plans', 'POST', { destination_id: this.destinationId, title: this.title,
                start_date: this.startDate, end_date: this.endDate, pax: Number(this.pax), mode: this.mode })).data;
            this.savedPlans.unshift(this.plan); this.notice = 'Trip saved. Choose the places you want to include.';
            this.savedPlanId = String(this.plan.id);
        });
    },
    async openPlan(id) {
        await this.run(async () => {
            this.loadSequence++; this.catalogLoading = false; this.closeMapPreview(false); this.itineraryMapContext = false; this.search = ''; this.editing = null;
            const body = await this.api('/plans/' + id);
            const catalog = await this.api('/catalog/' + body.data.destination_id);
            this.plan = body.data; this.selections = body.data.items;
            this.draftItinerary = body.itinerary?.snapshot.kind === 'chatbot_draft' ? body.itinerary : null;
            this.itinerary = this.draftItinerary ? null : body.itinerary;
            this.savedPlanId = String(this.plan.id);
            if (!this.savedPlans.some(saved => saved.id === this.plan.id)) this.savedPlans.unshift(this.plan);
            this.destinationId = Number(this.plan.destination_id); this.title = this.plan.title;
            if (!this.draftItinerary) this.loadWeather(this.destinationId);
            else { this.weatherSequence++; this.weatherHtml = ''; this.weatherLoading = false; this.weatherError = ''; }
            this.startDate = this.plan.start_date?.slice(0, 10) || ''; this.endDate = this.plan.end_date?.slice(0, 10) || '';
            this.pax = this.plan.pax; this.mode = this.plan.mode;
            this.catalog = catalog.data;
            const room = this.selections.find(item => item.item_type === 'room');
            this.activeHotelId = room ? this.allListings().find(item => item.type === 'room' && item.id === room.item_id)?.hotel_id || 0 : this.selections.find(item => item.item_type === 'hotel')?.item_id || 0;
            if (this.itinerary?.revision !== this.plan.revision) this.itinerary = null;
            this.tab = this.itinerary || this.draftItinerary ? 'itinerary' : 'selections'; this.activeDay = 0; this.renderMap();
        });
    },
    newTrip() {
        const reloadCatalog = this.catalogLoading;
        this.loadSequence++; this.catalogLoading = false; this.closeMapPreview(false); this.itineraryMapContext = false; this.search = '';
        this.plan = null; this.selections = []; this.itinerary = null; this.draftItinerary = null; this.tab = 'hotels'; this.error = ''; this.notice = '';
        this.savedPlanId = ''; this.activeHotelId = 0; this.editing = null; this.deleting = false;
        this.title = 'Trip to ' + (this.destination()?.name || 'the islands'); this.renderMap();
        if (reloadCatalog && !this.busy) this.chooseDestination(this.destinationId);
    },
    listings(type = this.tab) {
        const list = type === 'rooms' ? (this.activeHotel()?.rooms || []) : (this.catalog[type] || []);
        return list.filter(item => (item.name + ' ' + (item.hotel_name || '')).toLowerCase().includes(this.search.toLowerCase()));
    },
    allListings() { return [...this.catalog.hotels, ...this.catalog.hotels.flatMap(hotel => hotel.rooms), ...this.catalog.activities]; },
    activeHotel() { return this.catalog.hotels.find(hotel => hotel.id === Number(this.activeHotelId)); },
    viewHotelRooms(listing, focus = false) {
        const hotel = this.catalog.hotels.find(item => item.id === Number(listing?.hotel_id || listing?.id));
        if (!hotel) return;
        this.activeHotelId = hotel.id; this.tab = 'rooms'; this.search = '';
        this.renderMap(true);
        if (focus) this.$nextTick(() => this.$refs.roomsHeading?.focus());
    },
    setSection(section) {
        if (this.busy) return;
        this.closeMapPreview(false); this.itineraryMapContext = false; this.tab = section; this.search = ''; this.renderMap();
    },
    searchListings() {
        this.closeMapPreview(false); this.itineraryMapContext = false; this.renderMap();
    },
    placeKey(place) { return place.type + ':' + place.id; },
    mapPlace(listing) {
        if (listing.type === 'room') {
            return this.catalog.hotels.find(hotel => hotel.id === Number(listing.hotel_id))
                || { type: 'hotel', id: listing.hotel_id, name: listing.hotel_name || listing.name, lat: listing.lat, lng: listing.lng };
        }
        return listing;
    },
    previewListing() {
        return this.allListings().find(listing => this.placeKey(listing) === this.previewKey) || null;
    },
    previewPlace() { return this.previewPlaces.find(place => this.placeKey(place) === this.previewKey) || null; },
    openMapPreview(places, marker = null, event = null) {
        if (this.busy || this.catalogLoading) return;
        const unique = new Map();
        for (const listing of places) {
            const place = this.mapPlace(listing);
            if (place.id && ['hotel', 'activity'].includes(place.type)) {
                unique.set(this.placeKey(place), { type: place.type, id: place.id, name: place.name });
            }
        }
        if (!unique.size) return;
        const element = marker?.getElement?.();
        const keyboard = event?.originalEvent ? /^key/.test(event.originalEvent.type) : Boolean(element && element === document.activeElement);
        previewTrigger = marker;
        this.previewPlaces = Array.from(unique.values()); this.previewKey = ''; this.previewError = ''; this.previewStatus = '';
        this.itineraryMapContext = Boolean(this.itinerary && (this.tab === 'itinerary' || this.itineraryMapContext));
        if (this.previewPlaces.length === 1) this.selectMapPlace(this.previewPlaces[0]);
        if (keyboard) this.$nextTick?.(() => this.$refs.previewHeading?.focus());
    },
    selectMapPlace(place) {
        if (this.busy) return;
        previewSession++;
        this.previewKey = this.placeKey(place); this.previewImageFailed = false; this.previewError = ''; this.previewStatus = 'Previewing ' + place.name + '.';
        const listing = this.previewListing();
        if (listing?.type === 'hotel') this.viewHotelRooms(listing);
    },
    closeMapPreview(restoreFocus = true) {
        previewSession++;
        const key = this.previewKey || (this.previewPlaces[0] ? this.placeKey(this.previewPlaces[0]) : '');
        const element = previewTrigger?.getElement?.();
        this.previewPlaces = []; this.previewKey = ''; this.previewError = ''; this.previewStatus = ''; this.previewImageFailed = false;
        if (restoreFocus) {
            const target = element?.isConnected ? element : previewMarkers.get(key)?.getElement?.();
            (target || this.$refs.map)?.focus?.({ preventScroll: true });
        }
        previewTrigger = null;
    },
    dismissMapPreview(restoreFocus = true) {
        if (!this.previewPlaces.length) return;
        const key = this.previewKey || this.placeKey(this.previewPlaces[0]);
        const hotel = this.previewPlace()?.type === 'hotel';
        this.closeMapPreview(false);
        if (hotel) {
            this.tab = 'hotels'; this.search = ''; this.itineraryMapContext = false; this.renderMap(true);
        }
        if (restoreFocus) (previewMarkers.get(key)?.getElement?.() || this.$refs.map)?.focus?.({ preventScroll: true });
    },
    previewImages() {
        const image = this.previewListing()?.image;
        return image ? [{ url: image, key: this.previewKey }] : [];
    },
    markPreviewImageFailed(image) {
        if (image.key === this.previewKey && image.url === this.previewListing()?.image) this.previewImageFailed = true;
    },
    async addMapChoice() {
        if (this.busy) return;
        const listing = this.previewListing();
        if (!listing || !this.plan) return;
        if (this.selected(listing)) { this.edit(listing); return; }
        const key = this.previewKey;
        const session = previewSession;
        this.previewError = '';
        if (listing.type === 'hotel') {
            await this.chooseHotel(listing, session);
        } else {
            await this.run(async () => {
                const body = await this.api('/plans/' + this.plan.id + '/items', 'POST', {
                    item_type: 'activity', item_id: listing.id, pax: 1, quantity: 1, revision: this.plan.revision,
                });
                this.applyChoice(body); this.notice = listing.name + ' added to your trip. Edit its choice to adjust travelers or set a visit date.';
                this.renderMap(true);
            });
        }
        if (this.previewKey === key && previewSession === session) {
            this.previewError = this.error;
            this.previewStatus = this.error ? '' : listing.name + ' added to your trip.';
        }
    },
    hotelFromPrice(hotel) {
        const prices = (hotel?.rooms || []).map(room => room.price).filter(price => price != null && Number.isFinite(Number(price)));
        return prices.length ? Math.min(...prices.map(Number)) : null;
    },
    descriptionText(listing) { return String(listing?.description || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(); },
    revealTripForm() {
        this.$refs.tripForm?.scrollIntoView?.({ block: 'start', behavior: 'auto' });
        this.$root.querySelector('#planner-destination')?.focus();
    },
    async chooseHotel(listing, session = null) {
        if (!this.plan) { this.error = 'Save your trip dates first, then select a hotel.'; return; }
        await this.run(async () => {
            if (!this.selected(listing)) {
                const body = await this.api('/plans/' + this.plan.id + '/items', 'POST', { item_type: 'hotel', item_id: listing.id, revision: this.plan.revision });
                this.applyChoice(body);
            }
            if (session === null || session === previewSession) {
                this.viewHotelRooms(listing);
                this.notice = 'Choose a room at ' + listing.name + '. Edit the hotel in My choices to adjust its stay dates.';
            } else {
                this.renderMap(true); this.notice = listing.name + ' saved to your trip.';
            }
        });
    },
    applyChoice(body) {
        this.selections = body.items;
        if (this.plan.revision !== body.revision) { this.itinerary = null; this.itineraryMapContext = false; }
        this.plan.revision = body.revision;
        if (body.draft_itinerary) this.draftItinerary = body.draft_itinerary;
    },
    async deletePlan() {
        await this.run(async () => {
            const id = this.plan.id;
            const body = await this.api('/plans/' + id, 'DELETE');
            this.savedPlans = this.savedPlans.filter(plan => plan.id !== id);
            this.newTrip(); this.notice = body.message;
            window.dispatchEvent(new CustomEvent('cart-updated'));
        });
    },
    selected(listing) { return Boolean(listing && this.selections.some(item => item.item_type === listing.type && item.item_id === listing.id)); },
    selectionName(item) { return this.allListings().find(listing => listing.type === item.item_type && listing.id === item.item_id)?.name || 'Unavailable listing'; },
    edit(listing) {
        if (!this.plan) { this.error = 'Save your trip dates first, then add choices.'; return; }
        this.previewError = '';
        const existing = this.selections.find(item => item.item_type === listing.type && item.item_id === listing.id);
        const hotelStay = this.selections.find(item => item.item_type === 'hotel' && item.item_id === listing.hotel_id);
        this.editing = listing; this.optionIn = hotelStay?.options.check_in || existing?.options.check_in || this.startDate;
        this.optionOut = hotelStay?.options.check_out || existing?.options.check_out || this.endDate; this.optionDate = existing?.options.date || '';
        this.customStayDates = this.optionIn !== this.startDate || this.optionOut !== this.endDate;
        this.optionPax = existing?.options.pax || (listing.type === 'activity' ? 1 : this.pax); this.optionQuantity = existing?.options.quantity || 1;
        this.$nextTick(() => this.$refs.choiceHeading?.focus());
    },
    editSelection(item) {
        const listing = this.allListings().find(listing => listing.type === item.item_type && listing.id === item.item_id);
        if (listing) this.edit(listing); else this.error = 'This listing is no longer available. Remove it and select another.';
    },
    async saveChoice() {
        if (this.editing?.type === 'activity' && (Number(this.optionPax) < 1 || Number(this.optionPax) > this.activityLimit(this.editing))) {
            this.error = 'Choose between 1 and ' + this.activityLimit(this.editing) + ' traveler(s) for this activity.'; return;
        }
        await this.run(async () => {
            const type = this.editing.type;
            if (type === 'hotel' && !this.customStayDates) { this.optionIn = this.startDate; this.optionOut = this.endDate; }
            const body = await this.api('/plans/' + this.plan.id + '/items', 'POST', { item_type: type, item_id: this.editing.id,
                pax: Number(this.optionPax), quantity: Number(this.optionQuantity), revision: this.plan.revision,
                ...(type === 'hotel' || type === 'room' ? { check_in: this.optionIn, check_out: this.optionOut } : { date: this.optionDate || null }),
            });
            this.applyChoice(body);
            if (type === 'hotel' || type === 'room') this.activeHotelId = this.editing.hotel_id;
            this.editing = null;
            this.notice = 'Choice saved to your planner.'; this.renderMap();
        });
    },
    async remove(item) {
        await this.run(async () => {
            const body = await this.api('/plans/' + this.plan.id + '/items/' + item.id, 'DELETE', { revision: this.plan.revision });
            this.selections = this.selections.filter(choice => choice.id !== item.id);
            this.plan.revision = body.revision; this.itinerary = null; this.itineraryMapContext = false; this.renderMap();
            if (body.draft_itinerary) this.draftItinerary = body.draft_itinerary;
        });
    },
    async generate() {
        await this.run(async () => {
            this.closeMapPreview(false); this.itineraryMapContext = false;
            this.itinerary = (await this.api('/plans/' + this.plan.id + '/generate', 'POST')).data;
            this.draftItinerary = null; this.plan.status = 'dated';
            this.activeDay = 0; this.tab = 'itinerary'; this.renderMap(); this.notice = 'Itinerary saved. Review the schedule and its explanation.';
        });
    },
    async transfer() {
        this.refreshMinimumArrival();
        if (this.arrivalTooSoon(this.startDate)) { this.error = this.arrivalHint(); return; }
        await this.run(async () => {
            const body = await this.api('/plans/' + this.plan.id + '/itineraries/' + this.itinerary.id + '/cart', 'POST');
            this.notice = body.data.status === 'already_transferred' ? 'This itinerary still has items in your Trip Basket. Remove its remaining items before adding it again.' : 'Added to your Trip Basket. Continue there to review prices and request booking.';
            this.itinerary.transferred_at = new Date().toISOString(); window.dispatchEvent(new CustomEvent('cart-updated'));
            window.dispatchEvent(new CustomEvent('show-cart-modal', { detail: {
                alreadyInCart: body.data.status === 'already_transferred',
                message: body.data.status === 'already_transferred' ? this.notice : this.title + ' · ' + body.data.count + ' item(s) added to your Trip Basket.',
            } }));
        });
    },
    documentUrl(print = false) { const current = this.itinerary || this.draftItinerary; return current ? config.base + '/plans/' + this.plan.id + '/itineraries/' + current.id + '/document' + (print ? '?print=1' : '') : '#'; },
    async email() {
        await this.run(async () => { this.notice = (await this.api('/plans/' + this.plan.id + '/itineraries/' + (this.itinerary || this.draftItinerary).id + '/email', 'POST')).message; });
    },

    savedPlanLabel(saved) {
        return saved.title + ' · ' + (saved.start_date ? saved.start_date.slice(0, 10) + ' → ' + saved.end_date.slice(0, 10) : 'Dates to be decided') + (saved.status === 'draft' ? ' · Draft' : '');
    },
    async loadMorePlans() {
        await this.run(async () => {
            const body = await this.api('/plans?page=' + (this.plansPage + 1));
            const existing = new Set(this.savedPlans.map(plan => plan.id));
            this.savedPlans.push(...body.data.filter(plan => !existing.has(plan.id)));
            this.plansPage++; this.hasMorePlans = Boolean(body.next_page_url);
        });
    },
    async updateDraft(removeChoice = null) {
        const changes = {};
        if (removeChoice) changes.remove_choice = removeChoice;
        else {
            // Send only edited fields so a rename never re-validates unchanged dates or rebuilds choices.
            if (this.title !== this.plan.title) changes.title = this.title;
            if (Number(this.pax) !== Number(this.plan.pax)) changes.pax = Number(this.pax);
            if (this.mode !== this.plan.mode) changes.mode = this.mode;
            const datesChanged = this.startDate !== (this.plan.start_date?.slice(0, 10) || '') || this.endDate !== (this.plan.end_date?.slice(0, 10) || '');
            if ((this.startDate || this.endDate) && datesChanged) Object.assign(changes, { start_date: this.startDate, end_date: this.endDate });
        }
        await this.savePlanDetails(changes);
    },
    async clearDates() { await this.savePlanDetails({ clear_dates: true }); },
    isChatbotDraft() { return this.plan?.source === 'chatbot' && this.plan?.status === 'draft'; },
    async savePlanDetails(changes) {
        await this.run(async () => {
            const body = await this.api('/plans/' + this.plan.id, 'PATCH', { revision: this.plan.revision, ...changes });
            this.plan = body.data; this.selections = body.data.items;
            this.startDate = this.plan.start_date?.slice(0, 10) || ''; this.endDate = this.plan.end_date?.slice(0, 10) || '';
            // A draft keeps showing its draft; a rescheduled trip's old schedule no longer matches and must be rebuilt.
            const latest = body.itinerary;
            this.draftItinerary = latest?.snapshot?.kind === 'chatbot_draft' ? latest : null;
            this.itinerary = !this.draftItinerary && latest?.revision === this.plan.revision ? latest : null;
            this.savedPlans = this.savedPlans.map(plan => plan.id === this.plan.id ? this.plan : plan);
            this.notice = this.draftItinerary ? 'Draft saved. Build a schedule when your dates and choices are ready.'
                : 'Trip details saved. Your choices were kept where they still fit; build the schedule again before adding it to your Trip Basket.';
            this.renderMap(true);
        });
    },
    async saveEmailPreferences() {
        await this.run(async () => { this.notice = (await this.api('/email-preferences', 'PATCH', { reminders: this.reminders, marketing: this.marketing })).message; });
    },
    async selectDay(index, preserveViewport = false) { this.activeDay = index; this.renderMap(preserveViewport); },
    focusStop(stop) {
        const lat = Number(stop.lat), lng = Number(stop.lng);
        if (map && stop.lat != null && stop.lng != null && Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) map.flyTo([lat, lng], 16);
    },
    peso(amount) { return '₱' + Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    nightsBetween(from, to) {
        if (!from || !to) return 1;
        return Math.max(1, Math.round((new Date(to) - new Date(from)) / 86400000));
    },
    roomNightlyRate(listing, pax) {
        const base = Number(listing?.base_price) || 0, basePax = Number(listing?.base_occupancy) || 2, fee = Number(listing?.extra_person_fee) || 0;
        pax = Math.max(1, Number(pax) || basePax);
        return pax > basePax ? base + (pax - basePax) * fee : base;
    },
    roomTotal(listing, pax, quantity, from, to) {
        return this.roomNightlyRate(listing, pax) * this.nightsBetween(from, to) * Math.max(1, Number(quantity) || 1);
    },
    activityUnitRate(listing, pax) {
        return (Number(pax) || 1) <= 1 ? (Number(listing?.rate_min) || 0) : (Number(listing?.rate_max) || 0);
    },
    activityLimit(listing) {
        const capacity = Number(listing?.max_pax);
        return Number.isFinite(capacity) && capacity >= 1 ? Math.min(20, Math.floor(capacity)) : 20;
    },
    refreshMinimumArrival(timestamp = null) {
        if (!config.datePolicy) return;
        const now = timestamp ?? (Number.isFinite(serverClock) ? serverClock + Date.now() - clockStartedAt : Date.now());
        const parts = new Intl.DateTimeFormat('en-US', { timeZone: config.datePolicy.timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date(now));
        const part = type => parts.find(item => item.type === type).value;
        const date = new Date(Date.UTC(Number(part('year')), Number(part('month')) - 1, Number(part('day')) + config.datePolicy.leadDays));
        this.minimumArrival = date.toISOString().slice(0, 10);
    },
    arrivalTooSoon(date) { return Boolean(this.minimumArrival && date && date < this.minimumArrival); },
    arrivalHint() { return 'Arrival must be at least two calendar days ahead in Philippine time. The earliest arrival is ' + this.minimumArrival + '. Change the trip dates if needed.'; },
    adjustActivityPax(change) {
        const current = Math.max(1, Number(this.optionPax) || 1);
        this.optionPax = change < 0 ? Math.max(1, current - 1) : (current < this.activityLimit(this.editing) ? current + 1 : current);
    },
    activityTotal(listing, pax) {
        pax = Math.max(1, Number(pax) || 1);
        const unit = this.activityUnitRate(listing, pax);
        return listing?.per_person ? unit * pax : unit;
    },
    selectionListing(item) { return this.allListings().find(listing => listing.type === item.item_type && listing.id === item.item_id); },
    // Mirrors the transfer rule: rooms and scheduled activities are added; unscheduled activities and hotel bases are not.
    basketPreview() {
        const scheduled = new Set((this.itinerary?.snapshot?.days || []).flatMap(day => (day.stops || []).map(stop => stop.plan_item_id)));
        const preview = { added: [], skipped: [] };
        for (const item of this.selections) {
            if (item.item_type === 'hotel') continue;
            (item.item_type === 'room' || scheduled.has(item.id) ? preview.added : preview.skipped).push(this.selectionName(item));
        }
        return preview;
    },
    travelersLabel(item) {
        return item.item_type === 'activity'
            ? (item.options?.pax || 1) + ' of ' + (this.plan?.pax || 1) + ' traveler(s) joining'
            : (item.options?.quantity || 1) + ' room(s) × ' + (item.options?.pax || 1) + ' guest(s)';
    },
    canApplyPartySize(item) {
        const listing = this.selectionListing(item);
        return Boolean(item.item_type === 'activity' && listing && Number(item.options?.pax || 1) < Math.min(Number(this.plan?.pax) || 1, this.activityLimit(listing)));
    },
    async applyPartySize(item) {
        const listing = this.selectionListing(item);
        if (!listing) return;
        const pax = Math.min(Number(this.plan.pax) || 1, this.activityLimit(listing));
        await this.run(async () => {
            const body = await this.api('/plans/' + this.plan.id + '/items', 'POST', { item_type: 'activity', item_id: item.item_id,
                pax, quantity: Number(item.options?.quantity) || 1, date: item.options?.date || null, revision: this.plan.revision });
            this.applyChoice(body);
            this.notice = listing.name + ' now includes ' + pax + ' traveler(s).';
        });
    },
    // A planning estimate from listed rates; the checkout manifest sets the final price.
    tripEstimate() {
        let rooms = 0, activities = 0;
        const roomItems = this.selections.filter(item => item.item_type === 'room');
        for (const item of this.selections) {
            const listing = this.selectionListing(item);
            if (!listing) continue;
            if (item.item_type === 'room') rooms += this.roomTotal(listing, item.options?.pax, item.options?.quantity, item.options?.check_in || this.startDate, item.options?.check_out || this.endDate);
            if (item.item_type === 'activity') activities += this.activityTotal(listing, item.options?.pax);
        }
        // The fewest guests the chosen rooms sleep on any night, to compare with the party size.
        let sleeps = null;
        if (roomItems.length && this.startDate && this.endDate) {
            for (const night = new Date(this.startDate); night < new Date(this.endDate); night.setUTCDate(night.getUTCDate() + 1)) {
                const key = night.toISOString().slice(0, 10);
                const count = roomItems.filter(item => (item.options?.check_in || this.startDate) <= key && key < (item.options?.check_out || this.endDate))
                    .reduce((sum, item) => sum + (Number(item.options?.pax) || 0) * (Number(item.options?.quantity) || 1), 0);
                sleeps = sleeps === null ? count : Math.min(sleeps, count);
            }
        }
        return { rooms, activities, total: rooms + activities, sleeps, party: Number(this.plan?.pax) || 0 };
    },
    // The chat budget converted to a whole-trip amount the same way the chatbot priced it.
    budgetComparison() {
        const context = this.plan?.context || {};
        const max = Number(context.max_price);
        const basis = context.itinerary_budget_basis || 'trip_total';
        if (!max || basis === 'per_activity') return null;
        let budget = max * (basis === 'per_person' ? (Number(this.plan.pax) || 1) : 1);
        if (basis === 'per_night' || context.itinerary_budget_period === 'per_night') budget *= this.nightsBetween(this.startDate, this.endDate);
        const scope = context.itinerary_budget_scope || 'trip';
        const estimate = this.tripEstimate();
        const spend = scope === 'lodging' ? estimate.rooms : (scope === 'activities' ? estimate.activities : estimate.total);
        return { budget, spend, scope, remaining: budget - spend };
    },
    dayPlan(day) {
        const events = day?.events || [];
        const arrival = events.filter(event => !event.departure).map(event => ({ event: true, label: event.name, assumed: event.assumed }));
        const departure = events.filter(event => event.departure).map(event => ({ event: true, label: event.name, assumed: event.assumed }));
        const stops = (day?.stops || []).map(stop => ({ event: false, stop }));
        return [...arrival, ...stops, ...departure].map((item, index) => ({ ...item, num: index + 1 }));
    },
    itineraryMarkers() {
        const markers = new Map();
        for (const [dayIndex, day] of (this.itinerary?.snapshot.days || []).entries()) {
            for (const item of this.dayPlan(day)) {
                const location = item.event ? day.hotel : item.stop;
                if (location?.lat == null || location?.lng == null) continue;
                const lat = Number(location.lat), lng = Number(location.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
                const key = lat + ':' + lng;
                if (!markers.has(key)) markers.set(key, { lat, lng, labels: [], names: [], places: [], dayIndex });
                const marker = markers.get(key);
                marker.labels.push('D' + day.day + '·' + item.num);
                marker.names.push('Day ' + day.day + ' · ' + item.num + ' · ' + (item.event ? item.label : item.stop.name));
                const place = this.itineraryPlace(location, item.event);
                if (place && !marker.places.some(existing => this.placeKey(existing) === this.placeKey(place))) marker.places.push(place);
            }
        }
        return Array.from(markers.values()).map(marker => ({ ...marker, name: marker.names.join('\n') }));
    },
    itineraryPlace(location, hotel = false) {
        if (hotel) {
            return location?.hotel_id ? { type: 'hotel', id: location.hotel_id, name: location.name } : null;
        }
        const choice = this.selections.find(item => item.id === location?.plan_item_id)
            || this.itinerary?.snapshot.selections?.find(item => item.plan_item_id === location?.plan_item_id);
        const type = location?.type || choice?.item_type || choice?.type;
        const id = location?.item_id || choice?.item_id;
        if (!type || !id) return null;
        const listing = this.allListings().find(item => item.type === type && item.id === Number(id));
        return listing ? this.mapPlace(listing) : { type, id, name: location.name };
    },
    /** The hotel the trip is anchored on, used to connect unplaced picks. */
    baseHotel() {
        const day = (this.itinerary?.snapshot.days || []).find(day => day.hotel?.lat != null && day.hotel?.lng != null);
        return day ? day.hotel : null;
    },
    /**
     * Picks the planner could not fit onto a day. They still have coordinates,
     * so they belong on the map as pending pins rather than disappearing.
     */
    unscheduledMarkers() {
        return (this.itinerary?.snapshot.unscheduled || [])
            .filter(item => item.lat != null && item.lng != null && Number.isFinite(Number(item.lat)) && Number.isFinite(Number(item.lng)))
            .map(item => ({ ...item, lat: Number(item.lat), lng: Number(item.lng) }));
    },
    /** Tooltip lines for one leg, including how exact its drawn line is. */
    legTooltip(leg, dayNumber) {
        const lines = ['Day ' + dayNumber + ' - ' + leg.from + ' to ' + leg.to];
        lines.push(leg.distance_km != null && leg.duration_minutes != null
            ? Number(leg.distance_km).toFixed(2) + ' km, ' + leg.duration_minutes + ' min by ' + (leg.mode === 'driving' ? 'vehicle' : 'foot')
            : 'Travel distance and time are not available for this leg.');
        if (leg.geometry_kind === 'straight_line') lines.push('Straight line only: there is no mapped road route, so a boat or arranged transfer is needed.');
        if (leg.geometry_kind === 'road_with_connector') lines.push('The dotted ends bridge the gap between the mapped road and the exact spot.');
        if (leg.return_to_hotel) lines.push('Return to your hotel.');
        return lines;
    },
    /** Leaflet tooltips take a node; build one line per row without raw HTML. */
    tooltipFor(lines) {
        const wrapper = document.createElement('span');
        for (const line of lines) {
            const row = document.createElement('span');
            row.style.display = 'block';
            row.textContent = line;
            wrapper.append(row);
        }
        return wrapper;
    },
    scheduleMapResize() {
        const container = this.$refs.map;
        if (!map || !container?.isConnected) return;
        if (container.sunnyTripsResizeFrame) cancelAnimationFrame(container.sunnyTripsResizeFrame);
        container.sunnyTripsResizeFrame = requestAnimationFrame(() => {
            container.sunnyTripsResizeFrame = null;
            if (map && this.$refs.map?.isConnected) map.invalidateSize({ pan: false, debounceMoveend: true });
        });
    },
    renderMap(preserveViewport = false) {
        if (!map || !layers || !this.$refs.map?.isConnected) return;
        map.stop();
        map.invalidateSize({ pan: false, debounceMoveend: true });
        layers.clearLayers();
        previewMarkers.clear();
        this.routeNotice = '';
        const viewportParts = [this.destinationId, this.tab, this.activeDay];
        const addMarker = (item, label, onClick, variant = '', places = []) => {
            if (item.lat == null || item.lng == null) return;
            const latitude = Number(item.lat);
            const longitude = Number(item.lng);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) return;
            const accommodation = label === 'hotel' || (!label && (item.type === 'hotel' || item.type === 'room'));
            const grouped = Array.isArray(label);
            const width = grouped ? Math.max(48, ...label.map(text => text.length * 8 + 12)) : 32;
            const height = grouped ? label.length * 20 + 12 : 32;
            const content = accommodation ? hotelSymbol : (grouped ? label.map(text => '<span>' + text + '</span>').join('') : label);
            const options = label || accommodation ? { title: item.name, alt: item.name, icon: L.divIcon({ className: 'planner-map-icon', html: '<span class="planner-map-number' + (variant ? ' planner-map-' + variant : '') + '"' + (grouped ? ' style="width:' + width + 'px;height:' + height + 'px;border-radius:12px;line-height:20px"' : '') + '>' + content + '</span>', iconSize: [width, height], iconAnchor: [width / 2, height / 2] }) } : { title: item.name, alt: item.name };
            const marker = L.marker([latitude, longitude], options).addTo(layers);
            const tooltip = document.createElement('span'); tooltip.textContent = item.name;
            marker.bindTooltip(tooltip); if (onClick) marker.on('click', event => onClick(marker, event));
            for (const place of places) previewMarkers.set(this.placeKey(place), marker);
            viewportParts.push(item.type || label || 'destination', item.id || item.name, latitude, longitude);
            return marker;
        };
        if (!this.destinationId) {
            this.destinations.forEach(item => addMarker({ lat: item.latitude, lng: item.longitude, name: item.name }, null, () => this.chooseDestination(item.id)));
        } else if (this.itinerary && (this.tab === 'itinerary' || this.itineraryMapContext)) {
            const scheduled = this.itineraryMarkers();
            const pending = this.unscheduledMarkers();
            const pendingGroups = new Map();
            pending.forEach(item => {
                const key = item.lat + ':' + item.lng;
                if (!pendingGroups.has(key)) pendingGroups.set(key, []);
                pendingGroups.get(key).push(item);
            });
            scheduled.forEach(item => {
                const key = item.lat + ':' + item.lng;
                const sharedPending = pendingGroups.get(key) || [];
                for (const pick of sharedPending) {
                    const place = this.itineraryPlace(pick);
                    if (place && !item.places.some(existing => this.placeKey(existing) === this.placeKey(place))) item.places.push(place);
                }
                if (sharedPending.length) {
                    item.labels.push('?');
                    item.name += '\n' + sharedPending.map(pick => pick.name + ' · Not placed on a day. ' + pick.reason).join('\n');
                }
                addMarker(item, item.labels, (marker, event) => {
                if (this.busy) return;
                this.openMapPreview(item.places, marker, event); this.selectDay(item.dayIndex, true); this.focusStop(item);
                }, '', item.places);
            });
            let approximate = 0;
            let drawn = 0;
            try {
                this.itinerary.snapshot.days.forEach((day, index) => {
                    const active = index === this.activeDay;
                    const color = dayColors[index % dayColors.length];
                    day.legs?.forEach(leg => {
                        if (!leg.geometry) return;
                        const straight = leg.geometry_kind === 'straight_line';
                        drawn++;
                        if (straight) approximate++;
                        // A straight line is an estimate, so it stays dashed and thinner
                        // than a real road route even on the day being viewed.
                        const line = L.geoJSON(leg.geometry, { style: {
                            color, weight: active ? (straight ? 3 : 4) : 3,
                            opacity: active ? (straight ? 0.7 : 0.9) : 0.3,
                            dashArray: straight ? '3 9' : (leg.return_to_hotel ? '6 6' : null),
                        } }).addTo(layers);
                        line.bindTooltip(this.tooltipFor(this.legTooltip(leg, day.day)), { sticky: true });
                        line.on('click', () => this.selectDay(index));
                        // Dotted stubs close the gap when the road graph snapped the leg
                        // away from the real coordinate, so the line reaches its pin.
                        (leg.connectors || []).forEach(connector => L.geoJSON(connector, {
                            style: { color, weight: 2, opacity: active ? 0.8 : 0.3, dashArray: '1 6' },
                        }).addTo(layers));
                        viewportParts.push(leg.geometry_kind, leg.from, leg.to);
                    });
                });
            } catch { this.mapError = 'The mapped route could not be drawn. The day-by-day schedule and stops are still available.'; }
            const base = this.baseHotel();
            pendingGroups.forEach((items, key) => {
                const item = { ...items[0], name: items.map(pick => pick.name).join('\n') };
                if (!scheduled.some(stop => stop.lat + ':' + stop.lng === key)) {
                    const places = items.map(pick => this.itineraryPlace(pick)).filter(Boolean);
                    const marker = addMarker(item, '?', (marker, event) => this.openMapPreview(places, marker, event), 'pending', places);
                    marker?.bindTooltip(this.tooltipFor(items.map(pick => pick.name + ' · Not placed on a day. ' + pick.reason)), { sticky: true });
                }
                if (base) {
                    L.geoJSON({ type: 'LineString', coordinates: [[Number(base.lng), Number(base.lat)], [item.lng, item.lat]] },
                        { style: { color: '#6b7280', weight: 2, opacity: 0.45, dashArray: '2 8' } }).addTo(layers);
                }
            });
            const notices = [];
            if (approximate) notices.push(approximate + ' of ' + drawn + ' route lines are straight-line estimates — no mapped road connects those stops, so arrange a boat or transfer.');
            if (pending.length) notices.push(pending.length + ' pick(s) could not be placed on a day; they show as hollow pins linked to your hotel.');
            this.routeNotice = notices.join(' ');
        } else {
            const visible = this.tab === 'selections' ? this.allListings().filter(item => this.selected(item)) : this.listings();
            if (this.tab === 'rooms' && this.activeHotel()) {
                const hotel = this.activeHotel();
                addMarker(hotel, 'hotel', (marker, event) => this.openMapPreview([hotel], marker, event), '', [hotel]);
            } else {
                const places = new Map();
                visible.forEach(item => {
                    const place = this.mapPlace(item);
                    places.set(this.placeKey(place), place);
                });
                const grouped = new Map();
                places.forEach(item => {
                    if (item.lat == null || item.lng == null || !Number.isFinite(Number(item.lat)) || !Number.isFinite(Number(item.lng))) return;
                    const key = Number(item.lat) + ':' + Number(item.lng);
                    if (!grouped.has(key)) grouped.set(key, []);
                    grouped.get(key).push(item);
                });
                grouped.forEach(items => {
                    const item = { ...items[0], name: items.map(place => place.name).join('\n') };
                    addMarker(item, items.length > 1 ? String(items.length) : null, (marker, event) => this.openMapPreview(items, marker, event), '', items);
                });
            }
            const destination = this.destination();
            if (!layers.getLayers().length && destination) addMarker({ lat: destination.latitude, lng: destination.longitude, name: destination.name }, null);
        }
        const viewportSignature = viewportParts.join('|');
        if (!preserveViewport && layers.getLayers().length && viewportSignature !== this.viewportSignature) {
            map.fitBounds(layers.getBounds(), { padding: [35, 35], maxZoom: 15, animate: false });
        }
        this.viewportSignature = viewportSignature;
        this.scheduleMapResize();
    },
    destroy() {
        if (dateTimer !== null) window.clearInterval(dateTimer);
        if (dateRefreshHandler) {
            window.removeEventListener('focus', dateRefreshHandler);
            document.removeEventListener('visibilitychange', dateRefreshHandler);
        }
        dateTimer = null; dateRefreshHandler = null;
        this.weatherSequence++; this.loadSequence++; this.closeMapPreview(false); this.itineraryMapContext = false;
        const container = this.$refs.map;
        if (container?.sunnyTripsResizeFrame) cancelAnimationFrame(container.sunnyTripsResizeFrame);
        if (container?.sunnyTripsResizeObserver === resizeObserver) {
            container.sunnyTripsResizeObserver.disconnect();
            delete container.sunnyTripsResizeObserver;
        }
        if (container?.sunnyTripsPageShowHandler === pageShowHandler) {
            window.removeEventListener('pageshow', container.sunnyTripsPageShowHandler);
            delete container.sunnyTripsPageShowHandler;
        }
        map?.stop();
        map?.remove();
        if (container?.sunnyTripsMap === map) delete container.sunnyTripsMap;
        if (container) delete container.sunnyTripsResizeFrame;
        resizeObserver = null; pageShowHandler = null; map = null; layers = null;
        previewMarkers.clear();
    },
    };
};
