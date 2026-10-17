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
