@extends('layouts.admin')

@section('title', 'Generate Reports | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body"
         x-data="{ analyzing: false }">

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Booking Reports</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Summarize, export, and AI-analyze booking performance.</p>
            </div>
            <div class="flex items-center gap-2 text-xs font-bold">
                <span class="px-3 py-1.5 rounded-xl bg-sky-50 text-sky-700 border border-sky-200">{{ $fromLabel }} → {{ $toLabel }}</span>
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
        <form method="GET" action="{{ route('admin.reports.index') }}" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-4 mb-6 flex flex-col lg:flex-row lg:items-end gap-3">
            <div>
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">From</label>
                <input type="date" name="from" value="{{ $fromLabel }}"
                       class="px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
            <div>
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">To</label>
                <input type="date" name="to" value="{{ $toLabel }}"
                       class="px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
            <div class="flex-1">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
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
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs transition flex items-center justify-center gap-1 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                Generate
            </button>
            <a href="{{ route('admin.reports.export-pdf', request()->query()) }}"
               class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                Export PDF
            </a>
        </form>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Bookings</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $totalBookings }}</p>
                <p class="text-[11px] text-slate-500 mt-1">{{ $fromLabel }} → {{ $toLabel }}</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Revenue</p>
                <p class="text-2xl font-black text-emerald-700 mt-1">₱{{ number_format($collected, 2) }}</p>
                <p class="text-[11px] text-slate-500 mt-1">Collected (paid) · +₱{{ number_format($estimated, 2) }} awaiting payment</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Avg Booking Value</p>
                <p class="text-2xl font-black text-sky-800 mt-1">₱{{ number_format($avgValue, 2) }}</p>
                <p class="text-[11px] text-slate-500 mt-1">net amount average</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Status Funnel</p>
                <div class="mt-2 space-y-1 text-[11px] font-bold">
                    @foreach ([
                        'pending' => ['bg-amber-100 text-amber-700', 'Pending'],
                        'approved' => ['bg-sky-100 text-sky-700', 'Approved'],
                        'paid' => ['bg-emerald-100 text-emerald-700', 'Paid'],
                        'completed' => ['bg-teal-100 text-teal-700', 'Completed'],
                        'rejected' => ['bg-rose-100 text-rose-700', 'Rejected'],
                        'cancelled' => ['bg-slate-100 text-slate-600', 'Cancelled'],
                        'expired' => ['bg-slate-100 text-slate-500', 'Expired'],
                    ] as $key => [$classes, $label])
                        @if(($statusCounts[$key] ?? 0) > 0)
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-2 py-0.5 rounded-md {{ $classes }}">{{ $label }}</span>
                                <span>{{ $statusCounts[$key] }}</span>
                            </div>
                        @endif
                    @endforeach
                    @if($totalBookings === 0)
                        <p class="text-slate-400">No bookings in range</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- AI Analysis Panel --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sky-600">psychology</span>
                    AI Report Analysis
                </h2>
                <form id="analyze-form" method="POST" action="{{ route('admin.reports.analyze') }}"
                      @submit.prevent="analyzing = true; fetch('{{ route('admin.reports.analyze') }}', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' }, body: new FormData($event.target) }).then(r => r.text()).then(html => { const el = document.getElementById('analysis-panel'); if (el) el.outerHTML = html; }).finally(() => analyzing = false)">
                    @csrf
                    <input type="hidden" name="from" value="{{ $fromLabel }}">
                    <input type="hidden" name="to" value="{{ $toLabel }}">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" :disabled="analyzing"
                            class="px-5 py-2.5 rounded-xl bg-sky-600 text-white font-extrabold text-xs transition-all hover:bg-sky-500 shadow-lg shadow-sky-600/25 flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="material-symbols-outlined text-[16px]" x-show="!analyzing">auto_awesome</span>
                        <x-thinking-orb state="searching" :size="16" light x-show="analyzing" class="shrink-0" aria-hidden="true" />
                        <span x-text="analyzing ? 'Analyzing...' : 'Analyze with AI'"></span>
                    </button>
                </form>
            </div>

            <div id="analysis-panel">
                @include('admin.reports._analysis', ['analysis' => $analysis ?? null])
            </div>
        </div>

        {{-- Daily Breakdown --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 mb-6">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                <span class="material-symbols-outlined text-sky-600">calendar_month</span>
                Daily Breakdown
            </h2>
            @if($daily->isEmpty())
                <p class="text-xs text-slate-400 font-medium">No bookings in this period.</p>
            @else
                @php $maxDaily = max(1, $daily->max('bookings')); @endphp
                <div class="space-y-2">
                    @foreach($daily as $row)
                        <div class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-[11px] font-bold text-slate-600">{{ \Carbon\Carbon::parse($row->day)->format('M j, Y') }}</span>
                            <div class="flex-1 h-6 bg-slate-100 rounded-lg overflow-hidden">
                                <div class="h-full bg-sky-500/80 rounded-lg flex items-center px-2"
                                     style="width: {{ max(4, round(($row->bookings / $maxDaily) * 100)) }}%">
                                    <span class="text-[10px] font-black text-white">{{ $row->bookings }}</span>
                                </div>
                            </div>
                            <span class="w-28 shrink-0 text-right text-[11px] font-bold text-emerald-700">₱{{ number_format($row->collected + $row->estimated, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Detail Table --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="px-6 pt-5 pb-3 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sky-600">receipt_long</span>
                    Booking Details
                </h2>
                <span class="text-[11px] font-bold text-slate-400">{{ $bookings->count() }} shown (latest 150)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3.5 px-6">Booking Code</th>
                            <th class="py-3.5 px-6">Guest</th>
                            <th class="py-3.5 px-6">Items</th>
                            <th class="py-3.5 px-6">Net Amount</th>
                            <th class="py-3.5 px-6">Status</th>
                            <th class="py-3.5 px-6">Payment</th>
                            <th class="py-3.5 px-6">Requested</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($bookings as $booking)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-6">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 font-mono text-xs font-bold text-slate-900 hover:bg-slate-200 transition">
                                        {{ $booking->booking_code }}
                                    </a>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-bold text-slate-900">{{ $booking->contact_name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $booking->contact_email }}</p>
                                </td>
                                <td class="py-4 px-6 font-bold text-slate-900">{{ $booking->items_count }}</td>
                                <td class="py-4 px-6 font-black text-slate-900">₱{{ number_format((float)$booking->net_amount, 2) }}</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border bg-slate-50 border-slate-200 text-slate-600">{{ $booking->status }}</span>
                                </td>
                                <td class="py-4 px-6 font-bold text-slate-700">{{ ucfirst($booking->payment_status) }}</td>
                                <td class="py-4 px-6 text-slate-500">{{ $booking->created_at->format('M j, g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <p class="text-sm font-bold text-slate-500">No bookings found</p>
                                    <p class="text-xs text-slate-400 mt-1">Try adjusting your filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
