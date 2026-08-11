@extends('layouts.admin')

@section('title', 'Hotel Listing | SunnyTrips Admin')

@section('content')
    <div class="pb-12"
         x-data="{
             search: @js($search),
             destinationId: @js((string) ($selectedDestinationId ?? '')),
             loading: false,
             timer: null,
             baseUrl: @js(route('view-listings')),
             async fetchResults() {
                 const params = new URLSearchParams();
                 if (this.search) params.set('search', this.search);
                 if (this.destinationId) params.set('destination_id', this.destinationId);
                 const target = this.baseUrl + (params.toString() ? '?' + params.toString() : '');
                 this.loading = true;
                 try {
                     const res = await fetch(target, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                     const html = await res.text();
                     const el = document.getElementById('listings-results');
                     if (el) el.outerHTML = html;
                 } finally {
                     this.loading = false;
                 }
             },
             debouncedSearch() {
                 clearTimeout(this.timer);
                 this.timer = setTimeout(() => this.fetchResults(), 250);
             },
             setDestinationId(id) {
                 this.destinationId = id;
                 this.fetchResults();
             }
         }">
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

        {{-- Filter Controls (live outside the swapped results region so the input keeps focus) --}}
        <div class="mb-6 bg-white p-4 border border-slate-200 rounded-lg shadow-sm space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <!-- Location Tabs -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">filter_alt</span>
                        Location:
                    </span>

                    <a href="{{ route('view-listings', array_filter(['search' => $search])) }}"
                       @click.prevent="setDestinationId('')"
                       :class="destinationId === '' ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0">
                        All Locations
                    </a>

                    @foreach($destinations as $dest)
                        <a href="{{ route('view-listings', array_filter(['destination_id' => $dest->id, 'search' => $search])) }}"
                           @click.prevent="setDestinationId({{ $dest->id }})"
                           :class="destinationId == {{ $dest->id }} ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                           class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0">
                            {{ $dest->name }}
                        </a>
                    @endforeach
                </div>

                <!-- Search Form -->
                <form action="{{ route('view-listings') }}" method="GET" @submit.prevent="fetchResults()" class="flex items-center gap-2 w-full lg:w-auto">
                    @if($selectedDestinationId)
                        <input type="hidden" name="destination_id" value="{{ $selectedDestinationId }}" />
                    @endif

                    <div class="relative w-full sm:w-64">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" x-model="search" value="{{ $search }}"
                               @input="debouncedSearch()"
                               placeholder="Search hotel or address..."
                               class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-md py-1.5 pl-9 pr-8 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600 transition-all" />
                        <a href="{{ route('view-listings', array_filter(['destination_id' => $selectedDestinationId])) }}"
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
        </div>

        <div :class="loading ? 'opacity-60 transition-opacity' : 'transition-opacity'">
            @include('admin.hotel._hotel_rows', compact('hotels', 'selectedDestinationId'))
        </div>
    </div>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endsection