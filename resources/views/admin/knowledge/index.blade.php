@extends('layouts.admin')

@section('title', 'Travel Guidance | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body"
         x-data="adminLiveList({
             base: @js(route('admin.knowledge.index')),
             filters: { search: @js($search), destinationId: @js($destinationId ?? ''), category: @js($category ?? ''), visibility: @js($visibility ?? '') },
             params: { search: 'search', destinationId: 'destinationId', category: 'category', visibility: 'visibility' },
         })"
         @click="handleNavClick($event)">

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Travel Guidance</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Reviewed, non-bookable destination guidance SunnyBot may cite. Only published, reviewed and unexpired articles are used, and never as prices, availability or bookings.</p>
            </div>
            <a href="{{ route('admin.knowledge.create') }}"
               class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_circle</span>
                <span>+ New Article</span>
            </a>
        </div>

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2" role="status">
                <span class="material-symbols-outlined text-[20px] text-emerald-600" aria-hidden="true">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            @foreach ([['Total articles', $stats['total'], 'text-slate-900'], ['Citable by SunnyBot', $stats['citable'], 'text-emerald-700'], ['Drafts', $stats['draft'], 'text-sky-700'], ['Expired', $stats['expired'], 'text-amber-700']] as [$label, $value, $tone])
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">{{ $label }}</span>
                    <p class="text-2xl font-black {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs mb-6">
            <form action="{{ route('admin.knowledge.index') }}" method="GET" @submit.prevent="fetchResults()" class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:w-72">
                    <label for="knowledge-search" class="sr-only">Search articles</label>
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm" aria-hidden="true">search</span>
                    <input id="knowledge-search" type="text" name="search" x-model="search" value="{{ $search }}" placeholder="Search title, text or source..." @input="debouncedSearch()"
                           class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>
                <label for="knowledge-destination" class="sr-only">Destination</label>
                <select id="knowledge-destination" name="destinationId" x-model="destinationId" @change="fetchResults()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Destinations</option>
                    <option value="general">General (all destinations)</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                    @endforeach
                </select>
                <label for="knowledge-category" class="sr-only">Category</label>
                <select id="knowledge-category" name="category" x-model="category" @change="fetchResults()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Categories</option>
                    @foreach (\App\Models\KnowledgeArticle::CATEGORIES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label for="knowledge-status" class="sr-only">Status</label>
                <select id="knowledge-status" name="visibility" x-model="visibility" @change="fetchResults()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Statuses</option>
                    <option value="citable">Citable now</option>
                    <option value="draft">Draft</option>
                    <option value="archived">Archived</option>
                    <option value="expired">Expired</option>
                    <option value="unreviewed">No review date</option>
                </select>
                <a href="{{ route('admin.knowledge.index') }}" @click.prevent="search = ''; destinationId = ''; category = ''; visibility = ''; fetchResults()" x-show="search || destinationId || category || visibility" class="text-xs font-bold text-rose-600 hover:underline px-2">Clear Filters</a>
            </form>
        </div>

        <div :class="loading ? 'opacity-60 transition-opacity' : 'transition-opacity'">
            <div x-show="error" x-cloak role="alert" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"><p x-text="error" class="flex-1"></p><button type="button" @click="retryResults()" class="min-h-11 px-3 font-bold underline underline-offset-4">Retry</button></div>
            @include('admin.knowledge._table', compact('articles'))
        </div>
    </div>
@endsection
