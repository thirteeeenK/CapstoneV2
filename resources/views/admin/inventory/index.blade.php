@extends('layouts.admin')

@section('title', 'Central Inventory Management | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Central Inventory Management</h1>
                <p class="text-xs text-slate-500 mt-1">Control public visibility (<code
                        class="text-ocean-600 font-mono">is_shown</code>) and monitor inventory availability for hotels,
                    room types, activities, and add-ons/transfers.</p>
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div
                class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">extension</span>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Add-ons & Transfers</p>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="text-lg font-bold text-slate-900">{{ $stats['total_addons'] }}</span>
                        <span class="text-xs text-emerald-600 font-medium">{{ $stats['shown_addons'] }} Visible</span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="text-xs text-amber-600 font-medium">{{ $stats['hidden_addons'] }} Hidden</span>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{
            search: @js($search),
            destinationId: @js((string) ($selectedDestinationId ?? '')),
            visibility: @js($visibility ?? ''),
            tab: @js($activeTab),
            loading: false,
            timer: null,
            baseUrl: @js(route('admin.inventory.index')),
            async fetchResults() {
                const params = new URLSearchParams();
                params.set('tab', this.tab || 'hotels');
                if (this.destinationId) params.set('destination_id', this.destinationId);
                if (this.visibility) params.set('visibility', this.visibility);
                if (this.search) params.set('search', this.search);
                const target = this.baseUrl + '?' + params.toString();
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
            },
            setVisibility(v) {
                this.visibility = v;
                this.fetchResults();
            },
            setTab(tab) {
                this.tab = tab;
                this.fetchResults();
            }
        }">
{{-- Filter Controls (live outside the swapped results region so the input keeps focus) --}}
        <div class="mb-6 bg-white p-4 border border-slate-200 rounded-lg shadow-sm space-y-4">
            {{-- Location Filters --}}
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0">
                    <span
                        class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">place</span>
                        Location:
                    </span>

                    <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'visibility' => $visibility, 'search' => $search])) }}"
                        @click.prevent="setDestinationId('')"
                        :class="destinationId === '' ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0">
                        All Locations
                    </a>

                    @foreach($destinations as $dest)
                        <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $dest->id, 'visibility' => $visibility, 'search' => $search])) }}"
                            @click.prevent="setDestinationId({{ $dest->id }})"
                            :class="destinationId == {{ $dest->id }} ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0">
                            {{ $dest->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Visibility Status Filters & Search Bar --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-3 border-t border-slate-100">
                <div class="flex items-center gap-2 overflow-x-auto">
                    <span
                        class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">visibility</span>
                        Visibility:
                    </span>

                    <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $selectedDestinationId, 'search' => $search])) }}"
                        @click.prevent="setVisibility('')"
                        :class="visibility === '' ? 'bg-slate-800 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0">
                        All Statuses
                    </a>

                    <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $selectedDestinationId, 'visibility' => 'visible', 'search' => $search])) }}"
                        @click.prevent="setVisibility('visible')"
                        :class="visibility === 'visible' ? 'bg-emerald-600 text-white font-semibold' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1">
                        <span
                            :class="visibility === 'visible' ? 'bg-white' : 'bg-emerald-500'"
                            class="w-1.5 h-1.5 rounded-full"></span>
                        Visible Only
                    </a>

                    <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $selectedDestinationId, 'visibility' => 'hidden', 'search' => $search])) }}"
                        @click.prevent="setVisibility('hidden')"
                        :class="visibility === 'hidden' ? 'bg-amber-600 text-white font-semibold' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'"
                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1">
                        <span
                            :class="visibility === 'hidden' ? 'bg-white' : 'bg-amber-500'"
                            class="w-1.5 h-1.5 rounded-full"></span>
                        Hidden Only
                    </a>
                </div>

                {{-- Search Box --}}
                <form action="{{ route('admin.inventory.index') }}" method="GET" @submit.prevent="fetchResults()"
                    class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    @if($selectedDestinationId)
                        <input type="hidden" name="destination_id" value="{{ $selectedDestinationId }}">
                    @endif
                    @if($visibility)
                        <input type="hidden" name="visibility" value="{{ $visibility }}">
                    @endif

                    <div class="relative flex-1 sm:w-64">
                        <span
                            class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" x-model="search" value="{{ $search }}" placeholder="Search inventory items..."
                            @input="debouncedSearch()"
                            class="w-full bg-slate-50 border border-slate-300 rounded-md pl-8 pr-8 py-1.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600">
                        <a href="{{ route('admin.inventory.index', array_filter(['tab' => $activeTab, 'destination_id' => $selectedDestinationId, 'visibility' => $visibility])) }}"
                            @click.prevent="search = ''; fetchResults()"
                            x-show="search"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            title="Clear search">
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
            @include('admin.inventory._results', compact('hotels', 'rooms', 'activities', 'addons', 'destinations', 'activeTab', 'selectedDestinationId', 'visibility', 'search'))
        </div>
    </div>
@endsection