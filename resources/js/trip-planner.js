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

    return {
    destinations: config.destinations, savedPlans: config.plans, destinationId: Number(config.destination || 0),
    catalog: { hotels: [], activities: [] }, plan: null, selections: [], itinerary: null,
    weatherHtml: '', weatherLoading: false, weatherError: '', weatherSequence: 0,
    tab: 'hotels', busy: false, error: '', notice: '', mapError: '', routeNotice: '', search: '', activeDay: 0, editing: null,
    title: '', startDate: '', endDate: '', pax: 2, mode: 'walking', optionIn: '', optionOut: '', optionDate: '', optionPax: 2, optionQuantity: 1,
    viewportSignature: '', loadSequence: 0, activeHotelId: 0, savedPlanId: '', deleting: false, customStayDates: false,
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
            resizeObserver = new ResizeObserver(() => this.scheduleMapResize());
            resizeObserver.observe(container);
            container.sunnyTripsResizeObserver = resizeObserver;
            pageShowHandler = () => {
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
        this.loadWeather(this.destinationId);
        const sequence = ++this.loadSequence;
        try {
            const body = await this.api('/catalog/' + id);
            if (sequence !== this.loadSequence) return;
            this.catalog = body.data; this.renderMap();
        } catch (error) { console.error('Trip planner destination failed', error); this.error = error.message; }
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
            this.loadWeather(this.destinationId);
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
            this.notice = body.data.status === 'already_transferred' ? 'This itinerary still has items in your Trip Basket. Remove its remaining items before adding it again.' : 'Added to your Trip Basket. Continue there to review prices and request booking.';
            this.itinerary.transferred_at = new Date().toISOString(); window.dispatchEvent(new CustomEvent('cart-updated'));
            window.dispatchEvent(new CustomEvent('show-cart-modal', { detail: {
                alreadyInCart: body.data.status === 'already_transferred',
                message: body.data.status === 'already_transferred' ? this.notice : this.title + ' · ' + body.data.count + ' item(s) added to your Trip Basket.',
            } }));
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
    focusStop(stop) { if (map && stop.lat != null && stop.lng != null) map.flyTo([stop.lat, stop.lng], 16); },
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
    activityTotal(listing, pax) {
        pax = Math.max(1, Number(pax) || 1);
        const unit = this.activityUnitRate(listing, pax);
        return listing?.per_person ? unit * pax : unit;
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
                if (!markers.has(key)) markers.set(key, { lat, lng, labels: [], names: [], dayIndex });
                const marker = markers.get(key);
                marker.labels.push('D' + day.day + '·' + item.num);
                marker.names.push('Day ' + day.day + ' · ' + item.num + ' · ' + (item.event ? item.label : item.stop.name));
            }
        }
        return Array.from(markers.values()).map(marker => ({ ...marker, name: marker.names.join('\n') }));
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
    renderMap() {
        if (!map || !layers || !this.$refs.map?.isConnected) return;
        map.stop();
        map.invalidateSize({ pan: false, debounceMoveend: true });
        layers.clearLayers();
        this.routeNotice = '';
        const viewportParts = [this.destinationId, this.tab, this.activeDay];
        const addMarker = (item, label, onClick, variant = '') => {
            if (item.lat == null || item.lng == null) return;
            const latitude = Number(item.lat);
            const longitude = Number(item.lng);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
            const accommodation = label === 'hotel' || (!label && (item.type === 'hotel' || item.type === 'room'));
            const grouped = Array.isArray(label);
            const width = grouped ? Math.max(48, ...label.map(text => text.length * 8 + 12)) : 32;
            const height = grouped ? label.length * 20 + 12 : 32;
            const content = accommodation ? hotelSymbol : (grouped ? label.map(text => '<span>' + text + '</span>').join('') : label);
            const options = label || accommodation ? { title: item.name, alt: item.name, icon: L.divIcon({ className: 'planner-map-icon', html: '<span class="planner-map-number' + (variant ? ' planner-map-' + variant : '') + '"' + (grouped ? ' style="width:' + width + 'px;height:' + height + 'px;border-radius:12px;line-height:20px"' : '') + '>' + content + '</span>', iconSize: [width, height], iconAnchor: [width / 2, height / 2] }) } : { title: item.name, alt: item.name };
            const marker = L.marker([latitude, longitude], options).addTo(layers);
            const tooltip = document.createElement('span'); tooltip.textContent = item.name;
            marker.bindTooltip(tooltip); if (onClick) marker.on('click', onClick);
            viewportParts.push(item.type || label || 'destination', item.id || item.name, latitude, longitude);
            return marker;
        };
        if (!this.destinationId) {
            this.destinations.forEach(item => addMarker({ lat: item.latitude, lng: item.longitude, name: item.name }, null, () => this.chooseDestination(item.id)));
        } else if (this.itinerary && this.tab === 'itinerary') {
            this.itineraryMarkers().forEach(marker => addMarker(marker, marker.labels, () => { this.selectDay(marker.dayIndex); this.focusStop(marker); }));
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
            const pending = this.unscheduledMarkers();
            pending.forEach(item => {
                const marker = addMarker(item, '?', null, 'pending');
                marker?.bindTooltip(this.tooltipFor([item.name, 'Not placed on a day. ' + item.reason]), { sticky: true });
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
                addMarker(this.activeHotel(), 'hotel');
            } else {
                visible.forEach(item => addMarker(item, null, () => item.type === 'hotel' ? this.chooseHotel(item) : this.edit(item)));
            }
            const destination = this.destination();
            if (!layers.getLayers().length && destination) addMarker({ lat: destination.latitude, lng: destination.longitude, name: destination.name }, null);
        }
        const viewportSignature = viewportParts.join('|');
        if (layers.getLayers().length && viewportSignature !== this.viewportSignature) {
            map.fitBounds(layers.getBounds(), { padding: [35, 35], maxZoom: 15, animate: false });
        }
        this.viewportSignature = viewportSignature;
        this.scheduleMapResize();
    },
    destroy() {
        this.weatherSequence++;
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
    },
    };
};
