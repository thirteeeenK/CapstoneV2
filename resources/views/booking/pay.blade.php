<x-frontend.layout :title="'Payment — ' . $booking->booking_code . ' — SunnyTrips'">
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-10 font-body sm:px-6">
        <a href="{{ route('booking.show', $booking->booking_code) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-ocean-700 underline dark:text-ocean-300">Back to booking</a>
        <h1 class="font-headline text-2xl font-bold text-ink-900 dark:text-white">Complete your payment</h1>
        @if (session('error'))
            <p role="alert" class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">{{ session('error') }}</p>
        @endif
        <section class="space-y-6 rounded-3xl border border-sand-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900 sm:p-8">
            <div>
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ $booking->booking_code }}</p>
                <p class="mt-2 font-headline text-3xl font-bold text-ink-900 dark:text-white">₱{{ number_format((float) $booking->net_amount, 2) }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Pay before {{ $booking->payment_deadline?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time).</p>
            </div>
            @if ($gateways['paymongo'])
                <div class="space-y-3">
                    <h2 class="font-headline text-lg font-bold text-ink-900 dark:text-white">PayMongo QR Ph — Live</h2>
                    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">Pay the exact amount with a participating bank or wallet. Your booking is confirmed after PayMongo verifies payment.</p>
                    <form action="{{ route('booking.pay.paymongo', $booking->booking_code) }}" method="POST">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-2xl bg-ocean-700 px-6 py-3 text-sm font-bold text-white hover:bg-ocean-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ocean-700">Pay with PayMongo QR Ph</button>
                    </form>
                </div>
            @else
                <p role="status" class="text-sm text-slate-700 dark:text-slate-200">Live PayMongo QR Ph is temporarily unavailable. Please choose another payment method.</p>
            @endif
            @if ($gateways['qrph'])
                <div class="space-y-3">
                    <h2 class="font-headline text-lg font-bold text-ink-900 dark:text-white">QR Ph Sandbox</h2>
                    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">Test the QR Ph flow using PayMongo's test authentication page. Do not scan or pay this QR code with a banking app.</p>
                    <form action="{{ route('booking.pay.qrph', $booking->booking_code) }}" method="POST">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-2xl border border-ocean-700 px-6 py-3 text-sm font-bold text-ocean-700 dark:text-ocean-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ocean-700">Open QR Ph Sandbox</button>
                    </form>
                </div>
            @else
                <p role="status" class="text-sm text-slate-700 dark:text-slate-200">QR Ph payments are temporarily unavailable. Please contact support.</p>
            @endif
            @if ($gateways['card'])
                <form action="{{ route('booking.pay.process', $booking->booking_code) }}" method="POST">
                    @csrf
                    <input type="hidden" name="gateway" value="card">
                    <button type="submit" class="min-h-11 w-full rounded-xl border border-ocean-700 p-3 text-sm font-bold text-ocean-700 dark:text-ocean-300">Pay with Card</button>
                </form>
            @endif
            @if ($gateways['gotyme'])
                <form action="{{ route('booking.pay.gotyme', $booking->booking_code) }}" method="POST">
                    @csrf
                    <button type="submit" class="min-h-11 w-full rounded-xl border border-ocean-700 p-3 text-sm font-bold text-ocean-700 dark:text-ocean-300">Pay to GoTyme QR</button>
                </form>
            @endif
        </section>
    </div>
</x-frontend.layout>
