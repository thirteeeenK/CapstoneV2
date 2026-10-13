const hotelSymbol = '<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18V6m18 12v-7a2 2 0 0 0-2-2h-7v7M3 16h18M3 9h6v7"/><path d="M5 11h2"/></svg>';

export default (config) => ({
    destinations: config.destinations, savedPlans: config.plans, destinationId: Number(config.destination || 0),
    catalog: { hotels: [], activities: [] }, plan: null, selections: [], itinerary: null,
    tab: 'hotels', busy: false, error: '', notice: '', mapError: '', search: '', activeDay: 0, editing: null,
    title: '', startDate: '', endDate: '', pax: 2, mode: 'walking', optionIn: '', optionOut: '', optionDate: '', optionPax: 2, optionQuantity: 1,
    map: null, layers: null, loadSequence: 0, activeHotelId: 0, savedPlanId: '', deleting: false, customStayDates: false,
    reminders: config.emailPreferences.reminders, marketing: config.emailPreferences.marketing,
    async init() {
        try {
            if (!window.L) {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    script.onload = resolve; script.onerror = reject; document.head.append(script);
                });
            }
            this.map = L.map(this.$refs.map, { scrollWheelZoom: false }).setView([11.967, 121.925], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 })
                .on('tileerror', () => { this.mapError = 'Map tiles are unavailable. You can still use the destination and listing controls.'; }).addTo(this.map);
            this.layers = L.featureGroup().addTo(this.map);
        } catch { this.mapError = 'The map could not load. You can still plan from the listings.'; }
        if (this.destinationId) await this.chooseDestination(this.destinationId);
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
        this.destinationId = Number(id); this.error = ''; this.title = 'Trip to ' + (this.destination()?.name || 'the islands');
        const sequence = ++this.loadSequence;
        try {
            const body = await this.api('/catalog/' + id);
            if (sequence !== this.loadSequence) return;
            this.catalog = body.data; this.renderMap();
        } catch (error) { this.error = error.message; }
    },
    async run(action) {
        if (this.busy) return;
        this.busy = true; this.error = ''; this.notice = '';
        try { await action(); } catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    async create() {
        await this.run(async () => {
            this.plan = (await this.api('/plans', 'POST', { destination_id: this.destinationId, title: this.title,
                start_date: this.startDate, end_date: this.endDate, pax: Number(this.pax), mode: this.mode })).data;
            this.savedPlans.unshift(this.plan); this.notice = 'Trip saved. Choose the places you want to include.';
            this.savedPlanId = String(this.plan.id);
        });
    },
    async openPlan(id) {
        await this.run(async () => {
            const body = await this.api('/plans/' + id);
            this.plan = body.data; this.selections = body.data.items; this.itinerary = body.itinerary;
            this.savedPlanId = String(this.plan.id);
            this.destinationId = Number(this.plan.destination_id); this.title = this.plan.title;
            this.startDate = this.plan.start_date.slice(0, 10); this.endDate = this.plan.end_date.slice(0, 10);
            this.pax = this.plan.pax; this.mode = this.plan.mode;
            this.catalog = (await this.api('/catalog/' + this.destinationId)).data;
            const room = this.selections.find(item => item.item_type === 'room');
            this.activeHotelId = room ? this.allListings().find(item => item.type === 'room' && item.id === room.item_id)?.hotel_id || 0 : this.selections.find(item => item.item_type === 'hotel')?.item_id || 0;
            if (this.itinerary?.revision !== this.plan.revision) this.itinerary = null;
            this.tab = this.itinerary ? 'itinerary' : 'selections'; this.activeDay = 0; this.renderMap();
        });
    },
    newTrip() {
        this.plan = null; this.selections = []; this.itinerary = null; this.tab = 'hotels'; this.error = ''; this.notice = '';
        this.savedPlanId = ''; this.activeHotelId = 0; this.editing = null; this.deleting = false;
        this.title = 'Trip to ' + (this.destination()?.name || 'the islands'); this.renderMap();
    },
    listings(type = this.tab) {
        const list = type === 'rooms' ? (this.activeHotel()?.rooms || []) : (this.catalog[type] || []);
        return list.filter(item => (item.name + ' ' + (item.hotel_name || '')).toLowerCase().includes(this.search.toLowerCase()));
    },
    allListings() { return [...this.catalog.hotels, ...this.catalog.hotels.flatMap(hotel => hotel.rooms), ...this.catalog.activities]; },
    activeHotel() { return this.catalog.hotels.find(hotel => hotel.id === Number(this.activeHotelId)); },
    async chooseHotel(listing) {
        if (!this.plan) { this.error = 'Save your trip dates first, then select a hotel.'; return; }
        await this.run(async () => {
            if (!this.selected(listing)) {
                const body = await this.api('/plans/' + this.plan.id + '/items', 'POST', { item_type: 'hotel', item_id: listing.id });
                this.applyChoice(body);
            }
            this.activeHotelId = listing.id; this.tab = 'rooms'; this.search = '';
            this.notice = 'Choose a room at ' + listing.name + '. Your stay uses the trip dates; edit the hotel in My choices for a different stay.';
            this.renderMap();
        });
    },
    applyChoice(body) {
        this.selections = body.items;
        if (this.plan.revision !== body.revision) this.itinerary = null;
        this.plan.revision = body.revision;
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
    selected(listing) { return this.selections.some(item => item.item_type === listing.type && item.item_id === listing.id); },
    selectionName(item) { return this.allListings().find(listing => listing.type === item.item_type && listing.id === item.item_id)?.name || 'Unavailable listing'; },
    edit(listing) {
        if (!this.plan) { this.error = 'Save your trip dates first, then add choices.'; return; }
        const existing = this.selections.find(item => item.item_type === listing.type && item.item_id === listing.id);
        const hotelStay = this.selections.find(item => item.item_type === 'hotel' && item.item_id === listing.hotel_id);
        this.editing = listing; this.optionIn = hotelStay?.options.check_in || existing?.options.check_in || this.startDate;
        this.optionOut = hotelStay?.options.check_out || existing?.options.check_out || this.endDate; this.optionDate = existing?.options.date || '';
        this.customStayDates = this.optionIn !== this.startDate || this.optionOut !== this.endDate;
        this.optionPax = existing?.options.pax || this.pax; this.optionQuantity = existing?.options.quantity || 1;
        this.$nextTick(() => this.$refs.choiceHeading?.focus());
    },
    editSelection(item) {
        const listing = this.allListings().find(listing => listing.type === item.item_type && listing.id === item.item_id);
        if (listing) this.edit(listing); else this.error = 'This listing is no longer available. Remove it and select another.';
    },
    async saveChoice() {
        await this.run(async () => {
            const type = this.editing.type;
            if (type === 'hotel' && !this.customStayDates) { this.optionIn = this.startDate; this.optionOut = this.endDate; }
            const body = await this.api('/plans/' + this.plan.id + '/items', 'POST', { item_type: type, item_id: this.editing.id,
                pax: Number(this.optionPax), quantity: Number(this.optionQuantity),
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
            const body = await this.api('/plans/' + this.plan.id + '/items/' + item.id, 'DELETE');
            this.selections = this.selections.filter(choice => choice.id !== item.id);
            this.plan.revision = body.revision; this.itinerary = null; this.renderMap();
        });
    },
    async generate() {
        await this.run(async () => {
            this.itinerary = (await this.api('/plans/' + this.plan.id + '/generate', 'POST')).data;
            this.activeDay = 0; this.tab = 'itinerary'; this.renderMap(); this.notice = 'Itinerary saved. Review the schedule and its explanation.';
        });
    },
    async transfer() {
        await this.run(async () => {
            const body = await this.api('/plans/' + this.plan.id + '/itineraries/' + this.itinerary.id + '/cart', 'POST');
            this.notice = body.data.status === 'already_transferred' ? 'This version has already been added to your Trip Basket.' : 'Added to your Trip Basket. Continue there to review prices and request booking.';
            this.itinerary.transferred_at = new Date().toISOString(); window.dispatchEvent(new CustomEvent('cart-updated'));
        });
    },
    documentUrl(print = false) { return this.itinerary ? config.base + '/plans/' + this.plan.id + '/itineraries/' + this.itinerary.id + '/document' + (print ? '?print=1' : '') : '#'; },
    async email() {
        await this.run(async () => { this.notice = (await this.api('/plans/' + this.plan.id + '/itineraries/' + this.itinerary.id + '/email', 'POST')).message; });
    },
    async saveEmailPreferences() {
        await this.run(async () => { this.notice = (await this.api('/email-preferences', 'PATCH', { reminders: this.reminders, marketing: this.marketing })).message; });
    },
    async selectDay(index) { this.activeDay = index; this.renderMap(); },
    focusStop(stop) { if (this.map && stop.lat != null && stop.lng != null) this.map.flyTo([stop.lat, stop.lng], 16); },
    renderMap() {
        if (!this.map || !this.layers) return;
        this.layers.clearLayers();
        const addMarker = (item, label, onClick) => {
            if (item.lat == null || item.lng == null) return;
            const accommodation = label === 'hotel' || item.type === 'hotel' || item.type === 'room';
            const options = label || accommodation ? { title: item.name, alt: item.name, icon: L.divIcon({ className: 'planner-map-icon', html: '<span class="planner-map-number">' + (accommodation ? hotelSymbol : label) + '</span>', iconSize: [32, 32], iconAnchor: [16, 16] }) } : { title: item.name, alt: item.name };
            const marker = L.marker([item.lat, item.lng], options).addTo(this.layers);
            const tooltip = document.createElement('span'); tooltip.textContent = item.name;
            marker.bindTooltip(tooltip); if (onClick) marker.on('click', onClick);
        };
        if (!this.destinationId) {
            this.destinations.forEach(item => addMarker({ lat: item.latitude, lng: item.longitude, name: item.name }, null, () => this.chooseDestination(item.id)));
        } else if (this.itinerary && this.tab === 'itinerary') {
            const day = this.itinerary.snapshot.days[this.activeDay];
            if (day?.hotel) addMarker(day.hotel, 'hotel');
            day?.stops.forEach((stop, index) => addMarker(stop, index + 1, () => this.focusStop(stop)));
            day?.legs.forEach(leg => { if (leg.geometry) L.geoJSON(leg.geometry, { style: { color: '#0077a5', weight: 4, opacity: 0.8, dashArray: leg.return_to_hotel ? '6 6' : null } }).addTo(this.layers); });
        } else {
            const destination = this.destination();
            if (destination) addMarker({ lat: destination.latitude, lng: destination.longitude, name: destination.name }, null);
            const visible = this.tab === 'selections' ? this.allListings().filter(item => this.selected(item)) : this.listings();
            visible.forEach(item => addMarker(item, null, () => item.type === 'hotel' ? this.chooseHotel(item) : this.edit(item)));
        }
        if (this.layers.getLayers().length) this.map.fitBounds(this.layers.getBounds(), { padding: [35, 35], maxZoom: 15 });
        this.$nextTick(() => this.map.invalidateSize());
    },
    destroy() { this.map?.remove(); },
});
