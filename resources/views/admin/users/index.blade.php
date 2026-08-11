@extends('layouts.admin')

@section('title', 'Registered Users & Chatbot Moderation | SunnyTrips Admin')

@section('content')
    <div class="pb-12"
         x-data="{
             search: @js($search),
             tab: @js($tab),
             loading: false,
             timer: null,
             baseUrl: @js(route('admin.users.index')),
             async fetchResults(url = null) {
                 const params = new URLSearchParams();
                 params.set('tab', this.tab || 'all');
                 if (this.search) params.set('search', this.search);
                 const target = url || (this.baseUrl + '?' + params.toString());
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
             setTab(tab) {
                 this.tab = tab;
                 this.fetchResults();
             },
             handleNavClick(e) {
                 const a = e.target.closest('a');
                 if (!a || !a.href.includes('page=')) return;
                 e.preventDefault();
                 this.fetchResults(a.href);
             }
         }"
         @click="handleNavClick($event)">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Registered Users & Moderation</h1>
                <p class="text-xs text-slate-500 mt-1">Manage user accounts, monitor AI chatbot abuse flags, and handle account suspensions.</p>
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        {{-- Filter Controls (live outside the swapped results region so the input keeps focus) --}}
        <div class="mb-6 bg-white p-4 border border-slate-200 rounded-lg shadow-sm space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <!-- Status Tabs -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">filter_list</span>
                        Status:
                    </span>

                    <a href="{{ route('admin.users.index', array_filter(['tab' => 'all', 'search' => $search])) }}"
                       @click.prevent="setTab('all')"
                       :class="tab === 'all' ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1.5">
                        <span>All Users</span>
                        <span :class="tab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'" class="px-1.5 py-0.2 rounded-full text-[10px]">{{ $allCount }}</span>
                    </a>

                    <a href="{{ route('admin.users.index', array_filter(['tab' => 'flagged', 'search' => $search])) }}"
                       @click.prevent="setTab('flagged')"
                       :class="tab === 'flagged' ? 'bg-amber-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px]">warning</span>
                        <span>Flagged Users</span>
                        <span :class="tab === 'flagged' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'" class="px-1.5 py-0.2 rounded-full text-[10px]">{{ $flaggedCount }}</span>
                    </a>

                    <a href="{{ route('admin.users.index', array_filter(['tab' => 'banned', 'search' => $search])) }}"
                       @click.prevent="setTab('banned')"
                       :class="tab === 'banned' ? 'bg-rose-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px]">block</span>
                        <span>Banned Users</span>
                        <span :class="tab === 'banned' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-800'" class="px-1.5 py-0.2 rounded-full text-[10px]">{{ $bannedCount }}</span>
                    </a>
                </div>

                <!-- Search Form -->
                <form action="{{ route('admin.users.index') }}" method="GET" @submit.prevent="fetchResults()" class="flex items-center gap-2 w-full lg:w-auto">
                    <input type="hidden" name="tab" value="{{ $tab }}" />

                    <div class="relative w-full sm:w-64">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" x-model="search" value="{{ $search }}"
                               placeholder="Search user name or email..."
                               @input="debouncedSearch()"
                               class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-md py-1.5 pl-9 pr-8 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600 transition-all" />
                        <a href="{{ route('admin.users.index', ['tab' => $tab]) }}"
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
            @include('admin.users._table', compact('users'))
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endsection