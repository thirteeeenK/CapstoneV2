<x-frontend.layout title="Tour Packages & Vacation Deals — SunnyTrips">
    @php
        $selectedDestId = request('destination_id');
        $searchQuery = request('search');
    @endphp

    <div x-data="{
        activeDestId: '{{ $selectedDestId ?: 'all' }}',
        searchQuery: @js($searchQuery ?? ''),
        previewPackage: null,
        activeImgIdx: 0,
        packageIndex: @js($packages->map(fn($p) => ['dest' => (string) $p->destination_id, 'name' => $p->name, 'type' => $p->type ?? ''])),
        matchesPackage(destId, pkgName = '', pkgType = '') {
            if (this.activeDestId !== 'all' && String(this.activeDestId) !== String(destId)) {
                return false;
            }
            if (this.searchQuery && this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const hay = (pkgName + ' ' + pkgType).toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        },
        get anyVisiblePackage() {
            return this.packageIndex.some(p => this.matchesPackage(p.dest, p.name, p.type));
        }
    }" class="pt-20 sm:pt-28 pb-12 bg-sand-50/70 text-slate-900 min-h-screen relative overflow-hidden">

        {{-- Background Soft Ambient Mesh Glows --}}
        <div
            class="absolute top-10 left-1/3 w-[500px] h-[300px] bg-sky-200/40 blur-3xl rounded-full pointer-events-none">
        </div>
        <div
            class="absolute bottom-10 right-1/3 w-[400px] h-[400px] bg-indigo-200/30 blur-3xl rounded-full pointer-events-none">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5 sm:space-y-8 relative z-10">

            {{-- Header Banner --}}
            <div
                class="bg-white border border-slate-200 rounded-3xl p-5 sm:p-8 lg:p-10 shadow-sm flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-bold uppercase tracking-widest border border-amber-200">
                        <span class="material-symbols-outlined text-[16px] text-amber-600">card_travel</span>
                        <span>Curated Tour Packages & Promos</span>
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-black text-slate-900 font-headline tracking-tight">
                        All-Inclusive Vacation Deals
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm font-body leading-relaxed">
                        Save big on complete island getaways combining flights, luxury hotel stays, roundtrip airport
                        transfers, and guided island hopping adventures.
                    </p>
                </div>

                {{-- Location Filter Tabs (Flex Wrap, No Horizontal Scrollbars) --}}
                <div class="w-full lg:w-auto p-2 sm:p-2.5 bg-slate-50/90 rounded-2xl border border-slate-200/80 space-y-2 lg:space-y-0">
                    <div class="flex items-center gap-1 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider px-1 lg:hidden">
                        <span class="material-symbols-outlined text-[15px] text-amber-600">location_on</span>
                        <span>Filter Island:</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <button type="button" @click="activeDestId = 'all'"
                            :class="activeDestId === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                            class="px-3.5 py-1.5 rounded-xl text-xs transition-all border cursor-pointer">
                            All Islands ({{ $packages->count() }})
                        </button>
                        @foreach($destinations as $dest)
                            @php
                                $destPkgCount = $packages->where('destination_id', $dest->id)->count();
                            @endphp
                            @if($destPkgCount > 0)
                                <button type="button" @click="activeDestId = '{{ $dest->id }}'"
                                    :class="activeDestId === '{{ $dest->id }}' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs transition-all flex items-center gap-1.5 border cursor-pointer">
                                    <span>{{ $dest->name }}</span>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px]"
                                        :class="activeDestId === '{{ $dest->id }}' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">
                                        {{ $destPkgCount }}
                                    </span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Keyword Search --}}
            <div class="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 shadow-sm">
                <div class="relative flex-1 w-full">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-[18px]">search</span>
                    <input type="search" x-model="searchQuery" placeholder="Search by package name, type, or keyword..." aria-label="Search packages"
                        class="w-full pl-10 pr-8 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors"
                        @keydown.enter="window.location.href = '{{ route('packages.index') }}?search=' + encodeURIComponent(searchQuery) + (activeDestId !== 'all' ? '&destination_id=' + activeDestId : '')">
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 p-1 rounded-full hover:bg-slate-100 text-slate-400 transition-colors cursor-pointer" aria-label="Clear search">
                        <span class="material-symbols-outlined text-[16px]">close</span>
                    </button>
                </div>
                <div class="flex gap-2 shrink-0 w-full sm:w-auto">
                    <a :href="'{{ route('packages.index') }}?search=' + encodeURIComponent(searchQuery) + (activeDestId !== 'all' ? '&destination_id=' + activeDestId : '')"
                        class="flex-1 sm:flex-none px-5 py-2.5 rounded-2xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs text-center transition-colors cursor-pointer shadow-xs">Search</a>
                    <a href="{{ route('packages.index') }}" x-show="searchQuery || activeDestId !== 'all'" class="flex-1 sm:flex-none px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-700 font-bold text-xs text-center transition-colors">Clear</a>
                </div>
            </div>

            {{-- Packages Grid --}}
            @if($packages->isEmpty())
                <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center text-slate-500 text-sm shadow-xs">
                    <span class="material-symbols-outlined text-4xl text-slate-300 mb-2 block">search_off</span>
                    {{ $searchQuery ? 'No packages match your search. Try different keywords or clear your filters.' : 'No tour packages listed yet. Check back soon!' }}
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <a href="{{ route('packages.index') }}"
                            class="px-4 py-2 rounded-2xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs transition-colors shadow-xs">{{ $searchQuery ? 'Clear Filters & View All Packages' : 'View All Packages' }}</a>
                    </div>
                </div>
            @else
                <div x-show="!anyVisiblePackage" x-cloak
                    class="bg-white p-12 rounded-3xl border border-slate-200 text-center text-slate-500 text-sm shadow-xs">
                    <span class="material-symbols-outlined text-4xl text-slate-300 mb-2 block">search_off</span>
                    No packages match your search or filters. Try different keywords or clear your filters.
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <a href="{{ route('packages.index') }}"
                            class="px-4 py-2 rounded-2xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs transition-colors shadow-xs">Clear Filters &amp; View All Packages</a>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6" x-show="anyVisiblePackage">
                    @foreach($packages as $pkg)
                        @php
                            $destName = $pkg->destination->name ?? 'Philippines';
                            $imagesRaw = is_array($pkg->images) ? $pkg->images : (is_string($pkg->images) ? (json_decode($pkg->images, true) ?: []) : []);
                            $resolvedImages = array_map(function ($img) {
                                return App\Concerns\ResolvesImages::resolveImg($img, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80');
                            }, $imagesRaw);
                            if (empty($resolvedImages)) {
                                $resolvedImages = [App\Concerns\ResolvesImages::resolveImg(null, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80')];
                            }
                            $coverImg = $resolvedImages[0];

                            $inclusionsRaw = is_array($pkg->generic_inclusions) ? $pkg->generic_inclusions : (is_string($pkg->generic_inclusions) ? array_filter(array_map('trim', explode(',', $pkg->generic_inclusions))) : []);

                            $pkgPayload = [
                                'id' => $pkg->id,
                                'name' => $pkg->name,
                                'type' => $pkg->type,
                                'price' => '₱' . number_format((float) $pkg->price, 2),
                                'days' => $pkg->days,
                                'nights' => $pkg->nights,
                                'min_pax' => $pkg->min_pax,
                                'destination_name' => $destName,
                                'destination_id' => $pkg->destination_id,
                                'images' => $resolvedImages,
                                'generic_inclusions' => array_values($inclusionsRaw),
                                'hotels' => $pkg->hotels->map(fn($h) => ['id' => $h->id, 'name' => $h->hotel_name])->toArray(),
                                'activities' => $pkg->activities->map(fn($a) => ['id' => $a->id, 'name' => $a->activity_name])->toArray(),
                                'valid_from' => $pkg->valid_from ? $pkg->valid_from->format('M d, Y') : null,
                                'valid_to' => $pkg->valid_to ? $pkg->valid_to->format('M d, Y') : null,
                            ];
                        @endphp

                        <div x-show="matchesPackage('{{ $pkg->destination_id }}', @js($pkg->name), @js($pkg->type ?? ''))"
                            class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-amber-400 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">

                            <div>
                                {{-- Card Image & Badges --}}
                                <div class="relative h-48 sm:h-52 overflow-hidden bg-slate-900">
                                    <img src="{{ $coverImg }}" alt="{{ $pkg->name }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                    <div
                                        class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent">
                                    </div>

                                    {{-- Price Badge --}}
                                    <div
                                        class="absolute top-3 right-3 bg-slate-950/90 text-emerald-400 font-extrabold text-xs px-3 py-1 rounded-xl border border-white/10 shadow-xs z-10">
                                        ₱{{ number_format((float) $pkg->price, 2) }} <span
                                            class="text-[10px] font-normal text-slate-300">/ pax</span>
                                    </div>

                                    {{-- Destination & Duration Badge --}}
                                    <div
                                        class="absolute bottom-3 left-3 right-3 flex items-center justify-between text-white text-xs z-10">
                                        <span
                                            class="font-bold flex items-center gap-1 bg-black/40 backdrop-blur-xs px-2 py-0.5 rounded-md">
                                            <span
                                                class="material-symbols-outlined text-[14px] text-amber-400">location_on</span>
                                            <span>{{ $destName }}</span>
                                        </span>

                                        @if($pkg->days && $pkg->nights)
                                            <span class="font-bold bg-white/20 backdrop-blur-xs px-2 py-0.5 rounded-md">
                                                {{ $pkg->days }}D / {{ $pkg->nights }}N
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Card Content --}}
                                <div class="p-4 sm:p-5 space-y-3">
                                    {{-- Type Badge (Full Text, No Overlap/Truncation) --}}
                                    @if($pkg->type)
                                        <div
                                            class="inline-block px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 text-[11px] font-extrabold tracking-wide border border-amber-200/80">
                                            {{ $pkg->type }}
                                        </div>
                                    @endif

                                    <div class="flex items-start justify-between gap-2">
                                        <h3
                                            class="text-base sm:text-lg font-black text-slate-900 group-hover:text-amber-600 transition-colors font-headline">
                                            {{ $pkg->name }}
                                        </h3>
                                        <span
                                            class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md shrink-0 border border-slate-200">
                                            Min {{ $pkg->min_pax }} Pax
                                        </span>
                                    </div>

                                    {{-- Generic Inclusions Checklist --}}
                                    @if(!empty($inclusionsRaw))
                                        <div class="space-y-1.5 pt-1 border-t border-slate-100">
                                            <span
                                                class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Package
                                                Inclusions:</span>
                                            <div class="space-y-1">
                                                @foreach(array_slice($inclusionsRaw, 0, 4) as $inc)
                                                    <div class="flex items-center gap-2 text-xs text-slate-700 font-medium">
                                                        <span
                                                            class="material-symbols-outlined text-[15px] text-emerald-500 shrink-0">check_circle</span>
                                                        <span class="line-clamp-1">{{ $inc }}</span>
                                                    </div>
                                                @endforeach
                                                @if(count($inclusionsRaw) > 4)
                                                    <span class="text-[10px] text-amber-600 font-bold block pt-0.5">
                                                        +{{ count($inclusionsRaw) - 4 }} additional inclusions
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Card Footer --}}
                            <div class="p-4 sm:p-5 pt-0 grid grid-cols-2 gap-2">
                                <button type="button"
                                    @click="previewPackage = {{ json_encode($pkgPayload) }}; activeImgIdx = 0;"
                                    class="w-full py-2.5 px-2 sm:px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px] text-slate-500">visibility</span>
                                    <span>Details</span>
                                </button>

                                <button type="button"
                                    @click="window.addToCart('package', {{ $pkg->id }})"
                                    class="w-full py-2.5 px-2 sm:px-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition-colors flex items-center justify-center gap-1 shadow-xs cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">shopping_cart</span>
                                    <span class="truncate">Add to Basket</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        {{-- Dynamic Package Preview Modal --}}
        <div x-show="previewPackage" x-transition.opacity @keydown.escape.window="previewPackage = null"
            class="fixed inset-0 z-[110] flex items-center justify-center p-3 sm:p-6 bg-slate-950/80 backdrop-blur-md overflow-y-auto"
            x-cloak style="display: none;">

            <div @click.away="previewPackage = null"
                class="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl relative my-auto">

                {{-- Modal Header --}}
                <div
                    class="relative bg-slate-900 text-white p-5 sm:p-7 shrink-0 overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <button @click="previewPackage = null"
                        aria-label="Close Package Details"
                        class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors cursor-pointer z-10">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>

                    <div class="flex flex-wrap items-center gap-2 mb-2 pr-10">
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 text-[10px] font-bold uppercase tracking-wider border border-amber-400/30 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px]">card_travel</span>
                            <span x-text="previewPackage?.type || 'Tour Package'"></span>
                        </span>
                        <template x-if="previewPackage?.destination_name">
                            <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-slate-200 text-[10px] font-medium flex items-center gap-1 border border-white/10">
                                <span class="material-symbols-outlined text-[12px] text-amber-400">location_on</span>
                                <span x-text="previewPackage.destination_name"></span>
                            </span>
                        </template>
                    </div>

                    <h2 class="text-xl sm:text-2xl font-black text-white font-headline tracking-tight leading-snug" x-text="previewPackage?.name"></h2>

                    <div class="mt-2.5 flex items-baseline gap-2">
                        <span class="text-xl sm:text-2xl font-black text-emerald-400 font-headline"
                            x-text="previewPackage?.price"></span>
                        <span class="text-xs text-slate-400 font-medium">/ person</span>
                    </div>
                </div>

                {{-- Modal Scrollable Body --}}
                <div class="p-4 sm:p-7 space-y-5 flex-1 overflow-y-auto overscroll-contain text-xs sm:text-sm text-slate-700">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
                        <span class="font-bold text-slate-800 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-amber-600">schedule</span>
                            <span x-text="previewPackage?.days + ' Days / ' + previewPackage?.nights + ' Nights'"></span>
                        </span>
                        <span>•</span>
                        <span class="font-bold text-slate-800 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-amber-600">group</span>
                            <span x-text="'Min ' + previewPackage?.min_pax + ' Passengers'"></span>
                        </span>
                        <template x-if="previewPackage?.valid_from">
                            <span class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full" x-text="'Valid: ' + previewPackage.valid_from + (previewPackage.valid_to ? ' → ' + previewPackage.valid_to : '')"></span>
                        </template>
                    </div>

                    {{-- Image Showcase & Gallery --}}
                    <template x-if="previewPackage?.images && previewPackage.images.length > 0">
                        <div class="space-y-2.5">
                            <div class="relative h-48 sm:h-64 rounded-2xl overflow-hidden bg-slate-900 group border border-slate-200/80 shadow-xs">
                                <img :src="previewPackage.images[activeImgIdx]" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute bottom-2.5 right-2.5 bg-slate-950/75 backdrop-blur-md text-white text-[11px] font-bold px-2.5 py-0.5 rounded-lg border border-white/20">
                                    <span x-text="activeImgIdx + 1"></span> / <span x-text="previewPackage.images.length"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Inclusions --}}
                    <template x-if="previewPackage?.generic_inclusions && previewPackage.generic_inclusions.length > 0">
                        <div class="space-y-2">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-emerald-600">checklist</span>
                                <span>Complete Package Inclusions</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="(inc, idx) in previewPackage.generic_inclusions" :key="idx">
                                    <div
                                        class="flex items-center gap-2 text-xs text-slate-700 bg-emerald-50/40 px-3 py-2.5 rounded-xl border border-emerald-200/60">
                                        <span
                                            class="material-symbols-outlined text-[16px] text-emerald-600 shrink-0">check_circle</span>
                                        <span x-text="inc"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Pinned Modal Footer --}}
                <div
                    class="bg-white p-3.5 sm:p-5 border-t border-slate-200/80 flex items-center gap-2.5 shrink-0 shadow-xs">
                    <button @click="previewPackage = null"
                        class="px-4 sm:px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-100 transition-colors cursor-pointer shrink-0">
                        Close
                    </button>

                    <button type="button" @click="window.addToCart('package', previewPackage.id); previewPackage = null;"
                        class="flex-1 py-2.5 px-4 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-[17px]">shopping_cart</span>
                        <span>Add Package to Trip Basket</span>
                    </button>
                </div>

            </div>
        </div>

    </div>
</x-frontend.layout>