<section class="my-6 space-y-4 rounded-2xl border border-ocean-200 bg-white p-5 font-body dark:border-ocean-800 dark:bg-slate-900" aria-labelledby="installment-summary-heading">
    <h2 id="installment-summary-heading" class="font-headline text-lg font-bold text-ink-900 dark:text-white">{{ $booking->balance_overdue_at ? 'Payment needs review' : ($booking->payment_status === 'paid' ? 'Fully paid' : ($booking->deposit_secured_at ? 'Reserved · Partially paid' : 'Booking payment')) }}</h2>
    <dl class="grid grid-cols-1 gap-3 text-sm dark:text-slate-200 sm:grid-cols-3">
        <div><dt class="text-slate-500 dark:text-slate-400">Booking total</dt><dd class="font-bold">₱{{ number_format($paymentSummary['due_cents']/100,2) }}</dd></div>
        <div><dt class="text-slate-500 dark:text-slate-400">Verified payments after refunds</dt><dd class="font-bold">₱{{ number_format($paymentSummary['verified_cents']/100,2) }}</dd></div>
        <div><dt class="text-slate-500 dark:text-slate-400">Remaining balance</dt><dd class="font-bold">₱{{ number_format($paymentSummary['remaining_cents']/100,2) }}</dd></div>
    </dl>
    @if($booking->status === 'approved' && $booking->payment_status !== 'paid')
        @if(! ($booking->payment_policy['deposit_eligible'] ?? false))
            <p class="text-sm leading-relaxed text-amber-800 dark:text-amber-200">{{ app(\App\Services\Payment\BookingPaymentPolicy::class)->depositUnavailableReason($booking) }}</p>
        @endif
        @if($booking->payment_environment === 'test')<p class="text-sm font-bold text-amber-800 dark:text-amber-200">Sandbox booking — test payments only.</p>@endif
        @if(($booking->payment_policy['deposit_eligible'] ?? false) && ! $booking->deposit_secured_at)
            <p class="text-sm dark:text-slate-200">Pay in full: ₱{{ number_format($paymentSummary['remaining_cents']/100,2) }}, or deposit: ₱{{ number_format(max(0, $booking->deposit_required_cents - $paymentSummary['verified_cents'])/100,2) }}. Remaining ₱{{ number_format(max(0, $paymentSummary['due_cents'] - $booking->deposit_required_cents)/100,2) }} due {{ $booking->balance_due_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time).</p>
        @endif
        @if($booking->balance_due_at && ($booking->payment_policy['balance_rule'] ?? null))<p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->payment_policy['balance_rule'] === 'after_approval' ? 'Balance deadline: configured window after approval because this booking has flexible travel dates.' : 'Balance deadline: before confirmed travel/check-in. The saved hotel check-in time is used when available; 9 AM is the date-only fallback.' }} Flexible activity dates remain your choice.</p>@endif
        @if(($booking->payment_policy['initial_deadline_basis'] ?? null) === 'submission' && ! $booking->deposit_secured_at)<p class="text-xs text-slate-500 dark:text-slate-400">The initial window starts at reservation submission. Approval does not restart it. {{ ($booking->payment_policy['shortened_window'] ?? false) ? 'This window is shortened so payment is verified before check-in or the first confirmed service.' : '' }}</p>@endif
        @if(($booking->payment_policy['service_time_source'] ?? null) === 'date_only_cutoff')<p class="text-xs text-slate-500 dark:text-slate-400">The 9 AM Philippine time anchor is a payment fallback because no confirmed time was stored for the earliest dated service.</p>@endif
        <p class="text-sm dark:text-slate-200">{{ $booking->deposit_secured_at ? 'Balance due' : 'Initial payment due' }}: {{ ($booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline)?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time).</p>
        @if(auth()->check() && $booking->user_id === auth()->id())
            <a href="{{ route('booking.pay', $booking->booking_code) }}" class="inline-flex min-h-11 items-center rounded-xl bg-ocean-700 px-5 py-3 text-sm font-bold text-white">{{ $booking->deposit_secured_at ? 'Pay remaining balance' : (($booking->payment_policy['deposit_eligible'] ?? false) ? 'Choose full payment or deposit' : 'Continue to full payment') }}</a>
        @endif
    @endif
    @if($paymentSummary['pending_claimed_cents'] > 0)<p role="status" class="text-sm text-amber-800 dark:text-amber-200">₱{{ number_format($paymentSummary['pending_claimed_cents']/100,2) }} is awaiting manual verification and is not yet credited.</p>@endif
    @if($paymentSummary['overpayment_cents'] > 0)<p role="status" class="text-sm text-amber-800 dark:text-amber-200">Overpayment for review: ₱{{ number_format($paymentSummary['overpayment_cents']/100,2) }}.</p>@endif
    @if($paymentTransactions->isNotEmpty())
        <h3 class="font-bold dark:text-white">Payment history</h3>
        <ul class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($paymentTransactions as $transaction)
                <li class="flex flex-wrap items-start justify-between gap-2 py-3 text-sm dark:text-slate-200">
                    <div><p class="font-bold">{{ ucfirst($transaction->gateway) }} · {{ ucfirst($transaction->kind) }} {{ $transaction->livemode ? '' : '· Sandbox' }}</p><p class="break-all text-xs text-slate-500 dark:text-slate-400">{{ $transaction->source_reference }} · {{ $transaction->occurred_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@if(! $transaction->allocated)<p class="text-xs text-amber-800 dark:text-amber-200">Received; awaiting support review. Not credited yet.</p>@endif</div>
                    <p class="font-bold">₱{{ number_format($transaction->amount/100,2) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
