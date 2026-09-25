<x-frontend.layout title="Tour Packages & Vacation Deals — SunnyTrips">
    @php
        $selectedDestId = request('destination_id');
        $searchQuery = request('search');
    @endphp

    <div x-data="{
        activeDestId: '{{ $selectedDestId ?: 'all' }}',
        searchQuery: @js($searchQuery ?? ''),
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
    }" class="{{ Auth::check() ? 'py-12' : 'pt-20 sm:pt-28 pb-12' }} bg-sand-50/70 text-slate-900 min-h-screen relative overflow-hidden">

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
                                        class="absolute top-3 right-3 bg-slate-950/90 text-emerald-400 font-extrabold text-xs px-3 py-1 rounded-xl border border-white/10 shadow-xs z-10 flex items-center gap-1.5">
                                        <span>₱{{ number_format((float) $pkg->price, 2) }} <span
                                                class="text-[10px] font-normal text-slate-300">/ pax</span></span>
                                        <x-frontend.price-change-badge :change="$priceChanges[$pkg->id] ?? null" />
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
                                    @click="$store.preview.openPackageById({{ $pkg->id }})"
                                    class="w-full py-2.5 px-2 sm:px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px] text-slate-500">visibility</span>
                                    <span>Details</span>
                                </button>

                                <button type="button"
                                    @click="window.addToCart('package', {{ $pkg->id }}, { quantity: {{ $pkg->min_pax ?: 2 }}, selected_pax: {{ $pkg->min_pax ?: 2 }} })"
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


    </div>
</x-frontend.layout>