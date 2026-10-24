@extends('layouts.admin')

@section('title', 'Activities Management | SunnyTrips Admin')

@section('content')
    <div class="pb-12"
         x-data="adminLiveList({
             base: @js(route('admin.activities.index')),
             filters: { search: @js($search), destinationId: @js((string) ($selectedDestinationId ?? '')), level: @js($selectedLevel ?? ''), category: @js($selectedCategory ?? '') },
             params: { search: 'search', destinationId: 'destination_id', level: 'level', category: 'category' },
         })">
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

        {{-- Filter Controls (live outside the swapped results region so the input keeps focus) --}}
        <div class="mb-6 bg-white p-4 border border-slate-200 rounded-lg shadow-sm space-y-3">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <!-- Location Tabs -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">location_on</span>
                        LOCATION:
                    </span>

                    <a href="{{ route('admin.activities.index', array_filter(['search' => $search, 'category' => $selectedCategory, 'level' => $selectedLevel])) }}"
                       @click.prevent="setDestinationId('')"
                       :class="destinationId === '' ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0">
                        All Locations
                    </a>

                    @foreach($destinations as $dest)
                        <a href="{{ route('admin.activities.index', array_filter(['destination_id' => $dest->id, 'search' => $search, 'category' => $selectedCategory, 'level' => $selectedLevel])) }}"
                           @click.prevent="setDestinationId({{ $dest->id }})"
                           :class="destinationId == {{ $dest->id }} ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                           class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0">
                            {{ $dest->name }}
                        </a>
                    @endforeach
                </div>

                <!-- Search Form -->
                <form action="{{ route('admin.activities.index') }}" method="GET" @submit.prevent="fetchResults()" class="flex items-center gap-2 w-full lg:w-auto">
                    @if($selectedDestinationId)
                        <input type="hidden" name="destination_id" value="{{ $selectedDestinationId }}" />
                    @endif
                    @if($selectedLevel)
                        <input type="hidden" name="level" value="{{ $selectedLevel }}" />
                    @endif
                    @if($selectedCategory)
                        <input type="hidden" name="category" value="{{ $selectedCategory }}" />
                    @endif

                    <div class="relative w-full sm:w-64">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" x-model="search" value="{{ $search }}"
                               placeholder="Search activity, level, or tags..."
                               @input="debouncedSearch()"
                               class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-md py-1.5 pl-9 pr-8 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600 transition-all" />
                        <a href="{{ route('admin.activities.index', array_filter(['destination_id' => $selectedDestinationId, 'level' => $selectedLevel, 'category' => $selectedCategory])) }}"
                           @click.prevent="search = ''; fetchResults()"
                           x-show="search"
                           class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" title="Clear search">
                            <span class="material-symbols-outlined text-[16px]">close</span>
                        </a>
                    </div>

                    <button type="submit"
                            class="px-3 py-1.5 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md text-xs font-semibold shadow-sm transition-colors shrink-0">
                        Search
                    </button>
                </form>
            </div>

            <!-- Activity Level Filter Tabs (5 Levels) -->
            <div class="pt-2 border-t border-slate-100 flex items-center gap-2 overflow-x-auto">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                    <span class="material-symbols-outlined text-[16px] text-slate-400">signal_cellular_alt</span>
                    ACTIVITY LEVEL:
                </span>

                <a href="{{ route('admin.activities.index', array_filter(['destination_id' => $selectedDestinationId, 'search' => $search, 'category' => $selectedCategory])) }}"
                   @click.prevent="setLevel('')"
                   :class="level === '' ? 'bg-slate-800 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                   class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors shrink-0">
                    All Levels
                </a>

                @foreach(['Relaxing' => 'emerald', 'Sightseeing' => 'sky', 'Adventure' => 'amber', 'Extreme' => 'rose', 'Underwater' => 'cyan'] as $lvl => $color)
                    <a href="{{ route('admin.activities.index', array_filter(['level' => $lvl, 'destination_id' => $selectedDestinationId, 'search' => $search, 'category' => $selectedCategory])) }}"
                       @click.prevent="setLevel('{{ $lvl }}')"
                       :class="level === '{{ $lvl }}' ? 'bg-ocean-600 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                       class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors shrink-0">
                        {{ $lvl }}
                    </a>
                @endforeach

                <a href="{{ route('admin.activities.index') }}" @click.prevent="resetFilters()" x-show="destinationId || level || search || category" class="ml-auto text-xs font-semibold text-rose-600 hover:text-rose-700 flex items-center gap-0.5 shrink-0">
                    <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                    Reset Filters
                </a>
            </div>
        </div>

        <div>
            <div :class="loading ? 'opacity-60 transition-opacity' : 'transition-opacity'">
                <div x-show="error" x-cloak role="alert" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"><p x-text="error" class="flex-1"></p><button type="button" @click="retryResults()" class="min-h-11 px-3 font-bold underline underline-offset-4">Retry</button></div>
                @include('admin.activities._table', compact('activities', 'selectedDestinationId'))
            </div>
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endsection