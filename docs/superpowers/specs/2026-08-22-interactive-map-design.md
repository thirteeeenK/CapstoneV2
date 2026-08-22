# Interactive Map — Subtle Polished Preview Cards — Design

**Date:** 2026-08-22
**Status:** Approved for implementation
**Scope:** `Dashboard` destination overview map + `/explore` full island explorer map. Hotel-detail `routeMode` map (`hotel/show.blade.php:516`) out of scope.
**Approach:** B — Subtle Polished (recommended). Keep Leaflet 1.9.4 + OSM tiles, no new npm deps, no Mapbox/MarkerCluster plugin.

## Problem

The user-facing interactive map feels boring. Today a marker click in `resources/views/components/frontend/map.blade.php:88` builds a tiny HTML popup (`buildPopupHtml()`) with name/subtitle/rating/distance and a single `View details →` link that navigates away to `/hotels?destination=ID` or `/hotels/{id}`. There is no image, no hover feedback, no price/vibe context, no add-to-basket affordance, and no sync between map and list. `MapService.php:20` `destinationMarkers()` and `:52` `allMarkers()` already compute useful DSS data (hotel/activity counts, weather, distance) but the UI surfaces almost none of it. The `/explore` Alpine store (`explore.blade.php:114` `explorer()`) has a `selected` panel but pin interaction is limited to `bindPopup` + `selected = m` and `flyTo` on watch.

## Goal

Make marker interaction feel rewarding without leaving the map. On click show a rich preview card (image, counts/price, rating, vibe pills, weather) with inline `Preview` / `Add to Trip Basket` actions that stay on the page. Add subtle hover + list↔map sync and smooth motion so the map feels premium, not gimmicky. Keep the OSM free-tile stack and the existing Alpine/Blade architecture.

Success = user can discover, preview, and basket items directly from the map; no mandatory navigation on every click.

## Context

- Stack: Laravel 13 / PHP 8.3, Blade + Tailwind 3 (palette `ocean/sand/ink/coral`, fonts Sora `font-headline` / DM Sans `font-body`) + Alpine.js 3 + Vite, PostgreSQL + pgvector. Leaflet via CDN `unpkg.com/leaflet@1.9.4`.
- Shared map component: `resources/views/components/frontend/map.blade.php` (props `markers`, `center`, `zoom`, `height`, `showDistance`, `routeMode`). Handles OSM tiles, divIcons (`hotelIcon` teal, `activityIcon` orange, `destinationIcon` dark), `buildPopupHtml`, `iconFor`, `deferred` reveal, geolocation `locate()/reveal()`.
- Dashboard wiring: `RecommendationController.php:104` `$mapService->destinationMarkers()` → `dashboard.blade.php:73` `<x-frontend.map>` (zoom 6, `h-80`).
- Explorer wiring: `DssController.php:90` `explore()` → `$map->allMarkers()` + `centerOf()` → `explore.blade.php:130` custom Leaflet `L.map('exploreMap')` + Alpine `explorer()` with `markers`, `selected`, `sortedMarkers()`, `locate()`, `fetchDistances()`.
- Marker contracts: `MapService.php:30` destination `{type:'destination', id, name, lat, lng, hotel_count, activity_count, weather, url}`; `:132` hotel `{type:'hotel', id, name, subtitle, lat, lng, address, rating, review_count, image, url, distance_*}`; `:149` activity `{type:'activity', ... rate, category, no_location}`.
- Image resolution: `App\Concerns\ResolvesImages::resolveImg()` / `resolveActivityImage()` — JSON columns store `storage/` paths; Unsplash fallbacks.
- Existing preview modals: `components/frontend/room-preview-modal` + `activity-preview-modal` via `Preview\RoomPreviewService` / `ActivityPreviewService`, opened with `$store.preview.openRoom/openActivity`.
- Cart: global `window.addToCart(itemType, itemId, options)` in `components/frontend/layout.blade.php:95`.
- Routes: `routes/dssRoute.php:10` — `GET /explore` + `GET /api/dss/markers` (throttle `dss`, not `ai`).

### Constraints discovered

- `.npmrc` sets `ignore-scripts=true` — npm lifecycle scripts never run; don't rely on them.
- No new backend mutation needed — markers are derived read models.
- <200 markers expected per explorer view; pure JS clustering not required for subtle scope.
- `routeMode` hotel-detail map has deferred activities and hotel→activity polylines; must not regress when enhancing the shared component.

## Design

### Change 1 — Enrich MapService payload (no schema change)

Add presentation fields to existing marker arrays; all new keys optional so old popups remain compatible until retired.

- `destinationMarkers()` add: `cover_image` (first hotel image for destination or Unsplash fallback), keep `weather` (icon + description + temp), keep `hotel_count`/`activity_count`. Add `destination_slug` for deep link.
- `hotelMarker()` add: `images` (array), `cheapest_price` (min room `base_price` or null), `vibe_tags` slice 3, `featured_amenities` slice 3. Keep `rating`, `review_count`, `distance_*`.
- `activityMarker()` add: `images` (array), `vibe_tags` slice 3, `inclusions` slice 2. Keep `rate`, `category`, `rating`.
- Backward compat: no fields removed; frontend falls back to existing `image`/`url` when new fields absent.

Files: `app/Services/MapService.php`.

### Change 2 — Shared map component event refactor

Refactor `resources/views/components/frontend/map.blade.php` to be a dumb renderer that emits events instead of owning UX:

- Remove `buildPopupHtml()` Leaflet `bindPopup` as primary UX. Keep a minimal `bindTooltip` for desktop hover (name + counts/weather).
- On `click` emit `CustomEvent('sunnytrip:map-select', {detail: marker})` and handle selection styling (lift pin: scale 1.15 + `box-shadow` + `zIndexOffset: 1000` + ocean ring via CSS class toggle). Expose `api.select(id)` and `api.highlight(id)` for list↔map sync.
- Add `marker:hover` / `marker:leave` events for hover sync.
- Filter support: `api.setFilter('all'|'hotel'|'activity')` cross-fades non-matching `layerGroup` members (`opacity` + `transform: scale`) instead of removing them; preserves fitBounds.
- Keep `locate()` / `reveal()` / `routeMode` behavior untouched.
- Respect `prefers-reduced-motion` (disable FlyTo animation + scale).

### Change 3 — Dashboard rich preview card

Wrap the dashboard map in Alpine `dashboardMap()` that listens for `sunnytrip:map-select`:

- Layout: `lg:grid-cols-3` — map `lg:col-span-2`, preview `lg:col-span-1`. On mobile preview becomes a bottom sheet (`fixed bottom-0` with drag handle, `translate-y` transition).
- Card content (destination type): hero `cover_image` 16:9, `hotel_count` · `activity_count` chips, weather pill (`weather.icon` from OpenWeatherMap), `★ rating` if destination aggregates one, 3 vibe pills, CTAs: primary `Explore Hotels` → `/hotels?destination=ID`, secondary `Open in Explorer` → `/explore?focus=destination:ID` (new optional query param handled by explorer `init`).
- Interaction: Esc / X / map click dismisses; clicking another pin cross-fades card (not flash).
- No navigation on pin click alone — card is the reward.

File: `resources/views/dashboard.blade.php` (add Alpine wrapper + card partial `components/frontend/map-preview-card.blade.php`).

### Change 4 — Explore rich preview + list↔map sync

Enhance `explore.blade.php` Alpine `explorer()`:

- Replace current `selected` panel popup path with the same shared `map-preview-card` component (hotel: hero + subtitle · address + `from ₱{cheapest_price}` + rating + vibe pills + `Preview` (opens `$store.preview`) + `Add to Trip Basket` → `window.addToCart`; activity: rate + category + same).
- Hover sync: `@mouseenter` on sidebar list items calls `highlight(m.id)` → pin lifts; pin hover emits → list row adds `bg-slate-50` ring. Use `Map` of `markerLayers` already present (`markerLayers: new Map()`).
- Click marker: `flyTo([lat,lng], 12, {animate: !prefersReducedMotion})` + open card; list scrolls selected into view.
- Filter chips (`All/Hotels/Activities`) already in header (`explore.blade.php:15`) — wire to `api.setFilter()` with 240ms opacity/scale stagger (50ms per marker by distance order) rather than full `render()` rebuild. Keep `sortedMarkers()` distance sort.
- Existing distance/lines overlay keeps working; card reuses `distance_label` already present.

Files: `resources/views/explore.blade.php` (+ shared card partial).

### Change 5 — Optional focus param

`DssController::explore(Request $request)` reads optional `?focus=destination:{id}|hotel:{id}` to pre-select a marker on load (used by dashboard `Open in Explorer` link). No route change; just query string handling and initial `selected` set after `render()`.

## Files touched

- `app/Services/MapService.php` — enrich `destinationMarkers()`, `hotelMarker()`, `activityMarker()`.
- `app/Http/Controllers/DssController.php` — optional `focus` param in `explore()`.
- `resources/views/components/frontend/map.blade.php` — tooltip vs popup, event emission, highlight/filter APIs, reduced-motion guard.
- `resources/views/dashboard.blade.php` — Alpine wrapper + preview card / bottom sheet.
- `resources/views/explore.blade.php` — preview card reuse, hover sync, animated filter.
- `resources/views/components/frontend/map-preview-card.blade.php` — new shared card (Blade partial, no new npm dep).
- `tests/Feature/MapServiceTest.php` — new/updated payload shape test (optional but recommended).

## Constraints / gotchas

- Keep Tailwind custom palette + `font-headline`/`font-body` only — no arbitrary `font-[...]`.
- Leaflet popups not removed until card proves stable — keep hidden fallback `bindPopup` during rollout if needed, then delete.
- `routeMode` hotel map must not adopt the preview card; `routeMode` prop gates the new behavior.
- `no_location` activities (`MapService.php:154`) never get pins — already filtered; card shows `No GPS` pill only if ever surfaced in list.
- No `composer.json` / `package.json` dependency added.

## Verification

- `php artisan test --filter=MapService` — destination + hotel/activity marker contains new optional keys; old keys still present.
- Manual browser checks (Brave via Playwright per AGENTS.md): dashboard pin hover tooltip → click card slide-in → filter; explore list hover → pin lift → click → flyTo + card with Preview + Add to Basket; mobile bottom sheet; Esc dismiss; filter All/Hotels/Activities animates; locate button still draws user lines.
- `vendor/bin/pint --dirty --format agent` before finalizing.
- No migration needed.

## Alternatives considered

- **A Minimal popup enrich:** upgrade `buildPopupHtml()` to rich HTML with `<img>` inside popup. Rejected: Leaflet popups clip images, lack basket/preview affordance, still navigational.
- **C Clustered DSS:** manual grid clustering at low zoom + personalized halo. Rejected: payload + JS cost not justified for <200 markers and `subtle & polished` brief; can be added later as additive change.
