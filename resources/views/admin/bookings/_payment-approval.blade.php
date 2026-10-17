<div class="space-y-3 rounded-xl border border-sky-200 bg-sky-50/50 p-5 font-body">
    <h4 class="font-headline text-sm font-bold text-slate-900">Set the initial deadline when approving</h4>
    <p id="{{ $deadlineInputId }}-help" class="text-sm leading-relaxed text-slate-600">Leave blank to use the initial window measured from reservation submission, shortened when necessary to finish before check-in. An override must stay within that window and before any balance deadline. Dated reservations made within {{ app(\App\Services\SystemSettingsService::class)->section('payments')['deposit_min_advance_days'] }} days require full payment; an earlier deadline cannot enable a deposit for them.</p>
    <label for="{{ $deadlineInputId }}" class="block text-sm font-semibold text-slate-900">Initial payment due by (Philippine time)</label>
    <input id="{{ $deadlineInputId }}" type="datetime-local" name="payment_deadline_override" value="{{ old('payment_deadline_override') }}" aria-describedby="{{ $deadlineInputId }}-help" class="w-full rounded-lg border-slate-300">
    @error('payment_deadline_override')<p role="alert" class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
    <p class="text-xs text-slate-600">Activities can stay flexible with dates chosen by the traveler. Balance deadlines use the confirmed trip start or fixed service; without travel dates, the configured balance window starts at approval. The initial deadline must be strictly before the balance deadline.</p>
    <p class="text-sm font-semibold text-slate-700">{{ app(\App\Services\Payment\PaymentEnvironmentResolver::class)->isLive() ? 'Live payments — production booking.' : 'Testing automatically enabled — sandbox providers and simulated manual payments. Do not send real money.' }}</p>
    @if(isset($booking) && $booking->status === 'pending')
        @php
            $approvalPreview = null;
            $approvalPreviewError = null;
            try {
                $approvalPreview = app(\App\Services\Payment\BookingPaymentPolicy::class)->approvalData($booking, old('payment_deadline_override'));
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $approvalPreviewError = collect($exception->errors())->flatten()->first();
            }
        @endphp
        @if($approvalPreviewError)
            <p role="status" class="text-sm text-amber-800">Approval preview: {{ $approvalPreviewError }}</p>
        @elseif($approvalPreview)
            <p class="text-sm text-slate-700">Default approval preview: initial payment due {{ $approvalPreview['payment_deadline']->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time).</p>
            @if(($approvalPreview['payment_policy']['service_time_source'] ?? null) === 'date_only_cutoff')<p class="text-xs text-slate-600">The 9 AM Philippine time anchor is a payment fallback because no confirmed time was stored for the earliest dated service.</p>@endif
            @if($approvalPreview['payment_policy']['deposit_eligible'] ?? false)
                <p class="text-sm text-slate-700">50% deposit: ₱{{ number_format($approvalPreview['deposit_required_cents']/100, 2) }}. Balance due {{ $approvalPreview['balance_due_at']->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time). {{ $approvalPreview['payment_policy']['balance_rule'] === 'after_approval' ? 'Uses the flexible-date balance window after approval.' : 'Uses the confirmed travel/check-in time; 9 AM is the fallback when no time is stored.' }}</p>
            @elseif($approvalPreview['payment_policy']['deposit_explanation'] ?? null)
                <p class="text-sm text-amber-800">{{ $approvalPreview['payment_policy']['deposit_explanation'] }}</p>
            @endif
            @if($approvalPreview['payment_policy']['shortened_window'] ?? false)<p class="text-sm font-semibold text-amber-800">Shortened payment window: full payment must be verified before the first confirmed service/check-in. Approval does not restart the submission clock.</p>@endif
            @if($approvalPreview['payment_policy']['legacy_arrival_accepted'] ?? false)<p class="text-xs text-slate-600">Existing request: its accepted arrival dates are preserved. The new two-day minimum applies to new requests.</p>@endif
        @endif
    @endif
</div>
