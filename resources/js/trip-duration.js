// Calendar-day trip arithmetic shared by the chat form and the planner.
// Dates are YYYY-MM-DD calendar days; they are compared in UTC so a traveler's
// browser timezone or a daylight-saving change can never shift a day.

const DAY_MS = 86400000;

function toDay(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
    const [year, month, day] = value.split('-').map(Number);
    const time = Date.UTC(year, month - 1, day);
    const date = new Date(time);
    return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day ? time : null;
}

/** The last day of a trip of `days` days starting on `start` (start + days − 1). */
export function tripEndDate(start, days) {
    const time = toDay(start);
    const count = Number(days);
    if (time === null || !Number.isInteger(count) || count < 1) return '';
    return new Date(time + (count - 1) * DAY_MS).toISOString().slice(0, 10);
}

/** Inclusive calendar days between two dates, or null when either is invalid. */
export function tripDays(start, end) {
    const from = toDay(start);
    const to = toDay(end);
    return from === null || to === null ? null : Math.round((to - from) / DAY_MS) + 1;
}

export function durationLabel(days) {
    const count = Number(days);
    if (!Number.isInteger(count) || count < 1) return '';
    const nights = count - 1;
    return `${count} day${count === 1 ? '' : 's'} · ${nights} night${nights === 1 ? '' : 's'}`;
}
