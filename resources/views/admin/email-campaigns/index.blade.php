@extends('layouts.admin')

@section('title', 'Email Campaigns | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body"
         x-data="adminLiveList({
             base: @js(route('admin.email-campaigns.index')),
             filters: { search: @js($search), tab: @js($tab) },
             params: { search: 'search', tab: 'tab' },
         })"
         @click="handleNavClick($event)">

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Email Campaigns</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl">Promotional emails to travelers who opted in to deals. Send one now, schedule it, or leave an automatic campaign on to announce new Lucky promotions and tour packages. Every email carries a one-click unsubscribe link.</p>
            </div>
            <a href="{{ route('admin.email-campaigns.create') }}"
               class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_circle</span>
                <span>New Campaign</span>
            </a>
        </div>

        @foreach (['success' => 'bg-emerald-50 border-emerald-200/80 text-emerald-800', 'error' => 'bg-rose-50 border-rose-200/80 text-rose-800'] as $flash => $tone)
            @if (session($flash))
                <div role="status" class="{{ $tone }} border text-sm px-4 py-3 rounded-2xl mb-6">{{ session($flash) }}</div>
            @endif
        @endforeach

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            @foreach ([
                ['Opted-in travelers', $stats['subscribers'], 'mark_email_read', 'bg-sky-50 text-sky-600 border-sky-200'],
                ['Can still send today', $stats['remaining'], 'schedule_send', 'bg-amber-50 text-amber-600 border-amber-200'],
                ['Sent in 30 days', $stats['sent_30d'], 'outgoing_mail', 'bg-emerald-50 text-emerald-600 border-emerald-200'],
                ['Automatic campaigns on', $stats['automatic'], 'bolt', 'bg-slate-50 text-slate-600 border-slate-200'],
            ] as [$label, $value, $icon, $tone])
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl border {{ $tone }} flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $icon }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">{{ $label }}</span>
                        <p class="text-2xl font-black text-slate-900">{{ number_format($value) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <form action="{{ route('admin.settings.update', 'marketing') }}" method="POST" class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs mb-6 flex flex-wrap items-end gap-3">
            @csrf
            @method('PATCH')
            <input type="hidden" name="section" value="marketing">
            <input type="hidden" name="revision" value="{{ $limitsRevision }}">
            <div>
                <label for="daily-cap" class="block text-xs font-bold text-slate-800 mb-1">Emails per day</label>
                <input id="daily-cap" type="number" name="values[daily_cap]" min="1" max="50000" required value="{{ old('values.daily_cap', $limits['daily_cap']) }}" aria-describedby="limits-hint"
                       class="w-32 px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
            <div>
                <label for="frequency-days" class="block text-xs font-bold text-slate-800 mb-1">Days between emails to one traveler</label>
                <input id="frequency-days" type="number" name="values[frequency_days]" min="0" max="30" required value="{{ old('values.frequency_days', $limits['frequency_days']) }}" aria-describedby="limits-hint"
                       class="w-32 px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
            <button type="submit" class="min-h-11 px-5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs cursor-pointer">Save limits</button>
            <p id="limits-hint" class="text-xs text-slate-500 basis-full">The daily limit resets at midnight Philippine time; emails over it go out the next day. Resend’s free plan allows 100 a day. The gap stops one traveler getting several promotions in a row (0 turns it off).</p>
            @error('values.daily_cap')<p class="text-xs text-rose-600 basis-full">{{ $message }}</p>@enderror
            @error('values.frequency_days')<p class="text-xs text-rose-600 basis-full">{{ $message }}</p>@enderror
            @error('revision')<p class="text-xs text-rose-600 basis-full">{{ $message }}</p>@enderror
        </form>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs mb-6 flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Campaign status">
                @foreach (['' => 'All', 'drafts' => 'Drafts', 'scheduled' => 'Scheduled & sending', 'sent' => 'Sent', 'automatic' => 'Automatic'] as $value => $label)
                    <button type="button" @click="setTab(@js($value))" :aria-pressed="tab === @js($value)"
                            :class="tab === @js($value) ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            class="min-h-9 px-3 rounded-xl border text-xs font-bold transition cursor-pointer">{{ $label }}</button>
                @endforeach
            </div>
            <div class="relative w-full sm:w-72 sm:ml-auto">
                <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm" aria-hidden="true">search</span>
                <label for="campaign-search" class="sr-only">Search campaigns</label>
                <input id="campaign-search" type="text" x-model="search" placeholder="Search name or subject..." @input="debouncedSearch()"
                       class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
        </div>

        <div :class="loading ? 'opacity-60 transition-opacity' : 'transition-opacity'">
            <div x-show="error" x-cloak role="alert" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800"><p x-text="error" class="flex-1"></p><button type="button" @click="retryResults()" class="min-h-11 px-3 font-bold underline underline-offset-4">Retry</button></div>
            @include('admin.email-campaigns._results', compact('campaigns'))
        </div>
    </div>
@endsection
