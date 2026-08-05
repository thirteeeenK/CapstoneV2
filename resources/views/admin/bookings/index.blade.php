@extends('layouts.admin')

@section('title', 'Bookings | SunnyTrips Admin')

@section('content')
    @php
        $statusStyles = [
            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
            'approved' => 'bg-sky-50 text-sky-700 border-sky-200',
            'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'completed' => 'bg-teal-50 text-teal-700 border-teal-200',
            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
            'cancelled' => 'bg-slate-100 text-slate-600 border-slate-200',
            'expired' => 'bg-slate-100 text-slate-500 border-slate-200',
        ];
    @endphp

    <div class="pb-12 font-body">
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
        <form method="GET" action="{{ route('admin.bookings.index') }}" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-4 mb-6 flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="Search booking code, guest name, or email..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
            </div>
            <select name="status" class="px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
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
            <a href="{{ route('admin.bookings.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-[16px]">refresh</span>
                Reset
            </a>
        </form>

        {{-- Bookings Table --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
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
                            <th class="py-3.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($bookings as $booking)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 font-mono text-xs font-bold text-slate-900">
                                        {{ $booking->booking_code }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <p class="font-bold text-slate-900">{{ $booking->contact_name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $booking->contact_email }}</p>
                                </td>
                                <td class="py-4 px-6 font-bold text-slate-900">{{ $booking->items_count }}</td>
                                <td class="py-4 px-6 font-black text-slate-900">₱{{ number_format((float)$booking->net_amount, 2) }}</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border {{ $statusStyles[$booking->status] ?? $statusStyles['pending'] }}">
                                        {{ $booking->status }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-bold text-slate-700">{{ ucfirst($booking->payment_status) }}</td>
                                <td class="py-4 px-6 text-slate-500">{{ $booking->created_at->format('M j, g:i A') }}</td>
                                <td class="py-4 px-6 text-right">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                       class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-sky-50 text-sky-700 border border-sky-200 font-bold text-xs hover:bg-sky-100 transition cursor-pointer">
                                        <span class="material-symbols-outlined text-[15px]">visibility</span>
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center">
                                    <p class="text-sm font-bold text-slate-500">No bookings found</p>
                                    <p class="text-xs text-slate-400 mt-1">Try adjusting your filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>
@endsection
