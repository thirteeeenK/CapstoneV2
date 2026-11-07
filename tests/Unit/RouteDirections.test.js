import assert from 'node:assert/strict';
import { test } from 'node:test';
import { directionRows, revealMap, stepDistanceLabel } from '../../resources/js/route-directions.js';

const route = {
    geometry: { type: 'LineString', coordinates: [[121.9257, 11.9564], [121.927, 11.96], [121.9255, 11.9658]] },
    steps: [
        { instruction: 'Head northeast on Sunset Road', street: 'Sunset Road', maneuver: 'depart', distance_m: 98.4, way_points: [0, 1], location: [121.9257, 11.9564] },
        { instruction: 'Turn right', street: null, maneuver: 'right', distance_m: 1250, way_points: [1, 2], location: [121.927, 11.96] },
        { instruction: 'Merge ahead', street: 'Main Road', maneuver: 'something_new', distance_m: 1000, way_points: [2, 2], location: [121.9255, 11.9658] },
        { instruction: 'Arrive at your destination', street: null, maneuver: 'arrive', distance_m: 0, way_points: [2, 2], location: [121.9255, 11.9658] },
    ],
};

test('direction rows carry icons, labels and Leaflet-ordered geometry for each step', () => {
    const rows = directionRows(route);
    assert.deepEqual(rows.map(row => row.icon), ['near_me', 'turn_right', 'straight', 'flag']);
    assert.deepEqual(rows.map(row => row.distance_label), ['98 m', '1.3 km', '1 km', '']);
    assert.deepEqual(rows.map(row => row.unnamed), [false, true, false, false], 'arrival is never flagged as an unnamed path');
    assert.deepEqual(rows[1].location, [11.96, 121.927]);
    assert.deepEqual(rows[1].segment, [[11.96, 121.927], [11.9658, 121.9255]]);
});

test('routes and saved legs without written steps produce no rows', () => {
    assert.deepEqual(directionRows(null), []);
    assert.deepEqual(directionRows({ geometry: route.geometry }), [], 'an itinerary saved before steps existed');
    assert.deepEqual(directionRows({ geometry: route.geometry, steps: [] }), []);
});

test('step distances read as metres below a kilometre and hide when empty', () => {
    assert.equal(stepDistanceLabel(0.4), '1 m');
    assert.equal(stepDistanceLabel(999.4), '999 m');
    assert.equal(stepDistanceLabel(999.6), '1 km');
    assert.equal(stepDistanceLabel(1049), '1 km');
    assert.equal(stepDistanceLabel(null), '');
    assert.equal(stepDistanceLabel(0), '');
});

test('the map scrolls into view only when it is off screen', () => {
    const originals = { innerHeight: globalThis.innerHeight, matchMedia: globalThis.matchMedia };
    try {
        globalThis.innerHeight = 800;
        globalThis.matchMedia = () => ({ matches: true });
        const scrolls = [];
        const element = top => ({ getBoundingClientRect: () => ({ top, bottom: top + 300 }), scrollIntoView: options => scrolls.push(options) });
        revealMap(element(100));
        assert.equal(scrolls.length, 0);
        revealMap(element(-900));
        revealMap(element(1200));
        assert.deepEqual(scrolls, [{ block: 'center', behavior: 'auto' }, { block: 'center', behavior: 'auto' }]);
        revealMap(null);
    } finally {
        globalThis.innerHeight = originals.innerHeight;
        globalThis.matchMedia = originals.matchMedia;
    }
});
