<div class="max-w-2xl">
    <form method="post" action="{{ route('profile.email-preferences') }}" class="space-y-4">
        @csrf
        @method('patch')

        <input type="hidden" name="reminders" value="0">
        <label class="flex items-start gap-3 rounded-2xl border border-sand-200 dark:border-slate-700 p-4 cursor-pointer">
            <input type="checkbox" name="reminders" value="1" @checked($user->trip_reminders_enabled) class="mt-0.5 h-5 w-5 shrink-0 rounded accent-ocean-600">
            <span class="font-body text-sm text-ink-700 dark:text-slate-300">
                <span class="font-bold text-ink-900 dark:text-slate-100">Trip reminders</span>
                <span class="block text-xs text-ink-500 dark:text-slate-400 mt-0.5">Pre-trip and departure reminders and review invitations for your bookings.</span>
            </span>
        </label>

        <input type="hidden" name="marketing" value="0">
        <label class="flex items-start gap-3 rounded-2xl border border-sand-200 dark:border-slate-700 p-4 cursor-pointer">
            <input type="checkbox" name="marketing" value="1" @checked($user->trip_marketing_enabled) class="mt-0.5 h-5 w-5 shrink-0 rounded accent-ocean-600">
            <span class="font-body text-sm text-ink-700 dark:text-slate-300">
                <span class="font-bold text-ink-900 dark:text-slate-100">Deals and destination ideas</span>
                <span class="block text-xs text-ink-500 dark:text-slate-400 mt-0.5">Promotions, new tour packages, Lucky discounts and planning follow-ups. Every one has an unsubscribe link.</span>
            </span>
        </label>

        <p class="font-body text-xs text-ink-500 dark:text-slate-400">Booking confirmations, payment updates and account security emails are always sent.</p>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-ocean-600 px-6 py-3 text-sm font-bold text-white tracking-wide transition-all duration-150 hover:bg-ocean-700 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1 active:bg-ocean-800 cursor-pointer">
                {{ __('Save email preferences') }}
            </button>

            @if (session('status') === 'email-preferences-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" role="status"
                    class="text-sm text-ocean-700 dark:text-ocean-300 font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">check_circle</span>
                    {{ __('Saved.') }}
                </p>
            @endif
        </div>
    </form>
</div>
