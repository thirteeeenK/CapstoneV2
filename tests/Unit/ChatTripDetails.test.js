import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

function widget() {
    const blade = readFileSync(new URL('../../resources/views/components/frontend/chat-widget.blade.php', import.meta.url), 'utf8');
    const script = blade.split('<script>')[1].split('</script>')[0]
        .replace(/@json\(\\App\\Http\\Controllers\\ChatbotController::MESSAGE_LIMIT\)/g, '1000')
        .replace(/@json\(auth\(\)->check\(\)\)/g, 'true')
        .replace(/@if \(session\('show_chat_guide'\)\)[\s\S]*?@endif/g, '')
        .replace(/@js\(route\('([^']+)'\)\)/g, (_, route) => JSON.stringify('/' + route))
        .replace(/\{\{ route\('([^']+)'\) \}\}/g, (_, route) => '/' + route);
    return new Function(script + '\nreturn chatWidget();')();
}

function successModal() {
    const blade = readFileSync(new URL('../../resources/views/components/frontend/trip-plan-success-modal.blade.php', import.meta.url), 'utf8');
    const browser = {};
    new Function('window', blade.split('<script>')[1].split('</script>')[0])(browser);
    const modal = browser.tripPlanSuccessModal();
    modal.$nextTick = (callback) => callback();
    return modal;
}

test('closing the success modal returns focus to the saved-plan link when the add button is hidden', () => {
    const focused = [];
    const hiddenLink = { isConnected: true, getClientRects: () => [] };
    const savedLink = { isConnected: true, getClientRects: () => [{}], focus: () => focused.push('saved') };
    const trigger = { isConnected: true, getClientRects: () => [],
        closest: () => ({ querySelectorAll: () => [hiddenLink, savedLink] }) };
    const modal = successModal();
    modal.openModal({ returnFocus: trigger, suggestion: { duration_days: 4 }, url: '/trip-planner?plan=7' });
    modal.closeModal();
    assert.equal(modal.isOpen, false);
    assert.deepEqual(focused, ['saved']);
    assert.equal(modal.returnFocus, null);
});

test('the success modal restores a visible trigger and safely ignores an already closed modal', () => {
    let focused = 0;
    const trigger = { isConnected: true, getClientRects: () => [{}], focus: () => focused++ };
    const modal = successModal();
    modal.openModal({ returnFocus: trigger });
    modal.closeModal();
    modal.closeModal();
    assert.equal(focused, 1);
    assert.equal(modal.isOpen, false);
});

test('closing the success modal tolerates a trigger removed by a conversation refresh', () => {
    const modal = successModal();
    modal.openModal({ returnFocus: { isConnected: false, closest: () => null } });
    assert.doesNotThrow(() => modal.closeModal());
    assert.equal(modal.isOpen, false);
});

test('trip form submission sends only changed fields with scope and revision', async () => {
    const chat = widget();
    let submission;
    chat.send = async (...args) => { submission = args[3]; };
    await chat.submitTripDetails({ id: 'form-1', revision: 3, scope_id: 'trip-1', values: { destination_id: 1, pax: 2, notes: 'Old note' } },
        { destination_id: 1, pax: 4, notes: '' });
    assert.deepEqual(submission, { trip_details: { pax: 4, notes: null }, form_id: 'form-1', form_revision: 3, scope_id: 'trip-1' });
});

test('choosing undecided dates clears dates through the server flag rather than sending contradictory dates', async () => {
    const chat = widget();
    let submission;
    chat.send = async (...args) => { submission = args[3]; };
    await chat.submitTripDetails({ id: 'form-2', revision: 4, scope_id: 'trip-1', values: {} },
        { dates_undecided: true, check_in_date: '2026-11-01', check_out_date: '2026-11-04' });
    assert.deepEqual(submission.trip_details, { dates_undecided: true });
});

test('a guest save posts the draft and reports the added state without a planner link', async () => {
    const originals = { fetch: globalThis.fetch, document: globalThis.document, window: globalThis.window };
    const events = [];
    let body;
    globalThis.fetch = async (_url, options) => { body = JSON.parse(options.body); return { ok: true, status: 201, json: async () => ({ url: null, claimed: false }) }; };
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    globalThis.window = { dispatchEvent: (event) => events.push(event) };
    globalThis.CustomEvent = class { constructor(type, init) { this.type = type; this.detail = init?.detail; } };
    try {
        const chat = widget();
        chat.isAuthed = false;
        chat.sessionToken = 'chat-token';
        const trigger = { id: 'save-button' };
        await chat.saveItineraryPlan({ id: 'suggestion-1' }, trigger);
        // The chat session token travels with the save so the server can find
        // the suggestion in the guest's own conversation.
        assert.equal(body.session_token, 'chat-token');
        assert.equal(chat.savedTripPlans['suggestion-1'], true);
        assert.equal(chat.tripPlanNotices['suggestion-1'], undefined);
        assert.equal(events.length, 1);
        assert.equal(events[0].type, 'show-trip-plan-modal');
        assert.equal(events[0].detail.claimed, false);
        assert.equal(events[0].detail.url, null);
        assert.equal(events[0].detail.returnFocus, trigger);
        assert.equal(chat.savingTripPlan, null);
    } finally { Object.assign(globalThis, originals); }
});

test('exact date forms do not submit a hidden stale day count', async () => {
    const chat = widget();
    let submission;
    chat.send = async (...args) => { submission = args[3]; };
    await chat.submitTripDetails({ id: 'dates', revision: 1, scope_id: 'trip', values: {} },
        { dates_undecided: false, check_in_date: '2026-11-01', check_out_date: '2026-11-04', days: 12 });
    assert.equal(submission.trip_details.days, undefined);
    assert.equal(submission.trip_details.check_out_date, '2026-11-04');
});

test('concurrent save clicks issue one request and retain the saved plan link', async () => {
    const originals = { fetch: globalThis.fetch, document: globalThis.document, sessionStorage: globalThis.sessionStorage, window: globalThis.window };
    let resolveRequest;
    let requests = 0;
    globalThis.fetch = async () => { requests++; return new Promise(resolve => { resolveRequest = resolve; }); };
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    globalThis.sessionStorage = { removeItem() {} };
    globalThis.window = { dispatchEvent() {} };
    globalThis.CustomEvent = class { constructor(type, init) { this.type = type; this.detail = init?.detail; } };
    try {
        const chat = widget();
        const first = chat.saveItineraryPlan({ id: 'suggestion-1' });
        await chat.saveItineraryPlan({ id: 'suggestion-1' });
        assert.equal(requests, 1);
        resolveRequest({ ok: true, status: 201, json: async () => ({ url: '/trip-planner?plan=7', claimed: true }) });
        await first;
        assert.equal(chat.savedTripPlans['suggestion-1'], '/trip-planner?plan=7');
        assert.equal(chat.savingTripPlan, null);
    } finally { Object.assign(globalThis, originals); }
});

test('a failed save exposes the server error and leaves the suggestion available to retry', async () => {
    const originals = { fetch: globalThis.fetch, document: globalThis.document };
    globalThis.fetch = async () => ({ ok: false, json: async () => ({ errors: { suggestion_id: ['Please regenerate this itinerary.'] } }) });
    globalThis.document = { querySelector: () => null };
    try {
        const chat = widget();
        await chat.saveItineraryPlan({ id: 'suggestion-1' });
        assert.equal(chat.savedTripPlans['suggestion-1'], undefined);
        assert.equal(chat.savingTripPlan, null);
        assert.equal(chat.tripPlanNotices['suggestion-1'], 'Please regenerate this itinerary.');
    } finally { Object.assign(globalThis, originals); }
});
