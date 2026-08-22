<x-frontend.layout title="Sanctuary Stays & Hotels — SunnyTrips">
    @php
        $selectedDestId = request('destination_id');
    @endphp

    <div x-data="{
        activeDestId: '{{ $selectedDestId ?: 'all' }}',
        searchQuery: '',
        matchesHotel(destId, searchableText) {
            if (this.activeDestId !== 'all' && String(this.activeDestId) !== String(destId)) {
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
    }" class="py-12 bg-sand-50/70 text-slate-900 min-h-screen relative overflow-hidden">
        
        {{-- Background Soft Ambient Mesh Glows --}}
        <div class="absolute top-10 left-1/3 w-[500px] h-[300px] bg-sky-200/40 blur-3xl rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-1/3 w-[400px] h-[400px] bg-indigo-200/30 blur-3xl rounded-full pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 relative z-10">

            {{-- Header Banner --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-widest border border-sky-200">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">hotel</span>
                        <span>Full Sanctuary Catalog</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 font-headline tracking-tight">
                        Sanctuary Stays & Accommodations
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm font-body max-w-xl leading-relaxed">
                        Browse our complete collection of beachfront resorts, private villas, and boutique sanctuaries. Select any stay to view full room options, amenities, and details.
                    </p>
                </div>

                {{-- Destination Filter Tabs (Admin Side Style) --}}
                <div class="flex items-center gap-2 overflow-x-auto max-w-full p-2 bg-slate-100/90 rounded-2xl border border-slate-200 shrink-0">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 shrink-0 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-400">filter_alt</span>
                        <span>Location:</span>
                    </span>
                    <button type="button" @click="activeDestId = 'all'"
                        :class="activeDestId === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 border cursor-pointer">
                        All Islands ({{ $hotels->count() }})
                    </button>
                    @foreach($destinations as $dest)
                        @php
                            $destHotelCount = $hotels->where('destination_id', $dest->id)->count();
                        @endphp
                        @if($destHotelCount > 0)
                            <button type="button" @click="activeDestId = '{{ $dest->id }}'"
                                :class="activeDestId === '{{ $dest->id }}' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer">
                                <span>{{ $dest->name }}</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="activeDestId === '{{ $dest->id }}' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">
                                    {{ $destHotelCount }}
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Clean Search Bar Toolbar --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="relative w-full sm:w-96">
                    <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                    <input type="text" x-model="searchQuery" placeholder="Search hotel name, tags, or location keyword..."
                        class="w-full bg-slate-50 border border-slate-200 focus:border-sky-500 focus:bg-white rounded-2xl py-2.5 pl-10 pr-8 text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none transition-all shadow-xs" />
                    <button x-show="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <span class="material-symbols-outlined text-sm">cancel</span>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <button x-show="searchQuery || activeDestId !== 'all'"
                        @click="searchQuery = ''; activeDestId = 'all';"
                        class="text-[11px] font-bold text-rose-600 hover:text-rose-700 flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                        <span>Clear Filter</span>
                    </button>
                </div>
            </div>

            {{-- Hotels Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($hotels as $hotel)
                    @php
                        $hotelImg = App\Concerns\ResolvesImages::resolveImg(
                            $hotel->images[0] ?? null,
                            'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80'
                        );
                        
                        $destName = $hotel->destination ? $hotel->destination->name : '';
                        $typeStr = str_replace('-', ' ', $hotel->type ?? '');
                        
                        $vibeTagsRaw = is_array($hotel->vibe_tags) ? $hotel->vibe_tags : (is_string($hotel->vibe_tags) ? array_filter(array_map('trim', explode(',', $hotel->vibe_tags))) : []);
                        $vibeTagsStr = implode(' ', $vibeTagsRaw);
                        
                        $amenitiesRaw = is_array($hotel->featured_amenities) ? $hotel->featured_amenities : (is_string($hotel->featured_amenities) ? array_filter(array_map('trim', explode(',', $hotel->featured_amenities))) : []);
                        $amenitiesStr = implode(' ', $amenitiesRaw);

                        $searchablePayload = implode(' ', [
                            $hotel->hotel_name,
                            $hotel->hotel_description ?? '',
                            $destName,
                            $typeStr,
                            $hotel->specific_address ?? '',
                            $vibeTagsStr,
                            $amenitiesStr
                        ]);
                    @endphp

                    <div x-show="matchesHotel('{{ $hotel->destination_id }}', {{ json_encode($searchablePayload) }})"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                        
                        <div>
                            {{-- Image Container --}}
                            <div class="relative h-48 overflow-hidden bg-slate-100">
                                <img src="{{ $hotelImg }}" alt="{{ $hotel->hotel_name }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>

                                {{-- Destination Badge --}}
                                {{-- @if($hotel->destination)
                                    <div class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-white/10 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[13px] text-sky-400">location_on</span>
                                        <span>{{ $hotel->destination->name }}</span>
                                    </div>
                                @endif --}}

                                @if($hotel->type)
                                    <div class="absolute bottom-3 left-3 bg-white/90 backdrop-blur-xs text-slate-900 text-[10px] font-bold px-2 py-0.5 rounded-md">
                                        {{ ucwords(str_replace('-', ' ', $hotel->type)) }}
                                    </div>
                                @endif
                            </div>

                            {{-- Details --}}
                            <div class="p-5 space-y-2">
                                <h3 class="text-base font-bold text-slate-900 group-hover:text-sky-600 transition-colors font-headline line-clamp-1">
                                    {{ $hotel->hotel_name }}
                                </h3>
                                <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                    {{ $hotel->hotel_description ?: 'Experience luxury island living with premium amenities and stunning views.' }}
                                </p>

                                @if(!empty($vibeTagsRaw))
                                    <div class="flex flex-wrap gap-1 pt-1">
                                        @foreach(array_slice($vibeTagsRaw, 0, 3) as $tag)
                                            <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 text-[10px] font-semibold border border-sky-200">
                                                #{{ $tag }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Card Footer --}}
                        <div class="p-5 pt-0">
                            <a href="{{ route('hotels.show', $hotel->id) }}"
                                class="w-full py-2.5 px-4 rounded-xl bg-slate-50 group-hover:bg-gradient-to-r group-hover:from-sky-500 group-hover:to-sky-600 text-slate-700 group-hover:text-white font-bold text-xs transition-all duration-300 flex items-center justify-between border border-slate-200 group-hover:border-sky-600 shadow-xs cursor-pointer">
                                <span>View Sanctuary Details</span>
                                <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</x-frontend.layout>
