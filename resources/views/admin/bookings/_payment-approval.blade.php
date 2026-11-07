{{-- Payment terms for approval. With $approvalBounds (a pending booking) it shows the computed terms
     and the real deadline limits; without it (proxy booking create) only the optional override. --}}
@php
    $approvalBounds ??= null;
    $terms = $approvalBounds['terms'] ?? null;
    $blockedReason = $approvalBounds['blocked_reason'] ?? null;
    $latest = $approvalBounds['latest'] ?? null;
    $manila = fn ($time) => $time->copy()->timezone('Asia/Manila')->format('M j, Y g:i A');
    $pesos = fn (int $cents) => '₱' . number_format($cents / 100, 2);
    $paymentSettings = app(\App\Services\SystemSettingsService::class)->section('payments');
    $isLive = app(\App\Services\Payment\PaymentEnvironmentResolver::class)->isLive();
    $hasOverride = $errors->has('payment_deadline_override') || filled(old('payment_deadline_override'));
@endphp

<div class="space-y-3 rounded-xl border border-sky-200 bg-sky-50/50 p-5 font-body" x-data="{ customDeadline: @js($hasOverride) }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h4 class="font-headline text-sm font-bold text-slate-900">Payment terms</h4>
        <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-bold {{ $isLive ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}"
              title="{{ $isLive ? 'Live payments — production booking.' : 'Sandbox providers and simulated manual payments. Do not send real money.' }}">
            {{ $isLive ? 'Live payments' : 'Test payments · no real money' }}
        </span>
    </div>

    @if ($blockedReason)
        <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
            <p class="font-bold">This request can no longer be approved.</p>
            <p class="mt-1">{{ $blockedReason }}</p>
        </div>
    @elseif ($terms)
        @php
            $policy = $terms['payment_policy'];
            $total = (int) $policy['quote_amount'];
        @endphp
        @if ($policy['deposit_eligible'] ?? false)
            <p class="text-sm text-slate-800">
                Traveler pays a <strong>50% deposit of {{ $pesos($terms['deposit_required_cents']) }}</strong> by <strong>{{ $manila($terms['payment_deadline']) }}</strong>,
                then the <strong>balance of {{ $pesos($total - $terms['deposit_required_cents']) }}</strong> by <strong>{{ $manila($terms['balance_due_at']) }}</strong> (Philippine time).
            </p>
        @else
            <p class="text-sm text-slate-800">Traveler pays <strong>{{ $pesos($total) }}</strong> in full by <strong>{{ $manila($terms['payment_deadline']) }}</strong> (Philippine time).</p>
            @if ($policy['deposit_explanation'] ?? null)
                <p class="text-xs text-amber-800">{{ $policy['deposit_explanation'] }}</p>
            @endif
        @endif
        <p class="text-xs text-slate-600">Based on the current total; price adjustments below change the amount, not the deadline.
            Approve before <strong>{{ $manila($terms['payment_deadline']) }}</strong> — after that this request can no longer be approved.</p>
    @else
        <p class="text-sm text-slate-700">The traveler gets the standard window: {{ $paymentSettings['initial_window_hours'] }} hours from submission, ending before check-in.</p>
    @endif

    @unless ($blockedReason)
        <div>
            <button type="button" @click="customDeadline = ! customDeadline" :aria-expanded="customDeadline.toString()" aria-controls="{{ $deadlineInputId }}-panel"
                    class="inline-flex min-h-11 items-center gap-1 text-sm font-bold text-sky-700 hover:text-sky-900 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="customDeadline ? 'expand_less' : 'expand_more'">expand_more</span>
                Use an earlier payment deadline (optional)
            </button>
            <div id="{{ $deadlineInputId }}-panel" x-show="customDeadline" x-cloak class="space-y-1.5">
                <label for="{{ $deadlineInputId }}" class="block text-sm font-semibold text-slate-900">Payment due by (Philippine time)</label>
                <input id="{{ $deadlineInputId }}" type="datetime-local" name="payment_deadline_override" value="{{ old('payment_deadline_override') }}"
                       :disabled="! customDeadline"
                       min="{{ now('Asia/Manila')->addMinutes(5)->format('Y-m-d\TH:i') }}"
                       @if ($latest) max="{{ $latest->format('Y-m-d\TH:i') }}" @endif
                       aria-describedby="{{ $deadlineInputId }}-help" class="w-full rounded-lg border-slate-300">
                <p id="{{ $deadlineInputId }}-help" class="text-xs text-slate-600">
                    @if ($latest)
                        Pick a time between now and {{ $manila($latest) }}. The default is already the latest allowed, so only set this to ask for payment sooner.
                    @else
                        Only set this to ask for payment sooner than the standard window. Leave it closed to use the standard window.
                    @endif
                </p>
                @error('payment_deadline_override')<p role="alert" class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
            </div>
        </div>
    @endunless

    <details class="text-xs text-slate-600">
        <summary class="min-h-11 py-2 cursor-pointer font-semibold text-slate-700">How payment deadlines work</summary>
        <ul class="list-disc space-y-1 pl-5 leading-relaxed">
            <li>The payment window starts when the traveler submits the request ({{ $paymentSettings['initial_window_hours'] }} hours) and approval does not restart it. It is shortened when check-in comes sooner.</li>
            <li>Reservations made within {{ $paymentSettings['deposit_min_advance_days'] }} days of the booking date require full payment; others may pay a 50% deposit first.</li>
            <li>Balance deadlines use the confirmed trip start or fixed service date. Without travel dates, the balance window starts at approval.</li>
            <li>Activities can keep flexible dates chosen by the traveler.</li>
        </ul>
        @if ($terms && ($terms['payment_policy']['service_time_source'] ?? null) === 'date_only_cutoff')
            <p class="mt-2">No confirmed time was stored for the earliest dated service, so 9 AM Philippine time is used.</p>
        @endif
        @if ($terms && ($terms['payment_policy']['legacy_arrival_accepted'] ?? false))
            <p class="mt-2">Existing request: its accepted arrival dates are preserved. The two-day minimum applies to new requests.</p>
        @endif
    </details>
</div>
