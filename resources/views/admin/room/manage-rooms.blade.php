@extends('layouts.admin')

@section('title', 'Listed Rooms | SunnyTrips Admin')

@section('content')
    <div x-data="{ previewRoom: null, activePreviewImgIndex: 0 }" class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Manage Rooms — {{ $hotel->hotel_name }}</h1>
                <p class="text-xs text-slate-500 mt-1">Configured room types and inventory for this hotel.</p>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <a href="{{ route('view-listings') }}" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Back to Hotels
                </a>
                <a href="{{ route('create-room', $hotel->id) }}" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-md bg-ocean-600 hover:bg-ocean-700 text-white text-xs font-semibold shadow-sm transition-colors">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Add Room Type
                </a>
            </div>
        </div>
        {{-- Success Message --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        {{-- Deleted Message --}}
        @if (session('deleted'))
            <div class="bg-amber-50 border border-amber-200/80 text-amber-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">delete</span>
                {{ session('deleted') }}
            </div>
        @endif


        @if($rooms->isNotEmpty())
            <div class="grid grid-cols-1 gap-4">
                @foreach($rooms as $room)
                    @php
                        $imagesRaw = is_string($room->images) ? json_decode($room->images, true) : (is_array($room->images) ? $room->images : []);
                        $resolvedImages = array_map(function($img) {
                            return App\Concerns\ResolvesImages::resolveImg($img);
                        }, $imagesRaw);
                        if (empty($resolvedImages)) {
                            $resolvedImages = [App\Concerns\ResolvesImages::resolveImg(null)];
                        }
                        $firstImage = $resolvedImages[0];
                        $amenitiesRaw = is_array($room->room_amenities) ? $room->room_amenities : (is_string($room->room_amenities) ? array_filter(array_map('trim', explode(',', $room->room_amenities))) : []);

                        $adminRoomPayload = [
                            'id' => $room->id,
                            'room_name' => $room->room_name,
                            'base_price' => (float)$room->base_price,
                            'occupancy' => $room->occupancy,
                            'bed_configuration' => $room->bed_configuration,
                            'room_size' => $room->room_size,
                            'view_type' => $room->view_type,
                            'description' => $room->description,
                            'ideal_guest' => $room->ideal_guest ?? $room->ideal_for ?? null,
                            'total_rooms' => $room->total_rooms ?? 1,
                            'is_shown' => (bool)$room->is_shown,
                            'has_embedding' => !empty($room->embedding),
                            'images' => $resolvedImages,
                            'amenities' => array_values($amenitiesRaw),
                        ];
                    @endphp
                    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col md:flex-row gap-6 items-start md:items-center justify-between">
                        
                        <!-- Left: Details -->
                        <div class="flex items-start sm:items-center gap-5 flex-1">
                            <!-- Information block -->
                            <div class="space-y-1.5 flex-1">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <h3 class="text-base font-bold text-slate-900">{{ $room->room_name }}</h3>
                                    @if(!empty($room->embedding))
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/60" title="AI Vector Embedding Active">
                                            <span class="material-symbols-outlined text-[14px]">psychology</span> AI Embedded
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500 border border-slate-200" title="Embedding pending or not generated">
                                            <span class="material-symbols-outlined text-[14px]">sensors_off</span> Pending AI
                                        </span>
                                    @endif

                                    @if($room->is_shown)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Visible to Public
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Hidden from Public
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                        <span class="material-symbols-outlined text-[14px]">inventory_2</span>
                                        {{ $room->total_rooms }} {{ Str::plural('Unit', $room->total_rooms) }} Total
                                    </span>
                                </div>

                                <div class="flex items-center gap-4 text-xs text-slate-500 flex-wrap">
                                    @if($room->ideal_guest ?? $room->ideal_for)
                                        <span class="flex items-center gap-1 font-medium text-ocean-700 bg-ocean-50 px-2 py-0.5 rounded border border-ocean-100">
                                            <span class="material-symbols-outlined text-[15px]">face</span>
                                            {{ $room->ideal_guest ?? $room->ideal_for }}
                                        </span>
                                    @endif
                                    @if($room->occupancy)
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px] text-slate-400">group</span>
                                            {{ $room->occupancy }} Guests
                                        </span>
                                    @endif
                                    @if($room->bed_configuration)
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px] text-slate-400">king_bed</span>
                                            {{ $room->bed_configuration }}
                                        </span>
                                    @endif
                                    @if($room->room_size)
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px] text-slate-400">straighten</span>
                                            {{ $room->room_size }}
                                        </span>
                                    @endif
                                </div>

                                @if(!empty($room->additional_notes))
                                    <p class="text-[11px] text-slate-500 italic bg-amber-50/60 text-amber-900 px-2 py-1 rounded border border-amber-100/80 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-amber-600">info</span>
                                        {{ Str::limit($room->additional_notes, 90) }}
                                    </p>
                                @endif

                                {{-- Amenities Pills --}}
                                @if(!empty($amenities))
                                    <div class="flex flex-wrap gap-1.5 pt-1">
                                        @foreach(array_slice($amenities, 0, 5) as $amenity)
                                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[11px] font-medium">
                                                {{ trim($amenity) }}
                                            </span>
                                        @endforeach
                                        @if(count($amenities) > 5)
                                            <span class="px-1.5 py-0.5 text-slate-400 text-[11px]">
                                                +{{ count($amenities) - 5 }} more
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Right: Price & Actions -->
                        <div class="flex flex-row md:flex-col items-end justify-between w-full md:w-auto border-t md:border-t-0 border-slate-100 pt-4 md:pt-0 gap-4 flex-shrink-0">
                            <div class="text-left md:text-right">
                                <span class="text-xs text-slate-400 block uppercase font-medium">Base Price</span>
                                <span class="text-lg font-extrabold text-primary">
                                    ₱{{ number_format($room->base_price, 2) }}
                                    <span class="text-xs text-slate-400 font-normal">/ night</span>
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <a href="{{ route('hotels.show', ['id' => $hotel->id, 'preview_room' => $room->id]) }}" target="_blank"
                                    class="inline-flex items-center gap-1 h-8 px-3 rounded bg-white border border-emerald-300 hover:border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-xs font-semibold transition-colors shadow-2xs"
                                    title="Preview user-facing room page in new tab">
                                    <span class="material-symbols-outlined text-[15px]">open_in_new</span>
                                    Live Preview
                                </a>

                                <button type="button" @click="previewRoom = {{ json_encode($adminRoomPayload) }}; activePreviewImgIndex = 0;"
                                    class="inline-flex items-center gap-1 h-8 px-3 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors border border-slate-200/80 shadow-2xs"
                                    title="Quick Modal Preview">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                    Modal
                                </button>

                                <a href="{{ route('edit-room', $room->id) }}"
                                    class="inline-flex items-center gap-1 h-8 px-3 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium transition-colors">
                                    <span class="material-symbols-outlined text-[15px]">edit</span>
                                    Edit
                                </a>

                                <form action="{{ route('delete-room', $room->id) }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete {{ addslashes($room->room_name) }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex items-center gap-1 h-8 px-3 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-medium transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">delete</span>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center border border-dashed border-slate-300 rounded-lg p-12 text-center bg-white">
                <div class="w-12 h-12 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mb-4 text-slate-400">
                    <span class="material-symbols-outlined text-2xl">meeting_room</span>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 mb-1">No Listed Rooms</h3>
                <p class="text-xs text-slate-500 max-w-sm mb-6">There are no rooms currently registered for <b>{{ $hotel->hotel_name }}</b>. Get started by adding one.</p>
                <a href="{{ route('create-room', $hotel->id) }}"
                    class="inline-flex items-center gap-1.5 h-9 px-4 bg-ocean-600 hover:bg-ocean-700 text-white text-xs font-semibold rounded-md shadow-sm transition-colors">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Add Room Type
                </a>
            </div>
        @endif

        {{-- Admin Room Preview Modal --}}
        <div x-show="previewRoom" x-transition.opacity @keydown.escape.window="previewRoom = null"
            class="fixed inset-0 z-[100] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
            style="display: none;">
            <div @click.away="previewRoom = null"
                class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full overflow-hidden border border-slate-200 my-auto transform transition-all">
                
                {{-- Modal Header --}}
                <div class="bg-slate-900 text-white p-6 relative">
                    <button @click="previewRoom = null"
                        class="absolute top-4 right-4 text-slate-400 hover:text-white bg-white/10 hover:bg-white/20 p-1.5 rounded-full transition-colors">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>

                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2.5 py-0.5 rounded-full bg-ocean-500/20 text-ocean-300 text-[10px] font-bold uppercase tracking-wider border border-ocean-400/30">
                            Admin Room Preview
                        </span>
                        <template x-if="previewRoom?.has_embedding">
                            <span class="px-2.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 text-[10px] font-semibold border border-indigo-400/30 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">psychology</span> AI Embedded
                            </span>
                        </template>
                        <template x-if="previewRoom?.is_shown">
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-semibold border border-emerald-400/30">
                                Visible
                            </span>
                        </template>
                    </div>

                    <h2 class="text-xl font-bold font-headline" x-text="previewRoom?.room_name"></h2>
                    <p class="text-xs text-slate-400 mt-1">Base Price: <span class="text-emerald-400 font-bold text-sm">₱<span x-text="Number(previewRoom?.base_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span> / night</p>
                </div>

                {{-- Modal Content --}}
                <div class="p-6 space-y-5 max-h-[60vh] overflow-y-auto text-xs text-slate-700">
                    
                    {{-- Images --}}
                    <template x-if="previewRoom?.images && previewRoom.images.length > 0">
                        <div class="space-y-2">
                            <div class="h-56 sm:h-64 rounded-xl overflow-hidden bg-slate-100 border border-slate-200">
                                <img :src="previewRoom.images[activePreviewImgIndex]" class="w-full h-full object-cover">
                            </div>
                            <template x-if="previewRoom.images.length > 1">
                                <div class="flex items-center gap-2 overflow-x-auto">
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

                    {{-- Key Specs Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Bed Layout</span>
                            <span class="font-bold text-slate-900" x-text="previewRoom?.bed_configuration || 'Standard'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Max Occupancy</span>
                            <span class="font-bold text-slate-900" x-text="(previewRoom?.occupancy || 2) + ' Guests'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Room Size</span>
                            <span class="font-bold text-slate-900" x-text="previewRoom?.room_size || 'N/A'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Total Units</span>
                            <span class="font-bold text-slate-900" x-text="(previewRoom?.total_rooms || 1) + ' Units'"></span>
                        </div>
                    </div>

                    {{-- Description --}}
                    <template x-if="previewRoom?.description">
                        <div>
                            <h4 class="font-bold text-slate-900 mb-1">Description</h4>
                            <p class="text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-lg border border-slate-100" x-text="previewRoom.description"></p>
                        </div>
                    </template>

                    {{-- Amenities --}}
                    <template x-if="previewRoom?.amenities && previewRoom.amenities.length > 0">
                        <div>
                            <h4 class="font-bold text-slate-900 mb-1.5">Amenities</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(amenity, idx) in previewRoom.amenities" :key="idx">
                                    <span class="px-2.5 py-1 bg-ocean-50 text-ocean-800 rounded-md text-[11px] font-semibold border border-ocean-100 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-ocean-600">check_circle</span>
                                        <span x-text="amenity"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                </div>

                {{-- Footer --}}
                <div class="bg-slate-50 p-4 border-t border-slate-200 flex items-center justify-between">
                    <button type="button" @click="previewRoom = null"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition-colors">
                        Close
                    </button>
                    <a :href="'/admin/rooms/' + previewRoom?.id + '/edit'"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-ocean-600 hover:bg-ocean-700 text-white font-semibold text-xs shadow-sm transition-colors">
                        <span class="material-symbols-outlined text-[15px]">edit</span> Edit Room
                    </a>
                </div>

            </div>
        </div>

    </div>
@endsection