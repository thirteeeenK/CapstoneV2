<x-frontend.layout title="Email preferences | SunnyTrips">
    <div class="min-h-[70vh] bg-sand-50/70 dark:bg-slate-800/60 px-4 py-12 sm:py-20 font-body">
        <section class="mx-auto max-w-lg rounded-3xl border border-sand-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-6 sm:p-8 shadow-sm text-center">
            <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-ocean-50 dark:bg-ocean-900/50 text-ocean-600 dark:text-ocean-100 border border-ocean-100 dark:border-ocean-800">
                <span class="material-symbols-outlined text-[24px]" aria-hidden="true">{{ $done ? 'mark_email_read' : 'unsubscribe' }}</span>
            </span>
            @if ($done)
                <h1 class="font-headline text-2xl font-bold text-ink-900 dark:text-slate-100">You’re unsubscribed</h1>
                <p class="mt-3 text-sm text-ink-600 dark:text-slate-300 leading-relaxed">
                    {{ $user->email }} will no longer receive deals and destination ideas from SunnyTrips.
                    Booking confirmations, payment updates and trip reminders for your bookings still arrive as usual.
                </p>
                <p class="mt-3 text-xs text-ink-500 dark:text-slate-400">Changed your mind? Turn deals back on from the Email preferences section of your profile.</p>
            @else
                <h1 class="font-headline text-2xl font-bold text-ink-900 dark:text-slate-100">Stop promotional emails?</h1>
                <p class="mt-3 text-sm text-ink-600 dark:text-slate-300 leading-relaxed">
                    {{ $user->email }} will stop receiving deals and destination ideas. Booking confirmations, payment updates and trip reminders are not affected.
                </p>
                <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6">
                    @csrf
                    <button type="submit" class="min-h-11 w-full sm:w-auto rounded-xl bg-ocean-700 hover:bg-ocean-800 px-6 py-3 text-sm font-bold text-white">Unsubscribe</button>
                </form>
            @endif
            <a href="{{ url('/') }}" class="mt-6 inline-block text-sm font-bold text-ocean-700 dark:text-ocean-300 hover:underline">Back to SunnyTrips</a>
        </section>
    </div>
</x-frontend.layout>
