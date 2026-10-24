@extends('layouts.admin')

@section('title', 'Lucky Promotions | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body"
         x-data="adminLiveList({
             base: @js(route('admin.promotions.index')),
             filters: { search: @js($search), visibility: @js($visibility ?? '') },
             params: { search: 'search', visibility: 'visibility' },
         })"
         @click="handleNavClick($event)">

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Lucky Promotions</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Codes are issued to a single traveler for one generated Lucky itinerary and apply only to that itinerary’s room and activities at checkout. Without an active campaign, no discount is promised.</p>
            </div>

            <a href="{{ route('admin.promotions.create') }}"
               class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>New Campaign</span>
            </a>
        </div>

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            @foreach ([
                ['Active campaigns', $stats['active'], 'campaign', 'bg-sky-50 text-sky-600 border-sky-200'],
                ['Codes issued', $stats['codes'], 'confirmation_number', 'bg-slate-50 text-slate-600 border-slate-200'],
                ['Reserved at checkout', $stats['reserved'], 'hourglass_top', 'bg-amber-50 text-amber-600 border-amber-200'],
                ['Redeemed (paid)', $stats['redeemed'], 'verified', 'bg-emerald-50 text-emerald-600 border-emerald-200'],
            ] as [$label, $value, $icon, $tone])
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl border {{ $tone }} flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl">{{ $icon }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">{{ $label }}</span>
                        <h3 class="text-2xl font-black text-slate-900">{{ $value }}</h3>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs mb-6">
            <form action="{{ route('admin.promotions.index') }}" method="GET" @submit.prevent="fetchResults()" class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:w-72">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm">search</span>
                    <label for="promotion-search" class="sr-only">Search campaigns</label>
                    <input id="promotion-search" type="text" name="search" x-model="search" value="{{ $search }}" placeholder="Search name or code prefix..."
                           @input="debouncedSearch()"
                           class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>
                <label for="promotion-status" class="sr-only">Status</label>
                <select id="promotion-status" name="visibility" x-model="visibility" @change="fetchResults()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Paused / draft</option>
                </select>
                <a href="{{ route('admin.promotions.index') }}" @click.prevent="search = ''; visibility = ''; fetchResults()" x-show="search || visibility" class="text-xs font-bold text-rose-600 hover:underline px-2">Clear Filters</a>
            </form>
        </div>

        <div :class="loading ? 'opacity-60 transition-opacity' : 'transition-opacity'">
            <div x-show="error" x-cloak role="alert" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"><p x-text="error" class="flex-1"></p><button type="button" @click="retryResults()" class="min-h-11 px-3 font-bold underline underline-offset-4">Retry</button></div>
            @include('admin.promotions._results', compact('promotions'))
        </div>
    </div>
@endsection
