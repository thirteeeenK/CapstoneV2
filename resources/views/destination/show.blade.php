<x-frontend.layout :title="$destination->name . ' — SunnyTrips'">

    @php
        $heroImage = App\Concerns\ResolvesImages::resolveImg(
            $destination->image,
            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80'
        );
    @endphp

    <div x-data="{
        previewActivity: null,
        activePreviewImgIdx: 0,
        levelFilter: 'all',
        searchQuery: '',
        matchesActivity(level, searchableText) {
            if (this.levelFilter !== 'all' && String(this.levelFilter).toLowerCase() !== String(level).toLowerCase()) {
                return false;
            }
            if (this.searchQuery.trim() !== '') {
                let terms = this.searchQuery.toLowerCase().trim().split(/\s+/);
                let sText = (searchableText || '').toLowerCase();
                return terms.every(term => sText.includes(term));
            }
            return true;
        }
    }"
        class="pt-32 sm:pt-36 pb-24 bg-slate-50 min-h-screen">

        {{-- HERO BANNER --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="relative rounded-3xl overflow-hidden shadow-2xl min-h-[440px] sm:min-h-[540px] flex items-end p-6 sm:p-12 group">
                <img src="{{ $heroImage }}" alt="{{ $destination->name }}"
                    class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/95 via-slate-950/50 to-slate-950/20"></div>

                <div class="relative z-10 space-y-4 max-w-3xl">
                    <a href="/#destinations"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white/20 backdrop-blur-md text-white text-xs font-bold hover:bg-white/30 transition-all border border-white/30 shadow-md">
                        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                        <span>Back to All Destinations</span>
                    </a>

                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-white text-[11px] font-extrabold uppercase tracking-widest border border-white/30">
                            Sanctuary Location
                        </span>
                        <span
                            class="px-3 py-1 rounded-full bg-ocean-600/90 backdrop-blur-md text-white text-[11px] font-bold uppercase tracking-wider border border-ocean-400/40 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">location_on</span>
                            {{ $destination->name }}
                        </span>
                    </div>

                    <h1
                        class="text-3xl sm:text-5xl lg:text-6xl font-black text-white font-headline tracking-tight leading-tight drop-shadow-md">
                        {{ $destination->name }}
                    </h1>
                    <p class="text-slate-200 text-xs sm:text-base leading-relaxed max-w-2xl font-body">
                        {{ $destination->description }}
                    </p>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16 pt-12">

            {{-- ══════════════════════════════════════════
            HOTELS SECTION (SANCTUARY STAYS)
            ══════════════════════════════════════════ --}}
            <section class="space-y-8">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <span class="text-xs font-bold text-ocean-600 tracking-wider uppercase block">WHERE TO
                            STAY</span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-headline">
                            Sanctuary Stays
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Handpicked luxury resorts and villas in {{ $destination->name }}
                        </p>
                    </div>
                    <span
                        class="text-xs font-bold text-ocean-700 bg-ocean-50 px-3.5 py-1.5 rounded-full border border-ocean-200/80 shadow-2xs">
                        {{ $destination->hotels->count() }} {{ Str::plural('Hotel', $destination->hotels->count()) }}
                    </span>
                </div>

                @if($destination->hotels->isEmpty())
                    <div
                        class="bg-white p-12 rounded-3xl border border-slate-200 text-center text-slate-400 text-sm shadow-xs">
                        <span class="material-symbols-outlined text-4xl text-slate-300 mb-2 block">hotel_class</span>
                        No hotels listed for this destination yet. Check back soon!
                    </div>
                @else
                    @php
                        $hotelCount = $destination->hotels->count();
                        $hotelGridClass = match (true) {
                            $hotelCount === 1 => 'grid-cols-1 max-w-2xl mx-auto',
                            $hotelCount === 2 => 'grid-cols-1 md:grid-cols-2',
                            $hotelCount === 4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                            default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
                        };
                    @endphp
                    <div class="grid {{ $hotelGridClass }} gap-8">
                        @foreach($destination->hotels as $hotel)
                            @php
                                $hotelImg = App\Concerns\ResolvesImages::resolveImg(
                                    $hotel->images[0] ?? null,
                                    'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80'
                                );
                                $roomsCount = $hotel->rooms ? $hotel->rooms->count() : 0;
                            @endphp
                            <a href="{{ route('hotels.show', $hotel->id) }}"
                                class="bg-white rounded-3xl border border-slate-200/90 overflow-hidden shadow-xs hover:shadow-2xl transition-all duration-500 hover:-translate-y-1.5 flex flex-col justify-between group">
                                <div>
                                    <div class="relative h-60 sm:h-64 overflow-hidden bg-slate-950">
                                        <img src="{{ $hotelImg }}" alt="{{ $hotel->hotel_name }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">

                                        <div
                                            class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent">
                                        </div>

                                        @if($hotel->type)
                                            <div
                                                class="absolute top-3 left-3 bg-slate-950/80 backdrop-blur-md text-white text-[10px] font-semibold px-3 py-1 rounded-full border border-white/20">
                                                {{ ucwords(str_replace('-', ' ', $hotel->type)) }}
                                            </div>
                                        @endif

                                        @if($roomsCount > 0)
                                            <div
                                                class="absolute bottom-3 left-3 bg-slate-950/75 backdrop-blur-md text-slate-200 text-[11px] font-bold px-3 py-1 rounded-lg border border-white/10 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px] text-ocean-400">bed</span>
                                                <span>{{ $roomsCount }} Room {{ Str::plural('Type', $roomsCount) }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="p-6 space-y-3">
                                        <h3
                                            class="text-lg font-bold text-slate-900 group-hover:text-ocean-600 transition-colors font-headline">
                                            {{ $hotel->hotel_name }}
                                        </h3>
                                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                            {{ $hotel->hotel_description }}
                                        </p>
                                        @if(!empty($hotel->vibe_tags) && is_array($hotel->vibe_tags))
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                @foreach(array_slice($hotel->vibe_tags, 0, 3) as $tag)
                                                    <span
                                                        class="px-2 py-0.5 rounded-md bg-ocean-50 text-ocean-700 text-[10px] font-semibold border border-ocean-100">
                                                        #{{ $tag }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="p-6 pt-0">
                                    <span
                                        class="w-full py-2.5 px-4 rounded-xl bg-slate-50 group-hover:bg-gradient-to-r group-hover:from-ocean-600 group-hover:to-ocean-700 group-hover:text-white text-slate-900 font-bold text-xs transition-all duration-300 flex items-center justify-between shadow-2xs">
                                        <span>Explore Sanctuary & Rooms</span>
                                        <span
                                            class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ══════════════════════════════════════════
            ACTIVITIES SECTION
            ══════════════════════════════════════════ --}}
            <section class="space-y-6">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">
                            Experiences & Tours
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Unforgettable activities waiting for you at {{ $destination->name }}
                        </p>
                    </div>
                    <span
                        class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-100">
                        {{ $destination->activities->count() }}
                        {{ Str::plural('Activity', $destination->activities->count()) }}
                    </span>
                </div>

                @if($destination->activities->isNotEmpty())
                    {{-- Activity Filter Toolbar (5 Levels & Search) --}}
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="relative w-full sm:w-72">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                            <input type="text" x-model="searchQuery" placeholder="Search activity, level, or tags..."
                                class="w-full bg-slate-50 border border-slate-200 focus:border-sky-500 focus:bg-white rounded-xl py-1.5 pl-9 pr-8 text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none transition-all shadow-xs" />
                            <button x-show="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
                                <span class="material-symbols-outlined text-xs">cancel</span>
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5 overflow-x-auto w-full sm:w-auto justify-start sm:justify-end">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 shrink-0 mr-1">
                                <span class="material-symbols-outlined text-[15px] text-sky-600">signal_cellular_alt</span>
                                <span>Level:</span>
                            </span>

                            <button type="button" @click="levelFilter = 'all'"
                                :class="levelFilter === 'all' ? 'bg-sky-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium'"
                                class="px-2.5 py-1 rounded-xl text-xs transition-all cursor-pointer shrink-0">
                                All
                            </button>

                            @foreach(['Relaxing' => '🧘', 'Sightseeing' => '🗺️', 'Adventure' => '🏔️', 'Extreme' => '⚡', 'Underwater' => '🤿'] as $lvl => $icon)
                                <button type="button" @click="levelFilter = '{{ $lvl }}'"
                                    :class="levelFilter === '{{ $lvl }}' ? 'bg-sky-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium'"
                                    class="px-2.5 py-1 rounded-xl text-xs transition-all cursor-pointer shrink-0 flex items-center gap-1">
                                    <span>{{ $icon }}</span>
                                    <span>{{ $lvl }}</span>
                                </button>
                            @endforeach

                            <button x-show="searchQuery || levelFilter !== 'all'"
                                @click="searchQuery = ''; levelFilter = 'all';"
                                class="text-[11px] font-bold text-rose-600 hover:text-rose-700 ml-1 flex items-center gap-0.5 cursor-pointer shrink-0">
                                <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                                <span>Clear</span>
                            </button>
                        </div>
                    </div>
                @endif

                @if($destination->activities->isEmpty())
                    <div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 text-sm">
                        No activities listed for this destination yet. Check back soon.
                    </div>
                @else
                    @php
                        $actCount = $destination->activities->count();
                        $actGridClass = match (true) {
                            $actCount === 1 => 'grid-cols-1 max-w-2xl mx-auto',
                            $actCount === 2 => 'grid-cols-1 md:grid-cols-2',
                            $actCount === 4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                            default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
                        };
                    @endphp
                    <div class="grid {{ $actGridClass }} gap-6">
                        @foreach($destination->activities as $activity)
                            @php
                                $actImagesRaw = is_array($activity->images) ? $activity->images : (is_string($activity->images) ? (json_decode($activity->images, true) ?: []) : []);
                                $resolvedActImages = array_map(function ($img) {
                                    return App\Concerns\ResolvesImages::resolveImg($img, 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80');
                                }, $actImagesRaw);
                                if (empty($resolvedActImages)) {
                                    $resolvedActImages = [App\Concerns\ResolvesImages::resolveImg(null, 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80')];
                                }
                                $actImg = $resolvedActImages[0];

                                $actRateText = (string) $activity->rate;
                                if (is_numeric($actRateText)) {
                                    $formattedActRate = '₱' . number_format((float) $actRateText, 2);
                                } elseif (str_starts_with($actRateText, '₱')) {
                                    $formattedActRate = $actRateText;
                                } else {
                                    $formattedActRate = '₱' . $actRateText;
                                }

                                $inclusionsRaw = is_array($activity->inclusions) ? $activity->inclusions : (is_string($activity->inclusions) ? array_filter(array_map('trim', explode(',', $activity->inclusions))) : []);
                                $exclusionsRaw = is_array($activity->exclusions) ? $activity->exclusions : (is_string($activity->exclusions) ? array_filter(array_map('trim', explode(',', $activity->exclusions))) : []);
                                $itineraryRaw = is_array($activity->itinerary) ? $activity->itinerary : (is_string($activity->itinerary) ? (json_decode($activity->itinerary, true) ?: []) : []);
                                $vibeTagsRaw = is_array($activity->vibe_tags) ? $activity->vibe_tags : (is_string($activity->vibe_tags) ? array_filter(array_map('trim', explode(',', $activity->vibe_tags))) : []);

                                $searchablePayload = implode(' ', [
                                    $activity->activity_name,
                                    $activity->category ?? '',
                                    $activity->activity_level ?? '',
                                    $activity->description ?? '',
                                    $activity->requirements ?? '',
                                    $activity->ideal_for ?? '',
                                    implode(' ', $inclusionsRaw),
                                    implode(' ', $vibeTagsRaw)
                                ]);

                                $actPayload = [
                                    'id' => $activity->id,
                                    'activity_name' => $activity->activity_name,
                                    'category' => $activity->category,
                                    'category_icon' => App\Concerns\ResolvesImages::getCategoryIcon($activity->category),
                                    'rate' => $formattedActRate,
                                    'duration' => $activity->duration,
                                    'activity_level' => $activity->activity_level,
                                    'capacity' => $activity->capacity,
                                    'requirements' => $activity->requirements,
                                    'ideal_for' => $activity->ideal_for,
                                    'description' => $activity->description,
                                    'notes' => $activity->notes,
                                    'destination_name' => $destination->name,
                                    'destination_id' => $destination->id,
                                    'images' => $resolvedActImages,
                                    'inclusions' => array_values($inclusionsRaw),
                                    'exclusions' => array_values($exclusionsRaw),
                                    'itinerary' => array_values($itineraryRaw),
                                    'vibe_tags' => array_values($vibeTagsRaw),
                                ];
                            @endphp
                            <div x-show="matchesActivity('{{ addslashes($activity->activity_level ?? '') }}', {{ json_encode($searchablePayload) }})"
                                class="bg-white rounded-3xl border border-slate-200/90 overflow-hidden shadow-xs hover:shadow-xl transition-all duration-300 flex flex-col justify-between group hover:-translate-y-1">
                                <div>
                                    <div class="relative h-48 sm:h-56 overflow-hidden bg-slate-950">
                                        <img src="{{ $actImg }}" alt="{{ $activity->activity_name }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">

                                        @if($activity->category)
                                            <div
                                                class="absolute top-3 left-3 bg-slate-950/80 backdrop-blur-md text-white text-[10px] font-semibold px-2.5 py-1 rounded-md border border-white/20">
                                                {{ $activity->category }}
                                            </div>
                                        @endif
                                        @if($activity->rate)
                                            <div
                                                class="absolute top-3 right-3 bg-slate-950/80 backdrop-blur-md text-emerald-400 font-extrabold text-xs px-2.5 py-1 rounded-lg border border-white/20">
                                                {{ $formattedActRate }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="p-5 space-y-3">
                                        <h3
                                            class="text-base font-bold text-slate-900 group-hover:text-ocean-600 transition-colors font-headline">
                                            {{ $activity->activity_name }}
                                        </h3>
                                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                            {{ $activity->description }}
                                        </p>
                                        <div class="flex flex-wrap gap-2 text-[11px] text-slate-600 pt-1">
                                            @if($activity->duration)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span
                                                        class="material-symbols-outlined text-[14px] text-slate-400">schedule</span>
                                                    {{ $activity->duration }}
                                                </span>
                                            @endif
                                            @if($activity->activity_level)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span
                                                        class="material-symbols-outlined text-[14px] text-slate-400">signal_cellular_alt</span>
                                                    {{ $activity->activity_level }}
                                                </span>
                                            @endif
                                            @if($activity->capacity)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">group</span>
                                                    Max {{ $activity->capacity }}
                                                </span>
                                            @endif
                                        </div>
                                        @if(!empty($vibeTagsRaw))
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach(array_slice($vibeTagsRaw, 0, 3) as $tag)
                                                    <span
                                                        class="px-2 py-0.5 rounded-md bg-ocean-50 text-ocean-700 text-[10px] font-semibold border border-ocean-100">
                                                        #{{ $tag }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Footer Action --}}
                                <div class="p-5 pt-0">
                                    <button type="button"
                                        @click="previewActivity = {{ json_encode($actPayload) }}; activePreviewImgIdx = 0;"
                                        class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-ocean-600 hover:text-white text-slate-800 font-bold text-xs transition-colors flex items-center justify-center gap-1.5 shadow-2xs">
                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                        <span>View Details & Itinerary</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

        </div>

        {{-- Dynamic Activity Preview Modal --}}
        <div x-show="previewActivity" x-transition.opacity @keydown.escape.window="previewActivity = null"
            class="fixed inset-0 z-[110] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
            style="display: none;">
            <div @click.away="previewActivity = null"
                class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full overflow-hidden border border-slate-200/80 my-auto transform transition-all">

                {{-- Modal Header --}}
                <div class="relative bg-slate-900 text-white p-6 sm:p-8 overflow-hidden">
                    <div
                        class="absolute top-0 right-0 w-64 h-64 bg-ocean-500/10 rounded-full blur-3xl pointer-events-none">
                    </div>

                    <button @click="previewActivity = null"
                        class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>

                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span
                            class="px-2.5 py-0.5 rounded-full bg-ocean-500/20 text-ocean-300 text-[10px] font-bold uppercase tracking-wider border border-ocean-400/30">
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
                                            :class="activePreviewImgIdx === idx ? 'ring-2 ring-ocean-600 scale-105' : 'opacity-70 hover:opacity-100'"
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
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">schedule</span>
                                <span x-text="previewActivity?.duration || 'Flexible'"></span>
                            </span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Activity
                                Level</span>
                            <span class="font-bold text-slate-800 flex items-center gap-1">
                                <span
                                    class="material-symbols-outlined text-[16px] text-ocean-600">signal_cellular_alt</span>
                                <span x-text="previewActivity?.activity_level || 'General'"></span>
                            </span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                            <span
                                class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Capacity</span>
                            <span class="font-bold text-slate-800 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">group</span>
                                <span x-text="previewActivity?.capacity || 'Standard Group'"></span>
                            </span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1"
                            x-show="previewActivity?.ideal_for">
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Ideal
                                For</span>
                            <span class="font-bold text-slate-800 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">face</span>
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
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Participant
                                Requirements</h4>
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
                                    <span
                                        class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
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
                                    <span class="material-symbols-outlined text-[16px] text-rose-600">cancel</span>
                                    Excluded / Add-ons
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
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Suggested Itinerary
                            </h4>
                            <div class="space-y-2 relative border-l-2 border-ocean-200 ml-3 pl-4">
                                <template x-for="(step, idx) in previewActivity.itinerary" :key="idx">
                                    <div class="relative group">
                                        <span
                                            class="absolute -left-[23px] top-0.5 w-3 h-3 rounded-full bg-ocean-500 ring-4 ring-white"></span>
                                        <div
                                            class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex items-center justify-between">
                                            <span class="font-bold text-slate-800 text-xs"
                                                x-text="step.title || step"></span>
                                            <span
                                                class="text-[11px] font-semibold text-ocean-600 bg-ocean-50 px-2 py-0.5 rounded-md border border-ocean-100"
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
                                        class="px-2.5 py-1 bg-ocean-50 text-ocean-700 text-xs font-semibold rounded-lg border border-ocean-100">
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
                    <button type="button" @click="previewActivity = null"
                        class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-gradient-to-r from-ocean-600 to-ocean-700 hover:from-ocean-700 hover:to-ocean-800 text-white font-bold text-xs shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        <span>Select & Book Experience</span>
                    </button>
                </div>

            </div>
        </div>

    </div>

</x-frontend.layout>