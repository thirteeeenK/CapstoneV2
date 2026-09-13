@php
    $statusStyles = [
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'approved' => 'bg-sky-50 text-sky-700 border-sky-200',
        'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'completed' => 'bg-teal-50 text-teal-700 border-teal-200',
        'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
        'cancelled' => 'bg-slate-100 text-slate-600 border-slate-200',
        'cancellation_requested' => 'bg-orange-50 text-orange-700 border-orange-200',
        'cancellation_denied' => 'bg-rose-50 text-rose-700 border-rose-200',
        'expired' => 'bg-slate-100 text-slate-500 border-slate-200',
    ];
@endphp

<div id="bookings-table" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-700">
            <thead class="bg-slate-100/70 uppercase text-[10px] font-extrabold text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="py-3.5 px-6 w-16">#</th>
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
                        <td class="py-4 px-6 text-slate-400 font-black">{{ $bookings->firstItem() + $loop->index }}</td>
                        <td class="py-4 px-6">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 font-mono text-xs font-bold text-slate-900">
                                {{ $booking->booking_code }}
                            </span>
                            @if($booking->isAgentBooked())
                                <span class="block mt-1 text-[9px] font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded w-fit">
                                    Agent Booked
                                </span>
                            @endif
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
                        <td colspan="9" class="py-12 text-center">
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
