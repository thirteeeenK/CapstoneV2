{{-- Travelers who reached today's Lucky limit. Sits outside #listings-results, so its pagination is a normal page load. --}}
<section aria-labelledby="attempts-exhausted-heading" class="mt-6 bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
        <h2 id="attempts-exhausted-heading" class="font-headline text-sm font-bold text-slate-900">Out of attempts today</h2>
        <p class="text-xs text-slate-500 mt-0.5">Resetting gives a traveler the full daily limit back. Their saved itineraries and won discounts stay as they are.</p>
    </div>

    @if($exhausted->isEmpty())
        <p class="p-6 text-center text-sm text-slate-500">No travelers have used all their attempts today.</p>
    @else
        <div class="overflow-x-auto" tabindex="0" role="region" aria-labelledby="attempts-exhausted-heading">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4 font-bold">Traveler</th>
                        <th class="py-3 px-4 font-bold">Attempts used</th>
                        <th class="py-3 px-4 font-bold">Last attempt (PH time)</th>
                        <th class="py-3 px-4 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($exhausted as $traveler)
                        <tr class="border-b border-slate-50 align-middle">
                            <td class="py-3 px-4">
                                <p class="font-bold text-slate-900">{{ $traveler->name }}</p>
                                <p class="text-[11px] text-slate-500">{{ $traveler->email }}</p>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-700">{{ $traveler->attempts_used }} of {{ $dailyAttempts }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($traveler->last_attempt_at)->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
                            <td class="py-3 px-4 text-right">
                                <form action="{{ route('admin.promotions.attempts.reset', $traveler->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" aria-label="Reset attempts for {{ $traveler->name }}" class="min-h-11 px-4 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-100 cursor-pointer">Reset</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($exhausted->hasPages())
            <div class="p-4 border-t border-slate-100">{{ $exhausted->links() }}</div>
        @endif
    @endif
</section>
