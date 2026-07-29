@extends('layouts.admin')

@section('title', 'Registered Users & Chatbot Moderation | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
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

        <!-- Filter Tabs & Search Bar -->
        <div class="mb-6 bg-white p-4 border border-slate-200 rounded-lg shadow-sm space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <!-- Status Tabs -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">filter_list</span>
                        Status:
                    </span>

                    <a href="{{ route('admin.users.index', array_filter(['tab' => 'all', 'search' => $search])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1.5 {{ $tab === 'all' ? 'bg-ocean-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <span>All Users</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $allCount }}</span>
                    </a>

                    <a href="{{ route('admin.users.index', array_filter(['tab' => 'flagged', 'search' => $search])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1.5 {{ $tab === 'flagged' ? 'bg-amber-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <span class="material-symbols-outlined text-[14px]">warning</span>
                        <span>Flagged Users</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'flagged' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $flaggedCount }}</span>
                    </a>

                    <a href="{{ route('admin.users.index', array_filter(['tab' => 'banned', 'search' => $search])) }}"
                       class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors shrink-0 flex items-center gap-1.5 {{ $tab === 'banned' ? 'bg-rose-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <span class="material-symbols-outlined text-[14px]">block</span>
                        <span>Banned Users</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'banned' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-800' }}">{{ $bannedCount }}</span>
                    </a>
                </div>

                <!-- Search Form -->
                <form action="{{ route('admin.users.index') }}" method="GET" class="flex items-center gap-2 w-full lg:w-auto">
                    <input type="hidden" name="tab" value="{{ $tab }}" />

                    <div class="relative w-full sm:w-64">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                        <input type="text" name="search" value="{{ $search }}"
                               placeholder="Search user name or email..."
                               class="w-full bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-md py-1.5 pl-9 pr-8 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-ocean-500/20 focus:border-ocean-600 transition-all" />
                        @if($search)
                            <a href="{{ route('admin.users.index', ['tab' => $tab]) }}"
                               class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" title="Clear search">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </a>
                        @endif
                    </div>

                    <button type="submit"
                            class="px-3 py-1.5 bg-ocean-600 hover:bg-ocean-700 text-white rounded-md text-xs font-semibold shadow-sm transition-colors shrink-0">
                        Search
                    </button>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white border border-slate-200 rounded-lg shadow-sm">
            <div class="w-full">
                <table class="w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-wider text-slate-500 w-8">#</th>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Registered User</th>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Contact & Address</th>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Joined Date</th>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Account Status</th>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Chatbot Abuse Flags</th>
                            <th class="px-3 py-3 text-left font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($users as $index => $u)
                            <tr class="hover:bg-slate-50/55 transition-colors">
                                <td class="px-3 py-3 text-slate-500 font-medium">
                                    {{ $users->firstItem() + $index }}
                                </td>

                                <td class="px-3 py-3 font-semibold text-slate-900">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-ocean-50 text-ocean-700 font-bold flex items-center justify-center text-xs shrink-0 border border-ocean-200">
                                            {{ strtoupper(substr($u->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-900 truncate max-w-[150px]">{{ $u->name }}</div>
                                            <div class="text-[11px] text-slate-500 font-normal truncate max-w-[150px]">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-[11px] text-slate-600">
                                    <div><strong class="text-slate-700">Phone:</strong> {{ $u->phone_number ?? 'N/A' }}</div>
                                    <div class="text-slate-500 truncate max-w-[160px]" title="{{ $u->address }}">{{ $u->address ?? 'No address set' }}</div>
                                </td>

                                <td class="px-3 py-3 text-[11px] text-slate-500 whitespace-nowrap">
                                    {{ $u->created_at ? $u->created_at->format('M d, Y') : 'N/A' }}
                                </td>

                                <td class="px-3 py-3 text-xs whitespace-nowrap">
                                    @if($u->is_banned)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="material-symbols-outlined text-[13px]">block</span> Banned
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="material-symbols-outlined text-[13px]">check_circle</span> Active
                                        </span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 text-xs whitespace-nowrap">
                                    @if($u->chatbot_flag_count > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-300" title="{{ $u->chatbot_flag_count }} recorded safety violations">
                                            <span class="material-symbols-outlined text-[13px]">warning</span> {{ $u->chatbot_flag_count }} Violation{{ $u->chatbot_flag_count > 1 ? 's' : '' }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-500 border border-slate-200">
                                            <span class="material-symbols-outlined text-[13px]">shield</span> Clean
                                        </span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 whitespace-nowrap text-xs">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.users.show', $u->id) }}"
                                           class="inline-flex items-center gap-1 h-8 px-3 rounded bg-white border border-ocean-300 hover:border-ocean-600 text-ocean-600 hover:bg-ocean-50 font-medium transition-colors shadow-sm">
                                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                                            View Logs
                                        </a>

                                        @if(!$u->is_banned)
                                            <div x-data="{ openBanModal: false }">
                                                <button @click="openBanModal = true" type="button"
                                                        class="inline-flex items-center gap-1 h-8 px-3 rounded bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-medium transition-colors cursor-pointer">
                                                    <span class="material-symbols-outlined text-[16px]">block</span>
                                                    Ban
                                                </button>

                                                <!-- Ban Confirmation Modal -->
                                                <div x-show="openBanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" x-cloak style="display: none;">
                                                    <div @click.away="openBanModal = false" class="bg-white rounded-lg p-6 w-full max-w-sm border border-slate-200 shadow-lg text-left whitespace-normal">
                                                        <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center mb-4 text-rose-600">
                                                            <span class="material-symbols-outlined text-xl">block</span>
                                                        </div>
                                                        <h3 class="text-sm font-bold text-slate-900">Ban User Account</h3>
                                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                                            Are you sure you want to suspend <strong class="text-slate-700">{{ $u->name }}</strong>? They will be blocked from using the AI Chatbot.
                                                        </p>

                                                        <form action="{{ route('admin.users.ban', $u->id) }}" method="POST" class="mt-4 space-y-4">
                                                            @csrf
                                                            <div class="space-y-1">
                                                                <label class="block text-xs font-semibold text-slate-700">Reason for Suspension</label>
                                                                <textarea name="ban_reason" rows="3" required placeholder="e.g. Repeated inappropriate queries and chatbot rule abuse."
                                                                          class="w-full text-xs bg-slate-50 border border-slate-300 rounded p-2 text-slate-900 focus:ring-2 focus:ring-rose-500/20 focus:border-rose-600"></textarea>
                                                            </div>

                                                            <div class="flex gap-3 pt-2">
                                                                <button @click="openBanModal = false" type="button" class="flex-1 h-8 px-3 bg-white border border-slate-300 text-slate-700 text-xs font-medium rounded hover:bg-slate-50 transition-colors">
                                                                    Cancel
                                                                </button>
                                                                <button type="submit" class="flex-1 h-8 px-3 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded transition-colors">
                                                                    Confirm Ban
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <form action="{{ route('admin.users.unban', $u->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" onclick="return confirm('Restore normal access for {{ $u->name }}?')"
                                                        class="inline-flex items-center gap-1 h-8 px-3 rounded bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-700 font-medium transition-colors cursor-pointer">
                                                    <span class="material-symbols-outlined text-[16px]">lock_open</span>
                                                    Unban
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">person_off</span>
                                        <p class="text-sm font-semibold text-slate-700">No registered users found.</p>
                                        <p class="text-xs text-slate-400 mt-1">Try switching tabs or searching for another user name.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="px-6 py-4 border-t border-slate-200">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endsection
