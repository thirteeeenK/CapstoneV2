@extends('layouts.admin')

@section('title', 'Activities Management | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Activities & Tours</h1>
                <p class="text-xs text-slate-500 mt-1">Manage destination activities, pricing, activity levels, and AI embeddings.</p>
            </div>
            <a href="{{ route('admin.activities.create') }}" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-ocean-600 hover:bg-ocean-700 text-white text-xs font-semibold shadow-sm transition-colors self-start sm:self-auto">
                <span class="material-symbols-outlined text-[16px]">add</span>
                Add New Activity
            </a>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        {{-- Delete Banner --}}
        @if (session('deleted'))
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
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
                    
                    <a href="{{ route('admin.activities.index', array_filter(['search' => $search, 'category' => $selectedCategory])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 {{ !$selectedDestinationId ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All Locations
                    </a>

                    @foreach($destinations as $dest)
                        <a href="{{ route('admin.activities.index', array_filter(['destination_id' => $dest->id, 'search' => $search, 'category' => $selectedCategory])) }}"
                           class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 {{ $selectedDestinationId == $dest->id ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $dest->name }}
                        </a>
                    @endforeach
                </div>

                <!-- Search Form -->
                <form action="{{ route('admin.activities.index') }}" method="GET" class="flex items-center gap-2 w-full lg:w-auto">
                    @if($selectedDestinationId)
                        <input type="hidden" name="destination_id" value="{{ $selectedDestinationId }}" />
                    @endif

                    <div class="relative w-full sm:w-64">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" value="{{ $search }}"
                               placeholder="Search activity or notes..."
                               class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-md py-1.5 pl-9 pr-8 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600 transition-all" />
                        @if($search)
                            <a href="{{ route('admin.activities.index', array_filter(['destination_id' => $selectedDestinationId])) }}"
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

        <!-- Activities Table -->
        <div class="bg-white border border-slate-200 rounded-lg shadow-sm">
            <div class="w-full">
                <table class="w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 w-10">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Activity Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Level</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Notes / Inclusions</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @php
                            $currentDestination = null;
                            $counter = 0;
                        @endphp

                        @forelse($activities as $act)
                            @php
                                $destName = $act->destination->name ?? 'Unassigned';
                            @endphp

                            {{-- Location Grouping Header --}}
                            @if(!$selectedDestinationId && $currentDestination !== $destName)
                                @php
                                    $currentDestination = $destName;
                                    $counter = 0;
                                @endphp
                                <tr class="bg-slate-100/80">
                                    <td colspan="7" class="px-6 py-2.5 text-xs font-bold text-slate-700 tracking-wide uppercase border-y border-slate-200">
                                        <div class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px] text-ocean-600">location_on</span>
                                            <span>{{ $currentDestination }} Activities</span>
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            @php
                                $counter++;
                                $images = $act->images;
                                if (!is_array($images)) {
                                    $images = json_decode($images, true) ?? [];
                                }
                                $firstImage = !empty($images) ? $images[0] : null;
                            @endphp

                            <tr class="hover:bg-slate-50/55 transition-colors">
                                <td class="px-6 py-4 text-sm font-medium text-slate-500">
                                    {{ $counter }}
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                            @if($firstImage)
                                                <img src="{{ asset('storage/' . $firstImage) }}" alt="{{ $act->activity_name }}" class="w-full h-full object-cover" />
                                            @else
                                                <span class="material-symbols-outlined text-slate-400 text-lg">explore</span>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                                <span>{{ $act->activity_name }}</span>
                                                @if(!empty($act->embedding))
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/60" title="AI Vector Embedding Active">
                                                        <span class="material-symbols-outlined text-[13px]">psychology</span> AI Embedded
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500 border border-slate-200" title="Embedding pending or not generated">
                                                        <span class="material-symbols-outlined text-[13px]">sensors_off</span> Pending AI
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 text-xs text-slate-500 flex-wrap font-normal">
                                                @if($act->duration)
                                                    <span class="flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-[14px] text-slate-400">schedule</span>
                                                        {{ $act->duration }}
                                                    </span>
                                                @endif
                                                @if($act->capacity)
                                                    <span class="flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-[14px] text-slate-400">group</span>
                                                        {{ $act->capacity }}
                                                    </span>
                                                @endif
                                                @if($act->ideal_for)
                                                    <span class="flex items-center gap-1 text-ocean-700 font-medium">
                                                        <span class="material-symbols-outlined text-[14px] text-ocean-500">face</span>
                                                        {{ $act->ideal_for }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ $act->category }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-sm">
                                    @switch($act->activity_level)
                                        @case('Extreme')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                ⚡ Extreme
                                            </span>
                                            @break
                                        @case('Underwater')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-cyan-50 text-cyan-700 border border-cyan-200">
                                                🪸 Underwater
                                            </span>
                                            @break
                                        @case('Adventure')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                🚵 Adventure
                                            </span>
                                            @break
                                        @case('Sightseeing')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200">
                                                📸 Sightseeing
                                            </span>
                                            @break
                                        @default
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                🌴 Relaxing
                                            </span>
                                    @endswitch
                                </td>

                                <td class="px-6 py-4 text-sm font-bold text-slate-900">
                                    {{ $act->rate }}
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate" title="{{ $act->notes }}">
                                    {{ $act->notes ?? 'N/A' }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.activities.edit', $act->id) }}"
                                            class="inline-flex items-center gap-1.5 h-8 px-3 rounded bg-white border border-ocean-300 hover:border-ocean-600 text-ocean-600 hover:bg-ocean-50 text-xs font-medium transition-colors shadow-sm">
                                            <span class="material-symbols-outlined text-[16px]">edit</span>
                                            Edit
                                        </a>

                                        <div x-data="{ openModal: false }">
                                            <button @click="openModal = true" type="button"
                                                class="inline-flex items-center gap-1.5 h-8 px-3 rounded bg-rose-50 border border-rose-200/80 hover:bg-rose-100 hover:border-rose-300 text-rose-700 text-xs font-medium transition-colors cursor-pointer">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                                Delete
                                            </button>

                                            <!-- Delete Modal -->
                                            <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" x-cloak style="display: none;">
                                                <div @click.away="openModal = false" class="bg-white rounded-lg p-6 w-full max-w-sm border border-slate-200 shadow-lg text-left">
                                                    <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center mb-4 text-rose-600">
                                                        <span class="material-symbols-outlined text-xl">warning</span>
                                                    </div>
                                                    <h3 class="text-sm font-bold text-slate-900">Confirm Deletion</h3>
                                                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                                        Are you sure you want to delete <strong class="text-slate-700">{{ $act->activity_name }}</strong>? This action cannot be undone and will remove its AI embedding vector.
                                                    </p>

                                                    <div class="flex gap-3 mt-6">
                                                        <button @click="openModal = false" type="button" class="flex-1 h-8 px-3 bg-white border border-slate-300 text-slate-700 text-xs font-medium rounded hover:bg-slate-50 transition-colors">
                                                            Cancel
                                                        </button>

                                                        <form action="{{ route('admin.activities.destroy', $act->id) }}" method="POST" class="flex-1">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="w-full h-8 px-3 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded transition-colors">
                                                                Delete Activity
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
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">explore_off</span>
                                        <p class="text-sm font-semibold text-slate-700">No activities found.</p>
                                        <p class="text-xs text-slate-400 mt-1">Try filtering by another destination or search term.</p>
                                    </div>
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
