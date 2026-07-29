@extends('layouts.admin')

@section('title', 'User Moderation Details | SunnyTrips Admin')

@section('content')
    <div class="pb-12 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 font-semibold transition-colors mb-2">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Back to Registered Users
                </a>
                <h1 class="text-xl font-bold text-slate-900">User Moderation & Abuse Logs</h1>
                <p class="text-xs text-slate-500 mt-1">Review account profile details and full AI chatbot safety incident logs for <strong>{{ $user->name }}</strong>.</p>
            </div>

            <div class="flex items-center gap-3">
                @if(!$user->is_banned)
                    <div x-data="{ openBanModal: false }">
                        <button @click="openBanModal = true" type="button"
                                class="inline-flex items-center gap-1.5 h-9 px-4 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">block</span>
                            Ban User Account
                        </button>

                        <!-- Ban Confirmation Modal -->
                        <div x-show="openBanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" x-cloak style="display: none;">
                            <div @click.away="openBanModal = false" class="bg-white rounded-lg p-6 w-full max-w-sm border border-slate-200 shadow-lg text-left">
                                <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center mb-4 text-rose-600">
                                    <span class="material-symbols-outlined text-xl">block</span>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">Ban User Account</h3>
                                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                    Are you sure you want to suspend <strong class="text-slate-700">{{ $user->name }}</strong>? They will be blocked from using the AI Chatbot.
                                </p>

                                <form action="{{ route('admin.users.ban', $user->id) }}" method="POST" class="mt-4 space-y-4">
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
                    <form action="{{ route('admin.users.unban', $user->id) }}" method="POST">
                        @csrf
                        <button type="submit" onclick="return confirm('Restore normal access for {{ $user->name }}?')"
                                class="inline-flex items-center gap-1.5 h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">lock_open</span>
                            Restore Account Access
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        <!-- User Information Summary Card -->
        <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
                <span class="inline-block w-1.5 h-4 bg-ocean-600 rounded-full"></span>
                Account Overview
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-xs">
                <div>
                    <span class="text-slate-400 font-medium block uppercase tracking-wider">Full Name</span>
                    <strong class="text-slate-900 text-sm font-semibold block mt-0.5">{{ $user->name }}</strong>
                </div>

                <div>
                    <span class="text-slate-400 font-medium block uppercase tracking-wider">Email Address</span>
                    <span class="text-slate-800 font-medium block mt-0.5">{{ $user->email }}</span>
                </div>

                <div>
                    <span class="text-slate-400 font-medium block uppercase tracking-wider">Contact & Address</span>
                    <span class="text-slate-800 font-medium block mt-0.5">{{ $user->phone_number ?? 'No Phone' }}</span>
                    <span class="text-slate-500 block truncate" title="{{ $user->address }}">{{ $user->address ?? 'No Address' }}</span>
                </div>

                <div>
                    <span class="text-slate-400 font-medium block uppercase tracking-wider">Account Status</span>
                    <div class="mt-1 flex items-center gap-2">
                        @if($user->is_banned)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                <span class="material-symbols-outlined text-[14px]">block</span> Banned
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span> Active
                            </span>
                        @endif

                        @if($user->chatbot_flag_count > 0)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-300">
                                <span class="material-symbols-outlined text-[14px]">warning</span> {{ $user->chatbot_flag_count }} Flags
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            @if($user->is_banned && $user->ban_reason)
                <div class="mt-4 p-3 bg-rose-50 border border-rose-200/80 rounded-md text-xs text-rose-800">
                    <strong>Suspension Reason:</strong> {{ $user->ban_reason }}
                </div>
            @endif
        </div>

        <!-- Chatbot Abuse Reports Table -->
        <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-amber-600">history</span>
                    Chatbot Safety Violation History ({{ count($abuseReports) }})
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wider text-slate-500 w-12">#</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wider text-slate-500">Timestamp</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wider text-slate-500">Flagged Query Content</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wider text-slate-500">Detection Category</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wider text-slate-500">Violation Reason</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wider text-slate-500">Review Status</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($abuseReports as $idx => $report)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 text-slate-400 font-medium">
                                    {{ $idx + 1 }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-slate-500">
                                    {{ $report->created_at ? $report->created_at->format('M d, Y H:i A') : 'N/A' }}
                                </td>

                                <td class="px-6 py-4 text-slate-900 font-medium max-w-sm">
                                    <div class="bg-slate-50 p-2.5 rounded border border-slate-200 font-mono text-[11px] text-slate-800 break-words">
                                        "{{ $report->message }}"
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @switch($report->category)
                                        @case('Sexual/Inappropriate')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                🔞 Sexual / Inappropriate
                                            </span>
                                            @break
                                        @case('Sensitive/Prohibited')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                                🚨 Sensitive / Prohibited
                                            </span>
                                            @break
                                        @case('Prompt Injection')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                ⚡ Prompt Injection Hack
                                            </span>
                                            @break
                                        @default
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                ⚠️ {{ $report->category }}
                                            </span>
                                    @endswitch
                                </td>

                                <td class="px-6 py-4 text-slate-600 max-w-xs leading-relaxed">
                                    {{ $report->reason ?? 'System rule violation' }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($report->status === 'pending')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                            <span class="material-symbols-outlined text-[12px]">schedule</span> Pending Review
                                        </span>
                                    @elseif($report->status === 'reviewed_dismissed')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                            <span class="material-symbols-outlined text-[12px]">check</span> Dismissed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                            <span class="material-symbols-outlined text-[12px]">block</span> Account Banned
                                        </span>
                                    @endif

                                    @if($report->admin)
                                        <div class="text-[10px] text-slate-400 mt-0.5">By Admin #{{ $report->admin->id }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($report->status === 'pending')
                                        <form action="{{ route('admin.users.dismiss-report', $report->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" onclick="return confirm('Dismiss this safety warning for {{ $user->name }}?')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium transition-colors cursor-pointer">
                                                <span class="material-symbols-outlined text-[14px]">close</span>
                                                Dismiss Warning
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-slate-400 italic">No action needed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">shield</span>
                                        <p class="text-sm font-semibold text-slate-700">No safety violations logged.</p>
                                        <p class="text-xs text-slate-400 mt-1">This user has a clean chatbot interaction history.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endsection
