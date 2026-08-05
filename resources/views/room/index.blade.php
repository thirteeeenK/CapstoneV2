<x-frontend.layout title="Rooms & Luxury Suites — SunnyTrips">
    @php
        $selectedDestId = request('destination_id');
    @endphp

    <div x-data="{
        activeDestId: '{{ $selectedDestId ?: 'all' }}',
        searchQuery: '',
        priceFilter: 'all',
        matchesRoom(destId, searchableText, rate) {
            if (this.activeDestId !== 'all' && String(this.activeDestId) !== String(destId)) {
                return false;
            }
            if (this.searchQuery.trim() !== '') {
                let terms = this.searchQuery.toLowerCase().trim().split(/\s+/);
                let sText = (searchableText || '').toLowerCase();
                let matches = terms.every(term => sText.includes(term));
                if (!matches) return false;
            }
            let numRate = Number(rate) || 0;
            if (this.priceFilter === 'budget' && numRate > 2500) return false;
            if (this.priceFilter === 'mid' && (numRate < 2500 || numRate > 6000)) return false;
            if (this.priceFilter === 'luxury' && numRate < 6000) return false;
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
                        <span class="material-symbols-outlined text-[16px] text-sky-600">king_bed</span>
                        <span>Accommodations Catalog</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 font-headline tracking-tight">
                        Rooms, Suites & Ocean Villas
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm font-body max-w-xl leading-relaxed">
                        Browse all available luxury room types across our partner sanctuary hotels. Select any room to view full hotel details and reservation options.
                    </p>
                </div>

                {{-- Destination Filter Tabs --}}
                <div class="flex items-center gap-2 overflow-x-auto max-w-full p-2 bg-slate-100/90 rounded-2xl border border-slate-200 shrink-0">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 shrink-0 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-400">filter_alt</span>
                        <span>Location:</span>
                    </span>
                    <button type="button" @click="activeDestId = 'all'"
                        :class="activeDestId === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 border cursor-pointer">
                        All Islands ({{ $rooms->count() }})
                    </button>
                    @foreach($destinations as $dest)
                        @php
                            $destRoomCount = $rooms->filter(fn($r) => $r->hotel && $r->hotel->destination_id == $dest->id)->count();
                        @endphp
                        @if($destRoomCount > 0)
                            <button type="button" @click="activeDestId = '{{ $dest->id }}'"
                                :class="activeDestId === '{{ $dest->id }}' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer">
                                <span>{{ $dest->name }}</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="activeDestId === '{{ $dest->id }}' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">
                                    {{ $destRoomCount }}
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Interactive Search & Price Toolbar --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    
                    {{-- Search Input --}}
                    <div class="md:col-span-6 relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" x-model="searchQuery" placeholder="Search room suite name, hotel, tags, or amenities..."
                            class="w-full bg-slate-50 border border-slate-200 focus:border-sky-500 focus:bg-white rounded-2xl py-2.5 pl-10 pr-8 text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none transition-all shadow-xs" />
                        <button x-show="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <span class="material-symbols-outlined text-sm">cancel</span>
                        </button>
                    </div>

                    {{-- Nightly Rate Pills --}}
                    <div class="md:col-span-6 flex flex-wrap items-center gap-2 justify-start md:justify-end">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1 shrink-0 mr-1">
                            <span class="material-symbols-outlined text-[15px] text-sky-600">payments</span>
                            <span>Rate Filter:</span>
                        </span>
                        
                        <button type="button" @click="priceFilter = 'all'"
                            :class="priceFilter === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-xl text-xs transition-all cursor-pointer">
                            All Rates
                        </button>

                        <button type="button" @click="priceFilter = 'budget'"
                            :class="priceFilter === 'budget' ? 'bg-sky-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-xl text-xs transition-all cursor-pointer">
                            Under ₱2,500
                        </button>

                        <button type="button" @click="priceFilter = 'mid'"
                            :class="priceFilter === 'mid' ? 'bg-sky-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-xl text-xs transition-all cursor-pointer">
                            ₱2.5k - ₱6k
                        </button>

                        <button type="button" @click="priceFilter = 'luxury'"
                            :class="priceFilter === 'luxury' ? 'bg-sky-600 text-white font-extrabold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3 py-1.5 rounded-xl text-xs transition-all cursor-pointer">
                            ₱6,000+
                        </button>

                        <button x-show="searchQuery || priceFilter !== 'all' || activeDestId !== 'all'"
                            @click="searchQuery = ''; priceFilter = 'all'; activeDestId = 'all';"
                            class="text-[11px] font-bold text-rose-600 hover:text-rose-700 ml-2 flex items-center gap-1 cursor-pointer">
                            <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                            <span>Clear</span>
                        </button>
                    </div>

                </div>
            </div>

            {{-- Rooms Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($rooms as $room)
                    @php
                        $roomImagesRaw = is_array($room->images) ? $room->images : (is_string($room->images) ? (json_decode($room->images, true) ?: []) : []);
                        $roomImg = App\Concerns\ResolvesImages::resolveImg(
                            $roomImagesRaw[0] ?? null,
                            'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=800&q=80'
                        );
                        $destId = $room->hotel ? $room->hotel->destination_id : null;
                        $destName = ($room->hotel && $room->hotel->destination) ? $room->hotel->destination->name : '';
                        $hotelName = $room->hotel ? $room->hotel->hotel_name : '';
                        $hotelType = $room->hotel ? str_replace('-', ' ', $room->hotel->type ?? '') : '';
                        
                        $rate = (float)($room->base_price ?: ($room->rate_per_night ?: 0));

                        $roomAmenitiesRaw = is_array($room->room_amenities) ? $room->room_amenities : (is_string($room->room_amenities) ? array_filter(array_map('trim', explode(',', $room->room_amenities))) : []);
                        $roomAmenitiesStr = implode(' ', $roomAmenitiesRaw);

                        $hotelVibesRaw = ($room->hotel && is_array($room->hotel->vibe_tags)) ? $room->hotel->vibe_tags : (($room->hotel && is_string($room->hotel->vibe_tags)) ? array_filter(array_map('trim', explode(',', $room->hotel->vibe_tags))) : []);
                        $hotelVibesStr = implode(' ', $hotelVibesRaw);

                        $searchablePayload = implode(' ', [
                            $room->room_name,
                            $room->description ?? '',
                            $hotelName,
                            $destName,
                            $hotelType,
                            $room->view_type ?? '',
                            $room->bed_configuration ?? '',
                            $room->ideal_guest ?? '',
                            $roomAmenitiesStr,
                            $hotelVibesStr
                        ]);
                    @endphp

                    <div x-show="matchesRoom('{{ $destId }}', {{ json_encode($searchablePayload) }}, {{ $rate }})"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                        
                        <div>
                            {{-- Image Container --}}
                            <div class="relative h-44 overflow-hidden bg-slate-100">
                                <img src="{{ $roomImg }}" alt="{{ $room->room_name }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>

                                @if($room->hotel)
                                    <div class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-white/10 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[13px] text-sky-400">hotel</span>
                                        <span class="truncate max-w-[140px]">{{ $room->hotel->hotel_name }}</span>
                                    </div>
                                @endif

                                @if($rate > 0)
                                    <div class="absolute bottom-3 right-3 bg-slate-900/90 text-emerald-400 font-extrabold text-xs px-2.5 py-0.5 rounded-lg border border-white/10">
                                        ₱{{ number_format($rate, 2) }} <span class="text-[10px] font-normal text-slate-400">/ night</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Details --}}
                            <div class="p-5 space-y-2">
                                <h3 class="text-base font-bold text-slate-900 group-hover:text-sky-600 transition-colors font-headline line-clamp-1">
                                    {{ $room->room_name }}
                                </h3>
                                <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                    {{ $room->description ?: 'Spacious room suite with modern amenities and scenic island views.' }}
                                </p>

                                <div class="flex items-center gap-3 pt-1 text-[11px] text-slate-600 font-medium flex-wrap">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-sky-600">group</span>
                                        <span>Base: {{ $room->base_occupancy ?: 2 }} • Max: {{ $room->max_occupancy ?: ($room->occupancy ?: 4) }} Guests</span>
                                    </span>

                                    @if($room->view_type)
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px] text-sky-600">visibility</span>
                                            <span>{{ $room->view_type }}</span>
                                        </span>
                                    @endif
                                </div>

                                @if(!empty($roomAmenitiesRaw))
                                    <div class="flex flex-wrap gap-1 pt-1.5">
                                        @foreach(array_slice($roomAmenitiesRaw, 0, 3) as $amenity)
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-medium border border-slate-200/80">
                                                {{ $amenity }}
                                            </span>
                                        @endforeach
                                        @if(count($roomAmenitiesRaw) > 3)
                                            <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-400 text-[10px]">
                                                +{{ count($roomAmenitiesRaw) - 3 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Card Footer --}}
                        <div class="p-5 pt-0">
                            @if($room->hotel_id)
                                <a href="{{ route('hotels.show', $room->hotel_id) }}#room-{{ $room->id }}"
                                    class="w-full py-2.5 px-4 rounded-xl bg-slate-50 group-hover:bg-gradient-to-r group-hover:from-sky-500 group-hover:to-sky-600 text-slate-700 group-hover:text-white font-bold text-xs transition-all duration-300 flex items-center justify-between border border-slate-200 group-hover:border-sky-600 shadow-xs cursor-pointer">
                                    <span>View Hotel & Book Room</span>
                                    <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</x-frontend.layout>
