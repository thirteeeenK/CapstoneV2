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
        $heroImage = App\Concerns\ResolvesImages::resolveImg(
            $hotel->images[0] ?? null,
            'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80'
        );
        $galleryImages = array_values(array_map(
            fn($img) => App\Concerns\ResolvesImages::resolveImg($img),
            is_array($hotel->images) ? $hotel->images : []
        ));
    @endphp

    <div x-data="{
        selectedRoom: null,
        selectedPax: 2,
        roomCheckIn: '',
        roomCheckOut: '',
        roomAvailable: true,
        activeModalImg: null,
        activeModalImgIndex: 0,
        galleryImages: {{ json_encode($galleryImages) }},
        previewRoom: null,
        activePreviewImgIndex: 0,
        autoPreviewRoomId: {{ request('preview_room') ? (int)request('preview_room') : 'null' }},

        get computedNightlyRate() {
            const target = this.previewRoom || this.selectedRoom;
            if (!target) return 0;
            const basePrice = Number(target.base_price) || 0;
            const basePax = Number(target.base_occupancy) || 2;
            const extraFee = Number(target.extra_person_fee) || 0;
            const pax = Number(this.selectedPax) || basePax;
            if (pax > basePax) {
                return basePrice + ((pax - basePax) * extraFee);
            }
            return basePrice;
        },

        getPaxOptions(room) {
            if (!room) return [1, 2];
            const max = Math.max(1, Number(room.max_occupancy) || Number(room.occupancy) || 3);
            const opts = [];
            for (let i = 1; i <= max; i++) {
                opts.push(i);
            }
            return opts;
        },

        init() {
            if (this.autoPreviewRoomId) {
                this.$nextTick(() => {
                    const card = document.getElementById('room-card-' + this.autoPreviewRoomId);
                    if (card) {
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    const btn = document.getElementById('preview-btn-' + this.autoPreviewRoomId);
                    if (btn) {
                        btn.click();
                    }
                });
            }
        },
        selectRoom(room) {
            this.selectedRoom = room;
            this.selectedPax = Number(room.base_occupancy) || 2;
            this.roomCheckIn = '';
            this.roomCheckOut = '';
            this.roomAvailable = true;
            this.$nextTick(() => {
                const el = document.getElementById('selected-room-sidebar');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        },
        openRoomPreview(room) {
            this.previewRoom = room;
            this.selectedPax = Number(room.base_occupancy) || 2;
            this.roomCheckIn = '';
            this.roomCheckOut = '';
            this.roomAvailable = true;
            this.activePreviewImgIndex = 0;
        },
        closeRoomPreview() {
            this.previewRoom = null;
            this.roomCheckIn = '';
            this.roomCheckOut = '';
        },
        confirmRoomSelection(room) {
            this.selectRoom(room);
            this.closeRoomPreview();
        },
        openGallery(idx) {
            this.activeModalImgIndex = idx;
            this.activeModalImg = this.galleryImages[idx] || null;
        },
        prevGallery() {
            if (!this.galleryImages.length) return;
            this.activeModalImgIndex = (this.activeModalImgIndex - 1 + this.galleryImages.length) % this.galleryImages.length;
            this.activeModalImg = this.galleryImages[this.activeModalImgIndex];
        },
        nextGallery() {
            if (!this.galleryImages.length) return;
            this.activeModalImgIndex = (this.activeModalImgIndex + 1) % this.galleryImages.length;
            this.activeModalImg = this.galleryImages[this.activeModalImgIndex];
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

                    {{-- DSS Review Summary & Verified Guest Reviews --}}
                    <x-reviews.summary-box :summary="$hotel->reviewSummary" title="Guest Reviews & Sentiment" />
                    <x-reviews.list :reviews="$hotel->reviews->where('is_published', true)" :limit="4" />

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
                                                {{ App\Concerns\ResolvesImages::getAmenityIcon($amenity) }}
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

                                {{-- Dynamic Calculated Price Display --}}
                                <div class="bg-slate-900 text-white p-4 rounded-xl space-y-2">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[11px] text-slate-400 uppercase font-semibold tracking-wider">Calculated Room Rate</p>
                                        <span x-show="selectedPax > (selectedRoom.base_occupancy || 2)" class="text-[10px] font-bold bg-amber-400 text-slate-950 px-2 py-0.5 rounded-full" x-cloak>
                                            +₱<span x-text="((selectedPax - (selectedRoom.base_occupancy || 2)) * (selectedRoom.extra_person_fee || 0)).toLocaleString('en-US')"></span> Extra Guest Fee
                                        </span>
                                    </div>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-headline">
                                            ₱<span x-text="Number(computedNightlyRate).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </span>
                                        <span class="text-xs text-slate-400 font-medium">/ night</span>
                                    </div>
                                </div>

                                 {{-- Specs Grid --}}
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 space-y-0.5">
                                        <span class="text-slate-400 text-[10px] block uppercase font-medium">Bed Layout</span>
                                        <span class="font-bold text-slate-800 truncate block" x-text="selectedRoom.bed_configuration || 'Standard'"></span>
                                    </div>
                                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 space-y-0.5">
                                        <span class="text-slate-400 text-[10px] block uppercase font-medium">Capacity (Base / Max)</span>
                                        <span class="font-bold text-slate-800 block" x-text="'Base: ' + (selectedRoom.base_occupancy || 2) + ' • Max: ' + (selectedRoom.max_occupancy || selectedRoom.occupancy || 4) + ' Pax'"></span>
                                    </div>
                                </div>

                                {{-- Description snippet --}}
                                <div x-show="selectedRoom.description"
                                    class="text-xs text-slate-600 bg-slate-50/70 p-3 rounded-lg border border-slate-100">
                                    <p x-text="selectedRoom.description"></p>
                                </div>

                                {{-- Date Range Picker Component --}}
                                <div class="pt-2" @date-range-changed.stop="roomCheckIn = $event.detail.checkIn; roomCheckOut = $event.detail.checkOut; roomAvailable = $event.detail.available">
                                    <x-frontend.date-range-picker :room-id="null" :base-price="0" />
                                </div>

                                {{-- Action Button --}}
                                <button type="button" 
                                    @click="if (!roomCheckIn || !roomCheckOut) { alert('Please select your stay check-in and check-out dates first!'); return; } window.addToCart('room', selectedRoom.id, { check_in_date: roomCheckIn, check_out_date: roomCheckOut, selected_pax: selectedPax })"
                                    :disabled="!roomAvailable || !roomCheckIn || !roomCheckOut"
                                    :class="(!roomAvailable || !roomCheckIn || !roomCheckOut) ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 shadow-md shadow-sky-600/20 hover:shadow-lg cursor-pointer'"
                                    class="w-full py-3 px-4 rounded-xl text-white font-bold text-sm transition-all flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-[18px]">shopping_cart</span>
                                    <span x-text="(!roomCheckIn || !roomCheckOut) ? 'Select Dates to Add' : 'Add to Trip Basket'"></span>
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
                                $roomImagesRaw = is_array($room->images) ? $room->images : (is_string($room->images) ? (json_decode($room->images, true) ?: []) : []);
                                $resolvedRoomImages = array_map(function($img) use ($heroImage) {
                                    return App\Concerns\ResolvesImages::resolveImg($img, $heroImage);
                                }, $roomImagesRaw);
                                if (empty($resolvedRoomImages)) {
                                    $resolvedRoomImages = [
                                        App\Concerns\ResolvesImages::resolveImg(null, $heroImage)
                                    ];
                                }
                                $roomImg = $resolvedRoomImages[0];

                                $roomAmenitiesRaw = is_array($room->room_amenities) ? $room->room_amenities : (is_string($room->room_amenities) ? array_filter(array_map('trim', explode(',', $room->room_amenities))) : []);

                                $roomPublishedReviews = $room->reviews->where('is_published', true)
                                    ->sortByDesc('created_at')->take(3)->values();

                                $roomReviewPayload = $roomPublishedReviews->map(fn($rv) => [
                                    'reviewer_alias' => $rv->reviewer_alias,
                                    'rating' => (int) $rv->rating,
                                    'sentiment' => $rv->sentiment,
                                    'comment' => $rv->comment,
                                    'keywords' => $rv->extracted_keywords ?? [],
                                    'created_at_label' => $rv->created_at?->format('M j, Y'),
                                ]);

                                $roomSummary = $room->reviewSummary;

                                $roomPayload = [
                                    'id' => $room->id,
                                    'room_name' => $room->room_name,
                                    'base_price' => (float)$room->base_price,
                                    'occupancy' => $room->occupancy,
                                    'bed_configuration' => $room->bed_configuration,
                                    'room_size' => $room->room_size,
                                    'view_type' => $room->view_type,
                                    'description' => $room->description,
                                    'ideal_guest' => $room->ideal_guest ?? $room->ideal_for ?? null,
                                    'total_rooms' => $room->total_rooms ?? null,
                                    'is_shown' => (bool)$room->is_shown,
                                    'images' => $resolvedRoomImages,
                                    'amenities' => array_values($roomAmenitiesRaw),
                                    'review_summary' => $roomSummary && $roomSummary->total_reviews > 0 ? [
                                        'average_rating' => (float) $roomSummary->average_rating,
                                        'total_reviews' => (int) $roomSummary->total_reviews,
                                        'positive_percentage' => (float) $roomSummary->positive_percentage,
                                        'neutral_percentage' => (float) $roomSummary->neutral_percentage,
                                        'negative_percentage' => (float) $roomSummary->negative_percentage,
                                        'ai_summary_text' => $roomSummary->ai_summary_text,
                                    ] : null,
                                    'reviews' => $roomReviewPayload,
                                ];
                            @endphp
                            <div id="room-card-{{ $room->id }}"
                                class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between group">
                                <div>
                                    {{-- Room Image with Hover Preview Overlay --}}
                                    <div @click="openRoomPreview({{ json_encode($roomPayload) }})"
                                        class="relative h-56 sm:h-64 overflow-hidden bg-slate-100 cursor-pointer">
                                        <img src="{{ $roomImg }}" alt="{{ $room->room_name }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        
                                        <div class="absolute inset-0 bg-slate-950/0 group-hover:bg-slate-950/20 transition-colors flex items-center justify-center">
                                            <span class="bg-white/90 backdrop-blur-md text-slate-900 font-bold text-xs px-3 py-1.5 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition-all transform scale-95 group-hover:scale-100 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[16px]">visibility</span>
                                                Quick Preview
                                            </span>
                                        </div>

                                        @if($isAdminPreview && !$room->is_shown)
                                            <div class="absolute top-3 left-3 bg-amber-500 text-slate-950 text-[10px] font-extrabold uppercase px-2 py-0.5 rounded shadow-md z-10 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[13px]">visibility_off</span>
                                                Hidden from Public
                                            </div>
                                        @endif

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
                                        <div class="flex items-center justify-between gap-2">
                                            <h3 @click="openRoomPreview({{ json_encode($roomPayload) }})"
                                                class="text-base font-bold text-slate-900 group-hover:text-ocean-600 transition-colors font-headline cursor-pointer">
                                                {{ $room->room_name }}
                                            </h3>
                                        </div>

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
                                            <span class="bg-slate-100 px-2.5 py-1 rounded-md flex items-center gap-1 font-medium text-slate-700">
                                                <span class="material-symbols-outlined text-[14px] text-sky-600">group</span>
                                                Base: {{ $room->base_occupancy ?: 2 }} • Max: {{ $room->max_occupancy ?: ($room->occupancy ?: 4) }} Guests
                                            </span>
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

                                 {{-- Card Footer / Action Buttons --}}
                                <div class="p-5 pt-0 grid grid-cols-2 gap-2">
                                    <button type="button" id="preview-btn-{{ $room->id }}" @click.stop="openRoomPreview({{ json_encode($roomPayload) }})"
                                        class="w-full py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                        <span class="material-symbols-outlined text-[15px]">visibility</span>
                                        <span>Preview</span>
                                    </button>
                                    <button type="button" @click.stop="selectRoom({{ json_encode($roomPayload) }})"
                                        class="w-full py-2.5 px-3 rounded-xl bg-ocean-50 hover:bg-ocean-600 hover:text-white text-ocean-700 font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                        <span class="material-symbols-outlined text-[15px]">touch_app</span>
                                        <span>Select</span>
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
                            @php $fullUrl = App\Concerns\ResolvesImages::resolveImg($img); @endphp
                            <div @click="openGallery({{ $idx }})"
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

        {{-- Dynamic Room Preview Modal --}}
        <div x-show="previewRoom" x-transition.opacity @keydown.escape.window="closeRoomPreview()"
            class="fixed inset-0 z-[110] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
            style="display: none;">
            <div @click.away="closeRoomPreview()"
                class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full overflow-hidden border border-slate-200/80 my-auto transform transition-all">
                
                {{-- Modal Header --}}
                <div class="relative bg-slate-900 text-white p-6 sm:p-8 overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-ocean-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <button @click="closeRoomPreview()"
                        class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>

                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-full bg-ocean-500/20 text-ocean-300 text-[10px] font-bold uppercase tracking-wider border border-ocean-400/30">
                            Room Details & Overview
                        </span>
                        <template x-if="previewRoom?.view_type">
                            <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-slate-200 text-[10px] font-medium flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">visibility</span>
                                <span x-text="previewRoom.view_type"></span>
                            </span>
                        </template>
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black text-white font-headline" x-text="previewRoom?.room_name"></h2>
                    
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-2xl sm:text-3xl font-black text-emerald-400 font-headline">
                            ₱<span x-text="Number(computedNightlyRate).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </span>
                        <span class="text-xs text-slate-300 font-medium">/ night</span>
                        <span x-show="selectedPax > (previewRoom?.base_occupancy || 2)" class="text-[10px] font-bold bg-amber-400 text-slate-950 px-2 py-0.5 rounded-full ml-2" x-cloak>
                            +₱<span x-text="((selectedPax - (previewRoom?.base_occupancy || 2)) * (previewRoom?.extra_person_fee || 0)).toLocaleString('en-US')"></span> Extra Guest Fee
                        </span>
                    </div>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 sm:p-8 space-y-6 max-h-[65vh] overflow-y-auto">
                    
                    {{-- Image Carousel / Selector --}}
                    <template x-if="previewRoom?.images && previewRoom.images.length > 0">
                        <div class="space-y-3">
                            <div class="relative h-64 sm:h-80 rounded-2xl overflow-hidden bg-slate-900 group">
                                <img :src="previewRoom.images[activePreviewImgIndex]" 
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute bottom-3 right-3 bg-slate-950/70 backdrop-blur-md text-white text-xs px-3 py-1 rounded-lg border border-white/20">
                                    Photo <span x-text="activePreviewImgIndex + 1"></span> of <span x-text="previewRoom.images.length"></span>
                                </div>
                            </div>

                            <template x-if="previewRoom.images.length > 1">
                                <div class="flex items-center gap-2 overflow-x-auto pb-2">
                                    <template x-for="(img, idx) in previewRoom.images" :key="idx">
                                        <button @click="activePreviewImgIndex = idx"
                                            :class="activePreviewImgIndex === idx ? 'ring-2 ring-ocean-600 scale-105' : 'opacity-70 hover:opacity-100'"
                                            class="w-16 h-12 rounded-lg overflow-hidden border border-slate-200 shrink-0 transition-all">
                                            <img :src="img" class="w-full h-full object-cover">
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Specs Quick Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Bed Layout</span>
                            <span class="font-bold text-slate-800 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">bed</span>
                                <span x-text="previewRoom?.bed_configuration || 'Standard'"></span>
                            </span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Base Occupancy</span>
                            <span class="font-bold text-slate-800 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">person</span>
                                <span x-text="(previewRoom?.base_occupancy || 2) + ' Guests'"></span>
                            </span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1">
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Max Occupancy</span>
                            <span class="font-bold text-slate-800 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">group</span>
                                <span x-text="(previewRoom?.max_occupancy || previewRoom?.occupancy || 4) + ' Guests'"></span>
                            </span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-1" x-show="previewRoom?.room_size">
                            <span class="text-slate-400 text-[10px] block uppercase font-bold tracking-wider">Room Size</span>
                            <span class="font-bold text-slate-800 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-ocean-600">straighten</span>
                                    <span x-text="previewRoom?.room_size"></span>
                                </span>
                            </div>
                        </div>

                    {{-- Description --}}
                    <template x-if="previewRoom?.description">
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Room Description</h4>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed bg-slate-50/70 p-4 rounded-xl border border-slate-200/60"
                                x-text="previewRoom.description"></p>
                        </div>
                    </template>

                    {{-- Amenities Badges --}}
                    <template x-if="previewRoom?.amenities && previewRoom.amenities.length > 0">
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Included Amenities</h4>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(amenity, idx) in previewRoom.amenities" :key="idx">
                                    <span class="px-3 py-1.5 bg-ocean-50 text-ocean-800 rounded-lg text-xs font-semibold border border-ocean-100 flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-ocean-600">check_circle</span>
                                        <span x-text="amenity"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Room Review Summary (DSS) --}}
                    <template x-if="previewRoom?.review_summary">
                        <div class="space-y-3 rounded-2xl bg-gradient-to-br from-ocean-50/70 to-sand-50/70 border border-ocean-100 p-4">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[15px] text-ocean-600">reviews</span>
                                    Guest Reviews & Sentiment
                                </h4>
                                <span class="flex items-center gap-1 text-amber-400">
                                    <template x-for="i in 5" :key="i">
                                        <span class="material-symbols-outlined text-[14px]" :style="'font-variation-settings: \'FILL\' ' + (i <= Math.round(previewRoom.review_summary.average_rating) ? 1 : 0)">star</span>
                                    </template>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 font-bold">
                                <span x-text="previewRoom.review_summary.average_rating.toFixed(1)"></span> / 5.0 ·
                                <span x-text="previewRoom.review_summary.total_reviews"></span> verified reviews
                            </p>
                            <p class="text-[11px] text-slate-500" x-text="previewRoom.review_summary.ai_summary_text"></p>
                        </div>
                    </template>

                    {{-- Room Recent Reviews --}}
                    <template x-if="previewRoom?.reviews && previewRoom.reviews.length > 0">
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Recent Verified Reviews</h4>
                            <template x-for="(rv, idx) in previewRoom.reviews" :key="idx">
                                <div class="bg-white border border-sand-200 rounded-xl p-3.5 space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-bold text-slate-900" x-text="rv.reviewer_alias"></p>
                                        <span class="flex items-center gap-0.5 text-amber-400">
                                            <template x-for="i in 5" :key="i">
                                                <span class="material-symbols-outlined text-[13px]" :style="'font-variation-settings: \'FILL\' ' + (i <= rv.rating ? 1 : 0)">star</span>
                                            </template>
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 leading-relaxed" x-text="rv.comment"></p>
                                    <p class="text-[9px] font-label uppercase tracking-[0.15em] text-slate-400 font-bold" x-text="rv.created_at_label"></p>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Date Picker Section --}}
                    <div class="pt-3 border-t border-slate-200" @date-range-changed.stop="roomCheckIn = $event.detail.checkIn; roomCheckOut = $event.detail.checkOut; roomAvailable = $event.detail.available">
                        <x-frontend.date-range-picker :room-id="null" :base-price="0" />
                    </div>

                </div>

                {{-- Modal Footer --}}
                <div class="bg-slate-50 p-4 sm:p-6 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <button type="button" @click="closeRoomPreview()"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-200 transition-colors">
                        Close Preview
                    </button>
                    <button type="button" 
                        @click="if (!roomCheckIn || !roomCheckOut) { alert('Please select your stay check-in and check-out dates first!'); return; } window.addToCart('room', previewRoom.id, { check_in_date: roomCheckIn, check_out_date: roomCheckOut, selected_pax: selectedPax }); closeRoomPreview();"
                        :disabled="!roomAvailable || !roomCheckIn || !roomCheckOut"
                        :class="(!roomAvailable || !roomCheckIn || !roomCheckOut) ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 shadow-md shadow-sky-600/20 hover:shadow-lg cursor-pointer'"
                        class="w-full sm:w-auto px-6 py-2.5 rounded-xl text-white font-bold text-xs transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">shopping_cart</span>
                        <span x-text="(!roomCheckIn || !roomCheckOut) ? 'Select Dates to Add' : 'Add to Trip Basket'"></span>
                    </button>
                </div>

            </div>
        </div>

        {{-- Hotel Photo Lightbox --}}
        <div x-show="activeModalImg" x-transition.opacity
            @keydown.escape.window="activeModalImg = null"
            @keydown.left.window="prevGallery()"
            @keydown.right.window="nextGallery()"
            class="fixed inset-0 z-[120] bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4 sm:p-8"
            style="display: none;">
            <div class="relative max-w-5xl w-full" @click.away="activeModalImg = null">

                <button @click="activeModalImg = null"
                    class="absolute -top-3 -right-3 z-10 text-white bg-slate-900/80 hover:bg-rose-600 p-2 rounded-full transition-colors cursor-pointer"
                    title="Close photo">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>

                <button @click.stop="prevGallery()"
                    class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 z-10 text-white bg-slate-900/60 hover:bg-slate-900/90 p-2.5 sm:p-3 rounded-full transition-colors cursor-pointer"
                    title="Previous photo">
                    <span class="material-symbols-outlined text-[22px]">chevron_left</span>
                </button>

                <button @click.stop="nextGallery()"
                    class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 z-10 text-white bg-slate-900/60 hover:bg-slate-900/90 p-2.5 sm:p-3 rounded-full transition-colors cursor-pointer"
                    title="Next photo">
                    <span class="material-symbols-outlined text-[22px]">chevron_right</span>
                </button>

                <img :src="activeModalImg" alt="Hotel photo"
                    class="w-full max-h-[85vh] object-contain rounded-2xl shadow-2xl bg-slate-900">

                <div class="absolute bottom-3 right-3 bg-slate-950/70 backdrop-blur-md text-white text-xs px-3 py-1 rounded-lg border border-white/20">
                    Photo <span x-text="activeModalImgIndex + 1"></span> of <span x-text="galleryImages.length"></span>
                </div>
            </div>
        </div>

    </div>

</x-frontend.layout>