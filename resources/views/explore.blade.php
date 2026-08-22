<x-frontend.layout title="Explore Islands — SunnyTrips">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <div class="py-6 sm:py-10 bg-slate-50 text-slate-900 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 font-headline tracking-tight">
                        Explore the Islands
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm font-body">
                        Every hotel, experience and destination across the Philippines — in one interactive view.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2" x-data="{ filter: 'all' }">
                    <button @click="filter = 'all'; window['exploreMap']?.setFilter('all')" :class="filter === 'all' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="rounded-full px-4 py-1.5 text-xs font-semibold border border-slate-200 transition">
                        All
                    </button>
                    <button @click="filter = 'hotel'; window['exploreMap']?.setFilter('hotel')" :class="filter === 'hotel' ? 'bg-sky-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="rounded-full px-4 py-1.5 text-xs font-semibold border border-slate-200 transition">
                        Hotels
                    </button>
                    <button @click="filter = 'activity'; window['exploreMap']?.setFilter('activity')" :class="filter === 'activity' ? 'bg-orange-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="rounded-full px-4 py-1.5 text-xs font-semibold border border-slate-200 transition">
                        Activities
                    </button>
                </div>
            </div>

            <div x-data="explorer()" x-init="init()"
                 class="grid lg:grid-cols-3 gap-6" style="z-index: 1;">

                <div class="lg:col-span-2">
                    <div id="exploreMap" class="h-[70vh] rounded-3xl overflow-hidden border border-slate-200 shadow-sm" style="z-index: 1;"></div>
                </div>

                <aside class="space-y-3 max-h-[70vh] overflow-y-auto pr-1" @sunnytrip:map-hover.window="highlight($event.detail.marker, $event.detail.on)">
                    <button @click="locate()" :disabled="locating"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-ocean-600 px-5 py-3 text-sm font-bold text-white transition-all hover:bg-ocean-700 disabled:opacity-60 cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">my_location</span>
                        <span x-text="locating ? 'Locating you…' : (userCoords ? 'Recalculate distance from me' : 'Show distance from me')"></span>
                    </button>

                    <template x-if="geoError">
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                            <span x-text="geoError"></span>
                        </div>
                    </template>

                    <template x-if="userCoords && !selected">
                        <div class="rounded-2xl border border-ocean-200 bg-ocean-50 p-4 text-xs text-ocean-800">
                            Showing nearest-first from your location.
                        </div>
                    </template>

                    <template x-if="userCoords">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-100 px-4 py-2.5 text-[11px] font-extrabold uppercase tracking-widest text-slate-500">
                                Distances from you
                                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500" x-text="markers.length"></span>
                            </div>
                            <div class="max-h-64 divide-y divide-slate-100 overflow-y-auto">
                                <template x-for="m in sortedMarkers()" :key="(m.type || 'x') + '-' + m.id">
                                    <button @click="selectWithCard(m)" @mouseenter="highlight(m,true)" @mouseleave="highlight(m,false)" type="button"
                                        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left transition hover:bg-slate-50 cursor-pointer">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full"
                                              :class="m.type === 'hotel' ? 'bg-sky-500' : m.type === 'activity' ? 'bg-orange-500' : 'bg-sky-900'"></span>
                                        <span class="flex-1 truncate text-xs font-semibold text-slate-700" x-text="m.name"></span>
                                        <span class="shrink-0 text-[11px] font-bold text-ocean-600" x-text="m.distance_label || '—'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="selected">
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 space-y-3">
                            <p class="text-[11px] font-extrabold uppercase tracking-widest text-sky-600"
                               x-text="selected.type === 'hotel' ? 'Hotel / Sanctuary Stay' : 'Activity / Experience'"></p>
                            <h2 class="text-lg font-black text-slate-900 font-headline" x-text="selected.name"></h2>
                            <p class="text-sm text-slate-500" x-text="selected.subtitle"></p>
                            <template x-if="selected.image">
                                <img :src="'/storage/' + selected.image" alt="" class="w-full h-40 object-cover rounded-xl">
                            </template>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <template x-if="selected.distance_label">
                                    <span class="rounded-full bg-ocean-50 px-3 py-1 font-medium text-ocean-700"
                                          x-text="selected.distance_label + ' from you'"></span>
                                </template>
                                <template x-if="selected.rating">
                                    <span class="rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-700"
                                          x-text="'★ ' + Number(selected.rating).toFixed(1) + ' (' + selected.review_count + ')'"></span>
                                </template>
                            </div>
                            <div class="flex flex-wrap gap-2" x-show="selected.type === 'activity'">
                                <button type="button"
                                        @click="$store.preview.openActivityById(selected.id)"
                                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-200 cursor-pointer">
                                    <span class="material-symbols-outlined text-[15px]">auto_awesome</span>
                                    <span>Quick Preview</span>
                                </button>
                                <button type="button"
                                        @click="window.addToCart('activity', selected.id, { selected_pax: 1 })"
                                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-ocean-600 px-3 py-2.5 text-xs font-bold text-white transition hover:bg-ocean-700 cursor-pointer">
                                    <span class="material-symbols-outlined text-[15px]">shopping_cart</span>
                                    <span>Add to Basket</span>
                                </button>
                            </div>

                            <div class="flex flex-wrap gap-2" x-show="selected.type === 'hotel'">
                                <a :href="selected.url" x-show="selected.url"
                                   class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2.5 text-xs font-bold text-white transition hover:bg-slate-800 cursor-pointer">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                    <span>View Details</span>
                                </a>
                            </div>
                        </div>
                    </template>

                    <template x-if="!selected">
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-5 text-center text-sm text-slate-400">
                            Tap a marker on the map to see its details here.
                        </div>
                    </template>
                </aside>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        function explorer() {
            return {
                markers: @json($markers),
                map: null,
                layer: null,
                lineLayer: null,
                markerLayers: new Map(),
                selected: null,
                userCoords: null,
                userMarker: null,
                locating: false,
                geoError: null,

                init() {
                    const el = document.getElementById('exploreMap');
                    if (!el || el._leaflet_id) return;
                    const lat = @json($center) || { lat: 12.0, lng: 122.0 };
                    this.map = L.map('exploreMap', { scrollWheelZoom: true }).setView([lat.lat, lat.lng], 7);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(this.map);
                    this.render();
                    this.$watch('selected', (val) => {
                        if (val && this.map) {
                            this.map.flyTo([val.lat, val.lng], 12);
                        }
                    });

                    window['exploreMap'] = {
                        setFilter: (type) => this.setFilter(type),
                        highlight: (id, on) => this.highlight(id, on),
                        select: (m) => this.selectWithCard(m),
                    };

                    const focusParam = @json($focus ?? null);
                    if (focusParam) {
                        const match = this.markers.find((m) => `${m.type}:${m.id}` === focusParam);
                        if (match) this.selectWithCard(match);
                    }
                },

                locate() {
                    if (!navigator.geolocation) {
                        this.geoError = 'Your browser does not support location sharing.';
                        return;
                    }
                    this.locating = true;
                    this.geoError = null;
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                            this.locating = false;
                            this.fetchDistances();
                        },
                        (err) => {
                            this.locating = false;
                            this.geoError = err.code === 1
                                ? 'Location permission was blocked. Allow access in your browser settings to see distances.'
                                : 'Could not get your location. Please try again.';
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
                    );
                },

                async fetchDistances() {
                    const { lat, lng } = this.userCoords;
                    try {
                        const res = await fetch('/api/dss/markers?lat=' + lat + '&lng=' + lng, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        if (data.data) {
                            this.markers = data.data;
                            this.render();
                        }
                    } catch (e) {
                        this.geoError = 'Could not load distances right now. Please try again.';
                    }
                },

                icons(type) {
                    if (type === 'hotel') return L.divIcon({
                        className: 'leaflet-div-icon',
                        html: '<div style="width:26px;height:26px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#0ea5e9;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);"></div>',
                        iconSize: [26, 26], iconAnchor: [13, 26],
                    });
                    return L.divIcon({
                        className: 'leaflet-div-icon',
                        html: '<div style="width:26px;height:26px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#f97316;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);"></div>',
                        iconSize: [26, 26], iconAnchor: [13, 26],
                    });
                },

                userIcon() {
                    return L.divIcon({
                        className: 'leaflet-div-icon',
                        html: '<div style="position:relative;width:22px;height:22px;"><span style="position:absolute;inset:0;border-radius:50%;background:rgba(16,185,129,.35);animation:stping 1.6s ease-out infinite;"></span><span style="position:absolute;inset:5px;border-radius:50%;background:#10b981;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35);"></span></div>',
                        iconSize: [22, 22], iconAnchor: [11, 11],
                    });
                },

                render() {
                    if (this.layer) this.map.removeLayer(this.layer);
                    this.layer = L.layerGroup().addTo(this.map);
                    const scope = this;

                    if (this.userMarker) this.map.removeLayer(this.userMarker);
                    if (this.userCoords) {
                        this.userMarker = L.marker([this.userCoords.lat, this.userCoords.lng], { icon: this.userIcon() })
                            .addTo(this.map)
                            .bindPopup('<strong>You are here</strong>');
                    }

                    if (this.lineLayer) this.map.removeLayer(this.lineLayer);
                    this.lineLayer = L.layerGroup().addTo(this.map);
                    if (this.userCoords) {
                        this.markers.forEach((m) => {
                            if (!m.lat || !m.lng) return;
                            L.polyline([[this.userCoords.lat, this.userCoords.lng], [m.lat, m.lng]], {
                                color: '#0ea5e9', weight: 2, dashArray: '6, 8', opacity: 0.5,
                            }).addTo(this.lineLayer);
                        });
                    }

                    this.markerLayers = new Map();
                    const ordered = this.sortedMarkers();

                    ordered.forEach((m) => {
                        const popupBits = [`<strong>${m.name}</strong>`];
                        if (m.subtitle) popupBits.push(m.subtitle);
                        if (m.distance_label) popupBits.push(`<em>${m.distance_label} from you</em>`);
                        if (m.rating) popupBits.push(`★ ${Number(m.rating).toFixed(1)} (${m.review_count})`);
                        if (m.url) popupBits.push(`<a href="${m.url}">View details →</a>`);

                        const layer = L.marker([m.lat, m.lng], { icon: this.icons(m.type) })
                            .on('click', () => { scope.selectWithCard(m); })
                            .addTo(this.layer);
                        this.markerLayers.set(m, layer);
                    });

                    if (this.userCoords) {
                        const pts = [
                            [this.userCoords.lat, this.userCoords.lng],
                            ...this.markers.filter((m) => m.lat && m.lng).map((m) => [m.lat, m.lng]),
                        ];
                        if (pts.length > 1) {
                            this.map.fitBounds(L.latLngBounds(pts).pad(0.15), { animate: true, maxZoom: 12 });
                        }
                    }
                },

                sortedMarkers() {
                    return [...this.markers].sort((a, b) => {
                        const da = a.distance_km ?? Infinity;
                        const db = b.distance_km ?? Infinity;
                        return da - db;
                    });
                },

                select(m) {
                    this.selected = m;
                    const layer = this.markerLayers.get(m);
                    if (layer) layer.openPopup();
                },

                selectWithCard(m) {
                    this.selected = m;
                    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    if (this.map && m.lat && m.lng) {
                        this.map.flyTo([m.lat, m.lng], 12, { animate: !reduced });
                    }
                    this.highlight(`${m.type}-${m.id}`, true);
                },

                setFilter(type) {
                    this.markerLayers.forEach((layer, m) => {
                        const show = type === 'all' || m.type === type;
                        const el = layer.getElement();
                        if (!el) return;
                        el.style.transition = 'opacity 240ms ease-out, transform 240ms ease-out';
                        el.style.opacity = show ? '1' : '0.14';
                        el.style.transform = show ? 'scale(1)' : 'scale(0.82)';
                        el.style.pointerEvents = show ? 'auto' : 'none';
                    });
                },

                highlight(m, on) {
                    let layer;
                    if (typeof m === 'string') {
                        layer = [...this.markerLayers.entries()]
                            .find(([mk]) => `${mk.type}-${mk.id}` === m)?.[1];
                    } else {
                        layer = this.markerLayers.get(m);
                    }
                    if (!layer) return;
                    const el = layer.getElement();
                    if (el) el.classList.toggle('map-pin-hover', on);
                },
            };
        }
    </script>
    <style>
        @keyframes stping {
            0% { transform: scale(0.6); opacity: 1; }
            100% { transform: scale(2.2); opacity: 0; }
        }
        .map-pin-hover div { box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25) !important; }
        .map-pin-active div { box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.35), 0 4px 14px rgba(0, 0, 0, 0.25) !important; transform: rotate(-45deg) scale(1.12); }
        @media (prefers-reduced-motion: reduce) {
            .map-pin-hover div, .map-pin-active div { transition: none !important; }
        }
    </style>
</x-frontend.layout>