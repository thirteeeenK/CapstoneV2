<section
    class="py-12 sm:py-16 lg:py-24 bg-gradient-to-b from-sand-50/70 via-sand-100/40 to-sand-50/70 border-y border-slate-200/60 overflow-hidden relative"
    id="experiences" x-data="{
        activeTab: 'all',
        levelFilter: 'all',
        searchQuery: '',
        showAll: false,
        previewActivity: null,
        activePreviewImgIdx: 0,
        tabCounts: {'all': {{ $activities->count() }}@foreach($destinations as $dest), '{{ $dest->id }}': {{ $activities->where('destination_id', $dest->id)->count() }}@endforeach},
        matchesActivity(destId, level, searchableText) {
            if (this.activeTab !== 'all' && String(this.activeTab) !== String(destId)) {
                return false;
            }
            if (this.levelFilter !== 'all' && String(this.levelFilter).toLowerCase() !== String(level).toLowerCase()) {
                return false;
            }
            if (this.searchQuery.trim() !== '') {
                let terms = this.searchQuery.toLowerCase().trim().split(/\s+/);
                let sText = (searchableText || '').toLowerCase();
                let matches = terms.every(term => sText.includes(term));
                if (!matches) return false;
            }
            return true;
        }
    }">

    {{-- Decorative Background Mesh/Glow --}}
    <div
        class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[min(800px,100vw)] max-w-[100vw] h-[400px] bg-sky-500/5 blur-3xl rounded-full pointer-events-none">
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 relative z-10">

        {{-- Header Section --}}
        <div class="flex flex-col md:flex-row justify-between items-center md:items-end text-center md:text-left gap-6 reveal-on-scroll">
            <div class="max-w-2xl space-y-3 mx-auto md:mx-0">
                <h2
                    class="font-headline text-2xl sm:text-3xl md:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">
                    Unforgettable Island Adventures
                </h2>
                <p class="text-slate-600 text-[11px] sm:text-xs md:text-sm leading-relaxed max-w-xl font-body mx-auto md:mx-0">
                    Immerse yourself in authentic local activities across our island sanctuaries—from scuba diving in
                    crystal reefs to sunset sailing and mountain treks.
                </p>
            </div>

            {{-- Location Filter Tabs --}}
            @if($destinations->isNotEmpty())
                <div
                    class="flex overflow-x-auto hide-scrollbar snap-x scroll-px-4 md:flex-wrap items-center justify-start gap-1 max-w-full p-1 bg-white/80 backdrop-blur-md rounded-xl border border-slate-200/80 shadow-xs">
                    <button @click="activeTab = 'all'; showAll = false"
                        :class="activeTab === 'all' ? 'bg-gradient-to-r from-sky-600 to-sky-700 text-white font-bold shadow-md shadow-sky-500/25' : 'text-slate-600 hover:bg-slate-100 font-medium'"
                        class="px-2.5 sm:px-4 py-1.5 sm:py-2 rounded-xl text-[11px] sm:text-xs transition-all duration-200 flex items-center gap-1.5 cursor-pointer whitespace-nowrap shrink-0">
                        <span class="material-symbols-outlined text-[13px]">explore</span>
                        <span>All Locations</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px]"
                            :class="activeTab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'">
                            {{ $activities->count() }}
                        </span>
                    </button>

                    @foreach($destinations as $dest)
                        <button @click="activeTab = '{{ $dest->id }}'; showAll = false"
                            :class="activeTab === '{{ $dest->id }}' ? 'bg-gradient-to-r from-sky-600 to-sky-700 text-white font-bold shadow-md shadow-sky-500/25' : 'text-slate-600 hover:bg-slate-100 font-medium'"
                            class="px-2.5 sm:px-4 py-1.5 sm:py-2 rounded-xl text-[11px] sm:text-xs transition-all duration-200 flex items-center gap-1.5 cursor-pointer whitespace-nowrap shrink-0">
                            <span class="material-symbols-outlined text-[13px]">location_on</span>
                            <span>{{ $dest->name }}</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px]"
                                :class="activeTab === '{{ $dest->id }}' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'">
                                {{ $activities->where('destination_id', $dest->id)->count() }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Interactive Toolbar: Search & 5 Activity Level Filters --}}
        <div class="bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-2xl sm:rounded-3xl p-3 sm:p-5 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
            {{-- Search Bar --}}
            <div class="relative w-full md:w-80 shrink-0">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input type="text" x-model="searchQuery" placeholder="Search activities..."
                    class="w-full bg-slate-50 border border-slate-200 focus:border-sky-500 focus:bg-white rounded-2xl py-2.5 pl-10 pr-8 text-base sm:text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none transition-all shadow-xs" />
                <button x-show="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
                    <span class="material-symbols-outlined text-sm">cancel</span>
                </button>
            </div>

            {{-- 5 Activity Level Filter Pills --}}
            <div class="flex flex-wrap items-center gap-1.5 w-full md:w-auto justify-start md:justify-end">
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 shrink-0 mr-1">
                    <span class="material-symbols-outlined text-[15px] text-sky-600">signal_cellular_alt</span>
                    <span>Level:</span>
                </span>

                <button type="button" @click="levelFilter = 'all'"
                    :class="levelFilter === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                    class="px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-xl text-[11px] sm:text-xs transition-all cursor-pointer shrink-0">
                    All Levels
                </button>

                @foreach(['Relaxing' => '🧘', 'Sightseeing' => '🗺️', 'Adventure' => '🏔️', 'Extreme' => '⚡', 'Underwater' => '🤿'] as $lvl => $icon)
                    <button type="button" @click="levelFilter = '{{ $lvl }}'"
                        :class="levelFilter === '{{ $lvl }}' ? 'bg-sky-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'"
                        class="px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-xl text-[11px] sm:text-xs transition-all cursor-pointer shrink-0 flex items-center gap-1">
                        <span>{{ $icon }}</span>
                        <span>{{ $lvl }}</span>
                    </button>
                @endforeach

                <button x-show="searchQuery || levelFilter !== 'all' || activeTab !== 'all'"
                    @click="searchQuery = ''; levelFilter = 'all'; activeTab = 'all'; showAll = false;"
                    class="text-[10px] sm:text-[11px] font-bold text-rose-600 hover:text-rose-700 ml-2 flex items-center gap-1 cursor-pointer shrink-0">
                    <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                    <span>Reset</span>
                </button>
            </div>
        </div>

        {{-- Activities Cards Grid --}}
        @if($activities->isEmpty())
            <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400 text-sm shadow-xs">
                <span class="material-symbols-outlined text-4xl text-slate-300 mb-2 block">explore_off</span>
                No experiences listed yet. Check back soon!
            </div>
        @else
            @php
                $actCount = $activities->count();
                $destPos = [];
                $actGridClass = match (true) {
                    $actCount === 1 => 'grid-cols-1 max-w-2xl mx-auto',
                    $actCount === 2 => 'grid-cols-1 md:grid-cols-2',
                    $actCount === 4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                    default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
                };
            @endphp
            <div class="grid {{ $actGridClass }} gap-5 sm:gap-6 lg:gap-8">
                @foreach($activities as $index => $act)
                    @php
                        $destPos[$act->destination_id] = ($destPos[$act->destination_id] ?? 0) + 1;
                        $posInDest = $destPos[$act->destination_id] - 1;
                        $imagesRaw = is_array($act->images) ? $act->images : (is_string($act->images) ? (json_decode($act->images, true) ?: []) : []);
                        $actImg = App\Concerns\ResolvesImages::resolveActivityImage($imagesRaw[0] ?? null, $act->activity_name, $act->category);
                        $resolvedImages = [$actImg];
                        $formattedRate = App\Concerns\ResolvesImages::formatRate($act->rate);

                        $inclusionsRaw = is_array($act->inclusions) ? $act->inclusions : (is_string($act->inclusions) ? array_filter(array_map('trim', explode(',', $act->inclusions))) : []);
                        $exclusionsRaw = is_array($act->exclusions) ? $act->exclusions : (is_string($act->exclusions) ? array_filter(array_map('trim', explode(',', $act->exclusions))) : []);
                        $itineraryRaw = is_array($act->itinerary) ? $act->itinerary : (is_string($act->itinerary) ? (json_decode($act->itinerary, true) ?: []) : []);
                        $vibeTagsRaw = is_array($act->vibe_tags) ? $act->vibe_tags : (is_string($act->vibe_tags) ? array_filter(array_map('trim', explode(',', $act->vibe_tags))) : []);

                        $destName = $act->destination->name ?? '';
                        $inclusionsStr = implode(' ', $inclusionsRaw);
                        $vibeTagsStr = implode(' ', $vibeTagsRaw);

                        $searchablePayload = implode(' ', [
                            $act->activity_name,
                            $act->category ?? '',
                            $act->activity_level ?? '',
                            $act->description ?? '',
                            $act->requirements ?? '',
                            $act->ideal_for ?? '',
                            $act->notes ?? '',
                            $destName,
                            $inclusionsStr,
                            $vibeTagsStr
                        ]);

                        $actPayload = [
                            'id' => $act->id,
                            'activity_name' => $act->activity_name,
                            'category' => $act->category,
                            'category_icon' => App\Concerns\ResolvesImages::getCategoryIcon($act->category),
                            'rate' => $formattedRate,
                            'duration' => $act->duration,
                            'activity_level' => $act->activity_level,
                            'capacity' => $act->capacity,
                            'requirements' => $act->requirements,
                            'ideal_for' => $act->ideal_for,
                            'description' => $act->description,
                            'notes' => $act->notes,
                            'destination_name' => $act->destination->name ?? null,
                            'destination_id' => $act->destination_id,
                            'images' => $resolvedImages,
                            'inclusions' => array_values($inclusionsRaw),
                            'exclusions' => array_values($exclusionsRaw),
                            'itinerary' => array_values($itineraryRaw),
                            'vibe_tags' => array_values($vibeTagsRaw),
                        ];
                    @endphp
                    <div x-show="matchesActivity('{{ $act->destination_id }}', '{{ addslashes($act->activity_level ?? '') }}', {{ json_encode($searchablePayload) }}) && (showAll || {{ $loop->index }} < 6 || (activeTab === '{{ $act->destination_id }}' && {{ $posInDest }} < 6))"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        class="group relative flex flex-col bg-white border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs hover:shadow-2xl transition-all duration-500 hover:-translate-y-1.5 reveal-on-scroll">

                        {{-- Activity Photo --}}
                        <div class="relative h-52 sm:h-64 w-full overflow-hidden bg-slate-950">
                            <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                src="{{ $actImg }}" alt="{{ $act->activity_name }}" />

                            {{-- Category Badge --}}
                            @if($act->category)
                                <div
                                    class="absolute top-3 left-3 bg-slate-950/80 backdrop-blur-md text-white text-[10px] font-semibold px-3 py-1 rounded-full border border-white/20">
                                    <span>{{ $act->category }}</span>
                                </div>
                            @endif

                            {{-- Price Badge --}}
                            @if($act->rate)
                                <div
                                    class="absolute top-3 right-3 bg-slate-950/85 backdrop-blur-md text-emerald-400 font-black text-xs px-3 py-1 rounded-xl border border-white/20 shadow-md">
                                    {{ $formattedRate }}
                                </div>
                            @endif

                            {{-- Location Badge --}}
                            @if($act->destination)
                                <div
                                    class="absolute bottom-3 left-3 bg-slate-950/75 backdrop-blur-md text-slate-200 text-[11px] font-bold px-3 py-1 rounded-lg flex items-center gap-1 border border-white/10">
                                    <span class="material-symbols-outlined text-sky-400 text-[14px]">location_on</span>
                                    <span>{{ $act->destination->name }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Activity Card Content --}}
                        <div class="p-6 flex flex-col flex-grow space-y-4">
                            <div class="space-y-1.5">
                                <h3
                                    class="font-headline text-lg font-bold text-slate-900 group-hover:text-sky-600 transition-colors">
                                    {{ $act->activity_name }}
                                </h3>
                                <p class="text-slate-500 text-xs leading-relaxed line-clamp-2">
                                    {{ $act->description }}
                                </p>
                            </div>

                            {{-- Specs Badges --}}
                            <div class="flex flex-wrap gap-2 text-[11px] text-slate-600 pt-1">
                                @if($act->duration)
                                    <span
                                        class="bg-slate-50 px-2.5 py-1 rounded-lg flex items-center gap-1 font-medium border border-slate-200/80">
                                        <span class="material-symbols-outlined text-[14px] text-sky-500">schedule</span>
                                        {{ $act->duration }}
                                    </span>
                                @endif
                                @if($act->activity_level)
                                    <span
                                        class="bg-slate-50 px-2.5 py-1 rounded-lg flex items-center gap-1 font-medium border border-slate-200/80">
                                        <span class="material-symbols-outlined text-[14px] text-sky-500">signal_cellular_alt</span>
                                        {{ $act->activity_level }}
                                    </span>
                                @endif
                                @if($act->capacity)
                                    <span
                                        class="bg-slate-50 px-2.5 py-1 rounded-lg flex items-center gap-1 font-medium border border-slate-200/80">
                                        <span class="material-symbols-outlined text-[14px] text-sky-500">group</span>
                                        Max {{ $act->capacity }}
                                    </span>
                                @endif
                            </div>

                            {{-- Vibe Tags --}}
                            @if(!empty($vibeTagsRaw))
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach(array_slice($vibeTagsRaw, 0, 3) as $tag)
                                        <span
                                            class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 text-[10px] font-semibold border border-sky-100">
                                            #{{ $tag }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Footer Dual Buttons --}}
                            <div class="mt-auto pt-4 border-t border-slate-100 grid grid-cols-2 gap-2">
                                <button type="button"
                                    @click="previewActivity = {{ json_encode($actPayload) }}; activePreviewImgIdx = 0;"
                                    class="w-full py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                    <span>Preview</span>
                                </button>
                                @if($act->destination_id)
                                    <a href="{{ route('destinations.show', $act->destination_id) }}"
                                        class="w-full py-2 px-3 rounded-xl bg-sky-50 hover:bg-sky-600 hover:text-white text-sky-700 font-bold text-xs transition-colors flex items-center justify-center gap-1">
                                        <span>Explore</span>
                                        <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                                    </a>
                                @else
                                    <span class="w-full py-2 px-3 text-center text-xs font-bold text-slate-400">Curated</span>
                                @endif
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Show More / Show Less Button --}}
            @php
                $maxDestCount = 0;
                foreach ($destinations as $dest) {
                    $maxDestCount = max($maxDestCount, $activities->where('destination_id', $dest->id)->count());
                }
            @endphp
            @if($activities->count() > 6 || $maxDestCount > 6)
                <div x-show="(tabCounts[activeTab] ?? 0) > 6" class="flex justify-center pt-8 reveal-on-scroll">
                    <button type="button" @click="showAll = !showAll"
                        class="px-8 py-3.5 rounded-full bg-white hover:bg-slate-900 text-slate-800 hover:text-white font-bold text-xs border border-slate-200/90 shadow-md hover:shadow-xl transition-all duration-300 flex items-center gap-2 group cursor-pointer">
                        <span x-text="showAll ? 'Show Less Experiences' : 'Show All Experiences (' + ((tabCounts[activeTab] ?? 0)) + ')'"></span>
                        <span class="material-symbols-outlined text-[18px] transition-transform duration-300"
                            :class="showAll ? 'rotate-180' : 'group-hover:translate-y-0.5'">
                            expand_more
                        </span>
                    </button>
                </div>
            @endif
        @endif

    </div>

    {{-- Dynamic Activity Preview Modal --}}
    <div x-show="previewActivity" x-transition.opacity @keydown.escape.window="previewActivity = null"
        class="fixed inset-0 z-[110] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
        style="display: none;">
        <div @click.away="previewActivity = null"
            class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full overflow-hidden border border-slate-200/80 my-auto transform transition-all">

            {{-- Modal Header --}}
            <div class="relative bg-slate-900 text-white p-6 sm:p-8 overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-sky-500/10 rounded-full blur-3xl pointer-events-none">
                </div>

                <button @click="previewActivity = null"
                    class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>

                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span
                        class="px-2.5 py-0.5 rounded-full bg-sky-500/20 text-sky-300 text-[10px] font-bold uppercase tracking-wider border border-sky-400/30">
                        <span x-text="previewActivity?.category || 'Activity'"></span>
                    </span>
                    <template x-if="previewActivity?.destination_name">
                        <span
                            class="px-2.5 py-0.5 rounded-full bg-white/10 text-slate-200 text-[10px] font-medium flex items-center gap-1">
                            <span class="material-symbols-outlined text-[12px]">location_on</span>
                            <span x-text="previewActivity.destination_name"></span>
                        </span>
                    </template>
                </div>

                <h2 class="text-2xl sm:text-3xl font-black text-white font-headline"
                    x-text="previewActivity?.activity_name"></h2>

                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-headline"
                        x-text="previewActivity?.rate"></span>
                    <span class="text-xs text-slate-300 font-medium">/ person</span>
                </div>
            </div>

            {{-- Modal Body --}}
            <div class="p-6 sm:p-8 space-y-6 max-h-[65vh] overflow-y-auto text-xs sm:text-sm text-slate-700">

                {{-- Image Carousel --}}
                <template x-if="previewActivity?.images && previewActivity.images.length > 0">
                    <div class="space-y-3">
                        <div
                            class="relative h-64 sm:h-80 rounded-2xl overflow-hidden bg-slate-900 group border border-slate-200/80">
                            <img :src="previewActivity.images[activePreviewImgIdx]"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div
                                class="absolute bottom-3 right-3 bg-slate-950/75 backdrop-blur-md text-white text-xs px-3 py-1 rounded-lg border border-white/20">
                                Photo <span x-text="activePreviewImgIdx + 1"></span> of <span
                                    x-text="previewActivity.images.length"></span>
                            </div>
                        </div>

                        <template x-if="previewActivity.images.length > 1">
                            <div class="flex items-center gap-2 overflow-x-auto pb-2">
                                <template x-for="(img, idx) in previewActivity.images" :key="idx">
                                    <button @click="activePreviewImgIdx = idx"
                                        :class="activePreviewImgIdx === idx ? 'ring-2 ring-sky-600 scale-105' : 'opacity-70 hover:opacity-100'"
                                        class="w-16 h-12 rounded-lg overflow-hidden border border-slate-200 shrink-0 transition-all">
                                        <img :src="img" class="w-full h-full object-cover">
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Quick Specs Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                        <span
                            class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Duration</span>
                        <span class="font-bold text-slate-800 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-sky-600">schedule</span>
                            <span x-text="previewActivity?.duration || 'Flexible'"></span>
                        </span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                        <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Activity
                            Level</span>
                        <span class="font-bold text-slate-800 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-sky-600">signal_cellular_alt</span>
                            <span x-text="previewActivity?.activity_level || 'General'"></span>
                        </span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                        <span
                            class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Capacity</span>
                        <span class="font-bold text-slate-800 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-sky-600">group</span>
                            <span x-text="previewActivity?.capacity || 'Standard Group'"></span>
                        </span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1"
                        x-show="previewActivity?.ideal_for">
                        <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Ideal
                            For</span>
                        <span class="font-bold text-slate-800 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-sky-600">face</span>
                            <span x-text="previewActivity?.ideal_for"></span>
                        </span>
                    </div>
                </div>

                {{-- Description --}}
                <template x-if="previewActivity?.description">
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Activity Overview</h4>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed bg-slate-50/80 p-4 rounded-2xl border border-slate-200/70"
                            x-text="previewActivity.description"></p>
                    </div>
                </template>

                {{-- Requirements --}}
                <template x-if="previewActivity?.requirements">
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Participant Requirements
                        </h4>
                        <p
                            class="text-xs text-amber-900 bg-amber-50/80 p-3 rounded-xl border border-amber-200/70 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-amber-600">info</span>
                            <span x-text="previewActivity.requirements"></span>
                        </p>
                    </div>
                </template>

                {{-- Inclusions & Exclusions --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <template x-if="previewActivity?.inclusions && previewActivity.inclusions.length > 0">
                        <div class="space-y-2 bg-emerald-50/40 p-4 rounded-2xl border border-emerald-100">
                            <h4
                                class="text-xs font-bold text-emerald-900 uppercase tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                                Included
                            </h4>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(inc, idx) in previewActivity.inclusions" :key="idx">
                                    <span
                                        class="px-2.5 py-1 bg-white text-emerald-800 text-xs font-semibold rounded-lg border border-emerald-200/80 shadow-2xs">
                                        <span x-text="inc"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="previewActivity?.exclusions && previewActivity.exclusions.length > 0">
                        <div class="space-y-2 bg-rose-50/40 p-4 rounded-2xl border border-rose-100">
                            <h4
                                class="text-xs font-bold text-rose-900 uppercase tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-rose-600">cancel</span> Excluded
                                / Add-ons
                            </h4>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(exc, idx) in previewActivity.exclusions" :key="idx">
                                    <span
                                        class="px-2.5 py-1 bg-white text-rose-800 text-xs font-semibold rounded-lg border border-rose-200/80 shadow-2xs">
                                        <span x-text="exc"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Itinerary Timeline --}}
                <template x-if="previewActivity?.itinerary && previewActivity.itinerary.length > 0">
                    <div class="space-y-3 pt-2">
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Suggested Itinerary</h4>
                        <div class="space-y-2 relative border-l-2 border-sky-200 ml-3 pl-4">
                            <template x-for="(step, idx) in previewActivity.itinerary" :key="idx">
                                <div class="relative group">
                                    <span
                                        class="absolute -left-[23px] top-0.5 w-3 h-3 rounded-full bg-sky-500 ring-4 ring-white"></span>
                                    <div
                                        class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex items-center justify-between">
                                        <span class="font-bold text-slate-800 text-xs"
                                            x-text="step.title || step"></span>
                                        <span
                                            class="text-[11px] font-semibold text-sky-600 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-100"
                                            x-show="step.duration" x-text="step.duration"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Vibe Tags --}}
                <template x-if="previewActivity?.vibe_tags && previewActivity.vibe_tags.length > 0">
                    <div class="space-y-2 pt-2">
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Atmosphere & Vibe</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(tag, idx) in previewActivity.vibe_tags" :key="idx">
                                <span
                                    class="px-2.5 py-1 bg-sky-50 text-sky-700 text-xs font-semibold rounded-lg border border-sky-100">
                                    #<span x-text="tag"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>

            </div>

            {{-- Modal Footer --}}
            <div
                class="bg-slate-50 p-4 sm:p-6 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-4">
                <button type="button" @click="previewActivity = null"
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-200 transition-colors">
                    Close Preview
                </button>
                <template x-if="previewActivity?.destination_id">
                    <a :href="'/destinations/' + previewActivity.destination_id"
                        class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-700 hover:to-sky-800 text-white font-bold text-xs shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">explore</span>
                        <span>Discover Sanctuary & Book</span>
                    </a>
                </template>
            </div>

        </div>
    </div>

</section>