import assert from 'node:assert/strict';
import test from 'node:test';
import { durationLabel, tripDays, tripEndDate } from '../../resources/js/trip-duration.js';
import { displayChatMessages } from '../../resources/js/chat-sections.js';

test('trip dates use calendar-day arithmetic across month, year and leap boundaries', () => {
    assert.equal(tripEndDate('2026-10-26', 6), '2026-10-31');
    assert.equal(tripEndDate('2026-12-30', 4), '2027-01-02');
    assert.equal(tripEndDate('2028-02-27', 4), '2028-03-01');
    assert.equal(tripDays('2026-10-26', '2026-10-31'), 6);
    assert.equal(durationLabel(6), '6 days · 5 nights');
});

test('invalid dates and durations produce no derived value', () => {
    assert.equal(tripEndDate('2026-02-29', 3), '');
    assert.equal(tripEndDate('2026-10-26', 0), '');
    assert.equal(tripEndDate('2026-10-26', 2.5), '');
    assert.equal(tripDays('2026-10-26', 'soon'), null);
    assert.equal(durationLabel(''), '');
});

test('a draft itinerary section is shown once, without duplicate room and activity cards', () => {
    const room = { id: 3, room_name: 'Garden Room' };
    const activity = { id: 4, activity_name: 'Island Tour' };
    const suggestion = { id: 'draft-1', days: [] };
    const [draft, search] = displayChatMessages([{ id: 9, sender: 'bot', text: 'Reply', sections: [
        { id: 'task-1', heading: 'Suggested itinerary', answer: 'Draft', data: { retrieved_rooms: [room], retrieved_activities: [activity], itinerary_suggestion: suggestion } },
        { id: 'task-2', heading: 'Things to do', answer: 'Activities', data: { retrieved_activities: [activity] } },
    ] }]);
    assert.equal(draft.itinerary_suggestion, suggestion);
    assert.equal(draft.rooms, null);
    assert.equal(draft.activities, null);
    assert.deepEqual(search.activities, [activity]);
});
