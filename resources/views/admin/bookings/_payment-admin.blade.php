<section class="mb-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 font-body dark:border-slate-700 dark:bg-slate-900" aria-label="Payment administration">
    @foreach($paymentTransactions->where('kind','receipt')->where('allocated',false) as $unallocated)
        <form method="POST" action="{{ route('admin.bookings.payment-allocation', $booking->id) }}" class="space-y-3 rounded-xl border border-amber-300 p-4">@csrf
            <h3 class="font-bold">Review received payment: {{ $unallocated->source_reference }}</h3>
            <p class="text-xs text-slate-500">Verify the real payment and resolve the booking deadline before allocating it. Cancelled reservations are not reopened automatically.</p>
            <input type="hidden" name="receipt_id" value="{{ $unallocated->id }}">
            <label for="allocate-{{ $unallocated->id }}" class="block text-sm font-semibold">Review reason</label>
            <input id="allocate-{{ $unallocated->id }}" name="reason" required minlength="5" maxlength="500" class="w-full rounded-lg border-slate-300 dark:bg-slate-800">
            <button class="min-h-11 rounded-xl bg-ocean-700 px-5 py-3 text-sm font-bold text-white">Allocate verified receipt</button>
        </form>
    @endforeach
    @if($booking->status === 'approved')
        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <summary class="min-h-11 cursor-pointer font-bold">Extend payment deadline after approval</summary>
            <form method="POST" action="{{ route('admin.bookings.payment-deadline', $booking->id) }}" class="mt-4 space-y-3">@csrf
                <p class="text-sm text-slate-600 dark:text-slate-300">Use this only to give an already approved customer more time. You are extending the {{ $booking->deposit_secured_at ? 'remaining-balance deadline after their deposit' : 'initial payment deadline for their full payment or eligible deposit' }}. This does not change deposit eligibility or trip dates.</p>
                <p class="text-sm text-slate-600 dark:text-slate-300">Current deadline: {{ ($booking->deposit_secured_at ? $booking->balance_due_at : $booking->payment_deadline)?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Philippine time).</p>
                <label for="extend-deadline" class="block text-sm font-semibold">New {{ $booking->deposit_secured_at ? 'remaining-balance' : 'initial payment' }} deadline (Philippine time)</label>
                <input id="extend-deadline" type="datetime-local" name="deadline" required class="w-full rounded-lg border-slate-300 dark:bg-slate-800">
                <label for="extend-reason" class="block text-sm font-semibold">Reason</label>
                <input id="extend-reason" name="reason" required minlength="5" maxlength="500" class="w-full rounded-lg border-slate-300 dark:bg-slate-800">
                <button class="min-h-11 rounded-xl bg-ocean-700 px-5 py-3 text-sm font-bold text-white">Extend and notify customer</button>
            </form>
        </details>
        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <summary class="min-h-11 cursor-pointer font-bold">Record money already received offline</summary>
            <form method="POST" action="{{ route('admin.bookings.mark-paid', $booking->id) }}" class="mt-4 space-y-3">@csrf
                <p class="text-xs text-slate-500">This is a separate payment-recording action; it does not extend a deadline. Record only money you have verified. An unresolved online payment blocks a conflicting receipt.</p>
                @foreach(['verified_amount' => ['Amount actually received', 'number'], 'payment_reference' => ['Receipt reference', 'text'], 'admin_note' => ['Verification note', 'text']] as $field => [$label,$type])
                    <label for="offline-{{ $field }}" class="block text-sm font-semibold">{{ $label }}</label><input id="offline-{{ $field }}" name="{{ $field }}" type="{{ $type }}" required maxlength="{{ $field === 'admin_note' ? 500 : 100 }}" @if($type === 'number') min="0.01" step="0.01" @endif class="w-full rounded-lg border-slate-300 dark:bg-slate-800">
                @endforeach
                <button class="min-h-11 rounded-xl bg-ocean-700 px-5 py-3 text-sm font-bold text-white">Record verified receipt</button>
            </form>
        </details>
    @endif
    @if($paymentTransactions->where('kind','receipt')->isNotEmpty())
        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <summary class="min-h-11 cursor-pointer font-bold">Record an external refund</summary>
            <form method="POST" action="{{ route('admin.bookings.mark-refunded', $booking->id) }}" class="mt-4 space-y-3">@csrf
                <p class="text-xs text-slate-500">This records a refund already completed outside SunnyTrips. It does not transfer money. Active reservations will require review.</p>
                <label for="refund-receipt" class="block text-sm font-semibold">Original receipt</label>
                <select id="refund-receipt" name="receipt_id" required class="w-full rounded-lg border-slate-300 dark:bg-slate-800">
                    @foreach($paymentTransactions->where('kind','receipt') as $receipt)<option value="{{ $receipt->id }}">{{ $receipt->source_reference }} · ₱{{ number_format($receipt->amount/100,2) }}</option>@endforeach
                </select>
                @foreach(['refund_amount' => ['Actual refund amount','number'], 'refund_reference' => ['External refund reference','text'], 'refund_note' => ['Verification note','text']] as $field => [$label,$type])
                    <label for="record-{{ $field }}" class="block text-sm font-semibold">{{ $label }}</label><input id="record-{{ $field }}" name="{{ $field }}" type="{{ $type }}" required maxlength="{{ $field === 'refund_note' ? 500 : 100 }}" @if($type === 'number') min="0.01" step="0.01" @endif class="w-full rounded-lg border-slate-300 dark:bg-slate-800">
                @endforeach
                <button class="min-h-11 rounded-xl bg-ocean-700 px-5 py-3 text-sm font-bold text-white">Record completed refund</button>
            </form>
        </details>
    @endif
</section>
