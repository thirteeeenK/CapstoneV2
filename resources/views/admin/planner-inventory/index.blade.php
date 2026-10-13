@extends('layouts.admin')
@section('title', 'Planner Inventory — SunnyTrips')
@section('content')
    <div x-data="{search: @js($search), tab: @js($type), loading: false, timer: null,
        async fetchResults(url = null) { this.loading = true; try { const response = await fetch(url || @js(route('admin.planner-inventory.index')) + '?' + new URLSearchParams({ search: this.search, type: this.tab }), { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); if (response.ok) this.$refs.results.innerHTML = await response.text(); } finally { this.loading = false; } },
        debouncedSearch() { clearTimeout(this.timer); this.timer = setTimeout(() => this.fetchResults(), 300); },
        setTab(tab) { this.tab = tab; this.fetchResults(); },
        handleNavClick(event) { const link = event.target.closest('a'); if (link && this.$refs.results.contains(link)) { event.preventDefault(); this.fetchResults(link.href); } }
    }" class="max-w-6xl space-y-6">
        <h1 class="text-2xl font-headline font-bold">Planner inventory &amp; scheduling</h1>
        <p class="text-sm text-slate-500">Verify road entrances, meeting points and operator schedules. Water activities route to their land meeting point. Hotel check-in/out times affect the first and last day.</p>
        @if(session('success'))<p role="status" class="p-3 rounded-lg bg-emerald-50 text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())<div role="alert" class="p-3 rounded-lg bg-rose-50 text-rose-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="flex flex-wrap gap-3 items-center"><label for="inventory-search" class="sr-only">Search planner inventory</label><input id="inventory-search" type="search" x-model="search" @input="debouncedSearch()" placeholder="Search inventory" class="rounded-lg border-slate-300 dark:bg-slate-800">
            @foreach(['activity' => 'Activities', 'hotel' => 'Hotels'] as $key => $label)<button type="button" @click="setTab('{{ $key }}')" :aria-pressed="tab === '{{ $key }}'" class="min-h-11 rounded-lg px-4 py-2 font-semibold" :class="tab === '{{ $key }}' ? 'bg-ocean-700 text-white' : 'bg-slate-100 dark:bg-slate-800'">{{ $label }}</button>@endforeach
            <span x-show="loading" role="status" class="text-sm">Loading…</span>
        </div>
        <div x-ref="results" @click="handleNavClick($event)">@include('admin.planner-inventory._results')</div>
    </div>
@endsection
