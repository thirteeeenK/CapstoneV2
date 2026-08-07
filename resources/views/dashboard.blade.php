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

            {{-- DSS Destination Overview Map --}}
            @if(!empty($mapMarkers))
                <section class="space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-900 font-headline tracking-tight">
                                Explore Islands on the Map
                            </h2>
                            <p class="text-slate-500 text-xs sm:text-sm font-body">
                                Tap a marker to see what each destination has in store — then plan your next escape.
                            </p>
                        </div>
                        <a href="{{ route('explore') }}"
                            class="inline-flex items-center gap-1.5 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-700">
                            <span class="material-symbols-outlined text-[16px]">map</span>
                            Open Full Map
                        </a>
                    </div>
                    <x-frontend.map :markers="$mapMarkers" :center="null" :zoom="6" height="h-80" />
                </section>
            @endif

            {{-- AI Recommendations & Default Listings Tabs Section --}}
            <x-frontend.recommendations :is-personalized="$isPersonalized" :ai-recommendations="$aiRecommendations"
                :default-recommendations="$defaultRecommendations" />

        </div>
    </div>
</x-frontend.layout>