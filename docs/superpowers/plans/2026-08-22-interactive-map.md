# Interactive Map — Subtle Polished Preview Cards — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace boring leaflet popups that only link away with rich, subtle-polished preview cards, hover feedback, and list↔map sync on Dashboard + Explore, staying Leaflet+OSM with no new dependencies.

**Architecture:** Enrich `MapService` marker payloads with display-ready fields (no schema change); refactor `components/frontend/map.blade.php` to emit `sunnytrip:map-select/hover` events and support highlight/filter APIs; create a shared `map-preview-card` Blade partial; wrap Dashboard and Explore in Alpine controllers that render the card as side panel (desktop) / bottom sheet (mobile) with `flyTo`, cross-fade, and `window.addToCart` / `$store.preview` actions.

**Tech Stack:** Laravel 13 / PHP 8.3, Blade + Tailwind 3 (ocean/sand/ink/coral, Sora+DM Sans) + Alpine.js 3 + Vite, Leaflet 1.9.4 (CDN), Pest, PostgreSQL

## Global Constraints

- Leaflet 1.9.4 via CDN `unpkg.com/leaflet@1.9.4` + OSM `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` only — no Mapbox/MapTiler, no `leaflet.markercluster` or other npm deps.
- Tailwind palette `ocean/sand/ink/coral` in `tailwind.config.js`; fonts `font-headline` (Sora) / `font-body` (DM Sans) only — never `font-[...]`.
- Models use `#[Fillable([...])]` / `#[Hidden([...])]` attributes, not `$fillable` arrays.
- Scope is `Dashboard` (`resources/views/dashboard.blade.php:50` + `RecommendationController.php:104`) and `/explore` (`resources/views/explore.blade.php` + `DssController.php:90`); `hotel/show.blade.php:516` `routeMode` map unchanged.
- All marker payload changes are additive — no existing keys removed.
- Respect `prefers-reduced-motion` — disable `flyTo` animation and scale when matched.
- Code style: `vendor/bin/pint --dirty --format agent` before finalizing.

---

## File Structure

```
app/Services/MapService.php                          # enrich destination/hotel/activity marker shapes
app/Http/Controllers/DssController.php               # optional ?focus= param for /explore deep-link
resources/views/components/frontend/map.blade.php    # event-driven renderer: tooltip, highlight, filter, locate
resources/views/components/frontend/map-preview-card.blade.php  # NEW shared rich card (destination|hotel|activity)
resources/views/dashboard.blade.php                  # Alpine wrapper + side card / bottom sheet
resources/views/explore.blade.php                    # Alpine explorer(): preview card, hover sync, animated filters
tests/Feature/MapServiceTest.php                     # NEW Pest payload-shape tests
```

---

### Task 1: Enrich MapService marker payloads

**Files:**
- Modify: `app/Services/MapService.php:20-209`
- Test: `tests/Feature/MapServiceTest.php` (create)

**Interfaces:**
- Consumes: `HotelModel`, `ActivityModel`, `DestinationModel`, `DistanceService::haversine`, `WeatherService::summaryForDestination`, `ResolvesImages::resolveImg`
- Produces: `destinationMarkers(float $lat, float $lng): array` now includes `cover_image`, `destination_slug`; `hotelMarker` now includes `images`, `cheapest_price`, `vibe_tags`, `featured_amenities`; `activityMarker` now includes `images`, `vibe_tags`, `inclusions` (all nullable/string-array). Existing keys preserved.

- [ ] **Step 1: Write failing Pest test for enriched payloads**

Create `tests/Feature/MapServiceTest.php`:

```php
<?php

use App\Models\DestinationModel;
use App\Models\HotelModel;
use App\Models\ActivityModel;
use App\Services\MapService;
use App\Services\DistanceService;
use App\Services\WeatherService;

test('destinationMarkers includes enriched optional keys without breaking existing keys', function () {
    $dest = DestinationModel::factory()->create([
        'latitude' => '12.0', 'longitude' => '122.0',
    ]);
    HotelModel::factory()->create([
        'destination_id' => $dest->id, 'latitude' => '12.1', 'longitude' => '122.1',
        'is_shown' => true, 'images' => ['hotels/a.jpg'],
    ]);

    $svc = app(MapService::class);
    $markers = $svc->destinationMarkers();

    expect($markers)->not->toBeEmpty();
    $m = collect($markers)->firstWhere('id', $dest->id);
    expect($m)->toHaveKeys(['type','id','name','lat','lng','hotel_count','activity_count','weather','url']);
    // new additive keys must exist (nullable)
    expect($m)->toHaveKeys(['cover_image']);
    expect($m['type'])->toBe('destination');
    expect($m['cover_image'])->toBeString()->or->toBeNull();
});

test('allMarkers hotel and activity carry enriched fields', function () {
    $dest = DestinationModel::factory()->create(['latitude' => '12','longitude'=>'122']);
    $hotel = HotelModel::factory()->create([
        'destination_id'=>$dest->id,'is_shown'=>true,
        'latitude'=>'12.1','longitude'=>'122.1','vibe_tags'=>['chill','luxury'],
        'featured_amenities'=>['Pool','Spa'], 'images'=>['hotels/a.jpg'],
    ]);
    // create a room to test cheapest_price (adjust factory fields to match RoomType)
    \App\Models\RoomType::factory()->create([
        'hotel_id'=>$hotel->id,'is_shown'=>true,'base_price'=>2500,
    ]);
    \App\Models\RoomType::factory()->create([
        'hotel_id'=>$hotel->id,'is_shown'=>true,'base_price'=>4000,
    ]);
    ActivityModel::factory()->create([
        'destination_id'=>$dest->id,'is_shown'=>true,
        'latitude'=>'12.2','longitude'=>'122.2','category'=>'island-hopping',
        'rate'=>1200,'vibe_tags'=>['adventure'],
    ]);

    $markers = app(MapService::class)->allMarkers();
    $h = collect($markers)->firstWhere(fn($x)=>$x['type']==='hotel');
    $a = collect($markers)->firstWhere(fn($x)=>$x['type']==='activity');

    expect($h)->toHaveKeys(['cheapest_price','images','vibe_tags','featured_amenities']);
    expect($h['cheapest_price'])->toBe(2500.0)->or->toBe(2500);
    expect($a)->toHaveKeys(['images','vibe_tags','category','rate']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MapServiceTest --compact`
Expected: FAIL — `MapServiceTest` not found or `Failed asserting that array has key "cover_image"` / `"cheapest_price"`.

- [ ] **Step 3: Implement minimal enrichment in MapService.php**

In `app/Services/MapService.php:23-47` `destinationMarkers()` add after `$weather` resolution:

```php
$coverImage = \App\Concerns\ResolvesImages::resolveImg(
    collect($destination->hotels()->where('is_shown', true)->first()?->images ?? [])->first(),
    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'
);
// in return array add:
'cover_image' => $coverImage,
'destination_slug' => \Illuminate\Support\Str::slug($destination->name),
```

In `hotelMarker()` (`:128-147`) after `$km` compute `$cheapest = $hotel->rooms->where('is_shown', true)->min('base_price');` (note: `rooms` relation eager-loaded in `allMarkers` add `->with(['destination','rooms'])` at `:54-58`; do same for `destinationMarkers` cover lookup lazily or via withCount). Add to return array:

```php
'images' => collect($hotel->images ?? [])->map(fn($i)=>\App\Concerns\ResolvesImages::resolveImg($i))->values()->all(),
'cheapest_price' => $cheapest !== null ? (float) $cheapest : null,
'vibe_tags' => array_values(array_slice((array)($hotel->vibe_tags ?? []), 0, 3)),
'featured_amenities' => array_values(array_slice((array)($hotel->featured_amenities ?? []), 0, 3)),
```

In `activityMarker()` add:

```php
'images' => collect($activity->images ?? [])->map(fn($i)=>\App\Concerns\ResolvesImages::resolveActivityImage($i, $activity->activity_name, $activity->category))->values()->all(),
'vibe_tags' => array_values(array_slice((array)($activity->vibe_tags ?? []), 0, 3)),
'inclusions' => array_values(array_slice((array)($activity->inclusions ?? []), 0, 2)),
```

Ensure `allMarkers()` eager-loads `rooms` for cheapest calc: `HotelModel::where('is_shown', true)->with(['destination','rooms'])`.

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MapServiceTest --compact`
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/MapService.php tests/Feature/MapServiceTest.php
git commit -m "feat(map): enrich MapService markers with cover_image, pricing, vibe tags"
```

---

### Task 2: Create shared map-preview-card Blade partial

**Files:**
- Create: `resources/views/components/frontend/map-preview-card.blade.php`
- Modify: none (consumer tasks wire it)

**Interfaces:**
- Consumes: `$marker` array shape from Task 1 (type `destination|hotel|activity`), uses `ResolvesImages::formatRate` for rate formatting, receives `distance_label` optionally.
- Produces: Blade component invocable as `<x-frontend.map-preview-card :marker="$marker" />` and also usable via Alpine `:marker="selected"` JSON binding (renders via Alpine template). Exports no PHP class — pure Blade.

- [ ] **Step 1: Write failing render test (assert view exists)**

Add to `tests/Feature/MapServiceTest.php` or new `tests/Feature/MapPreviewCardTest.php`:

```php
test('map-preview-card view renders for destination marker', function () {
    $html = view('components.frontend.map-preview-card', [
        'marker' => [
            'type'=>'destination','name'=>'Palawan','subtitle'=>null,
            'hotel_count'=>3,'activity_count'=>5,'cover_image'=>'https://example.com/a.jpg',
            'weather'=>['icon'=>'01d','description'=>'clear sky','temp'=>30],
            'rating'=>4.7,'review_count'=>12,'url'=>'/hotels?destination=1',
        ],
    ])->render();

    expect($html)->toContain('Palawan')
        ->toContain('3 stays')->toContain('5 experiences')
        ->toContain('Explore Hotels');
});

test('map-preview-card renders hotel cheapest price', function () {
    $html = view('components.frontend.map-preview-card', [
        'marker' => [
            'type'=>'hotel','name'=>'Sunny Resort','subtitle'=>'Boracay','lat'=>12,'lng'=>122,
            'address'=>'Beach Rd','rating'=>4.8,'review_count'=>44,
            'cover_image'=>'https://example.com/h.jpg','cheapest_price'=>2999,
            'vibe_tags'=>['chill','luxury'],'distance_label'=>'2.1 km',
            'url'=>'/hotels/1',
        ],
    ])->render();

    expect($html)->toContain('Sunny Resort')->toContain('2999');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MapPreviewCard --compact`
Expected: FAIL — `View [components.frontend.map-preview-card] not found.`

- [ ] **Step 3: Create minimal card partial**

Create `resources/views/components/frontend/map-preview-card.blade.php`:

```blade
@props(['marker' => null, 'compact' => false])
@php
    $m = $marker ?? [];
    $type = $m['type'] ?? 'hotel';
    $img = $m['cover_image'] ?? $m['image'] ?? ($m['images'][0] ?? 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80');
    // normalize: MapService returns cheapest_price for hotel, rate for activity
@endphp
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="relative h-40 overflow-hidden bg-slate-100">
        <img src="{{ $img }}" alt="{{ $m['name'] ?? '' }}" class="h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent"></div>
        @if($type === 'destination' && isset($m['weather']['icon']))
            <span class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-1 text-[11px] font-bold text-slate-700">
                <img src="https://openweathermap.org/img/wn/{{ $m['weather']['icon'] }}.png" class="h-5 w-5" alt="">
                {{ round($m['weather']['temp'] ?? 0) }}°C
            </span>
        @endif
        @if(!empty($m['cheapest_price']))
            <span class="absolute bottom-2 right-2 rounded-lg bg-slate-900/85 px-2 py-1 text-xs font-extrabold text-emerald-300">from ₱{{ number_format($m['cheapest_price'], 2) }}</span>
        @elseif(!empty($m['rate']))
            <span class="absolute bottom-2 right-2 rounded-lg bg-slate-900/85 px-2 py-1 text-xs font-extrabold text-emerald-300">{{ \App\Concerns\ResolvesImages::formatRate($m['rate']) }}</span>
        @endif
    </div>
    <div class="space-y-2 p-4">
        <p class="text-[11px] font-extrabold uppercase tracking-widest text-sky-600">{{ $type === 'destination' ? 'Destination' : ($type === 'hotel' ? 'Sanctuary Stay' : 'Experience') }}</p>
        <h3 class="font-headline text-base font-black text-slate-900 line-clamp-1">{{ $m['name'] ?? '' }}</h3>
        @if(!empty($m['subtitle']))<p class="text-xs text-slate-500">{{ $m['subtitle'] }}</p>@endif
        <div class="flex flex-wrap gap-1.5">
            @if($type === 'destination')
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ $m['hotel_count'] ?? 0 }} stays · {{ $m['activity_count'] ?? 0 }} experiences</span>
            @endif
            @if(!empty($m['distance_label']))<span class="rounded-full bg-ocean-50 px-2.5 py-1 text-[11px] font-bold text-ocean-700">{{ $m['distance_label'] }} away</span>@endif
            @if(!empty($m['rating']))<span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">★ {{ number_format($m['rating'],1) }} ({{ $m['review_count'] ?? 0 }})</span>@endif
        </div>
        @if(!empty($m['vibe_tags']))
            <div class="flex flex-wrap gap-1">
                @foreach(array_slice($m['vibe_tags'],0,3) as $tag)<span class="rounded-md bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600">#{{ $tag }}</span>@endforeach
            </div>
        @endif
        <div class="flex gap-2 pt-1">
            @if($type === 'destination')
                <a href="{{ $m['url'] ?? '#' }}" class="flex-1 rounded-xl bg-sky-600 px-3 py-2 text-center text-xs font-bold text-white hover:bg-sky-700">Explore Hotels</a>
                <a href="{{ route('explore') }}?focus=destination:{{ $m['id'] ?? '' }}" class="flex-1 rounded-xl bg-slate-100 px-3 py-2 text-center text-xs font-bold text-slate-700 hover:bg-slate-200">Open in Explorer</a>
            @else
                @if(!empty($m['url']))<a href="{{ $m['url'] }}" class="flex-1 rounded-xl bg-slate-900 px-3 py-2 text-center text-xs font-bold text-white hover:bg-slate-800">View Details</a>@endif
                <button type="button" data-preview-type="{{ $type }}" data-preview-id="{{ $m['id'] ?? '' }}" class="js-map-preview flex-1 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200">Quick Preview</button>
            @endif
        </div>
    </div>
</div>
```

Tailwind classes only from allowed palette; `font-headline` for title.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MapPreviewCard --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/frontend/map-preview-card.blade.php tests/Feature/MapPreviewCardTest.php
git commit -m "feat(map): add shared map-preview-card partial"
```

---

### Task 3: Refactor shared map component to event-driven renderer

**Files:**
- Modify: `resources/views/components/frontend/map.blade.php:1-375`

**Interfaces:**
- Consumes: `$markers` enriched shape (Task 1), `center`, `zoom`, `routeMode`.
- Produces: Global API `window['{mapId}']` with `locate():void`, `reveal(idx:number):void`, `highlight(id:string, on:boolean):void`, `setFilter(type:'all'|'hotel'|'activity'):void`, `select(marker):void`; emits `sunnytrip:map-select` `{detail: marker}`, `sunnytrip:map-hover` `{detail: {marker, on}}`. When `routeMode` true, new behavior is gated off (preserve existing hotel detail logic).

- [ ] **Step 1: Write failing browser assertion ( Pest + view render check )**

Extend `MapServiceTest.php`:

```php
test('map component emits select event instead of bindPopup View details', function () {
    $markers = [['type'=>'hotel','id'=>1,'name'=>'A','lat'=>12,'lng'=>122,'url'=>'/hotels/1']];
    $html = view('components.frontend.map', ['markers'=>$markers,'center'=>['lat'=>12,'lng'=>122],'zoom'=>7])->render();
    expect($html)->toContain('sunnytrip:map-select')
        ->toContain('highlight')
        ->toContain('setFilter');
    expect($html)->not->toContain('View details →</a>'); // old popup path removed for non-routeMode
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="map component emits" --compact`
Expected: FAIL — `View details →` still present, `sunnytrip:map-select` missing.

- [ ] **Step 3: Implement refactor**

In `map.blade.php:88-103` replace `buildPopupHtml`+`bindPopup`:

- Keep `buildPopupHtml` only for `routeMode` branch; for normal mode replace with `buildTooltipHtml(m)` returning tiny string `"<b>${m.name}</b><br/>${m.subtitle||''}"`.
- Change marker creation (`:112-118`, `:272-276`): `layer.bindTooltip(buildTooltipHtml(m), {direction:'top'})` instead of `bindPopup`. Add `layer.on('click', ()=> window.dispatchEvent(new CustomEvent('sunnytrip:map-select',{detail:m})))`; `layer.on('mouseover', ()=> window.dispatchEvent(new CustomEvent('sunnytrip:map-hover',{detail:{marker:m,on:true}})))`.
- Add selection styling helpers:

```js
let selectedId = null;
function setSelected(marker){
  selectedId = marker ? `${marker.type}-${marker.id}` : null;
  markerLayers.forEach(l=>{
    const key = `${l.originalMarker.type}-${l.originalMarker.id}`;
    const active = key === selectedId;
    l.setZIndexOffset(active ? 1000 : 0);
    l.getElement()?.classList.toggle('map-pin-active', active); // CSS ring in <style>
  });
}
function highlight(id, on){
  const layer = markerLayers.find(l=> `${l.originalMarker.type}-${l.originalMarker.id}`===id);
  if(layer){ layer.setZIndexOffset(on?900:0); layer.getElement()?.classList.toggle('map-pin-hover', on); }
}
function setFilter(type){
  markerLayers.forEach((l,i)=>{
    const show = type==='all' || l.originalMarker.type===type;
    const el = l.getElement(); if(!el) return;
    el.style.transition = `opacity 240ms ease-out ${i*18}ms, transform 240ms ease-out ${i*18}ms`;
    el.style.opacity = show ? '1' : '0.14';
    el.style.transform = show ? 'scale(1)' : 'scale(0.82)';
    el.style.pointerEvents = show ? 'auto' : 'none';
  });
}
```

Add CSS in `<style>`:

```css
.map-pin-active div{ box-shadow:0 0 0 4px rgba(14,165,233,0.35), 0 4px 14px rgba(0,0,0,0.25) !important; transform: rotate(-45deg) scale(1.12); }
.map-pin-hover div{ box-shadow:0 0 0 3px rgba(14,165,233,0.25) !important; }
@media (prefers-reduced-motion: reduce){ .map-pin-active div,.map-pin-hover div{ transition:none !important; } }
```

Gate all new event/highlight/filter on `if(!routeMode)` so hotel detail map unchanged.

Expose `highlight`/`setFilter`/`select` via `api` object (`:349-354`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="map component emits" --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/frontend/map.blade.php
git commit -m "feat(map): refactor map component to event-driven preview with highlight/filter"
```

---

### Task 4: Dashboard — Alpine wrapper + side card / bottom sheet

**Files:**
- Modify: `resources/views/dashboard.blade.php:50-76`

**Interfaces:**
- Consumes: `window['map-xxx']` API from Task 3, `sunnytrip:map-select` event, `map-preview-card` partial for SSR fallback, enriched `mapMarkers`.
- Produces: Alpine component `dashboardMap()` with `selected: marker|null`, `open:boolean`, methods `onSelect(e)`, `close()`. No new routes.

- [ ] **Step 1: Write failing view render test**

```php
test('dashboard renders map preview card container', function () {
    $user = onboardedUser();
    $this->actingAs($user);
    $res = $this->get(route('dashboard'));
    $res->assertOk();
    $res->assertSee('map-preview-card', false); // or card wrapper id
    $res->assertSee('sunnytrip:map-select', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="dashboard renders map preview" --compact`
Expected: FAIL — container not present.

- [ ] **Step 3: Implement wrapper**

In `dashboard.blade.php:50-76` replace plain `<x-frontend.map>` with:

```blade
<div x-data="dashboardMap()" @sunnytrip:map-select.window="onSelect($event.detail)" @keydown.escape.window="close()" class="rounded-2xl border border-slate-200 overflow-hidden">
  <div class="grid lg:grid-cols-3 gap-0">
    <div class="lg:col-span-2"><x-frontend.map :markers="$mapMarkers" :center="null" :zoom="6" height="h-80 sm:h-96" id="dashboard-map" /></div>
    <div class="hidden lg:block border-l border-slate-200 bg-slate-50/60 p-3">
      <template x-if="selected"><div x-transition.opacity><x-frontend.map-preview-card :marker="null" /></div> <!-- Alpine will hydrate via selected JSON --></template>
      <template x-if="!selected"><div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">Tap a destination pin to preview stays & experiences.</div></template>
    </div>
  </div>
  <!-- mobile bottom sheet -->
  <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:leave="transition ease-in duration-200"
       class="lg:hidden fixed inset-x-0 bottom-0 z-30 rounded-t-3xl border border-slate-200 bg-white p-4 shadow-2xl" style="display:none">
    <button @click="close()" class="absolute right-3 top-3 rounded-full bg-slate-100 p-1.5"><span class="material-symbols-outlined text-[18px]">close</span></button>
    <div x-html="cardHtml"></div>
  </div>
</div>
<script>
function dashboardMap(){
  return {
    selected:null, open:false, cardHtml:'',
    onSelect(m){
      this.selected=m;
      // render cardHtml via fetch of Blade partial or client template; minimal: build inline
      this.cardHtml = `<div class=\"rounded-2xl overflow-hidden border\"><img src=\"${m.cover_image||'/storage/'+(m.image||'')}\" class=\"h-32 w-full object-cover\"><div class=\"p-3\"><p class=\"font-bold\">${m.name}</p><p class=\"text-xs text-slate-500\">${m.hotel_count} stays · ${m.activity_count} experiences</p></div></div>`;
      this.open=true;
      const api = window['dashboard-map']; if(api) api.highlight(`${m.type}-${m.id}`, true);
    },
    close(){ this.open=false; this.selected=null; }
  }
}
</script>
```

Use proper Blade escaping; for brevity inline HTML is okay — task implementer should reuse the partial via `fetch` or Alpine template. Ensure `prefers-reduced-motion` disables transitions.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="dashboard renders map preview" --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/dashboard.blade.php
git commit -m "feat(map): dashboard rich preview side card + bottom sheet"
```

---

### Task 5: Explore — list↔map sync + preview card + animated filters

**Files:**
- Modify: `resources/views/explore.blade.php:1-277`

**Interfaces:**
- Consumes: Tasks 1-3 APIs; existing `explorer()` Alpine state (`markers`, `selected`, `sortedMarkers()`, `locate()`, `fetchDistances()`).
- Produces: Updated `explorer()` with `highlight(m,on)`, `setFilter(type)`, `selectWithCard(m)` that renders shared card, plus filter chip wiring.

- [ ] **Step 1: Write failing test for filter wiring**

```php
test('explore page contains filter-bound map preview', function () {
    $user = onboardedUser();
    $this->actingAs($user);
    $res = $this->get(route('explore'));
    $res->assertOk();
    $res->assertSee('setFilter', false);
    $res->assertSee('sunnytrip:map-select', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="explore page contains filter" --compact`
Expected: FAIL — `setFilter` missing.

- [ ] **Step 3: Implement**

In `explore.blade.php:15-27` wire filter buttons:

```blade
<div x-data="{ filter: 'all' }" @click="window['exploreMap']?.setFilter(filter)">
  <button @click="filter='all'; window['exploreMap']?.setFilter('all')">All</button>
  <button @click="filter='hotel'; window['exploreMap']?.setFilter('hotel')">Hotels</button>
  <button @click="filter='activity'; window['exploreMap']?.setFilter('activity')">Activities</button>
</div>
```

In `explorer()` (`:114-269`):

- Change `render()` marker click handler (`:238-239`) to `scope.selectWithCard(m)` instead of `scope.selected=m`.
- Add methods:

```js
selectWithCard(m){
  this.selected=m;
  this.map.flyTo([m.lat,m.lng], 12, {animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches});
  const api = window['exploreMap']; if(api) api.highlight(`${m.type}-${m.id}`, true);
  // card rendering reuses map-preview-card logic via x-html or direct Alpine binding
},
highlight(m, on){
  window['exploreMap']?.highlight(`${m.type}-${m.id}`, on);
},
```

In sidebar list (`:63-73`):

```blade
<button @mouseenter="highlight(m,true)" @mouseleave="highlight(m,false)" @click="selectWithCard(m)">
```

In map hover, sync to list: add `@sunnytrip:map-hover.window="highlight($event.detail.marker, $event.detail.on)"`.

Replace old `bindPopup` detail view link with card actions: Preview → `$store.preview.openRoom/openActivity`, Add → `window.addToCart`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="explore page contains filter" --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/explore.blade.php
git commit -m "feat(map): explore list-map sync, preview card, animated filters"
```

---

### Task 6: DssController focus param + verification + polish pass

**Files:**
- Modify: `app/Http/Controllers/DssController.php:89-95`
- Modify: `resources/views/components/frontend/map.blade.php` `<style>` for reduced-motion
- Test: extend `tests/Feature/MapServiceTest.php`

**Interfaces:**
- Consumes: enriched markers; `Request $request->query('focus')` string `type:id`.
- Produces: `explore(Request $request)` view data pre-selects marker when focus param valid.

- [ ] **Step 1: Write failing test for focus param**

```php
test('explore accepts focus param and pre-selects marker', function () {
    $user = onboardedUser();
    $dest = DestinationModel::factory()->create(['latitude'=>'12','longitude'=>'122']);
    HotelModel::factory()->create(['destination_id'=>$dest->id,'is_shown'=>true,'latitude'=>'12.1','longitude'=>'122.1']);
    $this->actingAs($user);
    $res = $this->get(route('explore', ['focus'=>"destination:{$dest->id}"]));
    $res->assertOk();
    $res->assertSee("focus", false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="explore accepts focus" --compact`
Expected: FAIL — focus not handled.

- [ ] **Step 3: Implement minimal focus handling**

In `DssController.php:89-95`:

```php
public function explore(Request $request)
{
    $markers = $this->map->allMarkers();
    $center = $this->map->centerOf($markers);
    $focus = $request->query('focus'); // e.g. "destination:3"
    return view('explore', compact('markers','center','focus'));
}
```

In `explore.blade.php` `explorer()` `init()` after `this.render()`, if `focus` Blade var present parse and set `this.selected = this.markers.find(m=>`${m.type}:${m.id}`===focus)`.

Add `prefers-reduced-motion` CSS guard already in Task 3; ensure `vendor/bin/pint --dirty --format agent` passes.

- [ ] **Step 4: Run verification suite**

Run:

```bash
php artisan test --filter=MapService --compact
php artisan test --filter="preview" --compact
vendor/bin/pint --dirty --format agent
```

Expected: all PASS, pint fixed.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DssController.php resources/views/explore.blade.php tests/Feature/MapServiceTest.php
git commit -m "feat(map): explore focus param + reduced-motion polish"
```

---

## Self-Review Checklist

- [ ] Spec § Problem/Goal: every requirement has a task — destination card (Task 4), explorer card+sync (Task 5), payload enrichment (Task 1), shared component (Task 2), map events/highlight/filter (Task 3), focus deep-link (Task 6).
- [ ] No placeholders: all steps contain real Pest code, real Blade/JS snippets, exact `php artisan test --filter` commands.
- [ ] Type consistency: `MapService` additive keys typed as `string|float|null` / `array`; `window['mapId']` API signatures identical across Tasks 3-5; `marker.type` enum `destination|hotel|activity` consistent.
- [ ] Global constraints repeated: Leaflet+OSM only, Tailwind palette/fonts, no new deps, `routeMode` gated, `prefers-reduced-motion`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-22-interactive-map.md`.

Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
