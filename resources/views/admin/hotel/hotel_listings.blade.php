@extends('layouts.admin')

@section('title', 'Hotel Listing | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Hotel Listings</h1>
                <p class="text-xs text-slate-500 mt-1">Manage hotel listings filtered by their assigned location.</p>
            </div>
            <a href="{{ route('manage-hotels') }}" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-ocean-600 hover:bg-ocean-700 text-white text-xs font-semibold shadow-sm transition-colors self-start sm:self-auto">
                <span class="material-symbols-outlined text-[16px]">add</span>
                Add New Hotel
            </a>
        </div>

        {{-- Success Message --}}
        @if (session('deleted'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('deleted') }}
            </div>
        @endif

        <!-- Destination Filter & Search Bar -->
        <div class="mb-6 bg-white p-4 border border-slate-200 rounded-lg shadow-sm space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <!-- Location Tabs -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">filter_alt</span>
                        Location:
                    </span>
                    
                    <a href="{{ route('view-listings', array_filter(['search' => $search])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 {{ !$selectedDestinationId ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All Locations
                    </a>

                    @foreach($destinations as $dest)
                        <a href="{{ route('view-listings', array_filter(['destination_id' => $dest->id, 'search' => $search])) }}"
                           class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 {{ $selectedDestinationId == $dest->id ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $dest->name }}
                        </a>
                    @endforeach
                </div>

                <!-- Search Form -->
                <form action="{{ route('view-listings') }}" method="GET" class="flex items-center gap-2 w-full lg:w-auto">
                    @if($selectedDestinationId)
                        <input type="hidden" name="destination_id" value="{{ $selectedDestinationId }}" />
                    @endif

                    <div class="relative w-full sm:w-64">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" value="{{ $search }}"
                               placeholder="Search hotel or address..."
                               class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-md py-1.5 pl-9 pr-8 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600 transition-all" />
                        @if($search)
                            <a href="{{ route('view-listings', array_filter(['destination_id' => $selectedDestinationId])) }}"
                               class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" title="Clear search">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </a>
                        @endif
                    </div>

                    <button type="submit"
                            class="px-3 py-1.5 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md text-xs font-semibold shadow-sm transition-colors shrink-0">
                        Search
                    </button>
                </form>
            </div>
        </div>

        <div class="overflow-hidden bg-white border border-slate-200 rounded-lg shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 w-16">
                                #
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Hotel Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Specific Address
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Visibility
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @php
                            $currentDestination = null;
                            $locationCounter = 0;
                        @endphp

                        @forelse($hotels as $hotel)
                            @php
                                $destName = $hotel->destination->name ?? 'Unassigned';
                            @endphp

                            {{-- Group Header Row when viewing All Locations or when destination changes --}}
                            @if(!$selectedDestinationId && $currentDestination !== $destName)
                                @php
                                    $currentDestination = $destName;
                                    $locationCounter = 0; // Reset numbering per location
                                @endphp
                                <tr class="bg-slate-100/80">
                                    <td colspan="6" class="px-6 py-2.5 text-xs font-bold text-slate-700 tracking-wide uppercase border-y border-slate-200">
                                        <div class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px] text-ocean-600">location_on</span>
                                            <span>{{ $currentDestination }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            @php
                                $locationCounter++;
                            @endphp

                            <tr class="hover:bg-slate-50/55 transition-colors duration-150">
                                <td class="px-6 py-4 text-sm font-medium text-slate-500">
                                    {{ $locationCounter }}
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span>{{ $hotel->hotel_name }}</span>
                                        @if(!empty($hotel->embedding))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/60" title="AI Vector Embedding Active">
                                                <span class="material-symbols-outlined text-[13px]">psychology</span> AI Embedded
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500 border border-slate-200" title="Embedding pending or not generated">
                                                <span class="material-symbols-outlined text-[13px]">sensors_off</span> Pending AI
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-sm">
                                    @if($hotel->type === 'high-end-luxury-hotels')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200/60">
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                                            Luxury
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200/60">
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-sky-500 mr-1.5"></span>
                                            Affordable
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $hotel->specific_address }}
                                </td>

                                <td class="px-6 py-4 text-sm">
                                    @if($hotel->is_shown)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Visible
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Hidden
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('edit-view', $hotel->id) }}"
                                            class="inline-flex items-center gap-1.5 h-8 px-3 rounded bg-white border border-ocean-300 hover:border-ocean-600 text-ocean-600 hover:bg-ocean-50 text-xs font-medium transition-colors shadow-sm">
                                            <span class="material-symbols-outlined text-[16px]">edit</span>
                                            Edit
                                        </a>
                                        <a href="{{ route('manage-rooms',$hotel->id) }}"
                                            class="inline-flex items-center gap-1.5 h-8 px-3 rounded bg-white border border-slate-300 hover:bg-slate-50 hover:border-slate-400 text-slate-700 text-xs font-medium transition-colors shadow-sm">
                                            <span class="material-symbols-outlined text-[16px] text-slate-500">meeting_room</span>
                                            Rooms
                                        </a>
                                        <div x-data="{ openModal: false }">
                                            <button @click="openModal = true" type="button"
                                                class="inline-flex items-center gap-1.5 h-8 px-3 rounded bg-rose-50 border border-rose-200/80 hover:bg-rose-100 hover:border-rose-300 text-rose-700 text-xs font-medium transition-colors">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                                Delete
                                            </button>

                                            <div x-show="openModal"
                                                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
                                                x-cloak>

                                                <div @click.away="openModal = false"
                                                    class="bg-white rounded-lg p-6 w-full max-w-sm border border-slate-200 shadow-lg whitespace-normal text-left">
                                                    <h3 class="text-sm font-semibold text-slate-900">Delete Listing</h3>
                                                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                                        Are you sure you want to delete the hotel listing for <span class="font-semibold text-slate-900 break-words">{{ $hotel->hotel_name }}</span>? All associated room listings will be removed. This action cannot be undone.
                                                    </p>

                                                    <div class="flex gap-3 mt-6">
                                                        <button @click="openModal = false" type="button"
                                                            class="flex-1 h-8 px-3 bg-white border border-slate-300 text-slate-700 text-xs font-medium rounded hover:bg-slate-50 transition-colors">
                                                            Cancel
                                                        </button>

                                                        <form action="{{ route('deleteHotel', $hotel->id) }}" method="POST"
                                                            class="flex-1">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="w-full h-8 px-3 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded transition-colors">
                                                                Confirm Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-xs text-slate-500">
                                    No hotels found matching your search or selected location.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endsection
