@extends('layouts.admin')
@section('title', 'Planner Scheduling & Locations — SunnyTrips')
@section('content')
    <div x-data="plannerScheduling({search: @js($search), destinationId: @js($selectedDestinationId), type: @js($type), base: @js(route('admin.planner-inventory.index')), inventory: @js(route('admin.inventory.index'))})" class="max-w-6xl pb-12 font-body">
        <header class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div class="max-w-2xl">
                <h1 class="font-headline text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-2xl">Planner Scheduling &amp; Locations</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Set operator schedules and entrance or meeting locations used to build itineraries. Manage catalog visibility in Inventory.</p>
            </div>
            <a href="{{ route('admin.inventory.index', array_filter(['tab' => $type === 'hotel' ? 'hotels' : 'activities', 'destination_id' => $selectedDestinationId])) }}" :href="inventoryUrl()" class="inline-flex min-h-11 shrink-0 items-center gap-2 self-start rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-ocean-700 hover:bg-ocean-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600 dark:border-slate-600 dark:text-ocean-300 dark:hover:bg-slate-800"><span aria-hidden="true" class="material-symbols-outlined text-lg">inventory_2</span>Manage visibility in Inventory</a>
        </header>
        @if(session('success'))<p role="status" class="p-3 rounded-lg bg-emerald-50 text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())<div role="alert" class="p-3 rounded-lg bg-rose-50 text-rose-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <nav aria-label="Scheduling categories" class="mt-6 flex gap-6 border-b border-slate-200 dark:border-slate-700">
            @foreach(['activity' => 'Activities', 'hotel' => 'Hotels'] as $key => $label)
                <a href="{{ route('admin.planner-inventory.index', array_filter(['type' => $key, 'search' => $search, 'destination_id' => $selectedDestinationId])) }}" @click.prevent="setTab('{{ $key }}')" :aria-current="tab === '{{ $key }}' ? 'page' : null" :class="tab === '{{ $key }}' ? 'border-ocean-700 text-ocean-700 dark:border-ocean-300 dark:text-ocean-300' : 'border-transparent text-slate-600 dark:text-slate-300'" class="inline-flex min-h-11 items-center border-b-2 px-1 py-3 text-sm font-bold focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600">{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.planner-inventory.index') }}" @submit.prevent="fetchResults()" class="grid items-end gap-4 border-b border-slate-200 py-5 dark:border-slate-700 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(12rem,18rem)_auto]">
            <input type="hidden" name="type" value="{{ $type }}" :value="tab">
            <div><label for="inventory-search" class="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-200">Search hotels or activities</label><input id="inventory-search" name="search" type="search" x-model="search" value="{{ $search }}" @input="debouncedSearch()" placeholder="Search by name" class="min-h-11 w-full min-w-0 rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"></div>
            <div><label for="scheduling-destination" class="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-200">Destination</label><select id="scheduling-destination" name="destination_id" x-model="destinationId" @change="fetchResults()" class="min-h-11 w-full min-w-0 rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"><option value="">All destinations</option>@foreach($destinations as $destination)<option value="{{ $destination->id }}" @selected((string) $selectedDestinationId === (string) $destination->id)>{{ $destination->name }}</option>@endforeach</select></div>
            <div class="flex items-center gap-3"><button type="submit" class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2 text-sm font-semibold text-white hover:bg-ocean-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ocean-600">Apply filters</button><a href="{{ route('admin.planner-inventory.index', ['type' => $type]) }}" @click.prevent="resetFilters()" class="inline-flex min-h-11 items-center py-2 text-sm font-semibold text-slate-600 hover:text-ocean-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600 dark:text-slate-300">Clear filters</a></div>
        </form>
        <div x-show="error" x-cloak role="alert" class="my-4 flex flex-wrap items-center gap-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"><p x-text="error" class="flex-1"></p><button type="button" @click="retryResults()" class="min-h-11 px-3 font-bold underline underline-offset-4">Retry</button></div>
        <p x-show="loading" x-cloak role="status" class="py-3 text-sm text-ocean-700 dark:text-ocean-300">Updating scheduling results…</p>
        <div x-ref="results" @click="handleNavClick($event)" :aria-busy="loading" :inert="loading" :class="loading ? 'opacity-50' : ''">@include('admin.planner-inventory._results')</div>
    </div>
@endsection
