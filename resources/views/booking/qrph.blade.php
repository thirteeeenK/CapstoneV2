<x-frontend.layout :title="'QRPH Payment — ' . $booking->booking_code . ' — SunnyTrips'">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-10 font-body">

        @if (session('error'))
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-rose-600">error</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-md overflow-hidden">
            {{-- Header --}}
            <div class="p-6 sm:p-8 border-b border-slate-100 bg-gradient-to-r from-emerald-50 to-teal-50">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white font-black flex items-center justify-center">
                        <span class="material-symbols-outlined">qr_code_2</span>
                    </div>
                    <div>
                        <p class="text-sm font-black tracking-tight text-slate-900">QRPH — GCash / GoTyme</p>
                        <p class="text-[10px] text-slate-500 font-semibold">
                            @if($isDemo)
                                <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 border border-amber-200 px-2 py-0.5 rounded-full font-bold">Demo mode — no money moves</span>
                            @else
                                <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full font-bold">Live — PayMongo QR Ph</span>
                            @endif
                        </p>
                    </div>
                    <span class="ml-auto material-symbols-outlined text-slate-400">lock</span>
                </div>

                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Booking Reference</p>
                        <p class="text-xl font-black text-slate-900 font-headline">{{ $booking->booking_code }}</p>
                        <p class="text-[11px] font-mono text-slate-500 mt-1 break-all">Ref: {{ $booking->gateway_reference }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Amount Due</p>
                        <p class="text-3xl font-black text-emerald-900">₱{{ number_format((float)$booking->net_amount, 2) }}</p>
                        <p class="text-[11px] text-slate-500">Embedded in QR — exact amount</p>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-6">
                {{-- QR --}}
                <div class="flex flex-col items-center">
                    @php $qrIsImage = $qrIsImage ?? (str_starts_with($qrContent, 'data:image') || str_starts_with($qrContent, 'https://')); @endphp
                    @if($qrIsImage)
                        <div class="bg-white p-4 rounded-[1.5rem] border border-slate-200 shadow-sm">
                            <img src="{{ $qrContent }}" alt="QRPH QR Code — scan to pay ₱{{ number_format((float)$booking->net_amount, 2) }}" class="w-[240px] h-[240px] object-contain block" width="240" height="240" />
                        </div>
                        <p class="text-[11px] text-slate-500 mt-3 text-center max-w-sm">Open <span class="font-bold text-slate-700">GoTyme</span>, <span class="font-bold text-slate-700">GCash</span>, Maya or any QRPH app and scan. The QR encodes the exact amount — no need to type it.</p>
                    @else
                        <div id="qr-container" class="bg-white p-4 rounded-[1.5rem] border border-slate-200 shadow-sm">
                            <div id="qrcode" class="w-[240px] h-[240px] flex items-center justify-center text-slate-400 text-xs">Generating QR…</div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-3 text-center max-w-sm">Scan with <span class="font-bold text-slate-700">GCash</span>, <span class="font-bold text-slate-700">GoTyme</span>, Maya or any QRPH app. The amount ₱{{ number_format((float)$booking->net_amount, 2) }} is embedded — no need to type it.</p>
                    @endif
                </div>

                @if(!$isDemo)
                    @isset($qrTestUrl)
                        @if($qrTestUrl)
                            <div class="bg-amber-100 border-2 border-amber-400 rounded-2xl p-4 text-xs text-amber-900 space-y-2 shadow-sm">
                                <p class="font-black flex items-center gap-1.5 text-amber-900"><span class="material-symbols-outlined text-[18px] text-amber-600">warning</span> Test mode — real GoTyme/GCash scans will be declined</p>
                                <p>This QR was created with <code class="font-mono font-bold">sk_test_</code> keys, so a real GoTyme/GCash/Maya app will read it but the InstaPay/QR Ph rail will decline it — test merchants can't be settled. This is expected, not a webhook problem.</p>
                                <p class="font-bold">To verify the full flow in sandbox without a real payment:</p>
                                <a href="{{ $qrTestUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 bg-white border border-amber-300 rounded-xl px-3 py-2 text-amber-800 font-black hover:bg-amber-50 break-all"><span class="material-symbols-outlined text-[16px]">open_in_new</span> Open PayMongo test authentication page</a>
                                <p class="text-[11px] opacity-80">Click it, let PayMongo simulate the QR Ph payment, then come back and tap <span class="font-bold">I've completed the payment</span> — we poll PayMongo and mark the booking paid.</p>
                                <p class="text-[11px] opacity-70">For a real GoTyme debit you need <code class="font-mono">sk_live_</code> + QR Ph live-enabled + a verified settlement account. No code change needed — just swap keys.</p>
                            </div>
                        @endif
                    @endisset
                    <div class="bg-sky-50 border border-sky-200 rounded-2xl p-4 text-xs text-sky-900 space-y-2">
                        <p class="font-bold flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">qr_code_2</span> Live PayMongo QR Ph — GoTyme-scannable (real payment)</p>
                        <p>Open your <span class="font-black">GoTyme</span>, GCash or Maya app → <span class="font-bold">Scan QR</span> → pay <span class="font-black">₱{{ number_format((float)$booking->net_amount, 2) }}</span>. This is a real QR Ph code — any QRPH-native e-wallet or bank app will recognise it (not a checkout link).</p>
                        <div class="bg-white/70 border border-sky-200 rounded-xl px-3 py-2 text-[11px] leading-relaxed">
                            <p class="font-bold">How to confirm (poll-on-button)</p>
                            <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                                <li>Scan the QR above with your wallet and complete the payment.</li>
                                <li>Come back here and tap <span class="font-bold">I've completed the payment</span> — we poll PayMongo to verify and mark the booking paid.</li>
                                <li>If it says "not yet confirmed," wait a few seconds and try again. If your wallet had insufficient balance, the code simply won't pay — retry before the payment deadline.</li>
                            </ol>
                            <p class="mt-1.5 opacity-80">Test keys (<code class="font-mono">sk_test_…</code>) never move real money. No redirect or webhook is needed for this flow — confirmation is via PayMongo polling.</p>
                        </div>
                    </div>
                @else
                    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-900 space-y-1">
                        <p class="font-bold flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">info</span> Demo mode — add your PayMongo keys to generate a GoTyme-scannable QR</p>
                        <p>This QR is a demo EMVCo string with your booking's actual amount embedded. Scan with any QR reader to see the amount and booking code — no real money moves. The real QR Ph QR looks the same but is issued by PayMongo and is scannable by every QRPH wallet (GoTyme included).</p>
                        <p>Add your PayMongo <code class="font-mono font-bold">sk_test_</code> secret to <code class="font-mono">.env</code> as <code class="font-mono font-bold">PAYMONGO_SECRET_KEY</code> (enable <span class="font-bold">QR Payments</span> in your PayMongo dashboard), run <code class="font-mono">php artisan config:clear</code>, and refresh — this page will then show a real PayMongo QR Ph image and the poll-on-button flow will confirm payment.</p>
                    </div>
                @endif

                {{-- Raw content (collapsible) --}}
                <details class="group">
                    <summary class="text-[11px] font-bold text-slate-500 cursor-pointer list-none flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px] group-open:rotate-90 transition">chevron_right</span>
                        @if($qrIsImage && str_starts_with($qrContent, 'data:image'))
                            Show QR image data (base64)
                        @else
                            Show raw QR content
                        @endif
                    </summary>
                    @if($qrIsImage && str_starts_with($qrContent, 'data:image'))
                        <p class="mt-2 font-mono text-[10px] leading-relaxed text-slate-500 break-all bg-slate-50 border border-slate-200 rounded-xl p-3">Base64 QR image — {{ strlen($qrContent) }} chars (truncated): {{ Str::limit($qrContent, 120) }}</p>
                    @else
                        <p class="mt-2 font-mono text-[10px] leading-relaxed text-slate-600 break-all bg-slate-50 border border-slate-200 rounded-xl p-3">{{ $qrContent }}</p>
                    @endif
                </details>

                {{-- Confirm --}}
                <form action="{{ route('booking.pay.qrph.confirm', $booking->booking_code) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white font-extrabold text-sm transition-all hover:from-emerald-500 hover:to-teal-600 cursor-pointer shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-lg">verified</span>
                        <span>{{ $isDemo ? 'Simulate Successful QR Payment' : "I've completed the payment" }}</span>
                    </button>
                    @if(!$isDemo)
                        <p class="text-[11px] text-slate-500 text-center mt-2">After paying in your wallet, tap the button to verify. If not yet confirmed, wait a moment and try again.</p>
                    @endif
                </form>

                <p class="text-[11px] text-center text-slate-400">Pay before {{ $booking->payment_deadline?->format('M j, Y g:i A') }} — otherwise this booking expires. Payment can be retried from your booking page.</p>

                <div class="flex items-center justify-center gap-3">
                    <a href="{{ route('booking.show', $booking->booking_code) }}" class="text-xs font-bold text-slate-400 hover:text-slate-600">← Back to booking</a>
                    <span class="text-slate-300">·</span>
                    <a href="{{ route('booking.pay', $booking->booking_code) }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-800">Change payment method</a>
                </div>
            </div>
        </div>
    </div>

    @if(!$qrIsImage)
        <script src="{{ asset('js/qrcode.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var content = {!! json_encode($qrContent) !!};
                var container = document.getElementById('qrcode');
                if (!container) return;
                try {
                    var qr = qrcode(0, 'M');
                    qr.addData(content);
                    qr.make();
                    container.innerHTML = qr.createSvgTag({ scalable: true });
                    var svg = container.querySelector('svg');
                    if (svg) {
                        svg.style.width = '240px';
                        svg.style.height = '240px';
                        svg.style.display = 'block';
                    }
                } catch (e) {
                    console.error('QR generation failed', e);
                    container.textContent = 'Could not generate QR';
                    container.classList.add('text-rose-600');
                }
            });
        </script>
    @endif
</x-frontend.layout>
