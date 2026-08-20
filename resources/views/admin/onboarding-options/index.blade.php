@extends('layouts.admin')

@section('title', 'Onboarding Options Manager | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body"
         x-data="{
             search: @js($search),
             type: @js($type ?? ''),
             visibility: @js($visibility ?? ''),
             loading: false,
             timer: null,
             baseUrl: @js(route('admin.onboarding-options.index')),
             async fetchResults(url = null) {
                 const params = new URLSearchParams();
                 if (this.search) params.set('search', this.search);
                 if (this.type) params.set('type', this.type);
                 if (this.visibility) params.set('visibility', this.visibility);
                 const target = url || (this.baseUrl + (params.toString() ? '?' + params.toString() : ''));
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
             handleNavClick(e) {
                 const a = e.target.closest('a');
                 if (!a || !a.href.includes('page=')) return;
                 e.preventDefault();
                 this.fetchResults(a.href);
             }
         }"
         @click="handleNavClick($event)">

        {{-- Header & Create Action --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Onboarding Options Manager</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Control the vibes, traveler types, and amenity pills shown during AI onboarding.</p>
            </div>

            <a href="{{ route('admin.onboarding-options.create') }}"
               class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>+ Add Option</span>
            </a>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">tune</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">Total Options</span>
                    <h3 class="text-2xl font-black text-slate-900">{{ $stats['total'] }}</h3>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">visibility</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">Visible</span>
                    <h3 class="text-2xl font-black text-emerald-700">{{ $stats['visible'] }}</h3>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">visibility_off</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">Hidden</span>
                    <h3 class="text-2xl font-black text-amber-700">{{ $stats['hidden'] }}</h3>
                </div>
            </div>
        </div>

        {{-- Filter Controls --}}
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs mb-6">
            <form action="{{ route('admin.onboarding-options.index') }}" method="GET" @submit.prevent="fetchResults()" class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:w-72">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm">search</span>
                    <input type="text"
                           name="search"
                           x-model="search"
                           value="{{ $search }}"
                           placeholder="Search option name or description..."
                           @input="debouncedSearch()"
                           class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                <select name="type" x-model="type" @change="fetchResults()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Types</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select name="visibility" x-model="visibility" @change="fetchResults()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Statuses</option>
                    <option value="visible">Visible Only</option>
                    <option value="hidden">Hidden Only</option>
                </select>

                <a href="{{ route('admin.onboarding-options.index') }}" @click.prevent="search = ''; type = ''; visibility = ''; fetchResults()" x-show="search || type || visibility" class="text-xs font-bold text-rose-600 hover:underline px-2">Clear Filters</a>
            </form>
        </div>

        <div :class="loading ? 'opacity-60 transition-opacity' : 'transition-opacity'">
            @include('admin.onboarding-options._table', compact('options'))
        </div>
    </div>
@endsection
