import assert from 'node:assert/strict';
import { test } from 'node:test';
import tripPlanner from '../../resources/js/trip-planner.js';

test('undated saved drafts open without weather calls and retain their draft snapshot', async () => {
    const planner = tripPlanner({ destinations: [], plans: [], emailPreferences: {} });
    const draft = { id: 8, revision: 1, snapshot: { kind: 'chatbot_draft', days: [{ day: 1, choices: [] }] } };
    const plan = { id: 7, revision: 1, destination_id: 1, start_date: null, end_date: null, pax: 2, mode: 'walking', items: [], title: 'Flexible trip' };
    const requests = [];
    planner.run = async action => action();
    planner.closeMapPreview = () => {};
    planner.renderMap = () => {};
    planner.allListings = () => [];
    planner.loadWeather = () => { throw new Error('An undated draft must not request a forecast'); };
    planner.api = async path => { requests.push(path); return path.includes('/catalog/') ? { data: { hotels: [], activities: [] } } : { data: plan, itinerary: draft }; };
    await planner.openPlan(7);
    assert.equal(planner.startDate, '');
    assert.equal(planner.endDate, '');
    assert.equal(planner.itinerary, null);
    assert.equal(planner.draftItinerary.id, 8);
    assert.equal(planner.tab, 'itinerary');
    assert.equal(planner.savedPlans[0].id, 7);
    assert.match(planner.savedPlanLabel(plan), /Dates to be decided/);
    assert.deepEqual(requests, ['/plans/7', '/catalog/1']);
});

async function withPreviewMap(action, datePolicy = null) {
    const keys = ['window', 'document', 'L', 'ResizeObserver', 'requestAnimationFrame', 'cancelAnimationFrame'];
    const originals = new Map(keys.map(key => [key, globalThis[key]]));
    const markers = [], routes = [], requests = [];
    const lifecycle = { timers: [], cleared: [], windowEvents: new Map(), documentEvents: new Map() };
    const map = {
        fits: 0, flights: [], handlers: {}, setView() { return this; }, stop() {}, invalidateSize() {}, remove() {},
        fitBounds() { this.fits++; }, flyTo(point) { this.flights.push(point); }, on(event, handler) { this.handlers[event] = handler; },
    };
    const node = () => ({ isConnected: true, focus() { document.activeElement = this; } });
    const container = node();
    const group = {
        addTo() { return this; },
        clearLayers() { markers.forEach(marker => { marker.element.isConnected = false; }); markers.length = 0; routes.length = 0; },
        getLayers: () => [...markers, ...routes], getBounds: () => markers.map(marker => marker.coordinates),
    };
    const leaflet = {
        map: () => map,
        tileLayer: () => ({ on() { return this; }, addTo() {} }),
        featureGroup: () => group, divIcon: options => options,
        marker(coordinates, options) {
            const marker = {
                coordinates, options, element: node(), getElement() { return this.element; },
                addTo() { markers.push(this); return this; }, bindTooltip() { return this; },
                on(event, handler) { if (event === 'click') this.click = handler; return this; },
            };
            return marker;
        },
        geoJSON(geometry, options) {
            return { geometry, options, addTo() { routes.push(this); return this; }, bindTooltip() { return this; }, on() { return this; } };
        },
    };
    try {
        globalThis.document = { activeElement: null, createElement: () => ({ style: {}, append() {} }),
            addEventListener: (event, handler) => lifecycle.documentEvents.set(event, handler), removeEventListener: event => lifecycle.documentEvents.delete(event) };
        globalThis.window = { L: leaflet,
            setInterval: handler => { lifecycle.timers.push(handler); return lifecycle.timers.length; }, clearInterval: id => lifecycle.cleared.push(id),
            addEventListener: (event, handler) => lifecycle.windowEvents.set(event, handler), removeEventListener: event => lifecycle.windowEvents.delete(event) };
        globalThis.L = leaflet;
        globalThis.ResizeObserver = class { observe() {} disconnect() {} };
        globalThis.requestAnimationFrame = () => 1; globalThis.cancelAnimationFrame = () => {};
        const planner = tripPlanner({ destinations: [{ id: 1, name: 'Boracay', latitude: 11.96, longitude: 121.92 }], plans: [], destination: 0, emailPreferences: {}, datePolicy });
        planner.$refs = { map: container, previewHeading: node(), roomsHeading: node(), choiceHeading: node() };
        planner.$nextTick = callback => callback();
        planner.loadWeather = async () => {};
        planner.api = async (...args) => { requests.push(args); throw new Error('Unexpected write'); };
        await planner.init();
        planner.destinationId = 1;
        const hotel = { type: 'hotel', id: 7, hotel_id: 7, name: 'Ocean Hotel', description: 'A beachfront stay.', lat: 11.96, lng: 121.92,
            rooms: [{ type: 'room', id: 8, hotel_id: 7, hotel_name: 'Ocean Hotel', name: 'Ocean Room', price: 2500, lat: 11.96, lng: 121.92 }] };
        const activity = { type: 'activity', id: 7, name: 'Kayaking', description: 'Explore the coastline.', lat: 11.97, lng: 121.93, max_pax: 6 };
        planner.catalog = { hotels: [hotel, { type: 'hotel', id: 9, hotel_id: 9, name: 'Garden Hotel', lat: 11.98, lng: 121.94, rooms: [] }], activities: [activity] };
        planner.renderMap();
        await action({ planner, hotel, activity, map, markers, routes, requests, lifecycle });
        planner.destroy();
    } finally {
        for (const [key, value] of originals) {
            if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
        }
    }
}

test('hotel marker browsing shows only its rooms without saving or changing the map viewport', async () => {
    await withPreviewMap(async ({ planner, hotel, markers, map, requests }) => {
        const fits = map.fits;
        await markers[0].click();

        assert.equal(planner.previewListing(), hotel);
        assert.equal(planner.tab, 'rooms');
        assert.equal(planner.activeHotelId, 7);
        assert.deepEqual(planner.listings().map(room => room.name), ['Ocean Room']);
        assert.equal(requests.length, 0);
        assert.equal(planner.plan, null);
        assert.equal(map.fits, fits);
        await markers[0].click();
        assert.equal(planner.previewListing(), hotel, 'the Rooms tab hotel pin remains interactive');
    });
});

test('hotel preview navigation retains saved stay dates, revision and itinerary', async () => {
    await withPreviewMap(async ({ planner, hotel, requests }) => {
        const choice = { item_type: 'hotel', item_id: 7, options: { check_in: '2026-10-16', check_out: '2026-10-18' } };
        const itinerary = { revision: 4, snapshot: { days: [] } };
        planner.plan = { id: 1, revision: 4 }; planner.selections = [choice]; planner.itinerary = itinerary;
        planner.search = 'Garden';

        planner.openMapPreview([hotel]);
        planner.viewHotelRooms(hotel, true);

        assert.equal(planner.search, '');
        assert.equal(planner.plan.revision, 4);
        assert.equal(planner.itinerary, itinerary);
        assert.deepEqual(choice.options, { check_in: '2026-10-16', check_out: '2026-10-18' });
        assert.equal(requests.length, 0);
        assert.equal(document.activeElement, planner.$refs.roomsHeading);
    });
});

test('activity marker previews first and quick add persists once starting with one traveler', async () => {
    await withPreviewMap(async ({ planner, activity, markers, requests }) => {
        planner.plan = { id: 2, pax: 3, revision: 1 }; planner.pax = 8;
        planner.setSection('activities');
        markers[0].click();
        assert.equal(planner.previewListing(), activity);
        assert.equal(planner.editing, null);
        assert.equal(requests.length, 0);
        let complete;
        planner.api = (...args) => { requests.push(args); return new Promise(resolve => { complete = resolve; }); };
        planner.itinerary = { snapshot: { days: [] } };

        const addition = planner.addMapChoice();
        await planner.addMapChoice();
        assert.equal(requests.length, 1, 'busy guard rejects double clicks');
        assert.deepEqual(requests[0], ['/plans/2/items', 'POST', { item_type: 'activity', item_id: 7, pax: 1, quantity: 1 }]);
        complete({ items: [{ item_type: 'activity', item_id: 7, options: { pax: 1 } }], revision: 2 });
        await addition;

        assert.equal(planner.selected(activity), true);
        assert.equal(planner.plan.revision, 2);
        assert.equal(planner.itinerary, null);
        assert.equal(planner.busy, false);
        assert.match(planner.previewStatus, /Kayaking added/);
        assert.equal(planner.previewKey, 'activity:7');
    });
});

test('hotel card addition saves the planning base and keeps its matching rooms visible', async () => {
    await withPreviewMap(async ({ planner, hotel, requests }) => {
        planner.plan = { id: 3, pax: 2, revision: 1 };
        planner.openMapPreview([hotel]);
        planner.api = async (...args) => {
            requests.push(args);
            return { items: [{ item_type: 'hotel', item_id: 7, options: { check_in: '2026-10-15', check_out: '2026-10-17' } }], revision: 2 };
        };

        await planner.addMapChoice();

        assert.deepEqual(requests, [['/plans/3/items', 'POST', { item_type: 'hotel', item_id: 7 }]]);
        assert.equal(planner.selected(hotel), true);
        assert.equal(planner.activeHotelId, 7);
        assert.equal(planner.tab, 'rooms');
        assert.equal(planner.previewListing(), hotel);
        assert.equal(planner.plan.revision, 2);
    });
});

test('selected activity and hotel card actions open the editor without resetting saved options', async () => {
    await withPreviewMap(async ({ planner, hotel, activity, requests }) => {
        planner.plan = { id: 1, pax: 4, revision: 2 };
        planner.selections = [
            { item_type: 'activity', item_id: 7, options: { pax: 1, date: '2026-10-17' } },
            { item_type: 'hotel', item_id: 7, options: { pax: 4, check_in: '2026-10-15', check_out: '2026-10-18' } },
        ];
        planner.openMapPreview([activity]);
        await planner.addMapChoice();
        assert.equal(planner.editing, activity);
        assert.equal(planner.optionPax, 1);
        assert.equal(planner.optionDate, '2026-10-17');

        planner.editing = null; planner.openMapPreview([hotel]);
        await planner.addMapChoice();
        assert.equal(planner.editing, hotel);
        assert.equal(planner.optionIn, '2026-10-15');
        assert.equal(planner.optionOut, '2026-10-18');
        assert.equal(requests.length, 0);
    });
});

test('failed additions retain the preview and prior trip state and expose the server error', async () => {
    await withPreviewMap(async ({ planner, activity }) => {
        planner.plan = { id: 1, pax: 10, revision: 3 };
        const itinerary = { snapshot: { days: [] } }; planner.itinerary = itinerary;
        planner.openMapPreview([activity]);
        planner.api = async () => { throw new Error('The number of guests exceeds this activity’s capacity.'); };

        await planner.addMapChoice();

        assert.equal(planner.plan.revision, 3);
        assert.equal(planner.itinerary, itinerary);
        assert.deepEqual(planner.selections, []);
        assert.equal(planner.previewListing(), activity);
        assert.match(planner.previewError, /capacity/);
        assert.equal(planner.previewStatus, '');
        assert.equal(planner.busy, false);
    });
});

test('unsaved trips and unavailable snapshot places cannot submit preview additions', async () => {
    await withPreviewMap(async ({ planner, activity, requests }) => {
        planner.openMapPreview([activity]);
        await planner.addMapChoice();
        planner.plan = { id: 1, pax: 2 };
        planner.openMapPreview([{ type: 'activity', id: 99, name: 'Removed tour' }]);
        await planner.addMapChoice();

        assert.equal(planner.previewPlace().name, 'Removed tour');
        assert.equal(planner.previewListing(), null);
        assert.equal(requests.length, 0);
        assert.equal(planner.selected(null), false);
    });
});

test('itinerary hotel preview keeps all route geometry and day labels while opening Rooms', async () => {
    await withPreviewMap(async ({ planner, hotel, activity, markers, routes, map, requests }) => {
        planner.plan = { id: 1, revision: 2 };
        const geometry = { type: 'LineString', coordinates: [[121.92, 11.96], [121.93, 11.97]] };
        planner.itinerary = { snapshot: { days: [
            { day: 1, hotel: { ...hotel }, events: [{ name: 'Check in' }], stops: [{ ...activity, item_id: 7 }], legs: [{ geometry }] },
            { day: 2, hotel: { ...hotel }, events: [{ name: 'Check out', departure: true }], stops: [], legs: [{ geometry }] },
        ] } };
        planner.setSection('itinerary');
        const fits = map.fits;

        markers[0].click();

        assert.equal(planner.tab, 'rooms');
        assert.equal(planner.previewListing(), hotel);
        assert.equal(planner.itineraryMapContext, true);
        assert.equal(routes.length, 2);
        assert.equal(markers.length, 2);
        assert.match(markers[0].options.icon.html, /D1·1/);
        assert.match(markers[0].options.icon.html, /D2·1/);
        assert.equal(map.fits, fits);
        assert.deepEqual(map.flights, [[11.96, 121.92]]);
        assert.equal(requests.length, 0);

        planner.setSection('activities');
        assert.equal(routes.length, 0);
        assert.equal(planner.itineraryMapContext, false);
        assert.deepEqual(planner.previewPlaces, []);
    });
});

test('shared coordinate pins offer distinct places and pending pins resolve by selection identity', async () => {
    await withPreviewMap(async ({ planner, hotel, activity, markers }) => {
        planner.plan = { id: 1, revision: 1 };
        const other = { ...activity, id: 10, name: 'Paddleboarding' };
        planner.catalog.activities.push(other);
        planner.setSection('activities');
        assert.equal(markers.length, 1);
        markers[0].click();
        assert.deepEqual(planner.previewPlaces.map(place => place.name), ['Kayaking', 'Paddleboarding']);
        assert.equal(planner.previewListing(), null, 'shared pins require an explicit place selection');
        planner.selectMapPlace(planner.previewPlaces[1]);
        assert.equal(planner.previewListing(), other);

        planner.selections = [{ id: 12, item_type: 'activity', item_id: 7 }];
        planner.itinerary = { snapshot: { days: [{ day: 1, hotel, events: [{ name: 'Check in' }], stops: [{ ...activity, item_id: 7, lat: hotel.lat, lng: hotel.lng }], legs: [] }],
            unscheduled: [{ plan_item_id: 12, type: 'activity', name: 'Kayaking', lat: activity.lat, lng: activity.lng, reason: 'No route.' }] } };
        planner.setSection('itinerary');
        markers[0].click();
        assert.deepEqual(planner.previewPlaces.map(place => planner.placeKey(place)), ['hotel:7', 'activity:7']);
        assert.equal(planner.previewKey, '');
        markers[1].click();
        assert.equal(planner.previewListing(), activity);
    });
});

test('keyboard previews focus their heading and dismiss back to the redrawn hotel marker', async () => {
    await withPreviewMap(async ({ planner, markers }) => {
        markers[0].element.focus();
        markers[0].click();
        assert.equal(document.activeElement, planner.$refs.previewHeading);

        planner.dismissMapPreview();

        assert.equal(document.activeElement, markers[0].element);
        assert.deepEqual(planner.previewPlaces, []);
    });
});

test('pointer activation does not move focus even when the browser focused the hotel marker', async () => {
    await withPreviewMap(async ({ planner, markers }) => {
        const element = markers[0].element;
        element.focus();

        markers[0].click({ originalEvent: { type: 'click' } });

        assert.equal(document.activeElement, element);
        assert.equal(planner.tab, 'rooms');
    });
});

test('preview cleanup handles empty map, searching, new trips and stale destination responses', async () => {
    await withPreviewMap(async ({ planner, activity, map }) => {
        planner.openMapPreview([activity]);
        map.handlers.click();
        assert.deepEqual(planner.previewPlaces, []);
        planner.openMapPreview([activity]); planner.searchListings();
        assert.deepEqual(planner.previewPlaces, []);
        planner.openMapPreview([activity]); planner.newTrip();
        assert.deepEqual(planner.previewPlaces, []);
        assert.equal(planner.activeHotelId, 0);
        let complete;
        planner.api = () => new Promise(resolve => { complete = resolve; });
        const load = planner.chooseDestination(2);
        await planner.chooseDestination(0);
        complete({ data: { hotels: [{ type: 'hotel', id: 99, rooms: [] }], activities: [] } });
        await load;

        assert.equal(planner.destinationId, 0);
        assert.deepEqual(planner.catalog, { hotels: [], activities: [] });
        assert.equal(planner.catalogLoading, false);
    });
});

test('My choices deduplicates hotel and room pins without losing room selections', async () => {
    await withPreviewMap(async ({ planner, markers, hotel }) => {
        planner.selections = [{ item_type: 'hotel', item_id: 7 }, { item_type: 'room', item_id: 8 }];
        planner.setSection('selections');

        assert.equal(markers.length, 1);
        markers[0].click();
        assert.equal(planner.previewListing(), hotel);
        assert.equal(planner.selections.length, 2);
        assert.equal(planner.hotelFromPrice(hotel), 2500);
        assert.equal(planner.hotelFromPrice(planner.catalog.hotels[1]), null);
    });
});

test('pending picks at scheduled or shared coordinates stay reachable without overlapping pins', async () => {
    await withPreviewMap(async ({ planner, hotel, activity, markers }) => {
        const other = { ...activity, id: 11, name: 'Island tour' };
        planner.catalog.activities.push(other);
        planner.selections = [{ id: 21, item_type: 'activity', item_id: 7 }, { id: 22, item_type: 'activity', item_id: 11 }];
        const pending = (plan_item_id, name, lat, lng) => ({ plan_item_id, type: 'activity', name, lat, lng, reason: 'No time slot.' });
        planner.itinerary = { snapshot: { days: [{ day: 1, hotel, events: [{ name: 'Check in' }], stops: [], legs: [] }],
            unscheduled: [pending(21, activity.name, hotel.lat, hotel.lng), pending(22, other.name, hotel.lat, hotel.lng)] } };
        planner.setSection('itinerary');

        assert.equal(markers.length, 1);
        markers[0].click();
        assert.deepEqual(planner.previewPlaces.map(place => planner.placeKey(place)), ['hotel:7', 'activity:7', 'activity:11']);
        assert.match(markers[0].options.icon.html, /\?/);

        planner.itinerary.snapshot.days = [];
        planner.setSection('itinerary');
        assert.equal(markers.length, 1);
        markers[0].click();
        assert.deepEqual(planner.previewPlaces.map(place => planner.placeKey(place)), ['activity:7', 'activity:11']);
    });
});

test('switching saved trips clears preview and commits the matching catalog together', async () => {
    await withPreviewMap(async ({ planner, hotel, activity }) => {
        const oldPlan = { id: 1, destination_id: 1, revision: 1 };
        const oldCatalog = planner.catalog;
        planner.plan = oldPlan; planner.openMapPreview([activity]);
        planner.api = async path => {
            if (path === '/plans/2') return { data: { id: 2, destination_id: 2, items: [], revision: 1 } };
            throw new Error('Catalog unavailable.');
        };

        await planner.openPlan(2);

        assert.equal(planner.plan, oldPlan);
        assert.equal(planner.catalog, oldCatalog);
        assert.equal(planner.destinationId, 1);
        assert.deepEqual(planner.previewPlaces, []);
        assert.equal(planner.error, 'Catalog unavailable.');

        planner.api = async path => path === '/plans/2'
            ? { data: { id: 2, destination_id: 2, title: 'Second trip', start_date: '2026-10-15', end_date: '2026-10-18', pax: 2, mode: 'walking', revision: 1,
                items: [{ item_type: 'hotel', item_id: 7 }] }, itinerary: null }
            : { data: { hotels: [hotel], activities: [] } };
        await planner.openPlan(2);

        assert.equal(planner.plan.id, 2);
        assert.equal(planner.destinationId, 2);
        assert.deepEqual(planner.catalog.activities, []);
        assert.equal(planner.activeHotelId, 7);
        assert.equal(planner.error, '');
        planner.openMapPreview([hotel]); planner.destroy();
        assert.deepEqual(planner.previewPlaces, []);
        assert.equal(planner.itineraryMapContext, false);
    });
});

test('new activity choices start at one and the counter can decrease then increase within its limit', async () => {
    await withPreviewMap(async ({ planner, activity }) => {
        planner.plan = { id: 1, pax: 6 }; planner.pax = 6;
        planner.edit(activity);
        assert.equal(planner.optionPax, 1);
        planner.adjustActivityPax(1); assert.equal(planner.optionPax, 2);
        planner.adjustActivityPax(-1); assert.equal(planner.optionPax, 1);
        planner.adjustActivityPax(1); assert.equal(planner.optionPax, 2);
        activity.max_pax = 1; planner.edit(activity);
        planner.adjustActivityPax(1); assert.equal(planner.optionPax, 1);
        planner.adjustActivityPax(-1); assert.equal(planner.optionPax, 1);
        activity.max_pax = 30; assert.equal(planner.activityLimit(activity), 20);
        for (const max_pax of [null, 0, -1, 'invalid']) assert.equal(planner.activityLimit({ max_pax }), 20);
    });
});

test('saved activity counts above a lowered capacity remain visible until explicitly corrected', async () => {
    await withPreviewMap(async ({ planner, activity, requests }) => {
        planner.plan = { id: 1, revision: 3 };
        planner.selections = [{ item_type: 'activity', item_id: 7, options: { pax: 3, date: '2026-10-17' } }];
        activity.max_pax = 1; planner.edit(activity);
        assert.equal(planner.optionPax, 3);
        planner.adjustActivityPax(1); assert.equal(planner.optionPax, 3);
        await planner.saveChoice();
        assert.match(planner.error, /between 1 and 1/);
        assert.equal(requests.length, 0);
        planner.adjustActivityPax(-1); planner.adjustActivityPax(-1);
        assert.equal(planner.optionPax, 1);
        assert.equal(planner.optionDate, '2026-10-17');
        assert.equal(planner.selections[0].options.pax, 3);
    });
});

test('closing hotel previews restores the overview without refitting or changing saved choices', async () => {
    await withPreviewMap(async ({ planner, hotel, markers, map, requests }) => {
        planner.plan = { id: 1, revision: 4 };
        const choices = [{ item_type: 'hotel', item_id: 7, options: { pax: 2 } }]; planner.selections = choices;
        planner.catalog.hotels.push({ ...hotel, id: 12, lat: null, lng: null });
        const fits = map.fits;
        markers[0].click(); planner.search = 'Ocean Room';
        planner.dismissMapPreview();
        assert.equal(planner.tab, 'hotels'); assert.equal(planner.search, '');
        assert.deepEqual(markers.map(marker => marker.coordinates), [[11.96, 121.92], [11.98, 121.94]]);
        markers[1].click(); assert.equal(planner.activeHotelId, 9);
        planner.dismissMapPreview(); planner.renderMap();
        assert.equal(markers.length, 2); assert.equal(map.fits, fits);
        assert.deepEqual(map.flights, []); assert.equal(requests.length, 0);
        assert.equal(planner.selections, choices); assert.equal(planner.plan.revision, 4);
        assert.equal(planner.$refs.map.sunnyTripsMap, map);
    });
});

test('hotel dismissal leaves the itinerary saved and returning to its section restores routes', async () => {
    await withPreviewMap(async ({ planner, hotel, markers, routes, map }) => {
        const itinerary = { snapshot: { days: [{ day: 1, hotel, events: [{ name: 'Check in' }], stops: [],
            legs: [{ geometry: { type: 'LineString', coordinates: [[121.92, 11.96], [121.93, 11.97]] } }] }] } };
        planner.itinerary = itinerary; planner.setSection('itinerary'); markers[0].click();
        const fits = map.fits, flights = map.flights.length;
        planner.dismissMapPreview();
        assert.equal(planner.tab, 'hotels'); assert.equal(markers.length, 2);
        assert.equal(planner.itinerary, itinerary); assert.equal(planner.itineraryMapContext, false);
        assert.equal(routes.length, 0); assert.equal(map.fits, fits); assert.equal(map.flights.length, flights);
        planner.setSection('itinerary'); assert.equal(routes.length, 1);
    });
});

test('closing an activity or unselected shared pin does not force the Hotels section', async () => {
    await withPreviewMap(async ({ planner, activity, markers, map }) => {
        planner.setSection('activities'); markers[0].click(); map.handlers.click();
        assert.equal(planner.tab, 'activities'); assert.deepEqual(planner.previewPlaces, []);
        planner.catalog.activities.push({ ...activity, id: 15 }); planner.renderMap(); markers[0].click();
        assert.equal(planner.previewKey, ''); planner.dismissMapPreview();
        assert.equal(planner.tab, 'activities'); assert.equal(markers.length, 1);
    });
});

test('closing a hotel card during its save keeps the overview after the save completes', async () => {
    await withPreviewMap(async ({ planner, hotel, markers, map }) => {
        planner.plan = { id: 1, revision: 1 };
        let complete; planner.api = () => new Promise(resolve => { complete = resolve; });
        markers[0].click(); const fits = map.fits;
        const saving = planner.addMapChoice(); planner.dismissMapPreview();
        complete({ items: [{ item_type: 'hotel', item_id: 7 }], revision: 2 }); await saving;
        assert.equal(planner.selected(hotel), true); assert.equal(planner.plan.revision, 2);
        assert.equal(planner.tab, 'hotels'); assert.equal(markers.length, 2);
        assert.deepEqual(planner.previewPlaces, []); assert.equal(map.fits, fits);
    });
});

test('image failures belong to their selected listing and do not hide another pin photo', async () => {
    await withPreviewMap(async ({ planner, hotel, activity }) => {
        hotel.image = '/storage/hotel.jpg'; activity.image = '/storage/activity.jpg';
        planner.openMapPreview([hotel]); const oldImage = planner.previewImages()[0];
        planner.markPreviewImageFailed(oldImage); assert.equal(planner.previewImageFailed, true);
        planner.openMapPreview([activity]); planner.markPreviewImageFailed(oldImage);
        assert.equal(planner.previewImageFailed, false);
        assert.deepEqual(planner.previewImages(), [{ url: '/storage/activity.jpg', key: 'activity:7' }]);
        planner.markPreviewImageFailed(planner.previewImages()[0]); assert.equal(planner.previewImageFailed, true);
    });
});

test('planner arrival dates follow Manila midnight and calendar boundaries rather than browser timezone', () => {
    const planner = tripPlanner({ destinations: [], plans: [], emailPreferences: {}, datePolicy: { timeZone: 'Asia/Manila', leadDays: 2, serverNow: '2026-10-14T10:00:00+08:00' } });
    for (const [instant, minimum] of [
        ['2026-10-14T15:59:59Z', '2026-10-16'], ['2026-10-14T16:00:00Z', '2026-10-17'],
        ['2026-12-31T16:00:00Z', '2027-01-03'], ['2028-02-27T02:00:00Z', '2028-02-29'],
    ]) {
        planner.refreshMinimumArrival(Date.parse(instant));
        assert.equal(planner.minimumArrival, minimum);
    }
    planner.refreshMinimumArrival(Date.parse('2026-10-14T15:59:59Z'));
    assert.equal(planner.arrivalTooSoon('2026-10-15'), true);
    assert.equal(planner.arrivalTooSoon('2026-10-16'), false);
});

test('a page left open past Manila midnight rejects its stale arrival without changing departure or submitting', async () => {
    const originalNow = Date.now;
    let clientClock = Date.parse('2026-10-14T15:59:59Z');
    Date.now = () => clientClock;
    try {
        const planner = tripPlanner({ destinations: [], plans: [], emailPreferences: {}, datePolicy: { timeZone: 'Asia/Manila', leadDays: 2, serverNow: '2026-10-14T15:59:59Z' } });
        planner.startDate = '2026-10-16'; planner.endDate = '2026-10-19';
        planner.api = async () => { throw new Error('A stale arrival must not be submitted'); };
        planner.refreshMinimumArrival(); assert.equal(planner.minimumArrival, '2026-10-16');
        clientClock += 1000;

        await planner.create();

        assert.equal(planner.minimumArrival, '2026-10-17');
        assert.equal(planner.startDate, '2026-10-16'); assert.equal(planner.endDate, '2026-10-19');
        assert.match(planner.error, /earliest arrival is 2026-10-17/);
        assert.equal(planner.plan, null);
        planner.plan = { id: 1 }; planner.itinerary = { id: 1 };
        await planner.transfer(); assert.match(planner.error, /2026-10-17/);
    } finally { Date.now = originalNow; }
});

test('date refresh runs on focus, visibility, restore and interval then is removed on teardown', async () => {
    const originalNow = Date.now;
    let clientClock = Date.parse('2026-10-14T15:59:59Z'); Date.now = () => clientClock;
    try {
        await withPreviewMap(async ({ planner, lifecycle }) => {
            planner.$root = { querySelector: () => null };
            assert.equal(planner.minimumArrival, '2026-10-16');
            clientClock += 1000;
            for (const refresh of [lifecycle.windowEvents.get('focus'), lifecycle.documentEvents.get('visibilitychange'), lifecycle.windowEvents.get('pageshow'), lifecycle.timers[0]]) {
                planner.minimumArrival = '2026-10-16'; refresh(); assert.equal(planner.minimumArrival, '2026-10-17');
            }
            planner.destroy();
            assert.deepEqual(lifecycle.cleared, [1]);
            assert.equal(lifecycle.windowEvents.has('focus'), false); assert.equal(lifecycle.documentEvents.size, 0);
        }, { timeZone: 'Asia/Manila', leadDays: 2, serverNow: '2026-10-14T15:59:59Z', minimumArrival: '2026-10-16' });
    } finally { Date.now = originalNow; }
});

test('reactive planner keeps Leaflet instances raw through destination marker clicks', async () => {
    const originalGlobals = new Map(['window', 'document', 'L', 'ResizeObserver', 'requestAnimationFrame', 'cancelAnimationFrame'].map(key => [key, globalThis[key]]));
    const proxies = new WeakMap();
    const reactive = (value) => {
        if (!value || typeof value !== 'object') return value;
        if (!proxies.has(value)) proxies.set(value, new Proxy(value, { get: (target, key) => reactive(Reflect.get(target, key)) }));
        return proxies.get(value);
    };
    const container = { isConnected: true };
    let currentMap;
    let currentLayers;
    let fitCount = 0;
    const markers = [];
    const routes = [];
    const leaflet = {
        map() {
            currentMap = {
                setView() { assert.equal(this, currentMap); return this; },
                stop() { assert.equal(this, currentMap); },
                invalidateSize() { assert.equal(this, currentMap); },
                fitBounds() { assert.equal(this, currentMap); fitCount++; },
                remove() { assert.equal(this, currentMap); },
            };
            return currentMap;
        },
        tileLayer() {
            return { on() { return this; }, addTo(map) { assert.equal(map, currentMap); } };
        },
        featureGroup() {
            currentLayers = {
                addTo(map) { assert.equal(this, currentLayers); assert.equal(map, currentMap); return this; },
                clearLayers() { assert.equal(this, currentLayers); markers.length = 0; routes.length = 0; },
                getLayers() { assert.equal(this, currentLayers); return markers; },
                getBounds() { assert.equal(this, currentLayers); return markers.map(marker => marker.coordinates); },
            };
            return currentLayers;
        },
        divIcon(options) { return options; },
        geoJSON(geometry, options) {
            const line = {
                geometry, options,
                addTo(group) { assert.equal(group, currentLayers); routes.push(this); return this; },
                bindTooltip(content) { this.tooltip = content; return this; },
                on(event, callback) { if (event === 'click') this.click = callback; return this; },
            };
            return line;
        },
        marker(coordinates, options) {
            const marker = {
                coordinates, options,
                addTo(group) { assert.equal(group, currentLayers); markers.push(this); return this; },
                bindTooltip() { return this; },
                on(event, callback) { if (event === 'click') this.click = callback; return this; },
            };
            return marker;
        },
    };

    try {
        globalThis.L = leaflet;
        globalThis.window = { L: leaflet, addEventListener() {}, removeEventListener() {} };
        globalThis.document = { createElement: () => ({ textContent: '', style: {}, children: [], append(...nodes) { this.children.push(...nodes); } }) };
        globalThis.ResizeObserver = class { observe() {} disconnect() {} };
        globalThis.requestAnimationFrame = () => 1;
        globalThis.cancelAnimationFrame = () => {};
        const planner = reactive(tripPlanner({
            destinations: [{ id: 3, name: 'Boracay', latitude: 11.9686, longitude: 121.923 }],
            plans: [], destination: 0, emailPreferences: {},
        }));
        planner.$refs = { map: container };
        planner.loadWeather = async () => {};
        planner.api = async () => ({ data: { hotels: [
            { type: 'hotel', id: 1, name: 'Station 3 hotel', lat: 11.9509299, lng: 121.9257116, rooms: [] },
            { type: 'hotel', id: 2, name: 'Station 1 hotel', lat: 11.9699, lng: 121.9228, rooms: [] },
            { type: 'hotel', id: 3, name: 'Unlocated hotel', lat: null, lng: null, rooms: [] },
        ], activities: [] } });

        await planner.init();
        assert.equal(markers.length, 1);
        await markers[0].click();
        assert.equal(planner.error, '');
        assert.equal(planner.destinationId, 3);
        assert.deepEqual(markers.map(marker => marker.coordinates), [[11.9509299, 121.9257116], [11.9699, 121.9228]]);
        const fitted = fitCount;
        planner.renderMap();
        assert.equal(fitCount, fitted, 'unchanged listings must preserve the user viewport');
        planner.itinerary = { snapshot: { days: itineraryDays() } };
        planner.tab = 'itinerary';
        planner.renderMap();
        assert.equal(markers.length, 5, 'both days of activities and the shared hotel events must be plotted');
        assert.deepEqual(markers.map(marker => marker.options.icon.html.match(/D\d+·\d+/g)), [
            ['D1·1', 'D4·1'], ['D1·2'], ['D1·3'], ['D3·1'], ['D3·2'],
        ]);
        assert.equal(routes.length, 2, 'routes from both scheduled days must be drawn');
        assert.equal(routes[0].options.style.opacity, 0.9);
        assert.equal(routes[1].options.style.opacity, 0.3);
        assert.notEqual(routes[0].options.style.color, routes[1].options.style.color, 'each day needs its own colour');
        await planner.selectDay(2);
        assert.equal(markers.length, 5, 'selecting a day must not hide the other days');
        assert.equal(routes[1].options.style.opacity, 0.9);
        planner.destroy();
    } finally {
        for (const [key, value] of originalGlobals) {
            if (value === undefined) delete globalThis[key];
            else globalThis[key] = value;
        }
    }
});

function itineraryDays() {
    const hotel = { name: 'Hotel', lat: 11.96, lng: 121.92 };
    const leg = { geometry: { type: 'LineString', coordinates: [[121.92, 11.96], [121.93, 11.97]] } };
    return [
        { day: 1, hotel, events: [{ name: 'Check in', departure: false }], stops: [
            { name: 'Banana Boat', lat: 11.961, lng: 121.921 },
            { name: 'Crystal Kayak', lat: 11.962, lng: 121.922 },
        ], legs: [leg] },
        { day: 2, hotel, events: [], stops: [], legs: [] },
        { day: 3, hotel, events: [], stops: [
            { name: 'Jet Ski', lat: 11.963, lng: 121.923 },
            { name: 'ATV', lat: 11.964, lng: 121.924 },
        ], legs: [leg] },
        { day: 4, hotel, events: [{ name: 'Check out', departure: true }], stops: [], legs: [] },
    ];
}

test('itinerary markers retain list numbers when a stop has no coordinates', () => {
    const planner = tripPlanner({ destinations: [], plans: [], emailPreferences: {} });
    const days = itineraryDays();
    days[0].stops[0].lat = null;
    planner.itinerary = { snapshot: { days } };
    const markers = planner.itineraryMarkers();
    assert.ok(!markers.some(marker => marker.labels.includes('D1·2')));
    assert.ok(markers.some(marker => marker.labels.includes('D1·3') && marker.name.includes('Crystal Kayak')));
    assert.deepEqual(markers[0].labels, ['D1·1', 'D4·1']);
    assert.deepEqual([markers[0].lat, markers[0].lng], [days[0].hotel.lat, days[0].hotel.lng]);
});

test('planner basket transfer opens the shared success modal and reports an already added trip', async () => {
    const originalWindow = globalThis.window;
    const events = [];
    globalThis.window = { dispatchEvent: event => events.push(event) };
    try {
        const planner = tripPlanner({ destinations: [], plans: [], emailPreferences: {} });
        planner.plan = { id: 1 };
        planner.itinerary = { id: 2 };
        planner.title = 'Trip to Boracay';
        planner.api = async () => ({ data: { status: 'added', count: 3 } });
        await planner.transfer();
        assert.ok(events.some(event => event.type === 'cart-updated'));
        const modal = events.find(event => event.type === 'show-cart-modal');
        assert.equal(modal.detail.alreadyInCart, false);
        assert.match(modal.detail.message, /Trip to Boracay.*3 item.*Trip Basket/);
        events.length = 0;
        planner.api = async () => ({ data: { status: 'already_transferred' } });
        await planner.transfer();
        assert.equal(events.find(event => event.type === 'show-cart-modal').detail.alreadyInCart, true);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('weather widget ignores an older destination response and clears on deselection', async () => {
    const originalFetch = globalThis.fetch;
    const requests = [];
    globalThis.fetch = () => new Promise(resolve => requests.push(resolve));
    try {
        const planner = tripPlanner({ destinations: [], plans: [], emailPreferences: {}, base: '/trip-planner' });
        const first = planner.loadWeather(3);
        const second = planner.loadWeather(4);
        requests[1]({ ok: true, text: async () => 'El Nido forecast' });
        await second;
        requests[0]({ ok: true, text: async () => 'Boracay forecast' });
        await first;
        assert.equal(planner.weatherHtml, 'El Nido forecast');
        assert.equal(planner.weatherLoading, false);
        await planner.loadWeather(0);
        assert.equal(planner.weatherHtml, '');
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('weather widget fails gracefully without blocking the trip planner', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => { throw new Error('Network unavailable'); };
    try {
        const planner = tripPlanner({ destinations: [], plans: [], emailPreferences: {}, base: '/trip-planner' });
        await planner.loadWeather(3);
        assert.equal(planner.weatherHtml, '');
        assert.equal(planner.weatherLoading, false);
        assert.match(planner.weatherError, /Forecast unavailable/);
        assert.equal(planner.error, '');
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('every scheduled stop gets a visible line and unplaced picks stay on the map', async () => {
    const originalGlobals = new Map(['window', 'document', 'L', 'ResizeObserver', 'requestAnimationFrame', 'cancelAnimationFrame'].map(key => [key, globalThis[key]]));
    const container = { isConnected: true };
    const markers = [];
    const routes = [];
    let currentMap;
    let currentLayers;
    const leaflet = {
        map() { currentMap = { setView() { return this; }, stop() {}, invalidateSize() {}, fitBounds() {}, remove() {} }; return currentMap; },
        tileLayer() { return { on() { return this; }, addTo() {} }; },
        featureGroup() {
            currentLayers = {
                addTo() { return this; },
                clearLayers() { markers.length = 0; routes.length = 0; },
                getLayers: () => markers,
                getBounds: () => markers.map(marker => marker.coordinates),
            };
            return currentLayers;
        },
        divIcon(options) { return options; },
        geoJSON(geometry, options) {
            return {
                geometry, options,
                addTo(group) { assert.equal(group, currentLayers); routes.push(this); return this; },
                bindTooltip(content) { this.tooltip = content; return this; },
                on(event, callback) { if (event === 'click') this.click = callback; return this; },
            };
        },
        marker(coordinates, options) {
            return {
                coordinates, options,
                addTo(group) { assert.equal(group, currentLayers); markers.push(this); return this; },
                bindTooltip(content) { this.tooltip = content; return this; },
                on() { return this; },
            };
        },
    };

    try {
        globalThis.L = leaflet;
        globalThis.window = { L: leaflet, addEventListener() {}, removeEventListener() {} };
        globalThis.document = { createElement: () => ({ textContent: '', style: {}, children: [], append(...nodes) { this.children.push(...nodes); } }) };
        globalThis.ResizeObserver = class { observe() {} disconnect() {} };
        globalThis.requestAnimationFrame = callback => { callback(); return 1; };
        globalThis.cancelAnimationFrame = () => {};
        const planner = tripPlanner({ destinations: [], plans: [], destination: 0, emailPreferences: {} });
        planner.$refs = { map: container };
        await planner.init();

        const hotel = { name: 'Hotel', lat: 11.96, lng: 121.92 };
        planner.destinationId = 2;
        planner.tab = 'itinerary';
        planner.itinerary = { snapshot: {
            days: [{ day: 1, hotel, events: [{ name: 'Check in at Hotel', departure: false }], stops: [{ name: 'Taraw Cliff', lat: 11.9612, lng: 121.9215 }], legs: [
                { from: 'Hotel', to: 'Taraw Cliff', mode: 'walking', distance_km: 2.4, duration_minutes: 31,
                    geometry_kind: 'road_with_connector',
                    geometry: { type: 'LineString', coordinates: [[121.918, 11.955], [121.9215, 11.9612]] },
                    connectors: [{ type: 'LineString', coordinates: [[121.92, 11.96], [121.918, 11.955]] }] },
                { from: 'Taraw Cliff', to: 'Hotel', mode: 'walking', distance_km: null, duration_minutes: null,
                    geometry_kind: 'straight_line', return_to_hotel: true, connectors: [],
                    geometry: { type: 'LineString', coordinates: [[121.9215, 11.9612], [121.92, 11.96]] } },
            ] }],
            unscheduled: [
                { plan_item_id: 9, name: 'Island Hopping A', lat: 11.18, lng: 119.39, reason: 'No mapped route.' },
                { plan_item_id: 10, name: 'Unlocated tour', lat: null, lng: null, reason: 'No coordinates.' },
            ],
        } };
        planner.renderMap();

        // Both legs, the connector stub and the line back to the unplaced pick.
        assert.equal(routes.length, 4);
        assert.equal(routes[0].options.style.dashArray, null, 'a mapped road leg stays solid');
        assert.equal(routes[1].options.style.dashArray, '1 6', 'the connector stub is dotted');
        assert.equal(routes[1].options.style.color, routes[0].options.style.color, 'a connector keeps its day colour');
        assert.equal(routes[2].options.style.dashArray, '3 9', 'a straight-line estimate is dashed');
        assert.deepEqual(routes[3].geometry.coordinates, [[121.92, 11.96], [119.39, 11.18]]);
        assert.ok(routes.every(route => route.tooltip || route.options.style.dashArray), 'every drawn line is explained or marked approximate');
        assert.equal(typeof routes[0].click, 'function', 'clicking a route selects its day');

        // The hotel check-in, the stop and the pick that could not be placed.
        assert.equal(markers.length, 3);
        const pending = markers[2];
        assert.deepEqual(pending.coordinates, [11.18, 119.39]);
        assert.match(pending.options.icon.html, /planner-map-pending/);
        assert.match(planner.routeNotice, /1 of 2 route lines are straight-line estimates/);
        assert.match(planner.routeNotice, /1 pick\(s\) could not be placed/);

        planner.tab = 'hotels';
        planner.renderMap();
        assert.equal(planner.routeNotice, '', 'the notice clears when the route is not on screen');
        planner.destroy();
    } finally {
        for (const [key, value] of originalGlobals) {
            if (value === undefined) delete globalThis[key];
            else globalThis[key] = value;
        }
    }
});
