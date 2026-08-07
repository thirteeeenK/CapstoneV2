@props([
    'markers' => [],
    'center' => null,
    'zoom' => 7,
    'height' => 'h-80',
    'showDistance' => true,
    'id' => null,
    'routeMode' => false,
])

@php
    $markersJson = json_encode(array_values($markers));
    $center = $center ?: ['lat' => 12.0, 'lng' => 122.0];
    $mapId = $id ?: 'map-' . uniqid();
@endphp

<div class="relative">
    <div id="{{ $mapId }}" class="{{ $height }} w-full rounded-2xl border border-sand-200 shadow-sm" style="z-index: 1;"></div>

    @if ($showDistance)
        <button type="button" onclick="window['{{ $mapId }}']?.locate()"
            class="absolute bottom-4 right-4 z-10 inline-flex items-center gap-1.5 rounded-full bg-ocean-600 px-4 py-2 text-xs font-bold text-white shadow-md transition hover:bg-ocean-700 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">my_location</span>
            <span id="{{ $mapId }}LocateLabel">{{ $routeMode ? 'Calculate Distance' : 'Distance from me' }}</span>
        </button>
    @endif

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        (function () {
            const container = document.getElementById('{{ $mapId }}');
            if (!container || container._leaflet_id) return;
            if (window._sunnytripLeafletLoaded && window.L) {
                initMap();
                return;
            }
            const check = setInterval(() => {
                if (window.L) {
                    window._sunnytripLeafletLoaded = true;
                    clearInterval(check);
                    initMap();
                }
            }, 100);

            function initMap() {
                const routeMode = {{ $routeMode ? 'true' : 'false' }};
                const center = @json($center);
                const markers = @json($markers);
                const map = L.map('{{ $mapId }}', {
                    scrollWheelZoom: false,
                }).setView([center.lat, center.lng], {{ $zoom }});

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                }).addTo(map);

                const hotelIcon = L.divIcon({
                    className: 'leaflet-div-icon',
                    html: '<div style="width:24px;height:24px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#0ea5e9;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.3);"></div>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 24],
                });

                const activityIcon = L.divIcon({
                    className: 'leaflet-div-icon',
                    html: '<div style="width:24px;height:24px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#f97316;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.3);"></div>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 24],
                });

                const destinationIcon = L.divIcon({
                    className: 'leaflet-div-icon',
                    html: '<div style="width:26px;height:26px;border-radius:50%;background:#075985;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);"></div>',
                    iconSize: [26, 26],
                    iconAnchor: [13, 13],
                });

                const userIcon = L.divIcon({
                    className: 'leaflet-div-icon',
                    html: '<div style="position:relative;width:22px;height:22px;"><span style="position:absolute;inset:0;border-radius:50%;background:rgba(16,185,129,.35);animation:stping 1.6s ease-out infinite;"></span><span style="position:absolute;inset:5px;border-radius:50%;background:#10b981;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35);"></span></div>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                });

                function buildPopupHtml(m) {
                    const distance = m.distance_label ? `<br/><em>${m.distance_label} ${routeMode ? 'from hotel' : 'from you'}</em>` : '';
                    let popup = `<strong>${m.name}</strong><br/>`;
                    if (m.subtitle) popup += `${m.subtitle}<br/>`;
                    if (m.address) popup += `<small>${m.address}</small><br/>`;
                    if (m.rating) popup += `<small>★ ${Number(m.rating).toFixed(1)} (${m.review_count})</small>`;
                    popup += distance;
                    if (m.url) popup += `<br/><a href="${m.url}">View details →</a>`;
                    return popup;
                }

                function iconFor(m) {
                    return m.type === 'hotel' ? hotelIcon
                        : m.type === 'activity' ? activityIcon
                        : destinationIcon;
                }

                const markerLayers = [];
                const deferredMarkers = [];

                markers.forEach((m, idx) => {
                    if (m.deferred) {
                        deferredMarkers.push({ m, idx });
                        return;
                    }
                    const layer = L.marker([m.lat, m.lng], { icon: iconFor(m) });
                    layer.originalMarker = m;
                    layer.bindPopup(buildPopupHtml(m));
                    layer.addTo(map);
                    markerLayers.push(layer);
                });

                {{-- Geolocation core (always available, used by reveal + floating button) --}}
                let userCoords = null;
                let userMarker = null;
                let linesLayer = null;

                // Per-session user location cache so granted coordinates are
                // reused across pages in the same tab (cleared on tab close).
                const COORDS_KEY = 'sunnytrip_user_coords';

                function saveCoords(coords) {
                    try {
                        sessionStorage.setItem(COORDS_KEY, JSON.stringify({
                            lat: coords.lat,
                            lng: coords.lng,
                            at: Date.now(),
                        }));
                    } catch (e) { /* storage unavailable: ignore */ }
                }

                function loadCachedCoords() {
                    try {
                        const raw = sessionStorage.getItem(COORDS_KEY);
                        if (!raw) return null;
                        const c = JSON.parse(raw);
                        if (typeof c.lat !== 'number' || typeof c.lng !== 'number') return null;
                        return { lat: c.lat, lng: c.lng };
                    } catch (e) {
                        return null;
                    }
                }

                // Shared handling for a resolved user location.
                function applyLocation(coords) {
                    userCoords = coords;

                    if (routeMode) {
                        // Route mode: user <-> hotel only.
                        if (userMarker) map.removeLayer(userMarker);
                        userMarker = L.marker([coords.lat, coords.lng], { icon: userIcon })
                            .addTo(map)
                            .bindPopup('<strong>You are here</strong>');

                        const hotel = markerLayers.find((l) => l.originalMarker.type === 'hotel');
                        if (hotel) {
                            const hotelM = hotel.originalMarker;
                            if (userHotelLineLayer) map.removeLayer(userHotelLineLayer);
                            userHotelLineLayer = L.polyline([[coords.lat, coords.lng], [hotelM.lat, hotelM.lng]], {
                                color: '#0ea5e9', weight: 2, dashArray: '6, 8', opacity: 0.5,
                            }).addTo(map);

                            const km = haversineKm(coords.lat, coords.lng, hotelM.lat, hotelM.lng);
                            window.dispatchEvent(new CustomEvent('sunnytrip:user-hotel-distance', {
                                detail: {
                                    hotel: hotelM.name,
                                    distance_km: km,
                                    distance_label: formatKm(km),
                                },
                            }));
                        }
                        return;
                    }

                    computeDistances(coords.lat, coords.lng);
                    drawUserAndLines(coords.lat, coords.lng);
                    dispatchResults();
                }

                function haversineKm(lat1, lng1, lat2, lng2) {
                    const R = 6371;
                    const dLat = (lat2 - lat1) * Math.PI / 180;
                    const dLng = (lng2 - lng1) * Math.PI / 180;
                    const a = Math.sin(dLat / 2) ** 2 +
                        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
                    return 2 * R * Math.asin(Math.sqrt(a));
                }

                function formatKm(km) {
                    if (km < 1) return Math.round(km * 1000) + ' m';
                    const v = Math.round(km * 10) / 10;
                    return (v % 1 === 0 ? v.toFixed(0) : v.toFixed(1)) + ' km';
                }

                function computeDistances(lat, lng) {
                    markerLayers.forEach((layer) => {
                        const m = layer.originalMarker;
                        if (!m.lat || !m.lng) return;
                        const km = haversineKm(lat, lng, m.lat, m.lng);
                        m.distance_km = km;
                        m.distance_label = formatKm(km);
                        layer.setPopupContent(buildPopupHtml(m));
                    });
                }

                function drawUserAndLines(lat, lng) {
                    if (userMarker) map.removeLayer(userMarker);
                    userMarker = L.marker([lat, lng], { icon: userIcon })
                        .addTo(map)
                        .bindPopup('<strong>You are here</strong>');

                    if (linesLayer) map.removeLayer(linesLayer);
                    linesLayer = L.layerGroup().addTo(map);
                    markerLayers.forEach((layer) => {
                        const m = layer.originalMarker;
                        if (!m.lat || !m.lng) return;
                        L.polyline([[lat, lng], [m.lat, m.lng]], {
                            color: '#0ea5e9', weight: 2, dashArray: '6, 8', opacity: 0.5,
                        }).addTo(linesLayer);
                    });

                    const pts = [[lat, lng], ...markerLayers
                        .map((l) => l.originalMarker)
                        .filter((m) => m.lat && m.lng)
                        .map((m) => [m.lat, m.lng])];
                    if (pts.length > 1) {
                        map.fitBounds(L.latLngBounds(pts).pad(0.2), { animate: true, maxZoom: 12 });
                    } else {
                        map.flyTo([lat, lng], Math.max(map.getZoom(), 11));
                    }
                }

                function dispatchResults() {
                    window.dispatchEvent(new CustomEvent('sunnytrip:user-distances', {
                        detail: {
                            results: markerLayers
                                .map((l) => l.originalMarker)
                                .filter((m) => m.distance_km !== null && m.distance_km !== undefined)
                                .map((m) => ({
                                    name: m.name,
                                    type: m.type,
                                    url: m.url || null,
                                    distance_label: m.distance_label,
                                    distance_km: m.distance_km,
                                })),
                        }
                    }));
                }

                let routeLineLayer = null;
                let userHotelLineLayer = null;
                const revealedByIdx = {};
                function revealMarker(idx) {
                    // Already-revealed activity: just re-show its route.
                    if (revealedByIdx[idx] !== undefined) {
                        drawHotelRoute(revealedByIdx[idx]);
                        return;
                    }

                    const entry = deferredMarkers.find((d) => d.idx === idx);
                    if (!entry) return;
                    const { m } = entry;
                    if (!m.lat || !m.lng) return;

                    const layer = L.marker([m.lat, m.lng], { icon: iconFor(m) });
                    layer.originalMarker = m;
                    layer.bindPopup(buildPopupHtml(m));
                    layer.addTo(map);
                    markerLayers.push(layer);
                    deferredMarkers.splice(deferredMarkers.indexOf(entry), 1);
                    revealedByIdx[idx] = layer;

                    if (!routeMode) {
                        if (userCoords) {
                            computeDistances(userCoords.lat, userCoords.lng);
                            drawUserAndLines(userCoords.lat, userCoords.lng);
                            dispatchResults();
                            return;
                        }

                        locateFromBrowser((coords) => {
                            userCoords = coords;
                            computeDistances(coords.lat, coords.lng);
                            drawUserAndLines(coords.lat, coords.lng);
                            dispatchResults();
                        });
                        return;
                    }

                    drawHotelRoute(layer);
                }

                // Route mode: draw a line from the hotel (first marker) to the
                // chosen activity and report the hotel->activity distance.
                function drawHotelRoute(layer) {
                    const hotel = markerLayers.find((l) => l.originalMarker.type === 'hotel');
                    if (!hotel) return;
                    const hotelM = hotel.originalMarker;
                    const m = layer.originalMarker;

                    if (routeLineLayer) map.removeLayer(routeLineLayer);
                    routeLineLayer = L.polyline([[hotelM.lat, hotelM.lng], [m.lat, m.lng]], {
                        color: '#f97316', weight: 2.5, dashArray: '6, 8', opacity: 0.9,
                    }).addTo(map);

                    map.fitBounds(L.latLngBounds([[hotelM.lat, hotelM.lng], [m.lat, m.lng]]).pad(0.25), { animate: true, maxZoom: 13 });

                    const km = haversineKm(hotelM.lat, hotelM.lng, m.lat, m.lng);
                    window.dispatchEvent(new CustomEvent('sunnytrip:hotel-route', {
                        detail: {
                            hotel: hotelM.name,
                            activity: m.name,
                            distance_km: km,
                            distance_label: formatKm(km),
                        },
                    }));
                }

                function locateFromBrowser(onSuccess) {
                    if (!navigator.geolocation) {
                        alert('Your browser does not support location sharing.');
                        return;
                    }
                    const label = document.getElementById('{{ $mapId }}LocateLabel');
                    if (label) label.textContent = 'Locating…';
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            if (label) label.textContent = routeMode ? 'Recalculate Distance' : 'Recalculate distance';
                            saveCoords({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                            onSuccess({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                        },
                        (err) => {
                            if (label) label.textContent = routeMode ? 'Calculate Distance' : 'Distance from me';
                            alert(err.code === 1
                                ? 'Location permission was blocked. Allow access in your browser settings to see distances.'
                                : 'Could not get your location. Please try again.');
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
                    );
                }

                const api = {
                    locate() {
                        locateFromBrowser(applyLocation);
                    },
                    reveal: revealMarker,
                };

                // Auto-apply the cached location on hotel pages so the
                // user->hotel distance appears without another click.
                const cachedCoords = loadCachedCoords();
                if (routeMode && cachedCoords) {
                    const label = document.getElementById('{{ $mapId }}LocateLabel');
                    if (label) label.textContent = 'Recalculate Distance';
                    applyLocation(cachedCoords);
                }

                window['{{ $mapId }}'] = api;
            }
        })();
    </script>
    <style>
        @keyframes stping {
            0% { transform: scale(0.6); opacity: 1; }
            100% { transform: scale(2.2); opacity: 0; }
        }
    </style>
</div>