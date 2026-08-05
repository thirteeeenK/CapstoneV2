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
    activeDefaultDestId: {{ $firstDefaultDestId ? (int) $firstDefaultDestId : 'null' }}
}"
    class="bg-white text-slate-900 rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-8 relative overflow-hidden">

    {{-- Header Section with Header Tabs & Action Button --}}
    <div
        class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 border-b border-slate-100 pb-6">

        {{-- Header Mode Tabs: AI Recommendations vs Default Listings --}}
        <div class="space-y-3">
            <div class="inline-flex items-center gap-1.5 p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200">
                <button type="button" @click="mode = 'ai'"
                    :class="mode === 'ai' ? 'bg-gradient-to-r from-sky-500 to-sky-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                    class="px-4 py-2.5 rounded-xl text-xs transition-all duration-200 flex items-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                    <span>AI Recommendations</span>
                    @if($isPersonalized)
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    @endif
                </button>

                <button type="button" @click="mode = 'default'"
                    :class="mode === 'default' ? 'bg-gradient-to-r from-sky-500 to-sky-600 text-white font-extrabold shadow-sm' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                    class="px-4 py-2.5 rounded-xl text-xs transition-all duration-200 flex items-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">travel_explore</span>
                    <span>Default Listings</span>
                </button>
            </div>

            <p class="text-slate-500 text-xs font-body max-w-xl">
                <template x-if="mode === 'ai'">
                    <span>Top 5 sanctuary stays and top 5 experiences matched to your travel preference profile.</span>
                </template>
                <template x-if="mode === 'default'">
                    <span>Popular island highlights across top destinations.</span>
                </template>
            </p>
        </div>

        {{-- Action & Destination Tabs --}}
        <div class="flex flex-col items-start lg:items-end gap-3 shrink-0 w-full lg:w-auto">
            {{-- Reset / Personalize Profile Button --}}
            <a href="{{ route('onboarding.reset') }}"
                class="px-4 py-2.5 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 font-bold text-xs shadow-xs border border-sky-200/80 transition-all flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px] text-sky-600">tune</span>
                <span>{{ $isPersonalized ? 'Reset Preferences' : 'Personalize My Profile' }}</span>
            </a>

            {{-- Destination Filter Tabs for AI Mode --}}
            @if($hasAi)
                <div x-show="mode === 'ai'"
                    class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 max-w-full p-1 bg-slate-100/80 rounded-2xl border border-slate-200">
                    @foreach($aiRecommendations as $item)
                        @php $dest = $item['destination']; @endphp
                        <button type="button" @click="activeAiDestId = {{ $dest->id }}"
                            :class="activeAiDestId === {{ $dest->id }} ? 'bg-white text-slate-900 font-bold shadow-xs border-slate-200' : 'text-slate-600 hover:text-slate-900 font-medium border-transparent'"
                            class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 flex items-center gap-2 border cursor-pointer">
                            <span class="material-symbols-outlined text-[16px] text-sky-600">location_on</span>
                            <span>{{ $dest->name }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Destination Filter Tabs for Default Mode --}}
            @if($hasDefault)
                <div x-show="mode === 'default'"
                    class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 max-w-full p-1 bg-slate-100/80 rounded-2xl border border-slate-200">
                    @foreach($defaultRecommendations as $item)
                        @php $dest = $item['destination']; @endphp
                        <button type="button" @click="activeDefaultDestId = {{ $dest->id }}"
                            :class="activeDefaultDestId === {{ $dest->id }} ? 'bg-white text-slate-900 font-bold shadow-xs border-slate-200' : 'text-slate-600 hover:text-slate-900 font-medium border-transparent'"
                            class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 flex items-center gap-2 border cursor-pointer">
                            <span class="material-symbols-outlined text-[16px] text-sky-600">location_on</span>
                            <span>{{ $dest->name }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════
    MODE 1: AI RECOMMENDATIONS TAB CONTENT
    ══════════════════════════════════════════ --}}
    <div x-show="mode === 'ai'">
        @if(!$isPersonalized)
            {{-- Promo CTA Card when AI preferences are not set --}}
            <div
                class="bg-gradient-to-br from-sky-50 to-indigo-50/50 border border-sky-200/80 rounded-3xl p-8 text-center space-y-4 my-4">
                <div
                    class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-300/60 text-sky-600 flex items-center justify-center mx-auto shadow-xs">
                    <span class="material-symbols-outlined text-2xl">auto_awesome</span>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-black text-slate-900 font-headline">Unlock AI Vector Matching</h3>
                    <p class="text-xs sm:text-sm text-slate-600 max-w-md mx-auto leading-relaxed">
                        Answer 4 quick travel questions so our AI recommendation engine can match you with sanctuaries,
                        luxury rooms, and curated experiences.
                    </p>
                </div>
                <a href="{{ route('onboarding.index') }}"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-sky-500 to-sky-600 hover:from-sky-600 hover:to-sky-700 text-white font-extrabold text-xs shadow-sm transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">tune</span>
                    <span>Build My AI Travel Profile</span>
                </a>
            </div>
        @else
            @foreach($aiRecommendations as $group)
                @php
                    $dest = $group['destination'];
                    $hotels = $group['hotels'];
                    $activities = $group['activities'];
                @endphp

                <div x-show="activeAiDestId === {{ $dest->id }}" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 transform scale-95"
                    x-transition:enter-end="opacity-100 transform scale-100" class="space-y-10">

                    {{-- 1. TOP 5 AI HOTELS --}}
                    @if($hotels->isNotEmpty())
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sky-600 text-[22px]">hotel</span>
                                    <h3 class="text-base sm:text-lg font-extrabold text-slate-900 font-headline">
                                        Top {{ $hotels->count() }} Recommended Sanctuary Stays in {{ $dest->name }}
                                    </h3>
                                </div>
                                <span
                                    class="text-[11px] font-bold text-sky-700 bg-sky-50 px-3 py-1 rounded-full border border-sky-200">
                                    Ranked by AI Match
                                </span>
                            </div>

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
                                        $hotelImg = App\Concerns\ResolvesImages::resolveImg(
                                            $hotel->images[0] ?? null,
                                            'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80'
                                        );
                                    @endphp
                                    <a href="{{ route('hotels.show', $hotel->id) }}"
                                        class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                        <div>
                                            <div class="relative h-44 overflow-hidden bg-slate-100">
                                                <img src="{{ $hotelImg }}" alt="{{ $hotel->hotel_name }}"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                                <div
                                                    class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent">
                                                </div>

                                                {{-- Rank Pill --}}
                                                <div
                                                    class="absolute top-2.5 left-2.5 bg-sky-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-[13px]">auto_awesome</span>
                                                    <span>#{{ $rank + 1 }} AI Match</span>
                                                </div>

                                                @if($hotel->type)
                                                    <div
                                                        class="absolute bottom-2.5 left-2.5 bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-semibold px-2 py-0.5 rounded-md">
                                                        {{ ucwords(str_replace('-', ' ', $hotel->type)) }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="p-4 space-y-2">
                                                <h4
                                                    class="text-sm font-bold text-slate-900 group-hover:text-sky-600 transition-colors font-headline line-clamp-1">
                                                    {{ $hotel->hotel_name }}
                                                </h4>
                                                <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                                    {{ $hotel->hotel_description }}
                                                </p>
                                                @if(!empty($hotel->vibe_tags) && is_array($hotel->vibe_tags))
                                                    <div class="flex flex-wrap gap-1 pt-0.5">
                                                        @foreach(array_slice($hotel->vibe_tags, 0, 2) as $tag)
                                                            <span
                                                                class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 text-[9px] font-semibold border border-sky-200">
                                                                #{{ $tag }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="p-4 pt-0">
                                            <span
                                                class="w-full py-2 px-3 rounded-xl bg-slate-50 group-hover:bg-sky-600 text-slate-700 group-hover:text-white font-bold text-[11px] transition-all duration-300 flex items-center justify-between border border-slate-200 group-hover:border-sky-600">
                                                <span>View Sanctuary</span>
                                                <span
                                                    class="material-symbols-outlined text-[15px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- 2. TOP 5 AI ACTIVITIES --}}
                    @if($activities->isNotEmpty())
                        <div class="space-y-4 pt-2">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-emerald-600 text-[22px]">explore</span>
                                    <h3 class="text-base sm:text-lg font-extrabold text-slate-900 font-headline">
                                        Top {{ $activities->count() }} Must-Try Experiences in {{ $dest->name }}
                                    </h3>
                                </div>
                                <span
                                    class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                                    Curated AI Matches
                                </span>
                            </div>

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
                                        $actImg = App\Concerns\ResolvesImages::resolveImg(
                                            $actImagesRaw[0] ?? null,
                                            'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80'
                                        );
                                    @endphp
                                    <div
                                        class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-emerald-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                        <div>
                                            <div class="relative h-40 overflow-hidden bg-slate-100">
                                                <img src="{{ $actImg }}" alt="{{ $act->activity_name }}"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                                <div
                                                    class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent">
                                                </div>

                                                {{-- Rank Pill --}}
                                                <div
                                                    class="absolute top-2.5 left-2.5 bg-emerald-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm flex items-center gap-1">
                                                    <span>#{{ $rank + 1 }} Top Experience</span>
                                                </div>

                                                @if($act->rate)
                                                    <div
                                                        class="absolute bottom-2.5 right-2.5 bg-slate-900/85 text-emerald-300 font-extrabold text-[11px] px-2 py-0.5 rounded-md">
                                                        ₱{{ is_numeric($act->rate) ? number_format((float) $act->rate, 2) : $act->rate }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="p-4 space-y-2">
                                                <h4
                                                    class="text-sm font-bold text-slate-900 group-hover:text-emerald-600 transition-colors font-headline line-clamp-1">
                                                    {{ $act->activity_name }}
                                                </h4>
                                                <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                                    {{ $act->description }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="p-4 pt-0">
                                            <a href="/#experiences"
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

    {{-- ══════════════════════════════════════════
    MODE 2: DEFAULT LISTINGS TAB CONTENT
    ══════════════════════════════════════════ --}}
    <div x-show="mode === 'default'">
        @foreach($defaultRecommendations as $group)
            @php
                $dest = $group['destination'];
                $hotels = $group['hotels'];
                $activities = $group['activities'];
            @endphp

            <div x-show="activeDefaultDestId === {{ $dest->id }}" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100" class="space-y-10">

                {{-- 1. DEFAULT HOTELS --}}
                @if($hotels->isNotEmpty())
                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-sky-600 text-[22px]">hotel</span>
                                <h3 class="text-base sm:text-lg font-extrabold text-slate-900 font-headline">
                                    Popular Sanctuary Stays in {{ $dest->name }}
                                </h3>
                            </div>
                            <span
                                class="text-[11px] font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
                                Popular Highlights
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
                            @foreach($hotels as $rank => $hotel)
                                @php
                                    $hotelImg = App\Concerns\ResolvesImages::resolveImg(
                                        $hotel->images[0] ?? null,
                                        'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80'
                                    );
                                @endphp
                                <a href="{{ route('hotels.show', $hotel->id) }}"
                                    class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                    <div>
                                        <div class="relative h-44 overflow-hidden bg-slate-100">
                                            <img src="{{ $hotelImg }}" alt="{{ $hotel->hotel_name }}"
                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                            <div
                                                class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent">
                                            </div>

                                            @if($hotel->type)
                                                <div
                                                    class="absolute bottom-2.5 left-2.5 bg-slate-900/80 text-white text-[10px] font-semibold px-2 py-0.5 rounded-md">
                                                    {{ ucwords(str_replace('-', ' ', $hotel->type)) }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="p-4 space-y-2">
                                            <h4
                                                class="text-sm font-bold text-slate-900 group-hover:text-sky-600 transition-colors font-headline line-clamp-1">
                                                {{ $hotel->hotel_name }}
                                            </h4>
                                            <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                                {{ $hotel->hotel_description }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="p-4 pt-0">
                                        <span
                                            class="w-full py-2 px-3 rounded-xl bg-slate-50 group-hover:bg-sky-600 text-slate-700 group-hover:text-white font-bold text-[11px] transition-all duration-300 flex items-center justify-between border border-slate-200 group-hover:border-sky-600">
                                            <span>Explore Sanctuary</span>
                                            <span
                                                class="material-symbols-outlined text-[15px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
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
                            <span
                                class="text-[11px] font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
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
                                    $actImg = App\Concerns\ResolvesImages::resolveImg(
                                        $actImagesRaw[0] ?? null,
                                        'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80'
                                    );
                                @endphp
                                <div
                                    class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md hover:border-emerald-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                                    <div>
                                        <div class="relative h-40 overflow-hidden bg-slate-100">
                                            <img src="{{ $actImg }}" alt="{{ $act->activity_name }}"
                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                            <div
                                                class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent">
                                            </div>

                                            @if($act->rate)
                                                <div
                                                    class="absolute bottom-2.5 right-2.5 bg-slate-900/85 text-emerald-300 font-extrabold text-[11px] px-2 py-0.5 rounded-md">
                                                    ₱{{ is_numeric($act->rate) ? number_format((float) $act->rate, 2) : $act->rate }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="p-4 space-y-2">
                                            <h4
                                                class="text-sm font-bold text-slate-900 group-hover:text-emerald-600 transition-colors font-headline line-clamp-1">
                                                {{ $act->activity_name }}
                                            </h4>
                                            <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                                {{ $act->description }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="p-4 pt-0">
                                        <a href="/#experiences"
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
    </div>

</section>