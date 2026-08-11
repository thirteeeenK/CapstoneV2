@extends('layouts.admin')

@section('title', 'Bookings | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body"
         x-data="{
             search: @js($search),
             status: @js($status),
             loading: false,
             timer: null,
             baseUrl: @js(route('admin.bookings.index')),
             async fetchResults(url = null) {
                 const params = new URLSearchParams();
                 if (this.search) params.set('search', this.search);
                 if (this.status) params.set('status', this.status);
                 const target = url || (this.baseUrl + (params.toString() ? '?' + params.toString() : ''));
                 this.loading = true;
                 try {
                     const res = await fetch(target, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                     const html = await res.text();
                     const el = document.getElementById('bookings-table');
                     if (el) el.outerHTML = html;
                 } finally {
                     this.loading = false;
                 }
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
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Booking Requests</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Verify availability, approve bookings, and manage payments.</p>
            </div>
            <div class="flex items-center gap-2 text-xs font-bold">
                <span class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200">{{ $stats['pending'] }} pending</span>
                <span class="px-3 py-1.5 rounded-xl bg-sky-50 text-sky-700 border border-sky-200">{{ $stats['approved'] }} awaiting payment</span>
                <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">{{ $stats['paid'] }} paid</span>
            </div>
        </div>

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-rose-600">error</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.bookings.index') }}" @submit.prevent="fetchResults()"
              class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-4 mb-6 flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                <input type="text" name="search" x-model="search" value="{{ $search }}"
                       @input="clearTimeout(timer); timer = setTimeout(() => fetchResults(), 250)"
                       placeholder="Search booking code, guest name, or email..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
            <select name="status" x-model="status" @change="fetchResults()" class="px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                <option value="">All statuses</option>
                <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending review</option>
                <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved (awaiting payment)</option>
                <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                <option value="expired" {{ $status === 'expired' ? 'selected' : '' }}>Expired</option>
                <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>Closed (rejected/cancelled/expired/completed)</option>
            </select>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs transition flex items-center justify-center gap-1 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                Filter
            </button>
            <a href="{{ route('admin.bookings.index') }}" @click.prevent="search = ''; status = ''; fetchResults()" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition flex items-center justify-center gap-1 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">refresh</span>
                Reset
            </a>
        </form>

        {{-- Bookings Table --}}
        <div :class="loading ? 'opacity-60 transition-opacity' : 'transition-opacity'">
            @include('admin.bookings._table', compact('bookings'))
        </div>
    </div>
@endsection
