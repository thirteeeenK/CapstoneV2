<x-frontend.layout title="My Dashboard — SunnyTrips">
    <div class="py-10 bg-sand-50/70 text-slate-900 min-h-screen relative overflow-hidden">

        {{-- Background Soft Ambient Mesh Glows --}}
        <div
            class="absolute top-10 left-1/3 w-[500px] h-[300px] bg-sky-200/40 blur-3xl rounded-full pointer-events-none">
        </div>
        <div
            class="absolute bottom-10 right-1/3 w-[400px] h-[300px] bg-indigo-200/30 blur-3xl rounded-full pointer-events-none">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 relative z-10">

            {{-- Dashboard User Header Banner (Light Mode Theme) --}}
            <div
                class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($isPersonalized)
                            <span
                                class="px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-[11px] font-extrabold uppercase tracking-widest border border-sky-200 flex items-center gap-1.5 shadow-xs">
                                <span class="material-symbols-outlined text-[15px] text-sky-600">auto_awesome</span>
                                <span>Recommendation Active</span>
                            </span>
                        @else
                            <span
                                class="px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-[11px] font-extrabold uppercase tracking-widest border border-amber-200 flex items-center gap-1.5 shadow-xs">
                                <span class="material-symbols-outlined text-[15px] text-amber-600">travel_explore</span>
                                <span>Popular Highlights Active</span>
                            </span>
                        @endif

                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 font-headline tracking-tight">
                        Welcome back, {{ Auth::user()->name }}!
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm font-body">
                        Logged in as <span class="text-slate-800 font-semibold">{{ Auth::user()->email }}</span>.
                        Explore your island recommendations below.
                    </p>
                </div>
            </div>

            {{-- AI Recommendations & Default Listings Tabs Section (First Priority) --}}
            <x-frontend.recommendations :is-personalized="$isPersonalized" :ai-recommendations="$aiRecommendations"
                :default-recommendations="$defaultRecommendations" />

            {{-- DSS Destination Overview Map Section (Below Recommendations) --}}
            @if(!empty($mapMarkers))
                <section class="bg-white border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-sm space-y-5">
                    <div class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-100 pb-4">
                        <div class="space-y-1">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-ocean-50 text-ocean-700 text-xs font-bold uppercase tracking-widest border border-ocean-200">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">map</span>
                                <span>Interactive Map Explorer</span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-900 font-headline tracking-tight">
                                Explore Destinations Across the Philippines
                            </h2>
                            <p class="text-slate-500 text-xs sm:text-sm font-body">
                                Tap markers on the map to explore sanctuary hotels and local experiences across all partner islands.
                            </p>
                        </div>
                        <a href="{{ route('explore') }}"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 px-4 py-2.5 text-xs font-bold text-white transition-all shadow-xs cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">explore</span>
                            <span>Open Full Explorer Map</span>
                        </a>
                    </div>

                    <div x-data="dashboardMap()"
                         @sunnytrip:map-select.window="onSelect($event.detail)"
                         @keydown.escape.window="close()"
                         class="rounded-2xl border border-slate-200/80 overflow-hidden shadow-xs">
                        <div class="grid lg:grid-cols-3">
                            <div class="lg:col-span-2">
                                <x-frontend.map id="dashboard-map" :markers="$mapMarkers" :center="null" :zoom="6" height="h-80 sm:h-96" />
                            </div>
                            <div class="hidden lg:flex lg:flex-col justify-center border-l border-slate-200 bg-slate-50/60 p-4">
                                <div x-show="selected" x-transition.opacity.duration.300ms x-html="cardHtml" class="w-full"></div>
                                <div x-show="!selected" class="flex h-full min-h-[16rem] items-center justify-center rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400 font-body">
                                    Tap a destination pin on the map to preview its sanctuary stays and local experiences.
                                </div>
                            </div>
                        </div>

                        {{-- Mobile bottom sheet --}}
                        <div x-show="open" x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                             class="lg:hidden fixed inset-x-0 bottom-0 z-[60] rounded-t-3xl border border-slate-200 bg-white p-4 shadow-2xl"
                             style="display:none">
                            <button type="button" @click="close()"
                                    class="absolute right-3 top-3 rounded-full bg-slate-100 p-1.5 text-slate-500 hover:bg-slate-200 cursor-pointer">
                                <span class="material-symbols-outlined text-[18px]">close</span>
                            </button>
                            <div x-html="cardHtml"></div>
                        </div>
                    </div>
                </section>
            @endif

            <script>
                function dashboardMap() {
                    const fallbackImg = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80';

                    function resolveImage(m) {
                        if (!m) return fallbackImg;
                        let raw = m.cover_image || (Array.isArray(m.images) && m.images.length > 0 ? m.images[0] : null) || m.image;
                        if (!raw || typeof raw !== 'string') return fallbackImg;
                        raw = raw.trim();
                        if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:')) {
                            return raw;
                        }
                        if (raw.startsWith('/storage/')) {
                            return raw;
                        }
                        if (raw.startsWith('storage/')) {
                            return '/' + raw;
                        }
                        return '/storage/' + raw.replace(/^\/+/, '');
                    }

                    return {
                        selected: null,
                        open: false,
                        cardHtml: '',
                        onSelect(m) {
                            if (!m) return;
                            this.selected = m;
                            const imgUrl = resolveImage(m);
                            const price = m.cheapest_price
                                ? 'from ₱' + Number(m.cheapest_price).toLocaleString('en-US')
                                : (m.rate ? '₱' + Number(m.rate).toLocaleString('en-US') : '');

                            const weatherBadge = (m.weather && m.weather.icon)
                                ? `<span class="inline-flex items-center gap-1 rounded-full bg-white/90 backdrop-blur-xs px-2.5 py-1 text-[11px] font-bold text-slate-700 shadow-xs">
                                     <img src="https://openweathermap.org/img/wn/${m.weather.icon}.png" class="h-4 w-4" alt="">
                                     <span>${Math.round(m.weather.temp || 0)}°C</span>
                                   </span>`
                                : '';

                            const typeLabel = m.type === 'destination' ? 'Destination' : (m.type === 'hotel' ? 'Sanctuary Stay' : 'Experience');
                            const staysCount = m.hotel_count || 0;
                            const expCount = m.activity_count || 0;
                            const exploreUrl = m.url || `{{ url('/hotels') }}?destination=${m.id}`;
                            const explorerMapUrl = `{{ route('explore') }}?focus=destination:${m.id}`;

                            this.cardHtml = `
                                <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm hover:shadow-md transition-all duration-300">
                                    <div class="relative h-44 w-full overflow-hidden bg-slate-100 group">
                                        <img src="${imgUrl}" 
                                             alt="${m.name || 'Destination'}" 
                                             onerror="this.onerror=null;this.src='${fallbackImg}';" 
                                             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-950/20 to-transparent"></div>
                                        
                                        ${weatherBadge ? `<div class="absolute top-2.5 right-2.5 flex items-center gap-1.5">${weatherBadge}</div>` : ''}

                                        ${price ? `<span class="absolute bottom-2.5 right-2.5 rounded-lg bg-slate-900/90 backdrop-blur-xs px-2.5 py-1 text-xs font-black text-emerald-400 border border-emerald-500/20">${price}</span>` : ''}

                                        <div class="absolute bottom-2.5 left-3 right-16">
                                            <span class="inline-block rounded-md bg-sky-500/90 backdrop-blur-xs px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-widest text-white mb-0.5">${typeLabel}</span>
                                            <h3 class="font-headline text-base font-black text-white line-clamp-1 drop-shadow-sm">${m.name || ''}</h3>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3 p-4">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 font-label">
                                                <span class="material-symbols-outlined text-[13px] text-ocean-600">hotel</span>
                                                <span>${staysCount} stays</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 font-label">
                                                <span class="material-symbols-outlined text-[13px] text-coral-500">kayaking</span>
                                                <span>${expCount} experiences</span>
                                            </span>
                                            ${m.distance_label ? `<span class="inline-flex items-center gap-1 rounded-full bg-ocean-50 text-ocean-700 px-2.5 py-1 text-[11px] font-bold font-label"><span>${m.distance_label} away</span></span>` : ''}
                                        </div>

                                        <div class="flex items-center gap-2 pt-1 border-t border-slate-100">
                                            <a href="${exploreUrl}" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-ocean-600 hover:bg-ocean-700 px-3 py-2.5 text-center text-xs font-bold text-white transition shadow-xs cursor-pointer">
                                                <span class="material-symbols-outlined text-[14px]">bed</span>
                                                <span>Explore Hotels</span>
                                            </a>
                                            <a href="${explorerMapUrl}" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 px-3 py-2.5 text-center text-xs font-bold text-slate-700 transition cursor-pointer">
                                                <span class="material-symbols-outlined text-[14px]">explore</span>
                                                <span>Open in Explorer</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>`;
                            this.open = true;
                            const api = window['dashboard-map'];
                            if (api) api.highlight(`${m.type}-${m.id}`, true);
                        },
                        close() {
                            this.open = false;
                            this.selected = null;
                        },
                    };
                }
            </script>

        </div>
    </div>
</x-frontend.layout>