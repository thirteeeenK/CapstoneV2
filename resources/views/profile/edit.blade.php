<x-frontend.layout title="My Profile — SunnyTrips">
    <div class="py-8 sm:py-12 bg-sand-50/70 dark:bg-slate-800/60 text-ink-900 dark:text-slate-100 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Page header --}}
            <div class="space-y-2 mb-8">
                <p class="font-label text-[11px] uppercase tracking-[0.2em] text-ocean-600 dark:text-ocean-400 font-bold">
                    Travelers Portal · Account
                </p>
                <h1 class="text-2xl sm:text-3xl font-headline font-black tracking-tight text-ink-900 dark:text-slate-100">
                    Profile &amp; Security
                </h1>
                <p class="font-body text-xs sm:text-sm text-ink-500 dark:text-slate-400 max-w-lg leading-relaxed">
                    Your traveling identity and account details — everything about your SunnyTrips membership in one place.
                </p>
            </div>

            <div class="space-y-6">

                    {{-- Profile information --}}
                    <section id="profile" class="bg-white dark:bg-slate-900 border border-sand-200 dark:border-slate-700 rounded-3xl p-6 sm:p-8 shadow-sm">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-11 h-11 rounded-2xl bg-ocean-50 dark:bg-ocean-900/50 border border-ocean-100 dark:border-ocean-800 flex items-center justify-center text-ocean-600 dark:text-ocean-100 shrink-0">
                                <span class="material-symbols-outlined text-[22px]">person</span>
                            </div>
                            <div class="space-y-0.5">
                                <h2 class="font-headline font-bold text-lg text-ink-900 dark:text-slate-100 tracking-tight">Profile information</h2>
                                <p class="font-body text-xs text-ink-500 dark:text-slate-400">Your name and the email we use to reach you.</p>
                            </div>
                        </div>
                        @include('profile.partials.update-profile-information-form')
                    </section>

                    {{-- Security / password --}}
                    <section id="security" class="bg-white dark:bg-slate-900 border border-sand-200 dark:border-slate-700 rounded-3xl p-6 sm:p-8 shadow-sm">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-11 h-11 rounded-2xl bg-ocean-50 dark:bg-ocean-900/50 border border-ocean-100 dark:border-ocean-800 flex items-center justify-center text-ocean-600 dark:text-ocean-100 shrink-0">
                                <span class="material-symbols-outlined text-[22px]">lock</span>
                            </div>
                            <div class="space-y-0.5">
                                <h2 class="font-headline font-bold text-lg text-ink-900 dark:text-slate-100 tracking-tight">Security</h2>
                                <p class="font-body text-xs text-ink-500 dark:text-slate-400">Keep your password long, unique, and private.</p>
                            </div>
                        </div>
                        @include('profile.partials.update-password-form')
                    </section>

                    {{-- Danger zone --}}
                    <section id="danger" class="bg-white dark:bg-slate-900 border border-coral-200 rounded-3xl p-6 sm:p-8 shadow-sm">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-11 h-11 rounded-2xl bg-coral-50 dark:bg-coral-500/20 border border-coral-100 dark:border-coral-500/30 flex items-center justify-center text-coral-500 dark:text-coral-200 shrink-0">
                                <span class="material-symbols-outlined text-[22px]">delete_forever</span>
                            </div>
                            <div class="space-y-0.5">
                                <h2 class="font-headline font-bold text-lg text-ink-900 dark:text-slate-100 tracking-tight">Delete account</h2>
                                <p class="font-body text-xs text-ink-500 dark:text-slate-400">{{ __('Your account will be permanently deleted after a 5-day grace period. Log in within those 5 days to cancel deletion and recover your account.') }}</p>
                            </div>
                        </div>
                        @include('profile.partials.delete-user-form')
                    </section>

                </div>

        </div>
    </div>
</x-frontend.layout>
