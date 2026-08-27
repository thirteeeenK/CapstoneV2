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
                                    <a href="{{ route('admin.users.show', $u->id) }}"
                                       class="inline-flex items-center gap-1 h-8 px-3 rounded bg-white border border-ocean-300 hover:border-ocean-600 text-ocean-600 hover:bg-ocean-50 font-medium transition-colors shadow-sm">
                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                        View Logs
                                    </a>

                                    @if($u->ban_level)
                                        <form action="{{ route('admin.users.unban', $u->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" onclick="return confirm('Restore normal access for {{ $u->name }}? This will also clear any IP ban for their last known IP.')"
                                                    class="inline-flex items-center gap-1 h-8 px-3 rounded bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-700 font-medium transition-colors cursor-pointer">
                                                <span class="material-symbols-outlined text-[16px]">lock_open</span>
                                                Restore
                                            </button>
                                        </form>
                                    @endif

                                    @if(!$u->isPermanentlyBanned() && !$u->isTemporarilyBanned())
                                        <div x-data="{ openBanModal: false, level: 'temporary' }">
                                            <button @click="openBanModal = true" type="button"
                                                    class="inline-flex items-center gap-1 h-8 px-3 rounded bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-medium transition-colors cursor-pointer">
                                                <span class="material-symbols-outlined text-[16px]">gavel</span>
                                                Moderate
                                            </button>

                                            <!-- Ban Confirmation Modal -->
                                            <div x-show="openBanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" x-cloak style="display: none;">
                                                <div @click.away="openBanModal = false" class="bg-white rounded-lg p-6 w-full max-w-md border border-slate-200 shadow-lg text-left whitespace-normal">
                                                    <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center mb-4 text-rose-600">
                                                        <span class="material-symbols-outlined text-xl">gavel</span>
                                                    </div>
                                                    <h3 class="text-sm font-bold text-slate-900">Moderate {{ $u->name }}'s Account</h3>
                                                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                                        Warnings are notices only. Temporary suspensions and permanent bans revoke login access.
                                                    </p>

                                                    <form action="{{ route('admin.users.ban', $u->id) }}" method="POST" class="mt-4 space-y-4">
                                                        @csrf
                                                        <div class="space-y-2">
                                                            <label class="block text-xs font-semibold text-slate-700">Action Level</label>

                                                            <label class="flex items-start gap-2.5 p-2.5 border rounded-md cursor-pointer transition-colors"
                                                                   :class="level === 'warning' ? 'border-amber-400 bg-amber-50/60' : 'border-slate-200 hover:bg-slate-50'">
                                                                <input type="radio" name="ban_level" value="warning" x-model="level" class="mt-0.5 accent-amber-600" />
                                                                <span>
                                                                    <span class="block text-xs font-semibold text-slate-800">Warning</span>
                                                                    <span class="block text-[11px] text-slate-500">Records a notice; account stays fully active.</span>
                                                                </span>
                                                            </label>

                                                            <label class="flex items-start gap-2.5 p-2.5 border rounded-md cursor-pointer transition-colors"
                                                                   :class="level === 'temporary' ? 'border-rose-400 bg-rose-50/60' : 'border-slate-200 hover:bg-slate-50'">
                                                                <input type="radio" name="ban_level" value="temporary" x-model="level" checked class="mt-0.5 accent-rose-600" />
                                                                <span>
                                                                    <span class="block text-xs font-semibold text-slate-800">Temporary suspension</span>
                                                                    <span class="block text-[11px] text-slate-500">Blocks login until the expiry date.</span>
                                                                </span>
                                                            </label>

                                                            <label class="flex items-start gap-2.5 p-2.5 border rounded-md cursor-pointer transition-colors"
                                                                   :class="level === 'permanent' ? 'border-rose-600 bg-rose-50/60' : 'border-slate-200 hover:bg-slate-50'">
                                                                <input type="radio" name="ban_level" value="permanent" x-model="level" class="mt-0.5 accent-rose-700" />
                                                                <span>
                                                                    <span class="block text-xs font-semibold text-slate-800">Permanent ban</span>
                                                                    <span class="block text-[11px] text-slate-500">Permanently revokes access.</span>
                                                                </span>
                                                            </label>
                                                        </div>

                                                        <div x-show="level === 'temporary'" x-cloak class="space-y-1">
                                                            <label for="ban_duration_days" class="block text-xs font-semibold text-slate-700">Suspension Duration (days)</label>
                                                            <input id="ban_duration_days" type="number" name="ban_duration_days" min="1" max="365" value="7"
                                                                   x-bind:required="level === 'temporary'"
                                                                   class="w-full text-xs bg-slate-50 border border-slate-300 rounded p-2 text-slate-900 focus:ring-2 focus:ring-rose-500/20 focus:border-rose-600" />
                                                        </div>

                                                        <div class="space-y-1">
                                                            <label class="block text-xs font-semibold text-slate-700">Reason</label>
                                                            <textarea name="ban_reason" rows="3" x-bind:required="level !== 'warning'"
                                                                      placeholder="e.g. Repeated inappropriate queries and rule abuse."
                                                                      class="w-full text-xs bg-slate-50 border border-slate-300 rounded p-2 text-slate-900 focus:ring-2 focus:ring-rose-500/20 focus:border-rose-600"></textarea>
                                                        </div>

                                                        <label class="flex items-start gap-2 py-2 px-2 border border-slate-200 rounded-md bg-slate-50/50 cursor-pointer">
                                                            <input type="checkbox" name="also_ban_ip" value="1" class="mt-0.5 accent-rose-600">
                                                            <span class="text-xs">
                                                                <span class="font-semibold text-slate-800">Also ban IP address</span>
                                                                <span class="text-slate-500"> — {{ $u->consent_ip_address ?? 'auto-detect from sessions' }}</span>
                                                                <span class="block text-[11px] text-slate-500">Same level/duration as account. Warning = notice only; Temporary/Permanent = IP blocked site-wide.</span>
                                                            </span>
                                                        </label>

                                                        <div class="flex gap-3 pt-2">
                                                            <button @click="openBanModal = false" type="button" class="flex-1 h-8 px-3 bg-white border border-slate-300 text-slate-700 text-xs font-medium rounded hover:bg-slate-50 transition-colors">
                                                                Cancel
                                                            </button>
                                                            <button type="submit" class="flex-1 h-8 px-3 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded transition-colors">
                                                                Apply Action
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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