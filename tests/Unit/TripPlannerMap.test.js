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
            return { addTo(group) { assert.equal(group, currentLayers); routes.push({ geometry, options }); } };
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
        globalThis.document = { createElement: () => ({ textContent: '' }) };
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
        assert.equal(routes[0].options.style.opacity, 0.85);
        assert.equal(routes[1].options.style.opacity, 0.35);
        await planner.selectDay(2);
        assert.equal(markers.length, 5, 'selecting a day must not hide the other days');
        assert.equal(routes[1].options.style.opacity, 0.85);
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
