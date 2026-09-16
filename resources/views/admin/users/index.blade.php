@extends('layouts.admin')

@section('title', 'Registered Users & Chatbot Moderation | SunnyTrips Admin')

@section('content')
    <div class="pb-12" x-data="{
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
                 }" @click="handleNavClick($event)">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Registered Users & Moderation</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage user accounts, monitor AI chatbot abuse flags, and
                    handle account suspensions.</p>
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div
                class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        {{-- Filter Controls (live outside the swapped results region so the input keeps focus) --}}
        <div class="mb-6 bg-white p-4 sm:p-5 border border-slate-200/80 rounded-2xl shadow-xs">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <!-- Status Tabs -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span
                        class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">filter_list</span>
                        Status:
                    </span>

                    <a href="{{ route('admin.users.index', array_filter(['tab' => 'all', 'search' => $search])) }}"
                        @click.prevent="setTab('all')"
                        :class="tab === 'all' ? 'bg-ocean-600 text-white font-semibold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-3 py-2 rounded-xl text-xs font-medium transition-all shrink-0 flex items-center gap-1.5 cursor-pointer">
                        <span>All Users</span>
                        <span :class="tab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold">{{ $allCount }}</span>
                    </a>

                    <div class="relative">
                        <select @change="setTab($event.target.value || 'all')"
                            :value="['flagged', 'warned', 'temporary', 'permanent'].includes(tab) ? tab : ''"
                            :class="['flagged', 'warned', 'temporary', 'permanent'].includes(tab) ? 'bg-ocean-50 text-ocean-700 border-ocean-300 font-semibold ring-2 ring-ocean-500/10' : 'bg-slate-100 text-slate-600 border-transparent hover:bg-slate-200'"
                            class="appearance-none pl-3 pr-8 py-2 rounded-xl text-xs font-medium border focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600 transition-all cursor-pointer">
                            <option value="" disabled>Account status</option>
                            <option value="flagged">Flagged Users ({{ $flaggedCount }})</option>
                            <option value="warned">Warned ({{ $warnedCount }})</option>
                            <option value="temporary">Temporarily Banned ({{ $temporaryCount }})</option>
                            <option value="permanent">Permanently Banned ({{ $permanentCount }})</option>
                        </select>
                        <span
                            class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 text-[18px]">expand_more</span>
                    </div>
                </div>

                <!-- Search Form -->
                <form action="{{ route('admin.users.index') }}" method="GET" @submit.prevent="fetchResults()"
                    class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="hidden" name="tab" value="{{ $tab }}" />

                    <div class="relative w-full sm:w-72">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" x-model="search" value="{{ $search }}"
                            placeholder="Search user name or email..." @input="debouncedSearch()"
                            class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-ocean-600 rounded-xl py-2 pl-9 pr-8 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 transition-all" />
                        <a href="{{ route('admin.users.index', ['tab' => $tab]) }}"
                            @click.prevent="search = ''; fetchResults()" x-show="search"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            title="Clear search">
                            <span class="material-symbols-outlined text-[16px]">close</span>
                        </a>
                    </div>

                    <button type="submit"
                        class="px-4 py-2 bg-ocean-600 hover:bg-ocean-700 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors shrink-0 cursor-pointer">
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