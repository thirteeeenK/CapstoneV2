@extends('layouts.admin')

@section('title', $campaign->name.' | Email Campaigns | SunnyTrips Admin')

@use('App\Models\EmailCampaign')

@section('content')
    @php
        $filters = $campaign->audienceFilters();
        $reasonLabels = [
            'unsubscribed' => 'Unsubscribed before it went out', 'emailed_recently' => 'Received another promotion recently',
            'promotion_ended' => 'The linked promotion ended', 'package_unavailable' => 'The package was unpublished',
            'account_restricted' => 'Account restricted', 'account_removed' => 'Account removed', 'campaign_stopped' => 'Campaign stopped',
        ];
        $canSend = ! $campaign->isAutomatic() && in_array($campaign->status, [EmailCampaign::STATUS_DRAFT, EmailCampaign::STATUS_SCHEDULED], true);
        $canStop = in_array($campaign->status, [EmailCampaign::STATUS_SCHEDULED, EmailCampaign::STATUS_SENDING], true);
    @endphp
    <div class="max-w-7xl mx-auto pb-12 font-body">
        <div class="mb-6">
            <a href="{{ route('admin.email-campaigns.index') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                <span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_back</span>
                <span>Back to Email Campaigns</span>
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">{{ $campaign->name }}</h1>
                @include('admin.email-campaigns._status-badge')
            </div>
            <p class="text-xs text-slate-500 mt-1">{{ $campaign->modeLabel() }}</p>
        </div>

        @foreach (['success' => 'bg-emerald-50 border-emerald-200/80 text-emerald-800', 'error' => 'bg-rose-50 border-rose-200/80 text-rose-800'] as $flash => $tone)
            @if (session($flash))
                <div role="status" class="{{ $tone }} border text-sm px-4 py-3 rounded-2xl mb-6">{{ session($flash) }}</div>
            @endif
        @endforeach

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] gap-6">
            <div class="space-y-6 min-w-0">
                <section class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs">
                    <h2 class="font-headline text-base font-bold text-slate-900 mb-4">Actions</h2>
                    <div class="flex flex-wrap gap-3">
                        @if ($campaign->isEditable())
                            <a href="{{ route('admin.email-campaigns.edit', $campaign) }}" class="min-h-11 inline-flex items-center gap-1.5 px-4 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 font-bold text-sm">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>Edit
                            </a>
                        @endif
                        <form method="POST" action="{{ route('admin.email-campaigns.test', $campaign) }}">
                            @csrf
                            <button type="submit" class="min-h-11 inline-flex items-center gap-1.5 px-4 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 font-bold text-sm cursor-pointer">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">forward_to_inbox</span>Send me a test
                            </button>
                        </form>
                        @if ($canSend)
                            <form method="POST" action="{{ route('admin.email-campaigns.send', $campaign) }}"
                                  onsubmit="return confirm(@js('Send this campaign now to '.number_format($audienceCount).' opted-in traveler'.($audienceCount === 1 ? '' : 's').'? This cannot be undone.'))">
                                @csrf
                                <button type="submit" @disabled($audienceCount === 0) class="min-h-11 inline-flex items-center gap-1.5 px-5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>Send now
                                </button>
                            </form>
                        @endif
                        @if ($campaign->isAutomatic())
                            <form method="POST" action="{{ route('admin.email-campaigns.toggle', $campaign) }}">
                                @csrf
                                <button type="submit" class="min-h-11 inline-flex items-center gap-1.5 px-5 rounded-xl font-bold text-sm cursor-pointer {{ $campaign->status === EmailCampaign::STATUS_ACTIVE ? 'border border-rose-300 bg-white text-rose-700 hover:bg-rose-50' : 'bg-sky-600 hover:bg-sky-700 text-white' }}">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $campaign->status === EmailCampaign::STATUS_ACTIVE ? 'pause' : 'bolt' }}</span>
                                    {{ $campaign->status === EmailCampaign::STATUS_ACTIVE ? 'Switch off' : 'Switch on' }}
                                </button>
                            </form>
                        @endif
                        @if ($canStop)
                            <form method="POST" action="{{ route('admin.email-campaigns.cancel', $campaign) }}" onsubmit="return confirm('Stop this campaign? Emails that have not gone out yet are cancelled.')">
                                @csrf
                                <button type="submit" class="min-h-11 inline-flex items-center gap-1.5 px-4 rounded-xl border border-rose-300 bg-white hover:bg-rose-50 text-rose-700 font-bold text-sm cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">cancel_schedule_send</span>{{ $campaign->status === EmailCampaign::STATUS_SCHEDULED ? 'Cancel schedule' : 'Stop sending' }}
                                </button>
                            </form>
                        @endif
                    </div>
                    <p class="mt-3 text-xs text-slate-500">The test goes only to your admin email address. {{ number_format($remainingToday) }} more promotional email(s) can go out today; the rest wait for tomorrow.</p>
                </section>

                <section class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs">
                    <h2 class="font-headline text-base font-bold text-slate-900 mb-4">Details</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Subject</dt><dd class="mt-1 text-slate-800">{{ $campaign->subject }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Sending</dt><dd class="mt-1 text-slate-800">
                            {{ $campaign->modeLabel() }}
                            @if ($campaign->scheduled_at) · {{ $campaign->scheduled_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} @endif
                            @if ($campaign->isAutomatic() && $campaign->activated_at) · on since {{ $campaign->activated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} @endif
                        </dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Audience</dt><dd class="mt-1 text-slate-800">
                            {{ EmailCampaign::SEGMENTS[$filters['segment']] }}{{ $filters['segment'] === 'inactive' ? ' ('.$filters['inactive_days'].' days)' : '' }}
                            @if ($filters['destinations']) · prefers {{ implode(', ', $filters['destinations']) }} @endif
                            @if ($filters['traveler_types']) · {{ implode(', ', $filters['traveler_types']) }} @endif
                            <span class="block text-xs font-bold text-sky-700 mt-0.5">{{ number_format($audienceCount) }} opted-in traveler(s) match right now</span>
                        </dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Button</dt><dd class="mt-1 text-slate-800 break-all">{{ $campaign->cta_label ?: 'Explore now' }} → {{ app(\App\Services\EmailCampaignService::class)->ctaUrl($campaign) }}</dd></div>
                        @if ($campaign->promotion)
                            <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Linked promotion</dt><dd class="mt-1 text-slate-800">{{ $campaign->promotion->name }} ({{ $campaign->promotion->statusLabel() }})</dd></div>
                        @endif
                    </dl>
                </section>

                <section class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="p-6 pb-3">
                        <h2 class="font-headline text-base font-bold text-slate-900">Delivery</h2>
                        <p class="text-xs text-slate-500 mt-1">“Sent” means the mail provider accepted the email, not that it reached the inbox. Opens and clicks are not tracked.</p>
                    </div>
                    @if ($campaign->runs->isEmpty())
                        <p class="px-6 pb-6 text-sm text-slate-500">{{ $campaign->isAutomatic() ? 'Nothing sent yet. It sends the next time its trigger happens while it is on.' : 'Not sent yet.' }}</p>
                    @else
                        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Campaign sends">
                            <table class="w-full text-left text-sm">
                                <caption class="sr-only">Each send of this campaign and its delivery counts</caption>
                                <thead>
                                    <tr class="border-y border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-400">
                                        <th class="py-3 px-4 font-bold">Send</th>
                                        <th class="py-3 px-4 font-bold">Recipients</th>
                                        <th class="py-3 px-4 font-bold">Sent</th>
                                        <th class="py-3 px-4 font-bold">Waiting</th>
                                        <th class="py-3 px-4 font-bold">Skipped</th>
                                        <th class="py-3 px-4 font-bold">Failed / cancelled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($campaign->runs as $run)
                                        <tr class="border-b border-slate-50 align-top">
                                            <td class="py-3 px-4">
                                                <p class="font-bold text-slate-800">{{ $run->context['promotion'] ?? $run->context['package'] ?? ($run->run_key === 'send' ? 'Campaign send' : $run->run_key) }}</p>
                                                <p class="text-[11px] text-slate-500">{{ $run->started_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} · {{ ucfirst($run->status) }}</p>
                                            </td>
                                            <td class="py-3 px-4">{{ number_format($run->recipient_count) }}</td>
                                            <td class="py-3 px-4 font-bold text-emerald-700">{{ number_format($run->sent_count) }}</td>
                                            <td class="py-3 px-4">{{ number_format($run->waiting_count) }}</td>
                                            <td class="py-3 px-4">{{ number_format($run->skipped_count) }}</td>
                                            <td class="py-3 px-4">{{ number_format($run->failed_count) }} / {{ number_format($run->cancelled_count) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($skipReasons->isNotEmpty())
                            <div class="p-6 pt-4">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Why emails were skipped</h3>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                    @foreach ($skipReasons as $reason => $total)
                                        <li>{{ $reasonLabels[$reason] ?? $reason }}: <span class="font-bold">{{ number_format($total) }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </section>
            </div>

            <aside class="min-w-0 xl:sticky xl:top-6 self-start">
                <div class="rounded-3xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h2 class="text-sm font-bold text-slate-900">Email preview</h2>
                        <p class="text-[11px] text-slate-500">Subject: {{ $campaign->subject }}</p>
                    </div>
                    <iframe title="Email preview" srcdoc="{{ $previewHtml }}" sandbox="" class="h-[640px] w-full border-0 bg-slate-50"></iframe>
                </div>
            </aside>
        </div>
    </div>
@endsection
