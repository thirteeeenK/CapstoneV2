// Written turn-by-turn directions built from the normalized steps RoutingService
// returns (API routes and saved itinerary legs share the same shape).

// Material Symbols ligature per normalized maneuver.
const maneuverIcons = {
    depart: 'near_me', arrive: 'flag', left: 'turn_left', right: 'turn_right',
    sharp_left: 'turn_sharp_left', sharp_right: 'turn_sharp_right', slight_left: 'turn_slight_left', slight_right: 'turn_slight_right',
    straight: 'straight', uturn: 'u_turn_left', keep_left: 'fork_left', keep_right: 'fork_right',
    roundabout: 'roundabout_right', roundabout_exit: 'roundabout_right',
};

/** "98 m", "1.3 km", or '' when there is no distance to show. */
export function stepDistanceLabel(meters) {
    const value = Number(meters);
    if (!Number.isFinite(value) || value <= 0) return '';
    if (Math.round(value) < 1000) return Math.max(1, Math.round(value)) + ' m';
    return (value / 1000).toFixed(1).replace(/\.0$/, '') + ' km';
}

/**
 * Display rows for a route's written directions. Locations and segments are in
 * Leaflet [lat, lng] order; an empty list means there are no usable steps.
 */
export function directionRows(route) {
    const coordinates = route?.geometry?.coordinates;
    const steps = route?.steps;
    if (!Array.isArray(steps) || !Array.isArray(coordinates)) return [];
    return steps.map(step => ({
        icon: maneuverIcons[step.maneuver] || 'straight',
        instruction: step.instruction,
        distance_label: step.maneuver === 'arrive' ? '' : stepDistanceLabel(step.distance_m),
        unnamed: step.street == null && step.maneuver !== 'arrive',
        location: [step.location[1], step.location[0]],
        segment: coordinates.slice(step.way_points[0], step.way_points[1] + 1).map(([lng, lat]) => [lat, lng]),
    }));
}

/** Bring the map into view when a step is chosen while it is scrolled away (stacked mobile layout). */
export function revealMap(element) {
    if (!element?.getBoundingClientRect || !element.scrollIntoView) return;
    const rect = element.getBoundingClientRect();
    const viewportHeight = globalThis.innerHeight || 0;
    if (rect.top >= 0 && rect.bottom <= viewportHeight) return;
    const reduced = globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    element.scrollIntoView({ block: 'center', behavior: reduced ? 'auto' : 'smooth' });
}
