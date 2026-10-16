<x-frontend.layout :title="'QR Ph payment — ' . $booking->booking_code . ' — SunnyTrips'">
    <div class="mx-auto max-w-2xl px-4 py-10 font-body sm:px-6">
        <a href="{{ route('booking.show', $booking->booking_code) }}" class="mb-6 inline-flex min-h-11 items-center text-sm font-semibold text-ocean-700 underline dark:text-ocean-300">Back to booking</a>
        @if (session('error'))
            <div role="alert" class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">{{ session('error') }}</div>
        @endif

        <section class="overflow-hidden rounded-3xl border border-sand-200 bg-white dark:border-slate-700 dark:bg-slate-900" aria-labelledby="payment-title">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-sand-200 p-6 dark:border-slate-700 sm:p-8">
                <div>
                    <p class="mb-2 text-xs font-bold uppercase tracking-wider text-ocean-700 dark:text-ocean-300">{{ $attempt->livemode ? 'Live payment' : 'Test mode' }}</p>
                    <h1 id="payment-title" class="font-headline text-2xl font-bold text-ink-900 dark:text-white">{{ $attempt->livemode ? 'PayMongo QR Ph' : 'QR Ph Sandbox' }}</h1>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $booking->booking_code }}</p>
                </div>
                <div>
                    <p class="text-sm text-slate-600 dark:text-slate-300">Amount due</p>
                    <p class="font-headline text-3xl font-bold text-ink-900 dark:text-white">₱{{ number_format($attempt->amount / 100, 2) }}</p>
                </div>
            </header>

            <div class="space-y-6 p-6 sm:p-8">
                @if ($attempt->status === 'review_required')
                    <p role="alert" class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">A payment was received and needs support review. Please do not pay again.</p>
                @elseif ($qrExpired)
                    <p role="status" class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">This QR code is {{ in_array($attempt->status, ['failed', 'cancelled'], true) ? $attempt->status : 'expired' }}. If you already paid, check the payment status before trying again.</p>
                @else
                    @if (! $attempt->livemode)
                        <p role="status" class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">Sandbox payment: use the test authentication link below. Do not scan or pay this QR code with a banking app.</p>
                    @endif
                    <div class="flex flex-col items-center gap-4">
                        <div class="w-full max-w-[17rem] rounded-2xl border border-sand-200 bg-white p-4">
                            <img src="{{ $attempt->qr_image }}" alt="QR Ph code for payment of ₱{{ number_format($attempt->amount / 100, 2) }}" class="h-auto w-full" width="240" height="240">
                        </div>
                        @if ($attempt->livemode)
                            <p class="max-w-sm text-center text-sm leading-relaxed text-slate-600 dark:text-slate-300">Scan with a participating bank or wallet app. Confirm the merchant and exact amount before paying.</p>
                        @elseif ($attempt->test_url)
                            <a href="{{ $attempt->test_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl border border-ocean-700 px-4 py-2 text-sm font-bold text-ocean-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 dark:border-ocean-300 dark:text-ocean-300">Open PayMongo test authentication page</a>
                        @endif
                        <p class="text-center text-sm text-slate-600 dark:text-slate-300">QR expires {{ $attempt->expires_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time).</p>
                    </div>
                @endif

                <p role="status" class="text-sm text-slate-700 dark:text-slate-200">Payment status: <strong>{{ str_replace('_', ' ', ucfirst($attempt->status)) }}</strong>. Your booking is confirmed only after payment verification.</p>

                <form action="{{ route('booking.pay.'.$gateway.'.confirm', $booking->booking_code) }}" method="POST">
                    @csrf
                    <button type="submit" class="flex min-h-11 w-full items-center justify-center rounded-2xl bg-ocean-700 px-6 py-3 text-sm font-bold text-white transition hover:bg-ocean-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ocean-700">Check payment status</button>
                    <p class="mt-2 text-center text-sm text-slate-600 dark:text-slate-300">If payment is pending, wait a moment and check again. Do not pay twice.</p>
                </form>

                @if ($qrExpired && $attempt->status !== 'review_required' && ! $booking->isPaymentDeadlinePassed())
                    <form action="{{ route('booking.pay.'.$gateway, $booking->booking_code) }}" method="POST">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-2xl border border-ocean-700 px-6 py-3 text-sm font-bold text-ocean-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 dark:border-ocean-300 dark:text-ocean-300">Create a new QR code</button>
                    </form>
                @endif

                <dl class="space-y-2 break-all border-t border-sand-200 pt-4 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    <div><dt class="font-semibold">Payment reference</dt><dd>{{ $attempt->intent_id }}</dd></div>
                    <div><dt class="font-semibold">Booking payment deadline</dt><dd>{{ $booking->payment_deadline?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time)</dd></div>
                </dl>
            </div>
        </section>
    </div>
</x-frontend.layout>
