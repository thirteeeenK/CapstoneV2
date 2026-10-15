@php($hasPassword = $user->hasPassword())

<div class="max-w-2xl">
    <form method="post" action="{{ route('password.update') }}" class="space-y-6">
        @csrf
        @method('put')

        @if ($hasPassword)
            <div>
                <x-input-label for="update_password_current_password" :value="__('Current password')" />
                <x-text-input id="update_password_current_password" name="current_password" type="password"
                    class="mt-1.5 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>
        @else
            {{-- Google sign-in accounts have no password yet, so there is nothing
                 to confirm. Setting one adds email + password as a second way in. --}}
            <div class="flex items-start gap-3 rounded-2xl border border-ocean-100 dark:border-ocean-800 bg-ocean-50 dark:bg-ocean-900/40 px-4 py-3">
                <span class="material-symbols-outlined text-ocean-600 dark:text-ocean-200 text-[20px] shrink-0">info</span>
                <p class="font-body text-xs text-ink-600 dark:text-slate-300 leading-relaxed">
                    You signed up with Google, so you don't have a password yet. Set one here if you'd
                    also like to sign in with your email address — your Google sign-in keeps working either way.
                </p>
            </div>
        @endif

        <div>
            <x-input-label for="update_password_password" :value="$hasPassword ? __('New password') : __('Password')" />
            <x-text-input id="update_password_password" name="password" type="password"
                class="mt-1.5 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="$hasPassword ? __('Confirm new password') : __('Confirm password')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password"
                class="mt-1.5 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-ocean-600 px-6 py-3 text-sm font-bold text-white tracking-wide transition-all duration-150 hover:bg-ocean-700 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1 active:bg-ocean-800 cursor-pointer">
                {{ $hasPassword ? __('Update password') : __('Set password') }}
            </button>

            @if (in_array(session('status'), ['password-updated', 'password-created'], true))
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm text-ocean-700 dark:text-ocean-300 font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">check_circle</span>
                    {{ __('Saved.') }}
                </p>
            @endif
        </div>
    </form>
</div>