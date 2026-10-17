<x-frontend.layout :title="'Payment status — '.$booking->booking_code">
    <main class="mx-auto max-w-xl space-y-5 px-4 py-10 font-body">
        <a href="{{ route('booking.pay', $booking->booking_code) }}" class="inline-flex min-h-11 items-center font-bold text-ocean-700 underline dark:text-ocean-300">Back to payment</a>
        <h1 class="font-headline text-2xl font-bold dark:text-white">{{ ucfirst($collection->purpose) }} payment</h1>
        <p class="text-sm dark:text-slate-200">Booking total: ₱{{ number_format($collection->quote_amount/100,2) }}</p>
        <p class="font-headline text-3xl font-bold dark:text-white">Pay now: ₱{{ number_format($collection->amount/100,2) }}</p>
        <p class="text-sm dark:text-slate-200">Status: {{ str_replace('_',' ', $collection->status) }} · {{ $collection->livemode ? 'Live payment' : 'Sandbox payment' }}</p>
        @if(session('error'))<p role="alert" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ session('error') }}</p>@endif
        @if($attempt && $attempt->qr_image && $attempt->status === 'pending' && ! $attempt->expires_at?->isPast())
            <img src="{{ $attempt->qr_image }}" alt="Scan to pay the exact {{ $collection->purpose }} amount" width="400" height="400" class="mx-auto h-auto w-full max-w-sm rounded-2xl bg-white p-4">
            <p class="text-sm dark:text-slate-300">QR expires {{ $attempt->expires_at?->timezone('Asia/Manila')->format('M j, g:i A') }}. Scan with a participating bank or wallet.</p>
            @if(! $collection->livemode && $attempt->test_url)<a href="{{ $attempt->test_url }}" class="inline-flex min-h-11 items-center font-bold text-ocean-700 underline">Open PayMongo test authentication page</a>@endif
        @elseif($collection->gateway === 'stripe' && $collection->checkout_url)
            <a href="{{ $collection->checkout_url }}" class="inline-flex min-h-11 items-center font-bold text-ocean-700 underline dark:text-ocean-300">Open existing Stripe checkout</a>
        @else
            <p role="status" class="text-sm dark:text-slate-300">Resume your existing collection from the payment page to prepare or renew its QR. If you already paid, check its status first.</p>
        @endif
        <form method="POST" action="{{ route('booking.installment.confirm', [$booking->booking_code, $collection->uuid]) }}">@csrf<button class="min-h-11 w-full rounded-xl bg-ocean-700 p-3 font-bold text-white">Check payment status</button></form>
<form method="POST" action="{{ route('booking.installment.cancel', [$booking->booking_code, $collection->uuid]) }}">@csrf<button class="min-h-11 w-full rounded-xl border border-slate-300 p-3 text-sm font-bold dark:text-slate-100">Close unpaid payment before changing method</button></form>
        <p class="break-all text-xs text-slate-500 dark:text-slate-400">Reference: {{ $collection->provider_reference ?? $collection->uuid }}</p>
        <p class="text-xs text-slate-500 dark:text-slate-400">Payment due {{ $collection->due_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time). Do not pay twice.</p>
    </main>
</x-frontend.layout>
