@extends('layouts.admin')

@section('title', 'Central Inventory Management | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Central Inventory Management</h1>
                <p class="text-xs text-slate-500 mt-1">Control public visibility (<code class="text-ocean-600 font-mono">is_shown</code>) and monitor inventory availability for hotels, room types, and activities.</p>
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-ocean-50 text-ocean-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">inventory_2</span>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Hotels Inventory</p>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="text-lg font-bold text-slate-900">{{ $stats['total_hotels'] }}</span>
                        <span class="text-xs text-emerald-600 font-medium">{{ $stats['shown_hotels'] }} Visible</span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="text-xs text-amber-600 font-medium">{{ $stats['hidden_hotels'] }} Hidden</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">king_bed</span>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Rooms Inventory</p>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="text-lg font-bold text-slate-900">{{ $stats['total_rooms'] }}</span>
                        <span class="text-xs text-emerald-600 font-medium">{{ $stats['shown_rooms'] }} Visible</span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="text-xs text-amber-600 font-medium">{{ $stats['hidden_rooms'] }} Hidden</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">explore</span>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Activities Inventory</p>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="text-lg font-bold text-slate-900">{{ $stats['total_activities'] }}</span>
                        <span class="text-xs text-emerald-600 font-medium">{{ $stats['shown_activities'] }} Visible</span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="text-xs text-amber-600 font-medium">{{ $stats['hidden_activities'] }} Hidden</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls Bar -->
        <div class="mb-6 bg-white p-4 border border-slate-200 rounded-lg shadow-sm space-y-4">
            {{-- Location Filters --}}
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">place</span>
                        Location:
                    </span>
                    
                    <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'visibility' => $visibility, 'search' => $search])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 {{ !$selectedDestinationId ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All Locations
                    </a>

                    @foreach($destinations as $dest)
                        <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $dest->id, 'visibility' => $visibility, 'search' => $search])) }}"
                           class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 {{ $selectedDestinationId == $dest->id ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $dest->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Visibility Status Filters & Search Bar --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-3 border-t border-slate-100">
                <div class="flex items-center gap-2 overflow-x-auto">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">visibility</span>
                        Visibility:
                    </span>

                    <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $selectedDestinationId, 'search' => $search])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 {{ !$visibility ? 'bg-slate-800 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All Statuses
                    </a>

                    <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $selectedDestinationId, 'visibility' => 'visible', 'search' => $search])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1 {{ $visibility === 'visible' ? 'bg-emerald-600 text-white font-semibold' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $visibility === 'visible' ? 'bg-white' : 'bg-emerald-500' }}"></span>
                        Visible Only
                    </a>

                    <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $selectedDestinationId, 'visibility' => 'hidden', 'search' => $search])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1 {{ $visibility === 'hidden' ? 'bg-amber-600 text-white font-semibold' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $visibility === 'hidden' ? 'bg-white' : 'bg-amber-500' }}"></span>
                        Hidden Only
                    </a>
                </div>

                {{-- Search Box --}}
                <form action="{{ route('admin.inventory.index') }}" method="GET" class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    @if($selectedDestinationId)
                        <input type="hidden" name="destination_id" value="{{ $selectedDestinationId }}">
                    @endif
                    @if($visibility)
                        <input type="hidden" name="visibility" value="{{ $visibility }}">
                    @endif

                    <div class="relative flex-1 sm:w-64">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search inventory items..."
                               class="w-full bg-slate-50 border border-slate-300 rounded-md pl-8 pr-8 py-1.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600">
                        @if($search)
                            <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $selectedDestinationId, 'visibility' => $visibility])) }}"
                               class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" title="Clear search">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </a>
                        @endif
                    </div>

                    <button type="submit" class="px-3 py-1.5 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md text-xs font-semibold shadow-sm transition-colors shrink-0">
                        Search
                    </button>
                </form>
            </div>
        </div>

        <!-- Inventory Navigation Tabs -->
        <div class="mb-6 border-b border-slate-200 bg-white px-4 pt-3 rounded-t-xl">
            <nav class="flex space-x-6">
                <a href="{{ route('admin.inventory.index', array_filter(['tab' => 'hotels', 'destination_id' => $selectedDestinationId, 'visibility' => $visibility, 'search' => $search])) }}"
                   class="pb-3 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $activeTab === 'hotels' ? 'border-ocean-600 text-ocean-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    <span class="material-symbols-outlined text-[18px]">house</span>
                    Hotels Inventory ({{ $hotels->count() }})
                </a>
                <a href="{{ route('admin.inventory.index', array_filter(['tab' => 'rooms', 'destination_id' => $selectedDestinationId, 'visibility' => $visibility, 'search' => $search])) }}"
                   class="pb-3 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $activeTab === 'rooms' ? 'border-ocean-600 text-ocean-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    <span class="material-symbols-outlined text-[18px]">bed</span>
                    Room Types Inventory ({{ $rooms->count() }})
                </a>
                <a href="{{ route('admin.inventory.index', array_filter(['tab' => 'activities', 'destination_id' => $selectedDestinationId, 'visibility' => $visibility, 'search' => $search])) }}"
                   class="pb-3 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {{ $activeTab === 'activities' ? 'border-ocean-600 text-ocean-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    <span class="material-symbols-outlined text-[18px]">explore</span>
                    Activities Inventory ({{ $activities->count() }})
                </a>
            </nav>
        </div>

        <!-- TAB CONTENT -->
        <div class="bg-white rounded-b-xl border border-slate-200 shadow-sm overflow-hidden">
            @if ($activeTab === 'hotels')
                {{-- HOTELS TABLE --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">Hotel Name</th>
                                <th class="py-3 px-4">Destination</th>
                                <th class="py-3 px-4">Category / Type</th>
                                <th class="py-3 px-4">Address</th>
                                <th class="py-3 px-4 text-center">Public Visibility</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse ($hotels as $hotel)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-semibold text-slate-900">
                                        {{ $hotel->hotel_name }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700">
                                            <span class="material-symbols-outlined text-[14px] text-slate-400">place</span>
                                            {{ $hotel->destination->name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 capitalize text-slate-600">
                                        {{ ucwords(str_replace('-', ' ', $hotel->type ?? '—')) }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-500 truncate max-w-[200px]">
                                        {{ $hotel->specific_address }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <form action="{{ route('admin.inventory.toggle-visibility') }}" method="POST" class="inline-block">
                                            @csrf
                                            <input type="hidden" name="type" value="hotel">
                                            <input type="hidden" name="id" value="{{ $hotel->id }}">
                                            <input type="hidden" name="is_shown" value="{{ $hotel->is_shown ? '0' : '1' }}">
                                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer shadow-2xs {{ $hotel->is_shown ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200' }}">
                                                <span class="w-2 h-2 rounded-full {{ $hotel->is_shown ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                                {{ $hotel->is_shown ? 'Visible to Users' : 'Hidden from Users' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="py-3 px-4 text-right space-x-2">
                                        <a href="{{ route('edit-view', $hotel->id) }}" class="text-ocean-600 hover:text-ocean-800 font-semibold inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400 text-xs">No hotels matching the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif ($activeTab === 'rooms')
                {{-- ROOMS TABLE --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">Room Name</th>
                                <th class="py-3 px-4">Hotel</th>
                                <th class="py-3 px-4">Base Rate</th>
                                <th class="py-3 px-4 text-center">Total Inventory</th>
                                <th class="py-3 px-4 text-center">Public Visibility</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse ($rooms as $room)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-semibold text-slate-900">
                                        {{ $room->room_name }}
                                    </td>
                                    <td class="py-3 px-4 font-medium text-slate-700">
                                        {{ $room->hotel->hotel_name ?? 'N/A' }}
                                    </td>
                                    <td class="py-3 px-4 font-bold text-emerald-700">
                                        ₱{{ number_format($room->base_price, 2) }}
                                    </td>
                                    <td class="py-3 px-4 text-center font-medium">
                                        {{ $room->total_rooms }} rooms
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <form action="{{ route('admin.inventory.toggle-visibility') }}" method="POST" class="inline-block">
                                            @csrf
                                            <input type="hidden" name="type" value="room">
                                            <input type="hidden" name="id" value="{{ $room->id }}">
                                            <input type="hidden" name="is_shown" value="{{ $room->is_shown ? '0' : '1' }}">
                                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer shadow-2xs {{ $room->is_shown ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200' }}">
                                                <span class="w-2 h-2 rounded-full {{ $room->is_shown ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                                {{ $room->is_shown ? 'Visible to Users' : 'Hidden from Users' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="py-3 px-4 text-right space-x-2">
                                        <a href="{{ route('edit-room', $room->id) }}" class="text-ocean-600 hover:text-ocean-800 font-semibold inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                            Edit Room
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400 text-xs">No rooms matching the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @else
                {{-- ACTIVITIES TABLE --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">Activity Name</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Destination</th>
                                <th class="py-3 px-4">Rate</th>
                                <th class="py-3 px-4 text-center">Public Visibility</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse ($activities as $activity)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-semibold text-slate-900">
                                        {{ $activity->activity_name }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-ocean-50 text-ocean-700">
                                            {{ $activity->category }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-medium text-slate-700">
                                        {{ $activity->destination->name ?? 'N/A' }}
                                    </td>
                                    <td class="py-3 px-4 font-bold text-emerald-700">
                                        {{ $activity->rate }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <form action="{{ route('admin.inventory.toggle-visibility') }}" method="POST" class="inline-block">
                                            @csrf
                                            <input type="hidden" name="type" value="activity">
                                            <input type="hidden" name="id" value="{{ $activity->id }}">
                                            <input type="hidden" name="is_shown" value="{{ $activity->is_shown ? '0' : '1' }}">
                                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer shadow-2xs {{ $activity->is_shown ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200' }}">
                                                <span class="w-2 h-2 rounded-full {{ $activity->is_shown ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                                {{ $activity->is_shown ? 'Visible to Users' : 'Hidden from Users' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="py-3 px-4 text-right space-x-2">
                                        <a href="{{ route('admin.activities.edit', $activity->id) }}" class="text-ocean-600 hover:text-ocean-800 font-semibold inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                            Edit Activity
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400 text-xs">No activities matching the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
