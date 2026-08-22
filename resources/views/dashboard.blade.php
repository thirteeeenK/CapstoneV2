<x-frontend.layout title="My Dashboard — SunnyTrips">
    <div class="py-10 bg-slate-50 text-slate-900 min-h-screen relative overflow-hidden">

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
                                <span>AI Personalization Active</span>
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
                            <div class="hidden lg:block border-l border-slate-200 bg-slate-50/60 p-4">
                                <template x-if="selected">
                                    <div x-transition.opacity.duration.300ms>
                                        <x-frontend.map-preview-card :marker="null" />
                                    </div>
                                </template>
                                <template x-if="!selected">
                                    <div class="flex h-full min-h-[16rem] items-center justify-center rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">
                                        Tap a destination pin on the map to preview its sanctuary stays and local experiences.
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Mobile bottom sheet --}}
                        <div x-show="open" x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                             class="fixed inset-x-0 bottom-0 z-[60] rounded-t-3xl border border-slate-200 bg-white p-4 shadow-2xl"
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
                    return {
                        selected: null,
                        open: false,
                        cardHtml: '',
                        onSelect(m) {
                            this.selected = m;
                            const image = m.cover_image || (m.images ? m.images[0] : null) || (m.image ? '/storage/' + m.image : null);
                            const price = m.cheapest_price
                                ? 'from ₱' + Number(m.cheapest_price).toLocaleString('en-US')
                                : (m.rate ? '{{ App\\Concerns\\ResolvesImages::formatRate(m.rate ?? 0) }}' : '');
                            const weather = (m.weather && m.weather.icon)
                                ? `<div class="mt-1 flex items-center gap-1 text-xs text-slate-600"><img src="https://openweathermap.org/img/wn/${m.weather.icon}.png" class="h-5 w-5" alt=""> ${Math.round(m.weather.temp || 0)}°C</div>`
                                : '';
                            this.cardHtml = `
                                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                    <div class="relative h-36 overflow-hidden bg-slate-100">
                                        <img src="${image}" alt="${m.name}" class="h-full w-full object-cover">
                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent"></div>
                                        ${m.cheapest_price || m.rate ? `<span class="absolute bottom-2 right-2 rounded-lg bg-slate-900/85 px-2 py-1 text-xs font-extrabold text-emerald-300">${price}</span>` : ''}
                                    </div>
                                    <div class="space-y-2 p-4">
                                        <p class="text-[11px] font-extrabold uppercase tracking-widest text-sky-600">Destination</p>
                                        <h3 class="font-headline text-base font-black text-slate-900">${m.name}</h3>
                                        ${weather}
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">${m.hotel_count || 0} stays · ${m.activity_count || 0} experiences</span>
                                        </div>
                                        <div class="flex gap-2 pt-1">
                                            <a href="${m.url || '#'}" class="flex-1 rounded-xl bg-sky-600 px-3 py-2 text-center text-xs font-bold text-white hover:bg-sky-700">Explore Hotels</a>
                                            <a href="{{ route('explore') }}?focus=destination:${m.id}" class="flex-1 rounded-xl bg-slate-100 px-3 py-2 text-center text-xs font-bold text-slate-700 hover:bg-slate-200">Open in Explorer</a>
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