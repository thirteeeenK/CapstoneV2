<x-frontend.layout :title="'Payment — ' . $booking->booking_code . ' — SunnyTrips'">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10 font-body">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 mb-1">
                    <a href="{{ route('booking.show', $booking->booking_code) }}" class="hover:text-sky-600">Booking</a>
                    <span>/</span>
                    <span class="text-slate-800">Secure Payment</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight font-headline">
                    Complete Your Payment
                </h1>
            </div>
        </div>

        @if (session('error'))
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-rose-600">error</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-md overflow-hidden">
            <div class="p-6 sm:p-8 border-b border-slate-100 bg-gradient-to-r from-sky-50 to-indigo-50/60">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Booking Reference</p>
                        <p class="text-2xl font-black text-slate-900 font-headline">{{ $booking->booking_code }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Amount Due</p>
                        <p class="text-3xl font-black text-sky-900">₱{{ number_format((float)$booking->net_amount, 2) }}</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                    <span class="material-symbols-outlined text-[16px]">hourglass_bottom</span>
                    <span>Pay before {{ $booking->payment_deadline?->format('M j, Y g:i A') }} — otherwise this booking expires.</span>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <div class="flex items-center gap-3 text-xs text-slate-600 bg-slate-50 border border-slate-200/70 rounded-2xl p-4">
                    <span class="material-symbols-outlined text-emerald-600 shrink-0">lock</span>
                    <span>Payment is processed securely by our payment provider. Once completed, your booking is immediately confirmed.</span>
                </div>

                <form action="{{ route('booking.pay.process', $booking->booking_code) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-sky-600 to-sky-700 text-white font-extrabold text-sm transition-all hover:from-sky-500 hover:to-sky-600 cursor-pointer shadow-lg shadow-sky-600/30 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-lg">lock</span>
                        <span>Proceed to Secure Payment</span>
                    </button>
                </form>

                <p class="text-[11px] text-center text-slate-400 font-medium">
                    You will be redirected to the payment page. Payment can also be retried from your booking page at any time before the deadline.
                </p>

                <div class="text-center">
                    <a href="{{ route('booking.show', $booking->booking_code) }}" class="text-xs font-bold text-sky-600 hover:text-sky-800">
                        ← Back to booking details
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-frontend.layout>
