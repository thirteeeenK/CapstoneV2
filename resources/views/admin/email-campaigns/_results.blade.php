{{-- Live-search results partial: campaign table + pagination only.
    Rendered standalone for AJAX requests (X-Requested-With) and inline for full page loads. --}}

<div id="listings-results">
    @if($campaigns->isEmpty())
        <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 text-slate-300" aria-hidden="true">forward_to_inbox</span>
            <p class="text-sm font-bold text-slate-700">No email campaigns match your filters.</p>
            <a href="{{ route('admin.email-campaigns.create') }}" class="mt-3 inline-block text-xs font-bold text-sky-600 hover:underline">Create a campaign</a>
        </div>
    @else
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Email campaigns">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Email campaigns, how they send and their delivery</caption>
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-400">
                            <th class="py-3 px-4 font-bold">Campaign</th>
                            <th class="py-3 px-4 font-bold">Sending</th>
                            <th class="py-3 px-4 font-bold">Delivery</th>
                            <th class="py-3 px-4 font-bold">Status</th>
                            <th class="py-3 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $campaign)
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition align-top">
                                <td class="py-4 px-4">
                                    <a href="{{ route('admin.email-campaigns.show', $campaign) }}" class="font-bold text-slate-900 hover:text-sky-700">{{ $campaign->name }}</a>
                                    <p class="text-[11px] text-slate-500 mt-1 line-clamp-1">{{ $campaign->subject }}</p>
                                </td>
                                <td class="py-4 px-4 text-[11px] text-slate-600">
                                    <p class="font-bold text-slate-700">{{ $campaign->modeLabel() }}</p>
                                    @if($campaign->scheduled_at && $campaign->status === \App\Models\EmailCampaign::STATUS_SCHEDULED)
                                        <p>{{ $campaign->scheduled_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-[11px] text-slate-600">
                                    <p><span class="font-bold text-slate-700">{{ number_format($campaign->sent_count) }}</span> sent</p>
                                    @if($campaign->waiting_count > 0)
                                        <p>{{ number_format($campaign->waiting_count) }} waiting</p>
                                    @endif
                                </td>
                                <td class="py-4 px-4">
                                    @include('admin.email-campaigns._status-badge')
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <a href="{{ route('admin.email-campaigns.show', $campaign) }}" title="View campaign" aria-label="View {{ $campaign->name }}"
                                           class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">visibility</span>
                                        </a>
                                        @if($campaign->isEditable())
                                            <a href="{{ route('admin.email-campaigns.edit', $campaign) }}" title="Edit campaign" aria-label="Edit {{ $campaign->name }}"
                                               class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $campaigns->links() }}
            </div>
        </div>
    @endif
</div>
