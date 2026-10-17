<x-frontend.layout :title="'Payment — '.$booking->booking_code">
    <main class="mx-auto max-w-2xl space-y-6 px-4 py-10 font-body sm:px-6">
        <a href="{{ route('booking.show', $booking->booking_code) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-ocean-700 underline dark:text-ocean-300">Back to booking</a>
        <h1 class="font-headline text-2xl font-bold text-ink-900 dark:text-white">Choose how to pay</h1>
        @if(session('error'))<p role="alert" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ session('error') }}</p>@endif
        @if($errors->any())<div role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif
        <section class="space-y-4 rounded-2xl border border-sand-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-8">
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $booking->booking_code }} · Booking total ₱{{ number_format($summary['due_cents']/100, 2) }}</p>
            <p class="font-headline text-xl font-bold dark:text-white">Remaining balance: ₱{{ number_format($summary['remaining_cents']/100, 2) }}</p>
            @if($booking->balance_due_at && ($booking->payment_policy['balance_rule'] ?? null))<p class="text-sm text-slate-600 dark:text-slate-300">{{ $booking->payment_policy['balance_rule'] === 'after_approval' ? 'Your balance deadline uses the configured window after approval; your activities can stay undated.' : 'Your balance deadline is based on confirmed travel dates. Flexible activity schedules do not change it.' }}</p>@endif
            <p class="text-sm text-slate-600 dark:text-slate-300">Pay before {{ ($booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline)?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time).</p>
            @if(($booking->payment_policy['service_time_source'] ?? null) === 'date_only_cutoff')<p class="text-xs text-slate-500 dark:text-slate-400">The 9 AM Philippine time anchor is a payment fallback because no confirmed time was stored for the earliest dated service.</p>@endif
            @if($booking->status !== 'approved' || $booking->payment_status === 'paid')
                <p role="status" class="text-sm dark:text-slate-200">This booking is not awaiting a payment.</p>
            @elseif($booking->isPaymentDeadlinePassed() || $booking->balance_overdue_at)
                <p role="alert" class="text-sm text-amber-800 dark:text-amber-200">The payment deadline has passed. Contact support to review your reservation before paying.</p>
            @elseif($active)
                <p role="status" class="text-sm dark:text-slate-200">An existing {{ $active->purpose }} payment of ₱{{ number_format($active->amount/100, 2) }} is {{ str_replace('_', ' ', $active->status) }}. Continue it before selecting another amount or method.</p>
                <a href="{{ route('booking.installment.show', [$booking->booking_code, $active->uuid]) }}" class="inline-flex min-h-11 items-center font-bold text-ocean-700 underline dark:text-ocean-300">View existing payment</a>
                @if(in_array($active->status, ['creating','pending'], true))
                    <form method="POST" action="{{ route('booking.installment.resume', [$booking->booking_code, $active->uuid]) }}">@csrf
                        <button class="min-h-11 w-full rounded-xl bg-ocean-700 p-3 font-bold text-white">Resume existing payment</button>
                    </form>
                @endif
            @else
                <form method="POST" action="{{ route('booking.installment.store', $booking->booking_code) }}" class="space-y-6">
                    @csrf
                    <fieldset class="space-y-3">
                        <legend class="mb-2 font-bold dark:text-white">1. Amount to pay</legend>
                        @if($booking->deposit_secured_at)
                            <label class="flex min-h-11 items-start gap-3 rounded-xl border border-ocean-300 p-4 dark:text-slate-100"><input type="radio" name="purpose" value="balance" checked required class="mt-1 text-ocean-700"><span>Pay remaining balance <strong>₱{{ number_format($summary['remaining_cents']/100, 2) }}</strong></span></label>
                        @else
                            <label class="flex min-h-11 items-start gap-3 rounded-xl border border-slate-300 p-4 dark:text-slate-100"><input type="radio" name="purpose" value="full" checked required class="mt-1 text-ocean-700"><span>Pay in full <strong>₱{{ number_format($summary['remaining_cents']/100, 2) }}</strong></span></label>
                            @if($booking->payment_policy['deposit_eligible'] ?? false)
                                <label class="flex min-h-11 items-start gap-3 rounded-xl border border-slate-300 p-4 dark:text-slate-100"><input type="radio" name="purpose" value="deposit" required class="mt-1 text-ocean-700"><span>Pay 50% to reserve <strong>₱{{ number_format(max(0, $booking->deposit_required_cents - $summary['verified_cents'])/100, 2) }}</strong><span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Remaining balance due {{ $booking->balance_due_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time). Your reservation is secured after payment verification.</span></span></label>
                            @else
                                <p class="text-sm leading-relaxed text-amber-800 dark:text-amber-200">{{ app(\App\Services\Payment\BookingPaymentPolicy::class)->depositUnavailableReason($booking) }}</p>
                            @endif
                        @endif
                    </fieldset>
                    <fieldset class="space-y-3">
                        <legend class="mb-2 font-bold dark:text-white">2. Payment method</legend>
                        @foreach(app(\App\Services\Payment\InstallmentPaymentService::class)->gatewayOptions($booking) as $method => $option)
                            <label class="flex min-h-11 items-start gap-3 rounded-xl border border-slate-300 px-4 py-3 dark:text-slate-100 {{ $option['enabled'] ? 'cursor-pointer' : 'cursor-not-allowed bg-slate-50 dark:bg-slate-800' }}">
                                <input type="radio" name="gateway" value="{{ $method }}" required @disabled(! $option['enabled']) @if($option['reason']) aria-describedby="gateway-{{ $method }}-reason" @endif class="mt-1 text-ocean-700">
                                <span class="text-sm">{{ $option['label'] }}@if($option['reason'])<span id="gateway-{{ $method }}-reason" class="mt-1 block text-xs text-slate-600 dark:text-slate-300">{{ $option['reason'] }}</span>@endif</span>
                            </label>
                        @endforeach
                    </fieldset>
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $booking->payment_environment === 'test' ? 'Sandbox booking: use test payment details only. These transactions do not count as real collections.' : 'Live booking: use PayMongo, verified manual GoTyme, or Stripe configured with live keys. Sandbox methods require a separate test booking.' }}</p>
                    <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">PayMongo and Stripe confirm payments automatically. Manual GoTyme payments require staff verification. Contact support for cancellation and refund terms before paying.</p>
                    <button type="submit" class="min-h-11 w-full rounded-xl bg-ocean-700 p-3 font-bold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ocean-700">Continue to payment</button>
                </form>
            @endif
        </section>
    </main>
</x-frontend.layout>
