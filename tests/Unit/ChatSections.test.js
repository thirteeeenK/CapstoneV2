import assert from 'node:assert/strict';
import test from 'node:test';
import { displayChatMessages } from '../../resources/js/chat-sections.js';

test('section rendering preserves category cards, order, notices and one set of actions', () => {
    const hotel = { id: 1, hotel_name: 'Palm Resort' };
    const activity = { id: 1, activity_name: 'Island Tour' };
    const original = [{ id: 7, sender: 'bot', text: 'Combined reply', ai_notice: 'Backup model',
        price_effective_note: 'Prices updated today', suggested_actions: [{ id: 'build-itinerary' }],
        sections: [
            { id: 'task-1', heading: 'Where to stay', answer: 'Hotel answer', constraints: { destination_name: 'Boracay' }, data: { retrieved_hotels: [hotel] } },
            { id: 'task-2', heading: 'Things to do', answer: 'Activity answer', data: { retrieved_activities: [activity] } },
        ],
    }];
    const result = displayChatMessages(original);
    assert.deepEqual(result.map(message => message.id), ['7:task-1', '7:task-2']);
    assert.deepEqual(result[0].hotels, [hotel]);
    assert.equal(result[0].activities, null);
    assert.deepEqual(result[1].activities, [activity]);
    assert.equal(result[1].hotels, null);
    assert.match(result[0].text, /Boracay/);
    assert.equal(result[0].ai_notice, 'Backup model');
    assert.equal(result[1].ai_notice, null);
    assert.deepEqual(result[0].suggested_actions, []);
    assert.deepEqual(result[1].suggested_actions, [{ id: 'build-itinerary' }]);
    assert.match(result[1].text, /Prices updated today/);
    assert.equal(original.length, 1);
});

test('legacy messages and restored JSON sections retain their original behavior', () => {
    const legacy = { id: 1, sender: 'bot', text: 'Hello', hotels: [{ id: 2 }] };
    assert.equal(displayChatMessages([legacy])[0], legacy);
    const restored = JSON.parse(JSON.stringify([{ id: 3, sender: 'bot', text: 'Reply', sections: [
        { id: 'task-1', heading: 'Transfer', answer: 'Available', data: { retrieved_addons: [{ id: 4, name: 'Transfer' }] } },
    ] }]));
    assert.equal(displayChatMessages(restored)[0].addons[0].name, 'Transfer');
});

test('budget previews restore their cards and display a shared question once', () => {
    const restored = JSON.parse(JSON.stringify([{
        id: 8, sender: 'bot', text: 'Reply with question?',
        clarification: { id: 'q-1', text: 'What does the budget cover?' },
        suggested_actions: [{ id: 'skip', label: 'No preference', question_id: 'q-1' }],
        sections: [
            { id: 'task-1', heading: 'Activities', answer: 'Catalog previews.', data: { retrieved_activities: [{ id: 1 }] } },
            { id: 'task-2', heading: 'Hotels', answer: 'Catalog previews.', data: { retrieved_hotels: [{ id: 2 }] } },
        ],
    }]));
    const result = displayChatMessages(restored);
    assert.equal(result[0].activities[0].id, 1);
    assert.equal(result[1].hotels[0].id, 2);
    assert.equal(result.map(message => message.text).join('\n').split('What does the budget cover?').length - 1, 1);
    assert.equal(result[1].suggested_actions[0].stale, false);
    const later = { id: 9, sender: 'bot', text: 'New question', clarification: { id: 'q-2' } };
    assert.equal(displayChatMessages([...restored, later])[1].suggested_actions[0].stale, true);
});

test('the open question and its control survive section splitting, once and only on the last section', () => {
    const control = { type: 'count', field: 'pax', submit_label: 'Use this number', fields: [{ name: 'pax', label: 'Travelers', input: 'number', min: 1, max: 30 }] };
    const restored = JSON.parse(JSON.stringify([{
        id: 11, sender: 'bot', text: 'Reply',
        clarification: { id: 'q-9', revision: 3, text: 'How many travelers?', control },
        sections: [
            { id: 'task-1', heading: 'Hotels', answer: 'Hotel answer', data: {} },
            { id: 'task-2', heading: 'Activities', answer: 'Activity answer', data: {} },
        ],
    }]));
    const result = displayChatMessages(restored);
    assert.equal(result[0].clarification, null);
    assert.equal(result[1].clarification.id, 'q-9');
    assert.equal(result[1].clarification.control.field, 'pax');
    assert.equal(result.map(message => message.text).join('\n').split('How many travelers?').length - 1, 1);
});

test('notices are ordered prices first and the question last', () => {
    const restored = JSON.parse(JSON.stringify([{
        id: 12, sender: 'bot', text: 'Reply',
        price_effective_note: 'Prices updated today',
        dialogue_notice: 'Saved-task limit reached',
        section_footer: 'Footer line',
        clarification: { id: 'q-10', text: 'How many days?' },
        sections: [{ id: 'task-1', heading: 'Hotels', answer: 'Hotel answer', data: {} }],
    }]));
    const text = displayChatMessages(restored)[0].text;
    assert.ok(text.indexOf('Prices updated today') < text.indexOf('Saved-task limit reached'));
    assert.ok(text.indexOf('Saved-task limit reached') < text.indexOf('How many days?'));
    assert.ok(text.indexOf('How many days?') === text.lastIndexOf('How many days?'));
});

test('the current trip summary survives section splitting and replaces older summaries', () => {
    const summary = { items: [{ field: 'pax', value: '2' }] };
    const older = { id: 1, sender: 'bot', text: 'Old reply', trip_summary: summary };
    const current = { id: 2, sender: 'bot', text: 'Reply', trip_summary: summary, sections: [
        { id: 'task-1', heading: 'Hotels', answer: 'Stays', data: {} },
        { id: 'task-2', heading: 'Activities', answer: 'Things to do', data: {} },
    ] };
    const rows = displayChatMessages([older, current]);
    assert.equal(rows[0].trip_summary, null);
    assert.equal(rows[1].trip_summary, null);
    assert.deepEqual(rows[2].trip_summary, summary);
});

test('the consolidated form appears once while each itinerary keeps its own save reference', () => {
    const form = { id: 'form-new', revision: 3 };
    const previous = { id: 1, sender: 'bot', text: 'Previous', trip_details_form: { id: 'form-old' } };
    const current = { id: 2, sender: 'bot', text: 'Reply', trip_details_form: form, sections: [
        { id: 'task-1', heading: 'First trip', answer: 'Draft', data: { itinerary_suggestion: { id: 'suggestion-1' } } },
        { id: 'task-2', heading: 'Second trip', answer: 'Draft', data: { itinerary_suggestion: { id: 'suggestion-2' } } },
    ] };
    const rows = displayChatMessages([previous, current]);
    assert.equal(rows[0].trip_details_form, null);
    assert.equal(rows[1].trip_details_form, null);
    assert.deepEqual(rows[2].trip_details_form, form);
    assert.equal(rows[1].itinerary_suggestion.id, 'suggestion-1');
    assert.equal(rows[2].itinerary_suggestion.id, 'suggestion-2');
});
