@extends('layouts.admin')

@section('title', 'Booking ' . $booking->booking_code . ' | SunnyTrips Admin')

@section('content')
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

    <div class="pb-12 font-body space-y-6">

        {{-- Header --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('admin.bookings.index') }}" class="text-xs font-bold text-slate-400 hover:text-sky-600 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">arrow_back</span>
                        Bookings
                    </a>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline flex items-center gap-3">
                    <span class="font-mono">{{ $booking->booking_code }}</span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border {{ $statusStyles[$booking->status] ?? $statusStyles['pending'] }}">
                        {{ $booking->status }}
                    </span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border border-slate-200 bg-slate-50 text-slate-600">
                        payment: {{ $booking->payment_status }}
                    </span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">Requested {{ $booking->created_at->format('M j, Y g:i A') }}{{ $booking->payment_deadline ? ' • Payment deadline: ' . $booking->payment_deadline->format('M j, Y g:i A') : '' }}</p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                @if($booking->status === 'pending')
                    <form action="{{ route('admin.bookings.reject', $booking->id) }}" method="POST"
                          onsubmit="return confirm('Reject booking {{ $booking->booking_code }}? The customer will be notified.')">
                        @csrf
                        <input type="hidden" name="rejection_reason" value="Requested items are unavailable for the selected dates.">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">cancel</span>
                            Reject All
                        </button>
                    </form>
                @elseif($booking->status === 'approved')
                    <form action="{{ route('admin.bookings.cancel', $booking->id) }}" method="POST"
                          onsubmit="return confirm('Cancel booking {{ $booking->booking_code }}?')">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-600 hover:bg-slate-700 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">block</span>
                            Cancel Booking
                        </button>
                    </form>
                    <form action="{{ route('admin.bookings.mark-paid', $booking->id) }}" method="POST" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-xl p-1.5">
                        @csrf
                        <input type="text" name="payment_reference" placeholder="Manual reference (optional)"
                               class="w-44 px-3 py-1.5 rounded-lg border border-emerald-200 text-xs font-bold text-slate-900 bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">payments</span>
                            Mark as Paid
                        </button>
                    </form>
                @elseif($booking->status === 'paid')
                    <form action="{{ route('admin.bookings.mark-completed', $booking->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">flag</span>
                            Mark Completed
                        </button>
                    </form>
                    <form action="{{ route('admin.bookings.mark-refunded', $booking->id) }}" method="POST"
                          onsubmit="return confirm('Refund booking {{ $booking->booking_code }}? Payment status will become refunded.')">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">currency_exchange</span>
                            Mark Refunded
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-rose-600">error</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Cancellation request review (requested) --}}
        @if($booking->status === 'cancellation_requested')
            <div class="bg-orange-50 border border-orange-200 rounded-3xl p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white text-orange-600 border border-orange-200 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-xl">hourglass_top</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-extrabold text-orange-900">Cancellation Requested — awaiting your decision</h3>
                        <p class="text-xs text-orange-800/70 mt-1">Requested {{ $booking->cancellation_requested_at?->format('M j, Y g:i A') }} (was {{ $booking->cancellation_requested_from }}) by {{ $booking->contact_name }} ({{ $booking->contact_email }}).</p>
                        <div class="mt-3 bg-white border border-orange-200 rounded-2xl p-4">
                            <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Customer reason</p>
                            <p class="text-xs text-slate-800 leading-relaxed">"{{ $booking->cancellation_request_reason }}"</p>
                        </div>
                        <p class="text-[11px] text-orange-800/70 mt-2">Rooms/activities for this booking are still held (counted against availability) until you decide.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <form action="{{ route('admin.bookings.cancel-request.approve', $booking->id) }}" method="POST" class="bg-white rounded-2xl border border-emerald-200 p-4 space-y-3" onsubmit="return confirm('Approve cancellation for {{ $booking->booking_code }}? The booking will be cancelled and the customer notified.')">
                        @csrf
                        <label class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 block">Approve — optional message to customer</label>
                        <textarea name="admin_message" rows="3" maxlength="2000" placeholder="e.g. Approved — sorry to see you go! You may rebook anytime."
                                  class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">{{ old('admin_message') }}</textarea>
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">check_circle</span> Approve Cancellation
                        </button>
                    </form>
                    <form action="{{ route('admin.bookings.cancel-request.deny', $booking->id) }}" method="POST" class="bg-white rounded-2xl border border-rose-200 p-4 space-y-3" onsubmit="return confirm('Deny cancellation for {{ $booking->booking_code }}? The booking will remain active and the customer will be notified with your message.')">
                        @csrf
                        <label class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 block">Deny — message to customer <span class="text-rose-600">*</span></label>
                        <textarea name="admin_message" rows="3" required minlength="10" maxlength="2000" placeholder="Explain why the request is denied (at least 10 characters)..."
                                  class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-900 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20">{{ old('admin_message') }}</textarea>
                        @error('admin_message')<p class="text-[11px] text-rose-600">{{ $message }}</p>@enderror
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">block</span> Deny Cancellation
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if($booking->status === 'cancellation_denied')
            <div class="bg-rose-50 border border-rose-200 rounded-3xl p-6">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white text-rose-600 border border-rose-200 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-xl">block</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-extrabold text-rose-900">Cancellation denied — booking remains active</h3>
                        <p class="text-xs text-rose-800/70 mt-1">Decision recorded {{ $booking->updated_at->format('M j, Y g:i A') }}. Rooms/activities are still held.</p>
                        @if($booking->cancellation_request_reason)
                            <div class="mt-3 bg-white border border-rose-200 rounded-2xl p-4">
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Customer's original request</p>
                                <p class="text-xs text-slate-700">"{{ $booking->cancellation_request_reason }}" (requested {{ $booking->cancellation_requested_at?->format('M j, Y g:i A') }})</p>
                            </div>
                        @endif
                        @if($booking->cancellation_reason)
                            <div class="mt-3 bg-white border border-rose-200 rounded-2xl p-4">
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Your denial message</p>
                                <p class="text-xs text-slate-800">"{{ $booking->cancellation_reason }}"</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Contact & Manifest --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sky-600 text-base">person</span>
                    Lead Guest & Contact
                </h3>
                <div class="space-y-1 text-xs font-semibold text-slate-900">
                    <p class="text-base font-extrabold">{{ $booking->contact_name }}</p>
                    <p class="text-slate-600 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">mail</span>{{ $booking->contact_email }}</p>
                    <p class="text-slate-600 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">call</span>{{ $booking->contact_phone }}</p>
                    <p class="text-slate-500 pt-1">Account: {{ $booking->user ? $booking->user->name : 'Guest (no account)' }}</p>
                </div>
                @if($booking->special_requests)
                    <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-3 text-[11px] text-amber-900 font-medium">
                        <span class="font-bold flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">notes</span>Special Requests</span>
                        <p class="mt-1">{{ $booking->special_requests }}</p>
                    </div>
                @endif
                @if($booking->admin_notes)
                    <div class="mt-4 bg-sky-50 border border-sky-200 rounded-xl p-3 text-[11px] text-sky-900 font-medium">
                        <span class="font-bold flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">admin_panel_settings</span>Admin Notes</span>
                        <p class="mt-1">{{ $booking->admin_notes }}</p>
                    </div>
                @endif
                @if($booking->gateway_reference)
                    <p class="mt-3 text-[11px] text-slate-400 font-mono">Gateway: {{ $booking->gateway }} / {{ $booking->gateway_reference }}</p>
                @endif
                @if($booking->payment_method)
                    <p class="mt-1 text-[11px] text-slate-500">Method: <span class="font-bold text-slate-700">{{ $booking->payment_method === 'qrph' ? 'QRPH · GCash / GoTyme' : $booking->payment_method }}</span></p>
                @endif
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-amber-500 text-base">groups</span>
                    Passenger Manifest ({{ count($booking->guest_manifest ?? []) }})
                </h3>
                @if(!empty($booking->guest_manifest) && is_array($booking->guest_manifest))
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($booking->guest_manifest as $idx => $guest)
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-900 truncate">{{ $guest['full_name'] ?? 'Guest ' . ($idx + 1) }}</p>
                                    @if(!empty($guest['special_notes']))
                                        <p class="text-[10px] text-slate-500 truncate">{{ $guest['special_notes'] }}</p>
                                    @endif
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-white border border-slate-200 text-slate-700 shrink-0">
                                    {{ $guest['category'] ?? 'Adult' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-400 font-medium">No guest manifest submitted.</p>
                @endif
            </div>
        </div>

        {{-- Items & Availability Verification --}}
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sky-600">fact_check</span>
                    Items & Availability Verification
                </h3>
                @if($booking->status === 'pending')
                    <span class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1">
                        Uncheck items that are unavailable, adjust quantities, then approve
                    </span>
                @endif
            </div>

            <form action="{{ route('admin.bookings.approve', $booking->id) }}" method="POST">
                @csrf
                <div class="divide-y divide-slate-100">
                    @foreach($booking->items as $item)
                        @php
                            $autoCheck = $roomAvailability[$item->id] ?? null;
                            $isUnavailable = $item->availability_status === 'unavailable';
                            $isPending = $item->availability_status === 'pending';
                        @endphp
                        <div class="py-4 {{ $isUnavailable ? 'opacity-60' : '' }}">
                            <div class="flex items-start gap-4">
                                @if($booking->status === 'pending')
                                    <input type="checkbox"
                                           name="items[{{ $item->id }}][include]"
                                           value="1"
                                           {{ $isUnavailable ? '' : 'checked' }}
                                           class="mt-1.5 w-4 h-4 accent-sky-600 cursor-pointer"
                                           onchange="this.closest('div').parentElement.classList.toggle('opacity-60', !this.checked)">
                                @endif

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-3 flex-wrap">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center shrink-0">
                                                <span class="material-symbols-outlined text-lg">
                                                    {{ $item->item_type === 'room' ? 'hotel' : ($item->item_type === 'activity' ? 'explore' : ($item->item_type === 'package' ? 'card_travel' : 'extension')) }}
                                                </span>
                                            </div>
                                            <div class="min-w-0">
                                                @if($item->hotel_name)
                                                    <p class="text-[10px] font-bold text-sky-700 uppercase tracking-wider">{{ $item->hotel_name }}</p>
                                                @endif
                                                <h4 class="text-sm font-bold text-slate-900 truncate">{{ $item->item_title }}</h4>
                                                <p class="text-[11px] text-slate-500">
                                                    @if($item->check_in_date && $item->check_out_date)
                                                        {{ $item->check_in_date->format('M j, Y') }} – {{ $item->check_out_date->format('M j, Y') }} ({{ $item->nights }} nights) • {{ $item->selected_pax }} pax
                                                    @else
                                                        {{ $item->item_subtitle }} • {{ $item->selected_pax }} pax
                                                    @endif
                                                </p>
                                            </div>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <p class="text-sm font-black text-slate-900">₱{{ number_format((float)$item->subtotal, 2) }}</p>
                                            <p class="text-[10px] text-slate-400">₱{{ number_format((float)$item->unit_price, 2) }} / unit</p>
                                        </div>
                                    </div>

                                    {{-- Auto availability check result --}}
                                    @if($autoCheck)
                                        <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold border
                                            {{ $autoCheck['available'] ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700' }}">
                                            <span class="material-symbols-outlined text-[13px]">{{ $autoCheck['available'] ? 'check_circle' : 'error' }}</span>
                                            <span>Auto-check: {{ $autoCheck['available'] ? $autoCheck['remaining'] . ' of ' . $autoCheck['total_rooms'] . ' rooms free' : 'Sold out (' . $autoCheck['booked_count'] . ' of ' . $autoCheck['total_rooms'] . ' rooms booked)' }}</span>
                                        </div>
                                    @endif

                                    @if($booking->status === 'pending')
                                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Quantity</label>
                                                <input type="number" name="items[{{ $item->id }}][quantity]" min="1" max="50" value="{{ $item->quantity }}"
                                                       class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Admin note (for unavailable items)</label>
                                                <input type="text" name="items[{{ $item->id }}][admin_note]" value="{{ $item->admin_note }}"
                                                       placeholder="e.g. Activity sold out for this date"
                                                       class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                            </div>
                                        </div>
                                    @elseif($item->admin_note)
                                        <p class="mt-2 text-[11px] text-slate-500 font-medium">Note: {{ $item->admin_note }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Totals + Approve --}}
                <div class="mt-4 bg-slate-50 rounded-2xl p-5 border border-slate-200/80 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-slate-600 font-medium">
                        <span>Item Subtotal</span>
                        <span class="font-black text-slate-900">₱{{ number_format((float)$booking->total_amount, 2) }}</span>
                    </div>
                    @if((float)$booking->discount_amount > 0)
                        <div class="flex items-center justify-between text-emerald-700 font-semibold">
                            <span>Passenger Discounts</span>
                            <span>-₱{{ number_format((float)$booking->discount_amount, 2) }}</span>
                        </div>
                    @endif
                    @if((float)$booking->tax_amount > 0)
                        <div class="flex items-center justify-between text-amber-800 font-semibold">
                            <span>Foreign Tourist Surcharges</span>
                            <span>+₱{{ number_format((float)$booking->tax_amount, 2) }}</span>
                        </div>
                    @endif
                    @if((float)$booking->admin_discount_amount > 0)
                        <div class="flex items-center justify-between text-emerald-700 font-semibold">
                            <span>Admin Discount</span>
                            <span>-₱{{ number_format((float)$booking->admin_discount_amount, 2) }}</span>
                        </div>
                    @endif
                    @if((float)$booking->admin_surcharge_amount > 0)
                        <div class="flex items-center justify-between text-amber-800 font-semibold">
                            <span>Additional Amount</span>
                            <span>+₱{{ number_format((float)$booking->admin_surcharge_amount, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between text-sm font-black text-slate-900 pt-2 border-t border-slate-200">
                        <span>Net Amount Charged After Approval</span>
                        <span class="text-base text-sky-900">₱{{ number_format((float)$booking->net_amount, 2) }}</span>
                    </div>

                    @if($booking->status === 'pending')
                        <div class="pt-3 border-t border-slate-200">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Admin discount (−)</label>
                                    <input type="number" name="admin_discount_amount" min="0" step="0.01" value="{{ old('admin_discount_amount', '') }}"
                                           placeholder="0.00"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                </div>
                                <div>
                                    <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Additional amount (+)</label>
                                    <input type="number" name="admin_surcharge_amount" min="0" step="0.01" value="{{ old('admin_surcharge_amount', '') }}"
                                           placeholder="0.00"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Reason for adjustment (shown to customer)</label>
                                <textarea name="price_adjustment_reason" rows="2" placeholder="e.g. Applied a ₱500 courtesy discount because the room was unavailable on the first night."
                                          class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">{{ old('price_adjustment_reason', '') }}</textarea>
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium mt-1.5">A reason is required when either amount is set. Adjustments are locked in at approval.</p>
                        </div>
                        <div class="pt-3">
                            <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Admin notes to customer (optional)</label>
                            <textarea name="admin_notes" rows="2" placeholder="e.g. Room upgraded to a higher floor at no extra cost."
                                      class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20"></textarea>
                        </div>
                        <button type="submit" class="mt-4 w-full py-3.5 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white font-extrabold text-xs transition-all hover:from-emerald-500 hover:to-teal-600 cursor-pointer shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">verified</span>
                            Approve Booking & Open 48-Hour Payment Window
                        </button>
                        <p class="text-[10px] text-center text-slate-400 font-medium pt-2">
                            Approving sends the customer an email with a secure payment link.
                        </p>
                    @endif
                </div>
            </form>
        </div>

        {{-- Status History --}}
        @if($booking->history->isNotEmpty())
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                    <span class="material-symbols-outlined text-sky-600">history</span>
                    Booking Status History
                </h3>
                <ol class="relative border-l border-slate-200 ml-3 space-y-4">
                    @foreach($booking->history as $event)
                        <li class="ml-5">
                            <span class="absolute -left-[7px] mt-1 w-3 h-3 rounded-full border-2 border-white bg-sky-500 shadow"></span>
                            <p class="text-xs font-bold text-slate-900">
                                {{ ucfirst(str_replace('_', ' ', $event->to_status)) }}
                                @if($event->from_status)
                                    <span class="text-slate-400 font-medium">(was {{ $event->from_status }})</span>
                                @endif
                            </p>
                            @if($event->note)
                                <p class="text-[11px] text-slate-500">{{ $event->note }}</p>
                            @endif
                            <p class="text-[10px] text-slate-400 font-semibold">{{ $event->created_at->format('M j, Y g:i A') }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

    </div>
@endsection
