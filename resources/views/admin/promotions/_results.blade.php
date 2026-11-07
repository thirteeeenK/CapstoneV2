{{-- Live-search results partial: campaign table + pagination only.
    Rendered standalone for AJAX requests (X-Requested-With) and inline for full page loads. --}}

<div id="listings-results">
    @if($promotions->isEmpty())
        <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">campaign</span>
            <p class="text-sm font-bold text-slate-700">No Lucky campaigns match your filters.</p>
            <a href="{{ route('admin.promotions.create') }}" class="mt-3 inline-block text-xs font-bold text-sky-600 hover:underline">Create a campaign</a>
        </div>
    @else
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Lucky promotion campaigns">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Lucky promotion campaigns, their discount, validity and redemptions</caption>
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-400">
                            <th class="py-3 px-4 font-bold">Campaign</th>
                            <th class="py-3 px-4 font-bold">Discount</th>
                            <th class="py-3 px-4 font-bold">Validity (PH time)</th>
                            <th class="py-3 px-4 font-bold">Redemptions</th>
                            <th class="py-3 px-4 font-bold">Status</th>
                            <th class="py-3 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($promotions as $promotion)
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition align-top">
                                <td class="py-4 px-4">
                                    <p class="font-bold text-slate-900">{{ $promotion->name }}</p>
                                    <p class="text-[11px] text-slate-500 mt-1">{{ $promotion->win_percent }}% win chance</p>
                                </td>
                                <td class="py-4 px-4 text-xs text-slate-700">
                                    <p class="font-bold">{{ $promotion->discountLabel() }}</p>
                                    @if($promotion->min_eligible_cents > 0)
                                        <p class="text-slate-500">Min. ₱{{ number_format($promotion->min_eligible_cents / 100, 2) }}</p>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-[11px] text-slate-600 space-y-0.5">
                                    <p>Starts: {{ $promotion->redeem_starts_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'right away' }}</p>
                                    <p>Expires: {{ $promotion->expiresAt()?->copy()->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'no expiry' }}</p>
                                    @if($promotion->travel_starts_on || $promotion->travel_ends_on)
                                        <p>Travel: {{ $promotion->travel_starts_on?->format('M j, Y') ?? 'any' }} → {{ $promotion->travel_ends_on?->format('M j, Y') ?? 'any' }}</p>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-[11px] text-slate-600 space-y-0.5">
                                    <p class="font-bold text-slate-700">{{ $promotion->usageSummary() }}</p>
                                    <p>{{ $promotion->per_traveler_limit }} {{ $promotion->per_traveler_limit === 1 ? 'use' : 'uses' }} per user</p>
                                </td>
                                <td class="py-4 px-4">
                                    @include('admin.promotions._status-badge')
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <form action="{{ route('admin.promotions.toggle', $promotion) }}" method="POST">
                                            @csrf
                                            <button type="submit" title="{{ $promotion->is_active ? 'Pause campaign' : 'Activate campaign' }}" aria-label="{{ $promotion->is_active ? 'Pause campaign' : 'Activate campaign' }}"
                                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $promotion->is_active ? 'pause' : 'play_arrow' }}</span>
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.promotions.edit', $promotion) }}" title="Edit campaign" aria-label="Edit campaign"
                                           class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $promotions->links() }}
            </div>
        </div>
    @endif
</div>
