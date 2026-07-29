@extends('layouts.admin')

@section('title', 'Listed Rooms | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
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
                        $images = is_string($room->images) ? json_decode($room->images, true) : $room->images;
                        $firstImage = is_array($images) && count($images) > 0 ? $images[0] : null;
                        $amenities = is_array($room->room_amenities) ? $room->room_amenities : (is_string($room->room_amenities) ? array_filter(explode(',', $room->room_amenities)) : []);
                    @endphp
                    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col md:flex-row gap-6 items-start md:items-center justify-between">
                        
                        <!-- Left: Thumbnail & Details -->
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 flex-1">
                            <!-- Image Thumbnail -->
                            <div class="w-28 h-24 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                @if($firstImage)
                                    <img src="{{ asset('storage/' . $firstImage) }}" alt="{{ $room->room_name }}" class="w-full h-full object-cover">
                                @else
                                    <span class="material-symbols-outlined text-slate-400 text-3xl">bed</span>
                                @endif
                            </div>

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
    </div>
@endsection