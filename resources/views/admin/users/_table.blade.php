{{-- Live-search results partial: users table + pagination only.
    The filter controls (status tabs + search) live in the wrapper so the input never loses focus on AJAX swaps.
    Rendered standalone for AJAX requests (X-Requested-With) and inline for full page loads. --}}

<div id="listings-results">
    <!-- Users Table -->
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
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

                            <td class="px-3 py-3 font-semibold text-slate-900 group relative cursor-default">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-ocean-50 text-ocean-700 font-bold flex items-center justify-center text-xs shrink-0 border border-ocean-200">
                                        {{ strtoupper(substr($u->name, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-slate-900 truncate max-w-[150px]">{{ $u->name }}</div>
                                        <div class="text-[11px] text-slate-500 font-normal truncate max-w-[150px]">{{ $u->email }}</div>
                                    </div>
                                </div>
                                {{-- Hover Tooltip --}}
                                <div class="pointer-events-none absolute left-12 top-1/2 -translate-y-1/2 z-30 hidden group-hover:flex flex-col bg-slate-900 text-white text-[11px] font-normal rounded-lg px-2.5 py-1.5 shadow-xl border border-slate-700 whitespace-nowrap">
                                    <span class="font-semibold text-white">{{ $u->name }}</span>
                                    <span class="text-slate-300 text-[10px]">{{ $u->email }}</span>
                                </div>
                            </td>

                            <td class="px-3 py-3 text-[11px] text-slate-600 group relative cursor-default">
                                <div><strong class="text-slate-700">Phone:</strong> {{ $u->phone_number ?? 'N/A' }}</div>
                                <div class="text-slate-500 truncate max-w-[160px]">{{ $u->address ?? 'No address set' }}</div>
                                {{-- Hover Tooltip --}}
                                <div class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-30 hidden group-hover:flex flex-col max-w-xs bg-slate-900 text-white text-[11px] font-normal rounded-lg px-2.5 py-1.5 shadow-xl border border-slate-700">
                                    <span class="text-slate-300"><strong class="text-white">Phone:</strong> {{ $u->phone_number ?? 'N/A' }}</span>
                                    <span class="text-slate-200 mt-0.5"><strong class="text-white">Address:</strong> {{ $u->address ?? 'No address set' }}</span>
                                </div>
                            </td>

                            <td class="px-3 py-3 text-[11px] text-slate-500 whitespace-nowrap">
                                {{ $u->created_at ? $u->created_at->format('M d, Y') : 'N/A' }}
                            </td>

                            <td class="px-3 py-3 text-xs whitespace-nowrap">
                                @if($u->isPermanentlyBanned())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-600 text-white border border-rose-700">
                                        <span class="material-symbols-outlined text-[13px]">block</span> Banned
                                    </span>
                                @elseif($u->isTemporarilyBanned())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="material-symbols-outlined text-[13px]">schedule</span> Suspended until {{ $u->ban_expires_at->format('M d, Y') }}
                                    </span>
                                @elseif($u->isWarned())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-300">
                                        <span class="material-symbols-outlined text-[13px]">warning</span> Warned
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
                                    <a href="{{ route('admin.bookings.create', ['user_id' => $u->id]) }}"
                                       class="inline-flex items-center gap-1 h-8 px-2.5 rounded bg-sky-50 border border-sky-200 hover:bg-sky-100 text-sky-700 font-medium transition-colors shadow-xs"
                                       title="Create a booking on behalf of {{ $u->name }}">
                                        <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                                        Book
                                    </a>

                                    <a href="{{ route('admin.users.show', $u->id) }}"
                                       class="inline-flex items-center gap-1 h-8 px-3 rounded bg-white border border-ocean-300 hover:border-ocean-600 text-ocean-600 hover:bg-ocean-50 font-medium transition-colors shadow-sm">
                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                        View Logs
                                    </a>

                                    @if($u->ban_level)
                                        <form action="{{ route('admin.users.unban', $u->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" @if($u->isWarned()) onclick="return confirm('Remove warning for {{ $u->name }}?')" @else onclick="return confirm('Restore normal access for {{ $u->name }}? This will also clear any IP ban for their last known IP.')" @endif
                                                    class="inline-flex items-center gap-1 h-8 px-3 rounded bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-700 font-medium transition-colors cursor-pointer">
                                                <span class="material-symbols-outlined text-[16px]">lock_open</span>
                                                {{ $u->isWarned() ? 'Remove Warning' : 'Restore' }}
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