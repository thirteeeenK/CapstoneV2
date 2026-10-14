import assert from 'node:assert/strict';
import { test } from 'node:test';
import plannerScheduling from '../../resources/js/admin-planner-scheduling.js';

async function withScheduling(action) {
    const originals = { window: globalThis.window, fetch: globalThis.fetch };
    const requests = [], urls = [];
    const results = { innerHTML: 'Original listings', dirty: false, querySelector() { return this.dirty ? {} : null; }, contains() { return true; } };
    globalThis.window = { confirm: () => true, history: { state: null, replaceState: (_, __, url) => urls.push(url) } };
    globalThis.fetch = (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
    const planner = plannerScheduling({ base: 'http://localhost/admin/planner-inventory', inventory: 'http://localhost/admin/inventory', type: 'activity' });
    planner.$refs = { results }; planner.init();
    const response = html => ({ ok: true, text: async () => html });
    try { await action({ planner, requests, urls, results, response }); }
    finally {
        planner.destroy();
        for (const [key, value] of Object.entries(originals)) {
            if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
        }
    }
}

test('destination and search persist across categories while filter changes reset pagination', async () => {
    await withScheduling(async ({ planner, requests, urls, results, response }) => {
        planner.search = 'Kayak'; planner.destinationId = '3';
        const change = planner.setTab('hotel');
        const url = new URL(requests[0].url);
        assert.equal(url.searchParams.get('destination_id'), '3');
        assert.equal(url.searchParams.get('search'), 'Kayak'); assert.equal(url.searchParams.get('type'), 'hotel');
        assert.equal(url.searchParams.has('page'), false);
        requests[0].resolve(response('Destination hotel results')); await change;
        assert.equal(results.innerHTML, 'Destination hotel results'); assert.equal(urls[0], url.href);
        const inventory = new URL(planner.inventoryUrl());
        assert.equal(inventory.searchParams.get('tab'), 'hotels'); assert.equal(inventory.searchParams.get('destination_id'), '3');
        const clear = planner.resetFilters(); requests[1].resolve(response('All hotels')); await clear;
        assert.equal(planner.tab, 'hotel'); assert.equal(planner.search, ''); assert.equal(planner.destinationId, '');
    });
});

test('a late older filter response cannot overwrite the newest results or loading state', async () => {
    await withScheduling(async ({ planner, requests, results, response }) => {
        planner.destinationId = '1'; const older = planner.fetchResults();
        planner.destinationId = '2'; const newer = planner.fetchResults();
        assert.equal(requests[0].options.signal.aborted, true);
        requests[0].resolve(response('Outdated destination')); await older;
        assert.equal(planner.loading, true); assert.equal(results.innerHTML, 'Original listings');
        requests[1].resolve(response('Latest destination')); await newer;
        assert.equal(results.innerHTML, 'Latest destination'); assert.equal(planner.loading, false);
        assert.equal(planner.appliedFilters.destinationId, '2');
    });
});

test('failed filters restore applied controls and retry the intended request without discarding results', async () => {
    await withScheduling(async ({ planner, requests, results, urls, response }) => {
        planner.destinationId = '4'; planner.search = 'Beach';
        const change = planner.fetchResults(); requests[0].resolve({ ok: false }); await change;
        assert.equal(planner.destinationId, ''); assert.equal(planner.search, '');
        assert.equal(results.innerHTML, 'Original listings'); assert.equal(urls.length, 0);
        assert.match(planner.error, /Previous results are unchanged/);
        const retry = planner.retryResults(); requests[1].resolve(response('Recovered results')); await retry;
        assert.equal(planner.destinationId, '4'); assert.equal(planner.search, 'Beach');
        assert.equal(results.innerHTML, 'Recovered results'); assert.equal(planner.error, '');
    });
});

test('cancelling a discard decision retains unsaved forms and restores the active filters', async () => {
    await withScheduling(async ({ planner, requests, results, response }) => {
        results.dirty = true; window.confirm = () => false;
        planner.destinationId = '9'; await planner.setTab('hotel');
        assert.equal(requests.length, 0); assert.equal(results.innerHTML, 'Original listings');
        assert.equal(planner.tab, 'activity'); assert.equal(planner.destinationId, '');
        window.confirm = () => true; planner.destinationId = '9';
        const change = planner.fetchResults(); requests[0].resolve(response('New results')); await change;
        assert.equal(results.innerHTML, 'New results');
    });
});

test('cancel editor restores persisted values including unchecked lunch and select options', () => {
    const planner = plannerScheduling({});
    const inputs = [
        { type: 'text', value: 'Edited departure', dataset: { savedValue: '09:00' } },
        { type: 'select-one', value: 'indoor', dataset: { savedValue: 'outdoor' } },
        { type: 'checkbox', checked: true, dataset: { savedValue: '0' } },
    ];
    planner.resetEditor({ querySelectorAll: () => inputs });
    assert.equal(inputs[0].value, '09:00'); assert.equal(inputs[1].value, 'outdoor'); assert.equal(inputs[2].checked, false);
});

test('pagination follows its filtered URL and does not intercept other results links', async () => {
    await withScheduling(async ({ planner, requests, response }) => {
        let prevented = false;
        const link = { href: 'http://localhost/admin/planner-inventory?type=activity&destination_id=2&page=2' };
        const paging = planner.handleNavClick({ preventDefault: () => { prevented = true; }, target: { closest: () => link } });
        assert.equal(prevented, true); assert.equal(requests[0].url, link.href);
        assert.equal(requests[0].options.headers['X-Requested-With'], 'XMLHttpRequest');
        requests[0].resolve(response('Page 2')); await paging;
        planner.handleNavClick({ preventDefault: () => { throw new Error('Unrelated link intercepted'); }, target: { closest: () => null } });
    });
});

test('destroy cancels in-flight work and late responses cannot mutate the removed page', async () => {
    await withScheduling(async ({ planner, requests, results, response, urls }) => {
        const pending = planner.fetchResults(); planner.destroy();
        assert.equal(requests[0].options.signal.aborted, true);
        requests[0].resolve(response('Late response')); await pending;
        assert.equal(results.innerHTML, 'Original listings'); assert.equal(urls.length, 0);
    });
});

test('an authentication redirect cannot replace scheduling results with a login page', async () => {
    await withScheduling(async ({ planner, requests, results }) => {
        planner.destinationId = '8'; const pending = planner.fetchResults();
        requests[0].resolve({ ok: true, redirected: true, text: async () => 'Login page' }); await pending;
        assert.equal(results.innerHTML, 'Original listings'); assert.equal(planner.destinationId, ''); assert.match(planner.error, /Please retry/);
    });
});
