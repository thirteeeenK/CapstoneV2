<x-frontend.layout :title="$hotel->hotel_name . ' — SunnyTrips'">

    {{-- Admin Preview Banner --}}
    @if ($isAdminPreview)
        <div
            class="bg-amber-500 text-slate-950 font-bold px-4 py-2.5 text-xs text-center flex items-center justify-center gap-2 shadow-md z-[60] sticky top-0">
            <span class="material-symbols-outlined text-[18px]">visibility</span>
            <span>Admin Preview Mode &mdash; This is how public visitors see this hotel page.</span>
            @if (!$hotel->is_shown)
                <span
                    class="bg-slate-900 text-amber-300 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Hidden
                    from Public</span>
            @else
                <span
                    class="bg-emerald-950 text-emerald-300 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">Publicly
                    Visible</span>
            @endif
        </div>
    @endif

    @php
        // Image path resolution helper
        $resolveImg = function ($imgPath) {
            if (empty($imgPath)) {
                return 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80';
            }
            if (str_starts_with($imgPath, 'http://') || str_starts_with($imgPath, 'https://')) {
                return $imgPath;
            }
            return asset('storage/' . $imgPath);
        };

        $heroImage = !empty($hotel->images) && is_array($hotel->images) && isset($hotel->images[0])
            ? $resolveImg($hotel->images[0])
            : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80';

        // Amenity icon mapping helper
        $getAmenityIcon = function ($amenity) {
            $a = strtolower($amenity);
            if (str_contains($a, 'pool'))
                return 'pool';
            if (str_contains($a, 'wifi') || str_contains($a, 'internet'))
                return 'wifi';
            if (str_contains($a, 'bar') || str_contains($a, 'drink'))
                return 'local_bar';
            if (str_contains($a, 'restaurant') || str_contains($a, 'food') || str_contains($a, 'dining') || str_contains($a, 'breakfast'))
                return 'restaurant';
            if (str_contains($a, 'spa') || str_contains($a, 'massage'))
                return 'spa';
            if (str_contains($a, 'beach') || str_contains($a, 'ocean'))
                return 'beach_access';
            if (str_contains($a, 'air') || str_contains($a, 'climate') || str_contains($a, 'ac'))
                return 'ac_unit';
            if (str_contains($a, 'gym') || str_contains($a, 'fitness'))
                return 'fitness_center';
            if (str_contains($a, 'balcony') || str_contains($a, 'terrace') || str_contains($a, 'patio'))
                return 'deck';
            if (str_contains($a, 'bath') || str_contains($a, 'shower') || str_contains($a, 'tub'))
                return 'bathtub';
            if (str_contains($a, 'tour') || str_contains($a, 'desk'))
                return 'concierge';
            if (str_contains($a, 'coffee') || str_contains($a, 'tea') || str_contains($a, 'espresso'))
                return 'coffee_maker';
            if (str_contains($a, 'tv') || str_contains($a, 'screen'))
                return 'tv';
            return 'star';
        };
    @endphp

    <div x-data="{
        selectedRoom: null,
        activeModalImg: null,
        selectRoom(room) {
            this.selectedRoom = room;
            $nextTick(() => {
                const el = document.getElementById('selected-room-sidebar');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        }
    }" class="pt-32 sm:pt-36 pb-24 bg-slate-50 min-h-screen">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

            {{-- ══════════════════════════════════════════
            1. HERO BANNER
            ══════════════════════════════════════════ --}}
            <div
                class="relative rounded-3xl overflow-hidden shadow-2xl min-h-[550px] sm:min-h-[640px] lg:min-h-[720px] flex items-end p-6 sm:p-12 group">
                {{-- Background Image --}}
                <img src="{{ $heroImage }}" alt="{{ $hotel->hotel_name }}"
                    class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out">

                {{-- Dark Gradient Overlay --}}
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-transparent"></div>

                {{-- Hero Content --}}
                <div class="relative z-10 space-y-3 max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-white text-[11px] font-extrabold uppercase tracking-widest border border-white/30">
                            {{ ucwords(str_replace('-', ' ', $hotel->type ?? 'Luxury Stay')) }}
                        </span>
                        @if($hotel->destination)
                            <span
                                class="px-3 py-1 rounded-full bg-ocean-600/80 backdrop-blur-md text-white text-[11px] font-bold uppercase tracking-wider border border-ocean-400/40 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">location_on</span>
                                {{ $hotel->destination->name }}
                            </span>
                        @endif
                    </div>

                    <h1
                        class="text-3xl sm:text-5xl font-black text-white font-headline tracking-tight leading-tight drop-shadow-md">
                        {{ $hotel->hotel_name }}
                    </h1>

                    <p class="text-slate-200 text-xs sm:text-sm font-medium flex items-center gap-1.5 opacity-90">
                        <span class="material-symbols-outlined text-ocean-400 text-[18px]">pin_drop</span>
                        {{ $hotel->specific_address }}
                    </p>

                    {{-- Vibe Tags --}}
                    @if(!empty($hotel->vibe_tags) && is_array($hotel->vibe_tags))
                        <div class="pt-2 flex flex-wrap gap-1.5">
                            @foreach($hotel->vibe_tags as $tag)
                                <span
                                    class="px-2.5 py-1 rounded-lg bg-black/40 backdrop-blur-sm text-slate-200 text-[11px] font-medium border border-white/10">
                                    #{{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- ══════════════════════════════════════════
            2. MAIN OVERVIEW & STICKY SELECT ROOM SIDEBAR
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                {{-- LEFT COLUMN (8 cols on desktop): Description + Amenities --}}
                <div class="lg:col-span-7 xl:col-span-8 space-y-8">

                    {{-- Overview Card --}}
                    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">
                            Hotel Description
                        </h2>
                        <div class="text-slate-600 text-sm leading-relaxed space-y-3 font-body">
                            <p>{{ $hotel->hotel_description }}</p>
                        </div>
                    </div>

                    {{-- Exclusive Amenities Grid --}}
                    @if(!empty($hotel->featured_amenities) && is_array($hotel->featured_amenities))
                        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-4">
                                <span class="w-1.5 h-6 bg-ocean-600 rounded-full"></span>
                                <h3 class="text-lg font-bold text-slate-900 font-headline">
                                    Exclusive Amenities & Services
                                </h3>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                @foreach($hotel->featured_amenities as $amenity)
                                    <div
                                        class="bg-slate-50 hover:bg-ocean-50/50 p-4 rounded-xl border border-slate-200/60 transition-all duration-200 flex flex-col justify-between space-y-2 group">
                                        <div
                                            class="w-10 h-10 rounded-lg bg-white shadow-xs border border-slate-200 text-ocean-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                                            <span class="material-symbols-outlined text-[22px]">
                                                {{ $getAmenityIcon($amenity) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs font-bold text-slate-900 group-hover:text-ocean-700 transition-colors">
                                                {{ $amenity }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>

                {{-- RIGHT COLUMN (4 cols on desktop): STICKY ROOM SELECTOR & RATE CARD --}}
                <div id="selected-room-sidebar" class="lg:col-span-5 xl:col-span-4 sticky top-32 sm:top-36 space-y-4">
                    <div
                        class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200/90 shadow-xl relative overflow-hidden transition-all">
                        <div
                            class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-bl from-ocean-500/10 via-transparent to-transparent rounded-bl-full pointer-events-none">
                        </div>

                        {{-- WHEN NO ROOM IS SELECTED YET --}}
                        <template x-if="!selectedRoom">
                            <div class="text-center py-6 space-y-4">
                                <div
                                    class="w-16 h-16 rounded-full bg-ocean-50 text-ocean-600 flex items-center justify-center mx-auto border border-ocean-100">
                                    <span class="material-symbols-outlined text-[32px]">king_bed</span>
                                </div>
                                <div class="space-y-1">
                                    <h3 class="text-lg font-bold text-slate-900 font-headline">Select a Room</h3>
                                    <p class="text-xs text-slate-500 max-w-xs mx-auto">
                                        Choose your preferred room type from the gallery below to view live pricing and
                                        details.
                                    </p>
                                </div>
                                <a href="#room-gallery"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-ocean-600 hover:text-ocean-700 bg-ocean-50 px-4 py-2 rounded-full transition-colors">
                                    <span>Browse Available Rooms</span>
                                    <span class="material-symbols-outlined text-[16px]">arrow_downward</span>
                                </a>
                            </div>
                        </template>

                        {{-- WHEN A ROOM IS SELECTED --}}
                        <template x-if="selectedRoom">
                            <div class="space-y-6 animate-fade-in">
                                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                                    <div>
                                        <span
                                            class="text-[10px] font-bold uppercase tracking-wider text-ocean-600 bg-ocean-50 px-2.5 py-0.5 rounded-md">Selected
                                            Room</span>
                                        <h3 class="text-xl font-bold text-slate-900 font-headline mt-1"
                                            x-text="selectedRoom.room_name"></h3>
                                    </div>
                                    <button @click="selectedRoom = null"
                                        class="text-slate-400 hover:text-slate-600 text-xs flex items-center gap-0.5 bg-slate-100 px-2 py-1 rounded-md"
                                        title="Clear selection">
                                        <span class="material-symbols-outlined text-[14px]">close</span> Reset
                                    </button>
                                </div>

                                {{-- Price Display --}}
                                <div class="bg-slate-900 text-white p-4 rounded-xl space-y-1">
                                    <p class="text-[11px] text-slate-400 uppercase font-semibold tracking-wider">Base
                                        Room Rate</p>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-headline">
                                            ₱<span
                                                x-text="Number(selectedRoom.base_price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </span>
                                        <span class="text-xs text-slate-400 font-medium">/ night</span>
                                    </div>
                                </div>

                                {{-- Specs Grid --}}
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 space-y-0.5">
                                        <span class="text-slate-400 text-[10px] block uppercase font-medium">Bed
                                            Layout</span>
                                        <span class="font-bold text-slate-800"
                                            x-text="selectedRoom.bed_configuration || 'Standard'"></span>
                                    </div>
                                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 space-y-0.5">
                                        <span class="text-slate-400 text-[10px] block uppercase font-medium">Max
                                            Guests</span>
                                        <span class="font-bold text-slate-800"
                                            x-text="(selectedRoom.occupancy || 2) + ' Persons'"></span>
                                    </div>
                                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 space-y-0.5"
                                        x-show="selectedRoom.room_size">
                                        <span class="text-slate-400 text-[10px] block uppercase font-medium">Room
                                            Size</span>
                                        <span class="font-bold text-slate-800" x-text="selectedRoom.room_size"></span>
                                    </div>
                                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 space-y-0.5"
                                        x-show="selectedRoom.view_type">
                                        <span class="text-slate-400 text-[10px] block uppercase font-medium">View
                                            Type</span>
                                        <span class="font-bold text-slate-800" x-text="selectedRoom.view_type"></span>
                                    </div>
                                </div>

                                {{-- Description snippet --}}
                                <div x-show="selectedRoom.description"
                                    class="text-xs text-slate-600 bg-slate-50/70 p-3 rounded-lg border border-slate-100">
                                    <p x-text="selectedRoom.description"></p>
                                </div>

                                {{-- Action Button --}}
                                <button type="button"
                                    class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-ocean-600 to-ocean-700 hover:from-ocean-700 hover:to-ocean-800 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-[18px]">bookmark</span>
                                    <span>Select & Continue</span>
                                </button>
                            </div>
                        </template>

                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════
            3. ROOM GALLERY & TYPE SELECTION
            ══════════════════════════════════════════ --}}
            <div id="room-gallery" class="space-y-6 pt-6">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">
                            Available Room Types
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Explore available suites & rooms at {{ $hotel->hotel_name }}
                        </p>
                    </div>
                    <span
                        class="text-xs font-bold text-ocean-600 bg-ocean-50 px-3 py-1.5 rounded-full border border-ocean-100">
                        {{ $hotel->rooms->count() }} Room {{ Str::plural('Type', $hotel->rooms->count()) }}
                    </span>
                </div>

                @if($hotel->rooms->isEmpty())
                    <div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 text-sm">
                        No room details listed for this hotel yet.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($hotel->rooms as $room)
                            @php
                                $roomImg = !empty($room->images) && is_array($room->images) && isset($room->images[0])
                                    ? $resolveImg($room->images[0])
                                    : $heroImage;
                            @endphp
                            <div
                                class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between group">
                                <div>
                                    {{-- Room Image --}}
                                    <div class="relative h-56 sm:h-64 overflow-hidden bg-slate-100">
                                        <img src="{{ $roomImg }}" alt="{{ $room->room_name }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        <div
                                            class="absolute top-3 right-3 bg-slate-950/80 backdrop-blur-md text-emerald-400 font-extrabold text-xs px-2.5 py-1 rounded-lg border border-white/20">
                                            ₱{{ number_format($room->base_price, 2) }} / night
                                        </div>
                                        @if($room->view_type)
                                            <div
                                                class="absolute bottom-3 left-3 bg-slate-950/70 backdrop-blur-md text-white text-[10px] font-semibold px-2 py-0.5 rounded-md flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[12px]">visibility</span>
                                                {{ $room->view_type }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Room Information --}}
                                    <div class="p-5 space-y-3">
                                        <h3
                                            class="text-base font-bold text-slate-900 group-hover:text-ocean-600 transition-colors font-headline">
                                            {{ $room->room_name }}
                                        </h3>

                                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                            {{ $room->description }}
                                        </p>

                                        {{-- Badges --}}
                                        <div class="flex flex-wrap gap-2 text-[11px] text-slate-600 pt-1">
                                            @if($room->bed_configuration)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">bed</span>
                                                    {{ $room->bed_configuration }}
                                                </span>
                                            @endif
                                            @if($room->occupancy)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">person</span>
                                                    {{ $room->occupancy }} Guests
                                                </span>
                                            @endif
                                            @if($room->room_size)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span
                                                        class="material-symbols-outlined text-[14px] text-slate-400">aspect_ratio</span>
                                                    {{ $room->room_size }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Card Footer / Select Button --}}
                                <div class="p-5 pt-0">
                                    <button type="button" @click="selectRoom({{ json_encode($room) }})"
                                        class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-ocean-600 hover:text-white text-slate-800 font-bold text-xs transition-colors flex items-center justify-center gap-1.5 shadow-2xs">
                                        <span class="material-symbols-outlined text-[16px]">touch_app</span>
                                        <span>Select Room</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ══════════════════════════════════════════
            4. HOTEL PHOTO GALLERY & LIGHTBOX
            ══════════════════════════════════════════ --}}
            @if(!empty($hotel->images) && is_array($hotel->images) && count($hotel->images) > 1)
                <div class="space-y-4 pt-6">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">
                            Hotel Photo Gallery
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Immerse yourself in the atmosphere of {{ $hotel->hotel_name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        @foreach($hotel->images as $idx => $img)
                            @php $fullUrl = $resolveImg($img); @endphp
                            <div @click="activeModalImg = '{{ $fullUrl }}'"
                                class="relative h-40 sm:h-48 rounded-xl overflow-hidden cursor-pointer group bg-slate-200">
                                <img src="{{ $fullUrl }}" alt="Hotel photo {{ $idx + 1 }}"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                <div
                                    class="absolute inset-0 bg-slate-950/0 group-hover:bg-slate-950/40 transition-colors flex items-center justify-center">
                                    <span
                                        class="material-symbols-outlined text-white text-[28px] opacity-0 group-hover:opacity-100 transition-opacity">zoom_in</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        {{-- Lightbox Modal --}}
        <div x-show="activeModalImg" x-transition.opacity @keydown.escape.window="activeModalImg = null"
            class="fixed inset-0 z-[100] bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4"
            style="display: none;">
            <div class="relative max-w-5xl max-h-[90vh] w-full">
                <button @click="activeModalImg = null"
                    class="absolute -top-10 right-0 text-white hover:text-slate-300 flex items-center gap-1 text-xs font-bold bg-white/10 px-3 py-1 rounded-full">
                    <span class="material-symbols-outlined text-[16px]">close</span> Close (Esc)
                </button>
                <img :src="activeModalImg"
                    class="w-full h-auto max-h-[85vh] object-contain rounded-2xl shadow-2xl mx-auto border border-white/10">
            </div>
        </div>

    </div>

</x-frontend.layout>