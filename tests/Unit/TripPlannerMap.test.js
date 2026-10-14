import assert from 'node:assert/strict';
import { test } from 'node:test';
import tripPlanner from '../../resources/js/trip-planner.js';

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
