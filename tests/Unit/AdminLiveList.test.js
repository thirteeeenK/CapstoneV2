import assert from 'node:assert/strict';
import { test } from 'node:test';
import adminLiveList from '../../resources/js/admin-live-list.js';

async function withLiveList(config, action) {
    const originals = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch };
    const requests = [];
    const results = { outerHTML: 'Original listings', contains: link => link.insideResults };
    globalThis.window = { location: { href: 'http://localhost/admin/activities' } };
    globalThis.document = { getElementById: id => (id === (config.resultsId || 'listings-results') ? results : null) };
    globalThis.fetch = (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
    const list = adminLiveList({ base: 'http://localhost/admin/activities', ...config });
    const response = html => ({ ok: true, redirected: false, text: async () => html });
    try { await action({ list, requests, results, response }); }
    finally {
        list.destroy();
        for (const [key, value] of Object.entries(originals)) {
            if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
        }
    }
}

const activityFilters = {
    filters: { search: '', destinationId: '', tab: '' },
    params: { tab: 'tab', search: 'search', destinationId: 'destination_id' },
    defaults: { tab: 'hotels' },
};

test('filters use each page\'s query keys, keep defaults and reset to them', async () => {
    await withLiveList(activityFilters, async ({ list, requests, response }) => {
        list.search = 'Kayak';
        const change = list.setDestinationId('3');
        const url = new URL(requests[0].url);
        assert.equal(url.searchParams.get('destination_id'), '3');
        assert.equal(url.searchParams.get('search'), 'Kayak');
        assert.equal(url.searchParams.get('tab'), 'hotels');
        requests[0].resolve(response('Filtered')); await change;

        const reset = list.resetFilters();
        assert.equal(list.search, ''); assert.equal(list.destinationId, '');
        assert.equal(requests[1].url, 'http://localhost/admin/activities?tab=hotels');
        requests[1].resolve(response('All')); await reset;
    });
});

test('a late older response cannot overwrite newer results or loading state', async () => {
    await withLiveList(activityFilters, async ({ list, requests, results, response }) => {
        list.destinationId = '1'; const older = list.fetchResults();
        list.destinationId = '2'; const newer = list.fetchResults();
        assert.equal(requests[0].options.signal.aborted, true);

        requests[0].resolve(response('Outdated destination')); await older;
        assert.equal(list.loading, true); assert.equal(results.outerHTML, 'Original listings');

        requests[1].resolve(response('Latest destination')); await newer;
        assert.equal(results.outerHTML, 'Latest destination'); assert.equal(list.loading, false);
    });
});

test('failed and login-redirected responses keep the current list and retry the same request', async () => {
    await withLiveList(activityFilters, async ({ list, requests, results, response }) => {
        list.search = 'Beach';
        const failed = list.fetchResults(); requests[0].resolve({ ok: false, redirected: false }); await failed;
        assert.equal(results.outerHTML, 'Original listings');
        assert.match(list.error, /list below is unchanged/); assert.equal(list.loading, false);

        const redirected = list.retryResults(); requests[1].resolve({ ok: true, redirected: true, text: async () => 'Login page' }); await redirected;
        assert.equal(requests[1].url, requests[0].url);
        assert.equal(results.outerHTML, 'Original listings');

        const retry = list.retryResults(); requests[2].resolve(response('Recovered results')); await retry;
        assert.equal(results.outerHTML, 'Recovered results'); assert.equal(list.error, '');
    });
});

test('pagination links inside the results load in place while other links navigate normally', async () => {
    await withLiveList({ ...activityFilters, resultsId: 'bookings-table' }, async ({ list, requests, response }) => {
        const click = href => {
            const event = { prevented: false, preventDefault() { this.prevented = true; }, target: { closest: () => ({ href, insideResults: !href.includes('outside') }) } };
            return [event, list.handleNavClick(event)];
        };

        const [details] = click('http://localhost/admin/bookings/ST-1');
        const [outside] = click('http://localhost/outside?page=2');
        assert.equal(details.prevented, false); assert.equal(outside.prevented, false); assert.equal(requests.length, 0);

        const [page, loading] = click('http://localhost/admin/bookings?page=2');
        assert.equal(page.prevented, true); assert.equal(requests[0].url, 'http://localhost/admin/bookings?page=2');
        requests[0].resolve(response('Page two')); await loading;
    });
});

test('destroying the page cancels a pending search and ignores in-flight results', async () => {
    await withLiveList(activityFilters, async ({ list, requests, results, response }) => {
        const inFlight = list.fetchResults();
        list.debouncedSearch();
        list.destroy();
        assert.equal(requests[0].options.signal.aborted, true);

        requests[0].resolve(response('Too late')); await inFlight;
        await new Promise(resolve => setTimeout(resolve, 300));
        assert.equal(requests.length, 1); assert.equal(results.outerHTML, 'Original listings');
    });
});
