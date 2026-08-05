<x-frontend.layout :title="'Simulated Payment — ' . $booking->booking_code . ' — SunnyTrips'">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10 font-body">

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-md overflow-hidden">

            {{-- Fake Gateway Header --}}
            <div class="p-6 border-b border-slate-100 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 text-slate-950 font-black text-sm flex items-center justify-center">
                        <span class="material-symbols-outlined">credit_card</span>
                    </div>
                    <div>
                        <p class="text-sm font-black tracking-tight">SunnyTrips Secure Pay <span class="text-[10px] font-bold bg-emerald-500 text-white px-1.5 py-0.5 rounded-md ml-1">TEST MODE</span></p>
                        <p class="text-[10px] text-slate-400 font-semibold">Sandbox Payment Simulator — no real money moves</p>
                    </div>
                </div>
                <span class="material-symbols-outlined text-slate-500">lock</span>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Booking Reference</p>
                        <p class="text-xl font-black text-slate-900 font-headline">{{ $booking->booking_code }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Amount Due</p>
                        <p class="text-3xl font-black text-sky-900">₱{{ number_format((float)$booking->net_amount, 2) }}</p>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200/70 rounded-2xl p-4 text-xs text-slate-600 space-y-1">
                    <p class="font-bold text-slate-900 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">info</span>
                        Sandbox instructions
                    </p>
                    <p>This page simulates a successful payment for the capstone demo.</p>
                    <p>With real Stripe keys configured, customers are instead redirected to Stripe's hosted test checkout (card: <code class="font-mono font-bold">4242 4242 4242 4242</code>).</p>
                </div>

                <form action="{{ route('booking.pay.simulator.confirm', $booking->booking_code) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white font-extrabold text-sm transition-all hover:from-emerald-500 hover:to-teal-600 cursor-pointer shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-lg">verified</span>
                        <span>Simulate Successful Payment</span>
                    </button>
                </form>

                <div class="text-center">
                    <a href="{{ route('booking.show', $booking->booking_code) }}" class="text-xs font-bold text-slate-400 hover:text-slate-600">
                        Cancel and return to booking
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-frontend.layout>
