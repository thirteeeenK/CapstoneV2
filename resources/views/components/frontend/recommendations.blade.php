@php
    $hasAi = !empty($aiRecommendations);
    $hasDefault = !empty($defaultRecommendations);
    $defaultMode = $isPersonalized ? 'ai' : 'default';

    $firstAiDestId = $hasAi ? ($aiRecommendations[0]['destination']->id ?? null) : null;
    $firstDefaultDestId = $hasDefault ? ($defaultRecommendations[0]['destination']->id ?? null) : null;
@endphp

<section x-data="{ 
    mode: '{{ $defaultMode }}',
    activeAiDestId: {{ $firstAiDestId ? (int) $firstAiDestId : 'null' }},
    activeDefaultDestId: {{ $firstDefaultDestId ? (int) $firstDefaultDestId : 'null' }},
    activePackageDestId: {{ $firstDefaultDestId ? (int) $firstDefaultDestId : 'null' }}
}"
    class="bg-white text-slate-900 rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-8 relative overflow-hidden">

    {{-- Header Section with Header Tabs & Action Button --}}
    <div
        class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 border-b border-slate-100 pb-6">

        {{-- Header Mode Tabs: AI Recommendations vs Default Listings vs Tour Packages --}}
        <div class="space-y-3 min-h-[72px]">
            <div class="inline-flex flex-wrap items-center gap-1.5 p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200">
                <button type="button" @click="mode = 'ai'"
                    :class="mode === 'ai' ? 'bg-gradient-to-r from-sky-500 to-sky-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 font-semibold'"
                    class="px-4 py-2.5 rounded-xl text-xs transition-colors duration-150 flex items-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                    <span>AI Recommendations</span>
                    @if($isPersonalized)
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    @endif
                </button>

                <button type="button" @click="mode = 'default'"
                    :class="mode === 'default' ? 'bg-gradient-to-r from-sky-500 to-sky-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 font-semibold'"
                    class="px-4 py-2.5 rounded-xl text-xs transition-colors duration-150 flex items-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">travel_explore</span>
                    <span>Default Listings</span>
                </button>

                <button type="button" @click="mode = 'packages'"
                    :class="mode === 'packages' ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-black shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 font-semibold'"
                    class="px-4 py-2.5 rounded-xl text-xs transition-colors duration-150 flex items-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">card_travel</span>
                    <span>Tour Packages</span>
                </button>
            </div>

            <p class="text-slate-500 text-xs font-body max-w-xl h-5 flex items-center">
                <span x-show="mode === 'ai'" x-cloak>Top 5 sanctuary stays and top 5 experiences matched to your preference profile.</span>
                <span x-show="mode === 'default'" x-cloak>Popular island highlights across top destinations.</span>
                <span x-show="mode === 'packages'" x-cloak>Curated all-inclusive island tour packages combining stays, transfers, and activities.</span>
            </p>
        </div>

        {{-- Action & Destination Tabs --}}
        <div class="flex flex-col items-start lg:items-end gap-3 shrink-0 w-full lg:w-auto min-h-[72px] justify-between">
            {{-- Reset / Personalize Profile Button --}}
            <a href="{{ route('onboarding.reset') }}"
                class="px-4 py-2 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 font-bold text-xs shadow-xs border border-sky-200/80 transition-colors flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px] text-sky-600">tune</span>
                <span>{{ $isPersonalized ? 'Reset Preferences' : 'Personalize My Profile' }}</span>
            </a>

            {{-- Destination Filter Tabs (Instant Swap with Zero Vertical Shift) --}}
            <div class="relative w-full lg:w-auto">
                {{-- AI Mode Filters --}}
                @if($hasAi)
                    <div x-show="mode === 'ai'" x-cloak
                        class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 max-w-full p-1.5 bg-slate-100/90 rounded-2xl border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 shrink-0 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-slate-400">filter_alt</span>
                            <span>Sanctuary:</span>
                        </span>
                        @foreach($aiRecommendations as $item)
                            @php $dest = $item['destination']; @endphp
                            <button type="button" @click="activeAiDestId = {{ $dest->id }}"
                                :class="activeAiDestId === {{ $dest->id }} ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                class="px-3.5 py-1.5 rounded-xl text-xs transition-colors shrink-0 flex items-center gap-1.5 border cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]" :class="activeAiDestId === {{ $dest->id }} ? 'text-white' : 'text-sky-600'">location_on</span>
                                <span>{{ $dest->name }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Default Mode Filters --}}
                @if($hasDefault)
                    <div x-show="mode === 'default'" x-cloak
                        class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 max-w-full p-1.5 bg-slate-100/90 rounded-2xl border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 shrink-0 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-slate-400">filter_alt</span>
                            <span>Sanctuary:</span>
                        </span>
                        @foreach($defaultRecommendations as $item)
                            @php $dest = $item['destination']; @endphp
                            <button type="button" @click="activeDefaultDestId = {{ $dest->id }}"
                                :class="activeDefaultDestId === {{ $dest->id }} ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                class="px-3.5 py-1.5 rounded-xl text-xs transition-colors shrink-0 flex items-center gap-1.5 border cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]" :class="activeDefaultDestId === {{ $dest->id }} ? 'text-white' : 'text-sky-600'">location_on</span>
                                <span>{{ $dest->name }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Packages Mode Filters --}}
                @if($hasDefault)
                    <div x-show="mode === 'packages'" x-cloak
                        class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 max-w-full p-1.5 bg-slate-100/90 rounded-2xl border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 shrink-0 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-slate-400">filter_alt</span>
                            <span>Sanctuary:</span>
                        </span>
                        @foreach($defaultRecommendations as $item)
                            @php $dest = $item['destination']; @endphp
                            <button type="button" @click="activePackageDestId = {{ $dest->id }}"
                                :class="activePackageDestId === {{ $dest->id }} ? 'bg-amber-500 text-slate-950 font-extrabold shadow-xs border-amber-500' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                class="px-3.5 py-1.5 rounded-xl text-xs transition-colors shrink-0 flex items-center gap-1.5 border cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]" :class="activePackageDestId === {{ $dest->id }} ? 'text-slate-950' : 'text-amber-600'">location_on</span>
                                <span>{{ $dest->name }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Content Area 1: AI Recommendations Mode --}}
    <div x-show="mode === 'ai'" x-cloak class="space-y-8">
        @if(!$hasAi)
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                <span class="material-symbols-outlined text-4xl text-sky-500">auto_awesome</span>
                <p class="text-sm font-semibold text-slate-700">No AI Recommendations available yet.</p>
                <p class="text-xs text-slate-500 max-w-md mx-auto">Complete your travel preferences quiz to get tailored recommendations.</p>
                <a href="{{ route('onboarding.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-sky-600 text-white font-bold text-xs shadow-sm hover:bg-sky-700 transition-colors">
                    Take Quiz Now
                </a>
            </div>
        @else
            @foreach($aiRecommendations as $item)
                @php
                    $dest = $item['destination'];
                    $hotels = $item['hotels'];
                    $activities = $item['activities'];
                @endphp
                <div x-show="activeAiDestId === {{ $dest->id }}" x-cloak class="space-y-8">
                    {{-- 1. AI HOTELS --}}
                    @if($hotels->isNotEmpty())
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sky-600 text-[22px]">hotel</span>
                                    <h3 class="text-base sm:text-lg font-extrabold text-slate-900 font-headline">
                                        Top {{ $hotels->count() }} Recommended Sanctuary Stays in {{ $dest->name }}
                                    </h3>
                                </div>
                                <span class="text-[11px] font-bold text-sky-700 bg-sky-50 px-3 py-1 rounded-full border border-sky-200">
                                    Ranked by AI Match
                                </span>
                            </div>

                            @if(!empty($item['hotels_reason']))
                                <p class="text-xs sm:text-[13px] text-sky-800 bg-sky-50 border border-sky-100 rounded-xl px-4 py-3 leading-relaxed flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[18px] shrink-0 text-sky-500 mt-0.5">auto_awesome</span>
                                    <span>{{ $item['hotels_reason'] }}</span>
                                </p>
                            @else
                                <p class="text-xs text-slate-500">
                                    Complete your travel profile to see why these stays were chosen for you.
                                    <a href="{{ route('onboarding.reset') }}" class="font-bold text-sky-600 hover:underline">Personalize now</a>
                                </p>
                            @endif

                            @php
                                $hCols = match (true) {
                                    $hotels->count() === 1 => 'grid-cols-1 max-w-md mx-auto',
                                    $hotels->count() === 2 => 'grid-cols-1 md:grid-cols-2 max-w-3xl mx-auto',
                                    $hotels->count() === 3 => 'grid-cols-1 md:grid-cols-3',
                                    $hotels->count() === 4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                                    default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
                                };
                            @endphp

                            <div class="grid {{ $hCols }} gap-5">
                                @foreach($hotels as $rank => $hotel)
                                    @php
                                        $hotelImagesRaw = is_array($hotel->images) ? $hotel->images : (is_string($hotel->images) ? (json_decode($hotel->images, true) ?: []) : []);
                                        $hotelImg = App\Concerns\ResolvesImages::resolveImg(
                                            $hotelImagesRaw[0] ?? null,
                                            'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80'
                                        );
                                    @endphp
                                    <a href="{{ route('hotels.show', $hotel->id) }}"
                                        class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                        <div>
                                            <div class="relative h-40 overflow-hidden bg-slate-100">
                                                <img src="{{ $hotelImg }}" alt="{{ $hotel->hotel_name }}"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>
                                                @if($hotel->type)
                                                    <div class="absolute bottom-2.5 left-2.5 bg-slate-900/80 text-white text-[10px] font-semibold px-2 py-0.5 rounded-md">
                                                        {{ ucwords(str_replace('-', ' ', $hotel->type)) }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="p-4 space-y-2">
                                                <h4 class="text-sm font-bold text-slate-900 group-hover:text-sky-600 transition-colors font-headline line-clamp-1">
                                                    {{ $hotel->hotel_name }}
                                                </h4>
                                                <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                                    {{ $hotel->hotel_description }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="p-4 pt-0">
                                            <span class="w-full py-2 px-3 rounded-xl bg-slate-50 group-hover:bg-sky-600 text-slate-700 group-hover:text-white font-bold text-[11px] transition-all duration-300 flex items-center justify-between border border-slate-200 group-hover:border-sky-600">
                                                <span>View Sanctuary</span>
                                                <span class="material-symbols-outlined text-[15px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- 2. AI ACTIVITIES --}}
                    @if($activities->isNotEmpty())
                        <div class="space-y-4 pt-2">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-emerald-600 text-[22px]">explore</span>
                                    <h3 class="text-base sm:text-lg font-extrabold text-slate-900 font-headline">
                                        Top {{ $activities->count() }} Must-Try Experiences in {{ $dest->name }}
                                    </h3>
                                </div>
                                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                                    Curated AI Matches
                                </span>
                            </div>

                            @if(!empty($item['activities_reason']))
                                <p class="text-xs sm:text-[13px] text-emerald-800 bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3 leading-relaxed flex items-start gap-2.5">
                                    <span class="material-symbols-outlined text-[18px] shrink-0 text-emerald-500 mt-0.5">auto_awesome</span>
                                    <span>{{ $item['activities_reason'] }}</span>
                                </p>
                            @else
                                <p class="text-xs text-slate-500">
                                    Complete your travel profile to see why these experiences were chosen for you.
                                    <a href="{{ route('onboarding.reset') }}" class="font-bold text-emerald-600 hover:underline">Personalize now</a>
                                </p>
                            @endif

                            @php
                                $aCols = match (true) {
                                    $activities->count() === 1 => 'grid-cols-1 max-w-md mx-auto',
                                    $activities->count() === 2 => 'grid-cols-1 md:grid-cols-2 max-w-3xl mx-auto',
                                    $activities->count() === 3 => 'grid-cols-1 md:grid-cols-3',
                                    $activities->count() === 4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                                    default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
                                };
                            @endphp

                            <div class="grid {{ $aCols }} gap-5">
                                @foreach($activities as $rank => $act)
                                    @php
                                        $actImagesRaw = is_array($act->images) ? $act->images : (is_string($act->images) ? (json_decode($act->images, true) ?: []) : []);
                                        $actImg = App\Concerns\ResolvesImages::resolveActivityImage(
                                            $actImagesRaw[0] ?? null,
                                            $act->activity_name,
                                            $act->category
                                        );
                                    @endphp
                                    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-emerald-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                        <div>
                                            <div class="relative h-40 overflow-hidden bg-slate-100">
                                                <img src="{{ $actImg }}" alt="{{ $act->activity_name }}"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>
                                                @if($act->rate)
                                                    <div class="absolute bottom-2.5 right-2.5 bg-slate-900/85 text-emerald-300 font-extrabold text-[11px] px-2 py-0.5 rounded-md">
                                                        {{ App\Concerns\ResolvesImages::formatRate($act->rate) }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="p-4 space-y-2">
                                                <h4 class="text-sm font-bold text-slate-900 group-hover:text-emerald-600 transition-colors font-headline line-clamp-1">
                                                    {{ $act->activity_name }}
                                                </h4>
                                                <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                                    {{ $act->description }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="p-4 pt-0">
                                            <a href="{{ route('activities.index') }}"
                                                class="w-full py-2 px-3 rounded-xl bg-slate-50 hover:bg-emerald-600 text-slate-700 hover:text-white font-bold text-[11px] transition-all duration-300 flex items-center justify-between border border-slate-200 hover:border-emerald-600">
                                                <span>View Experience</span>
                                                <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- Content Area 2: Default Listings Mode --}}
    <div x-show="mode === 'default'" x-cloak class="space-y-8">
        @if(!$hasDefault)
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-200 text-slate-400 text-sm">
                No listings available.
            </div>
        @else
            @foreach($defaultRecommendations as $item)
                @php
                    $dest = $item['destination'];
                    $hotels = $item['hotels'];
                    $activities = $item['activities'];
                @endphp
                <div x-show="activeDefaultDestId === {{ $dest->id }}" x-cloak class="space-y-8">
                    {{-- 1. DEFAULT HOTELS --}}
                    @if($hotels->isNotEmpty())
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sky-600 text-[22px]">hotel</span>
                                    <h3 class="text-base sm:text-lg font-extrabold text-slate-900 font-headline">
                                        Popular Hotels in {{ $dest->name }}
                                    </h3>
                                </div>
                                <span class="text-[11px] font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
                                    Popular Stays
                                </span>
                            </div>

                            @php
                                $hColsDef = match (true) {
                                    $hotels->count() === 1 => 'grid-cols-1 max-w-md mx-auto',
                                    $hotels->count() === 2 => 'grid-cols-1 md:grid-cols-2 max-w-3xl mx-auto',
                                    $hotels->count() === 3 => 'grid-cols-1 md:grid-cols-3',
                                    $hotels->count() === 4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                                    default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
                                };
                            @endphp

                            <div class="grid {{ $hColsDef }} gap-5">
                                @foreach($hotels as $hotel)
                                    @php
                                        $hotelImagesRaw = is_array($hotel->images) ? $hotel->images : (is_string($hotel->images) ? (json_decode($hotel->images, true) ?: []) : []);
                                        $hotelImg = App\Concerns\ResolvesImages::resolveImg(
                                            $hotelImagesRaw[0] ?? null,
                                            'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80'
                                        );
                                    @endphp
                                    <a href="{{ route('hotels.show', $hotel->id) }}"
                                        class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                        <div>
                                            <div class="relative h-40 overflow-hidden bg-slate-100">
                                                <img src="{{ $hotelImg }}" alt="{{ $hotel->hotel_name }}"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>
                                                @if($hotel->type)
                                                    <div class="absolute bottom-2.5 left-2.5 bg-slate-900/80 text-white text-[10px] font-semibold px-2 py-0.5 rounded-md">
                                                        {{ ucwords(str_replace('-', ' ', $hotel->type)) }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="p-4 space-y-2">
                                                <h4 class="text-sm font-bold text-slate-900 group-hover:text-sky-600 transition-colors font-headline line-clamp-1">
                                                    {{ $hotel->hotel_name }}
                                                </h4>
                                                <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                                    {{ $hotel->hotel_description }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="p-4 pt-0">
                                            <span class="w-full py-2 px-3 rounded-xl bg-slate-50 group-hover:bg-sky-600 text-slate-700 group-hover:text-white font-bold text-[11px] transition-all duration-300 flex items-center justify-between border border-slate-200 group-hover:border-sky-600">
                                                <span>Explore Sanctuary</span>
                                                <span class="material-symbols-outlined text-[15px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- 2. DEFAULT ACTIVITIES --}}
                    @if($activities->isNotEmpty())
                        <div class="space-y-4 pt-2">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-emerald-600 text-[22px]">explore</span>
                                    <h3 class="text-base sm:text-lg font-extrabold text-slate-900 font-headline">
                                        Popular Experiences in {{ $dest->name }}
                                    </h3>
                                </div>
                                <span class="text-[11px] font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
                                    Top Highlights
                                </span>
                            </div>

                            @php
                                $aColsDef = match (true) {
                                    $activities->count() === 1 => 'grid-cols-1 max-w-md mx-auto',
                                    $activities->count() === 2 => 'grid-cols-1 md:grid-cols-2 max-w-3xl mx-auto',
                                    $activities->count() === 3 => 'grid-cols-1 md:grid-cols-3',
                                    $activities->count() === 4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                                    default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
                                };
                            @endphp

                            <div class="grid {{ $aColsDef }} gap-5">
                                @foreach($activities as $rank => $act)
                                    @php
                                        $actImagesRaw = is_array($act->images) ? $act->images : (is_string($act->images) ? (json_decode($act->images, true) ?: []) : []);
                                        $actImg = App\Concerns\ResolvesImages::resolveActivityImage(
                                            $actImagesRaw[0] ?? null,
                                            $act->activity_name,
                                            $act->category
                                        );
                                    @endphp
                                    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-emerald-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                        <div>
                                            <div class="relative h-40 overflow-hidden bg-slate-100">
                                                <img src="{{ $actImg }}" alt="{{ $act->activity_name }}"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>
                                                @if($act->rate)
                                                    <div class="absolute bottom-2.5 right-2.5 bg-slate-900/85 text-emerald-300 font-extrabold text-[11px] px-2 py-0.5 rounded-md">
                                                        {{ App\Concerns\ResolvesImages::formatRate($act->rate) }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="p-4 space-y-2">
                                                <h4 class="text-sm font-bold text-slate-900 group-hover:text-emerald-600 transition-colors font-headline line-clamp-1">
                                                    {{ $act->activity_name }}
                                                </h4>
                                                <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                                    {{ $act->description }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="p-4 pt-0">
                                            <a href="{{ route('activities.index') }}"
                                                class="w-full py-2 px-3 rounded-xl bg-slate-50 hover:bg-emerald-600 text-slate-700 hover:text-white font-bold text-[11px] transition-all duration-300 flex items-center justify-between border border-slate-200 hover:border-emerald-600">
                                                <span>View Experience</span>
                                                <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>
            @endforeach
        @endif
    </div>

    {{-- Content Area 3: Tour Packages Mode --}}
    <div x-show="mode === 'packages'" x-cloak class="space-y-8">
        @if(!$hasDefault)
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-200 text-slate-400 text-sm">
                No packages available.
            </div>
        @else
            @foreach($defaultRecommendations as $item)
                @php
                    $dest = $item['destination'];
                    $packages = $item['packages'] ?? collect();
                @endphp
                <div x-show="activePackageDestId === {{ $dest->id }}" x-cloak class="space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-amber-600 text-[24px]">card_travel</span>
                            <div>
                                <h3 class="text-base sm:text-lg font-black text-slate-900 font-headline">
                                    Tour Packages & All-Inclusive Promos in {{ $dest->name }}
                                </h3>
                                <p class="text-xs text-slate-500">Save big with flights, hotel stays, airport transfers, and guided island hopping.</p>
                            </div>
                        </div>
                        <a href="{{ route('packages.index', ['destination_id' => $dest->id]) }}"
                            class="text-xs font-bold text-amber-950 bg-amber-500 hover:bg-amber-600 px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5 shrink-0">
                            <span>Browse Catalog</span>
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </div>

                    @if($packages->isEmpty())
                        <div class="p-8 text-center bg-slate-50 rounded-2xl border border-slate-200 text-slate-400 text-xs">
                            No active promo packages listed for {{ $dest->name }} yet.
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($packages as $pkg)
                                @php
                                    $imagesRaw = is_array($pkg->images) ? $pkg->images : (is_string($pkg->images) ? (json_decode($pkg->images, true) ?: []) : []);
                                    $pkgImg = App\Concerns\ResolvesImages::resolveImg(
                                        $imagesRaw[0] ?? null,
                                        'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'
                                    );
                                    $inclusionsRaw = is_array($pkg->generic_inclusions) ? $pkg->generic_inclusions : (is_string($pkg->generic_inclusions) ? array_filter(array_map('trim', explode(',', $pkg->generic_inclusions))) : []);
                                @endphp
                                <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-amber-400 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                    <div>
                                        <div class="relative h-44 overflow-hidden bg-slate-900">
                                            <img src="{{ $pkgImg }}" alt="{{ $pkg->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent"></div>

                                            <div class="absolute top-3 right-3 bg-slate-950/90 text-emerald-400 font-extrabold text-xs px-2.5 py-1 rounded-xl border border-white/10 shadow-xs z-10">
                                                ₱{{ number_format((float) $pkg->price, 2) }} <span class="text-[10px] font-normal text-slate-300">/ pax</span>
                                            </div>

                                            <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between text-white text-xs z-10">
                                                <span class="font-bold flex items-center gap-1 bg-black/40 backdrop-blur-xs px-2 py-0.5 rounded-md">
                                                    <span class="material-symbols-outlined text-[14px] text-amber-400">location_on</span>
                                                    <span>{{ $dest->name }}</span>
                                                </span>
                                                @if($pkg->days && $pkg->nights)
                                                    <span class="font-bold bg-white/20 backdrop-blur-xs px-2 py-0.5 rounded-md">
                                                        {{ $pkg->days }}D / {{ $pkg->nights }}N
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="p-5 space-y-3">
                                            @if($pkg->type)
                                                <div class="inline-block px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 text-[11px] font-extrabold tracking-wide border border-amber-200/80">
                                                    {{ $pkg->type }}
                                                </div>
                                            @endif

                                            <div class="flex items-start justify-between gap-2">
                                                <h4 class="text-base font-black text-slate-900 group-hover:text-amber-600 transition-colors font-headline line-clamp-1">
                                                    {{ $pkg->name }}
                                                </h4>
                                                <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md shrink-0 border border-slate-200">
                                                    Min {{ $pkg->min_pax }} Pax
                                                </span>
                                            </div>

                                            @if(!empty($inclusionsRaw))
                                                <div class="space-y-1 pt-1 border-t border-slate-100 text-xs">
                                                    @foreach(array_slice($inclusionsRaw, 0, 3) as $inc)
                                                        <div class="flex items-center gap-1.5 text-slate-700 font-medium">
                                                            <span class="material-symbols-outlined text-[14px] text-emerald-500 shrink-0">check_circle</span>
                                                            <span class="line-clamp-1">{{ $inc }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="p-5 pt-0">
                                        <a href="{{ route('packages.index', ['destination_id' => $dest->id]) }}"
                                            class="w-full py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition-colors flex items-center justify-between shadow-xs">
                                            <span>View Tour Package</span>
                                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

</section>