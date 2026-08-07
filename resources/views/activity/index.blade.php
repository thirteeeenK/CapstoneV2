<x-frontend.layout title="Activities, Tours & Adventures — SunnyTrips">
    @php
        $selectedDestId = request('destination_id');
        $selectedLevel = request('level');
    @endphp

    <div x-data="{
        activeDestId: '{{ $selectedDestId ?: 'all' }}',
        levelFilter: '{{ $selectedLevel ?: 'all' }}',
        searchQuery: '',
        previewActivity: null,
        modalPax: 1,
        activePreviewImgIdx: 0,
        matchesActivity(destId, level, searchableText) {
            if (this.activeDestId !== 'all' && String(this.activeDestId) !== String(destId)) {
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
    }" class="py-12 bg-slate-50 text-slate-900 min-h-screen relative overflow-hidden">
        
        {{-- Background Soft Ambient Mesh Glows --}}
        <div class="absolute top-10 left-1/3 w-[500px] h-[300px] bg-sky-200/40 blur-3xl rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-1/3 w-[400px] h-[400px] bg-indigo-200/30 blur-3xl rounded-full pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 relative z-10">

            {{-- Header Banner --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-widest border border-sky-200">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">explore</span>
                        <span>Full Activities Catalog</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 font-headline tracking-tight">
                        Island Activities & Tours
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm font-body max-w-xl leading-relaxed">
                        Discover guided island tours, water sports, diving adventures, and cultural experiences. Select any activity to view complete itinerary, inclusions, and island details.
                    </p>
                </div>

                {{-- Location Filter Tabs --}}
                <div class="flex items-center gap-2 overflow-x-auto max-w-full p-2 bg-slate-100/90 rounded-2xl border border-slate-200 shrink-0">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 shrink-0 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-400">location_on</span>
                        <span>Location:</span>
                    </span>
                    <button type="button" @click="activeDestId = 'all'"
                        :class="activeDestId === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 border cursor-pointer">
                        All Islands ({{ $activities->count() }})
                    </button>
                    @foreach($destinations as $dest)
                        @php
                            $destActCount = $activities->where('destination_id', $dest->id)->count();
                        @endphp
                        @if($destActCount > 0)
                            <button type="button" @click="activeDestId = '{{ $dest->id }}'"
                                :class="activeDestId === '{{ $dest->id }}' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer">
                                <span>{{ $dest->name }}</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="activeDestId === '{{ $dest->id }}' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">
                                    {{ $destActCount }}
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Clean Toolbar: Search & 5 Activity Level Filters --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                {{-- Search Bar --}}
                <div class="relative w-full md:w-80 shrink-0">
                    <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                    <input type="text" x-model="searchQuery" placeholder="Search activity, level, tags, or inclusions..."
                        class="w-full bg-slate-50 border border-slate-200 focus:border-sky-500 focus:bg-white rounded-2xl py-2.5 pl-10 pr-8 text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none transition-all shadow-xs" />
                    <button x-show="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">cancel</span>
                    </button>
                </div>

                {{-- 5 Activity Level Filter Pills --}}
                <div class="flex flex-wrap items-center gap-2 overflow-x-auto w-full md:w-auto justify-start md:justify-end">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 shrink-0 mr-1">
                        <span class="material-symbols-outlined text-[15px] text-sky-600">signal_cellular_alt</span>
                        <span>Level Filter:</span>
                    </span>

                    <button type="button" @click="levelFilter = 'all'"
                        :class="levelFilter === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                        class="px-3 py-1.5 rounded-xl text-xs transition-all cursor-pointer shrink-0">
                        All Levels
                    </button>

                    @foreach(['Relaxing' => '🧘', 'Sightseeing' => '🗺️', 'Adventure' => '🏔️', 'Extreme' => '⚡', 'Underwater' => '🤿'] as $lvl => $icon)
                        <button type="button" @click="levelFilter = '{{ $lvl }}'"
                            :class="levelFilter === '{{ $lvl }}' ? 'bg-sky-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-xl text-xs transition-all cursor-pointer shrink-0 flex items-center gap-1">
                            <span>{{ $icon }}</span>
                            <span>{{ $lvl }}</span>
                        </button>
                    @endforeach

                    <button x-show="searchQuery || levelFilter !== 'all' || activeDestId !== 'all'"
                        @click="searchQuery = ''; levelFilter = 'all'; activeDestId = 'all';"
                        class="text-[11px] font-bold text-rose-600 hover:text-rose-700 ml-2 flex items-center gap-1 cursor-pointer shrink-0">
                        <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                        <span>Clear</span>
                    </button>
                </div>
            </div>

            {{-- Activities Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($activities as $act)
                    @php
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
                            'destination_name' => $destName,
                            'destination_id' => $act->destination_id,
                            'images' => $resolvedImages,
                            'inclusions' => array_values($inclusionsRaw),
                            'exclusions' => array_values($exclusionsRaw),
                            'itinerary' => array_values($itineraryRaw),
                            'vibe_tags' => array_values($vibeTagsRaw),
                        ];
                    @endphp

                    <div x-show="matchesActivity('{{ $act->destination_id }}', '{{ addslashes($act->activity_level ?? '') }}', {{ json_encode($searchablePayload) }})"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                        
                        <div>
                            {{-- Image Container --}}
                            <div class="relative h-48 overflow-hidden bg-slate-100">
                                <img src="{{ $actImg }}" alt="{{ $act->activity_name }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>

                                {{-- Category Badge --}}
                                @if($act->category)
                                    <div class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-white/10 flex items-center gap-1">
                                        <span>{{ $act->category }}</span>
                                    </div>
                                @endif

                                {{-- Price Badge --}}
                                @if($act->rate)
                                    <div class="absolute top-3 right-3 bg-slate-900/90 text-emerald-400 font-extrabold text-xs px-2.5 py-0.5 rounded-lg border border-white/10">
                                        {{ $formattedRate }}
                                    </div>
                                @endif

                                {{-- Destination Badge --}}
                                @if($act->destination)
                                    <div class="absolute bottom-3 left-3 bg-white/90 backdrop-blur-xs text-slate-900 text-[10px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[13px] text-sky-600">location_on</span>
                                        <span>{{ $act->destination->name }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Details --}}
                            <div class="p-5 space-y-2">
                                <h3 class="text-base font-bold text-slate-900 group-hover:text-sky-600 transition-colors font-headline line-clamp-1">
                                    {{ $act->activity_name }}
                                </h3>
                                <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                    {{ $act->description ?: 'Exciting island adventure with guided tour and safety equipment.' }}
                                </p>

                                {{-- Specs Pills --}}
                                <div class="flex items-center gap-2 pt-1 text-[11px] text-slate-600 font-medium flex-wrap">
                                    @if($act->duration)
                                        <span class="bg-slate-50 px-2 py-0.5 rounded-md flex items-center gap-1 border border-slate-200/80">
                                            <span class="material-symbols-outlined text-[13px] text-sky-600">schedule</span>
                                            <span>{{ $act->duration }}</span>
                                        </span>
                                    @endif

                                    @if($act->activity_level)
                                        <span class="bg-sky-50 text-sky-700 px-2 py-0.5 rounded-md flex items-center gap-1 border border-sky-200/80 font-bold">
                                            <span class="material-symbols-outlined text-[13px] text-sky-600">signal_cellular_alt</span>
                                            <span>{{ $act->activity_level }}</span>
                                        </span>
                                    @endif

                                    @if($act->capacity)
                                        <span class="bg-slate-50 px-2 py-0.5 rounded-md flex items-center gap-1 border border-slate-200/80">
                                            <span class="material-symbols-outlined text-[13px] text-sky-600">group</span>
                                            <span>Max {{ $act->capacity }}</span>
                                        </span>
                                    @endif
                                </div>

                                {{-- Vibe Tags --}}
                                @if(!empty($vibeTagsRaw))
                                    <div class="flex flex-wrap gap-1 pt-1">
                                        @foreach(array_slice($vibeTagsRaw, 0, 3) as $tag)
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-semibold border border-slate-200">
                                                #{{ $tag }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Card Footer --}}
                        <div class="p-5 pt-0 grid grid-cols-2 gap-2">
                            <button type="button"
                                @click="previewActivity = {{ json_encode($actPayload) }}; activePreviewImgIdx = 0;"
                                class="w-full py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]">visibility</span>
                                <span>Preview</span>
                            </button>

                            <button type="button"
                                @click="window.addToCart('activity', {{ $act->id }})"
                                class="w-full py-2 px-3 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs transition-colors flex items-center justify-center gap-1 shadow-xs cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]">shopping_cart</span>
                                <span>Add to Trip Basket</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>

        {{-- Dynamic Activity Preview Modal --}}
        <div x-show="previewActivity" x-transition.opacity @keydown.escape.window="previewActivity = null"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/75 backdrop-blur-md" x-cloak style="display: none;">
            
            <div @click.away="previewActivity = null"
                class="bg-white border border-slate-200 rounded-3xl max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl relative flex flex-col">
                
                {{-- Modal Header --}}
                <div class="sticky top-0 bg-white/90 backdrop-blur-md px-6 py-4 border-b border-slate-200 flex items-center justify-between z-20">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-sky-600 text-xl" x-text="previewActivity?.category_icon || 'explore'"></span>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider" x-text="previewActivity?.category || 'Activity'"></span>
                        <template x-if="previewActivity?.destination_name">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-sky-50 text-sky-700 text-[11px] font-bold border border-sky-200">
                                <span class="material-symbols-outlined text-[13px] text-sky-500">location_on</span>
                                <span x-text="previewActivity.destination_name"></span>
                            </span>
                        </template>
                    </div>
                    <button @click="previewActivity = null" class="p-1 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-4">
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 font-headline" x-text="previewActivity?.activity_name"></h2>
                        <span class="text-xl font-black text-emerald-600 font-mono bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200/80 inline-block w-fit" x-text="previewActivity?.rate"></span>
                    </div>

                    {{-- Image Carousel Preview --}}
                    <template x-if="previewActivity?.images && previewActivity.images.length > 0">
                        <div class="space-y-3">
                            <div class="relative h-64 sm:h-80 rounded-2xl overflow-hidden bg-slate-900 shadow-inner">
                                <img :src="previewActivity.images[activePreviewImgIdx]" class="w-full h-full object-cover">
                                <span class="absolute bottom-3 right-3 bg-slate-950/80 text-white text-[10px] font-bold px-2.5 py-1 rounded-full backdrop-blur-xs border border-white/10">
                                    <span x-text="activePreviewImgIdx + 1"></span> / <span x-text="previewActivity.images.length"></span>
                                </span>
                            </div>
                            <template x-if="previewActivity.images.length > 1">
                                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                                    <template x-for="(img, idx) in previewActivity.images" :key="idx">
                                        <button @click="activePreviewImgIdx = idx"
                                            :class="activePreviewImgIdx === idx ? 'ring-2 ring-sky-500 scale-95' : 'opacity-70 hover:opacity-100'"
                                            class="w-16 h-12 rounded-lg overflow-hidden shrink-0 transition-all cursor-pointer">
                                            <img :src="img" class="w-full h-full object-cover">
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Quick Specs Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                        <div>
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Duration</span>
                            <span class="text-xs font-bold text-slate-800" x-text="previewActivity?.duration || 'Flexible'"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Activity Level</span>
                            <span class="text-xs font-bold text-sky-700" x-text="previewActivity?.activity_level || 'General'"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Max Group</span>
                            <span class="text-xs font-bold text-slate-800" x-text="previewActivity?.capacity ? 'Up to ' + previewActivity.capacity + ' guests' : 'Flexible'"></span>
                        </div>
                    </div>

                    {{-- Pax Selector Control --}}
                    <div class="flex items-center justify-between p-3.5 bg-sky-50/80 rounded-2xl border border-sky-200/80">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sky-600">group</span>
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Number of Participants / Pax</span>
                                <span class="text-[11px] text-slate-500">Manifest entries will be generated for each participant</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-2xs">
                            <button type="button" @click="modalPax = Math.max(1, modalPax - 1)" :disabled="modalPax <= 1" class="text-slate-600 font-bold hover:text-sky-600 disabled:opacity-40 cursor-pointer">-</button>
                            <span class="text-xs font-black text-slate-900 w-6 text-center" x-text="modalPax"></span>
                            <button type="button" @click="modalPax += 1" class="text-slate-600 font-bold hover:text-sky-600 cursor-pointer">+</button>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="space-y-1.5">
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Description</h4>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed" x-text="previewActivity?.description"></p>
                    </div>

                    {{-- Requirements --}}
                    <template x-if="previewActivity?.requirements">
                        <div class="space-y-1.5 bg-amber-50/80 border border-amber-200/80 p-3.5 rounded-xl text-amber-900 text-xs">
                            <span class="font-bold block flex items-center gap-1">
                                <span class="material-symbols-outlined text-[15px]">info</span>
                                Requirements & Guidelines
                            </span>
                            <p x-text="previewActivity.requirements" class="leading-relaxed"></p>
                        </div>
                    </template>

                    {{-- Inclusions --}}
                    <template x-if="previewActivity?.inclusions && previewActivity.inclusions.length > 0">
                        <div class="space-y-2">
                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">What's Included</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="(inc, idx) in previewActivity.inclusions" :key="idx">
                                    <div class="flex items-center gap-2 text-xs text-slate-700 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200/80">
                                        <span class="material-symbols-outlined text-[15px] text-emerald-500">check_circle</span>
                                        <span x-text="inc"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Modal Footer --}}
                <div class="sticky bottom-0 bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-between gap-4">
                    <button @click="previewActivity = null" class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition-colors">
                        Close Preview
                    </button>

                    <button type="button"
                        @click="window.addToCart('activity', previewActivity.id, { selected_pax: modalPax }); previewActivity = null;"
                        class="px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-md transition-colors flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">shopping_cart</span>
                        <span>Add to Trip Basket</span>
                    </button>
                </div>

            </div>
        </div>

    </div>
</x-frontend.layout>
