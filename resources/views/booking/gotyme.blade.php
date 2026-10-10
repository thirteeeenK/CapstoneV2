<x-frontend.layout :title="'GoTyme QR Payment — ' . $booking->booking_code . ' — SunnyTrips'">
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10 font-body">
        <a href="{{ route('booking.show', $booking->booking_code) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-sky-700 hover:text-sky-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
            ← Back to booking details
        </a>

        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="gotyme-heading">
                <div class="text-center">
                    <p class="font-label text-[10px] font-bold uppercase tracking-[0.18em] text-sky-700">Manual QR payment</p>
                    <h1 id="gotyme-heading" class="mt-2 font-headline text-2xl font-black text-slate-900">Pay to GoTyme QR</h1>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">Scan this personal GoTyme QR. Your banking app will show the recipient, but you must type the amount yourself.</p>
                </div>

                <div class="mx-auto mt-5 max-w-sm rounded-3xl border border-sky-100 bg-sky-50 p-4 text-center">
                    <img src="{{ asset(ltrim($goTyme['qr_image'], '/')) }}"
                         alt="GoTyme QR code for {{ $goTyme['account_name'] }}"
                         width="640" height="640"
                         class="mx-auto h-auto w-full max-w-72 rounded-2xl bg-white object-contain">
                    <p class="mt-4 font-headline text-base font-black text-slate-900">{{ $goTyme['account_name'] }}</p>
                    <p class="mt-1 break-all font-mono text-sm font-bold text-slate-600">{{ $goTyme['account_number'] }}</p>
                </div>

                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <p class="font-bold">Type exactly ₱{{ number_format($paymentSummary['remaining_cents'] / 100, 2) }} in your GoTyme app.</p>
                    <p class="mt-1 text-xs leading-relaxed">Booking reference: <span class="break-all font-mono font-bold">{{ $booking->gateway_reference }}</span>. GoTyme does not automatically report personal-account transfers to SunnyTrips.</p>
                </div>
            </section>

            <div class="space-y-6">
                @if (session('success'))
                    <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900">
                        {{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        <p class="font-bold">Please review your transfer details.</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="balance-heading">
                    <h2 id="balance-heading" class="font-headline text-lg font-black text-slate-900">Payment balance</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Booking total</dt><dd class="font-bold text-slate-900">₱{{ number_format($paymentSummary['due_cents'] / 100, 2) }}</dd></div>
                        <div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Admin verified</dt><dd class="font-bold text-emerald-700">₱{{ number_format($paymentSummary['verified_cents'] / 100, 2) }}</dd></div>
                        <div class="flex items-center justify-between gap-4 border-t border-slate-100 pt-3"><dt class="font-bold text-slate-700">Remaining</dt><dd class="font-headline text-xl font-black text-sky-800">₱{{ number_format($paymentSummary['remaining_cents'] / 100, 2) }}</dd></div>
                    </dl>
                    @if ($paymentSummary['pending_claimed_cents'] > 0)
                        <p class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">₱{{ number_format($paymentSummary['pending_claimed_cents'] / 100, 2) }} is submitted and awaiting admin verification. It is not deducted yet.</p>
                    @endif
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="submit-heading">
                    <h2 id="submit-heading" class="font-headline text-lg font-black text-slate-900">Submit your transfer</h2>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Enter the transaction reference shown in GoTyme and the amount you actually sent. This does not automatically mark the booking paid.</p>

                    <form action="{{ route('booking.pay.gotyme.submit', $booking->booking_code) }}" method="POST" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="sender_ref" class="block text-sm font-bold text-slate-800">GoTyme transaction reference</label>
                            <input id="sender_ref" name="sender_ref" type="text" required maxlength="50" value="{{ old('sender_ref') }}"
                                   aria-describedby="sender-ref-hint @error('sender_ref') sender-ref-error @enderror"
                                   @error('sender_ref') aria-invalid="true" @enderror
                                   class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-base focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                            <p id="sender-ref-hint" class="mt-1 text-xs text-slate-500">Use the unique reference from the successful transfer receipt.</p>
                            @error('sender_ref')<p id="sender-ref-error" class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="claimed_amount" class="block text-sm font-bold text-slate-800">Amount sent</label>
                            <div class="mt-1 flex min-h-11 items-center rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/20">
                                <span class="pl-3 font-bold text-slate-500" aria-hidden="true">₱</span>
                                <input id="claimed_amount" name="claimed_amount" type="number" inputmode="decimal" required min="0.01" max="99999999.99" step="0.01"
                                       value="{{ old('claimed_amount', number_format($paymentSummary['remaining_cents'] / 100, 2, '.', '')) }}"
                                       @error('claimed_amount') aria-invalid="true" aria-describedby="claimed-amount-error" @enderror
                                       class="min-h-11 min-w-0 flex-1 rounded-xl border-0 px-2 text-base focus:ring-0">
                            </div>
                            @error('claimed_amount')<p id="claimed-amount-error" class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-sky-700 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-sky-700/20 transition hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">
                            <span class="material-symbols-outlined text-lg" aria-hidden="true">receipt_long</span>
                            Submit for admin verification
                        </button>
                    </form>
                </section>

                @if ($booking->payments->isNotEmpty())
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="transfers-heading">
                        <h2 id="transfers-heading" class="font-headline text-lg font-black text-slate-900">Submitted transfers</h2>
                        <ul class="mt-4 space-y-3">
                            @foreach ($booking->payments as $payment)
                                <li class="rounded-2xl border border-slate-200 p-3 text-sm">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <span class="break-all font-mono font-bold text-slate-800">{{ $payment->sender_reference }}</span>
                                        <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-wider {{ $payment->status === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">{{ $payment->status }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Reported: ₱{{ number_format((float) $payment->claimed_amount, 2) }}@if($payment->status === 'verified') · Verified: ₱{{ number_format((float) $payment->verified_amount, 2) }}@endif</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    </main>
</x-frontend.layout>
