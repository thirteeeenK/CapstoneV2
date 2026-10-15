<x-guest-layout>
    <div class="space-y-6 font-body">
        <h1 class="font-headline text-2xl font-bold text-ink-900 dark:text-white">{{ $operation['purpose'] === 'change-email' ? 'Review your email change' : 'Set your password' }}</h1>
        <p class="text-sm text-ink-600 dark:text-slate-300">Your linked Google account is confirmed. Complete this change within five minutes.</p>
        <x-input-error :messages="$errors->all()" />
        @if ($operation['purpose'] === 'change-email')
            <p class="text-sm text-ink-600 dark:text-slate-300">New email: <strong class="break-all">{{ $operation['data']['email'] }}</strong></p>
            <form method="post" action="{{ route('account-security.commit') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="confirmation_id" value="{{ $operation['id'] }}">
                <x-primary-button>Save email change</x-primary-button>
            </form>
        @else
            <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('put')
                <input type="hidden" name="confirmation_id" value="{{ $operation['id'] }}">
                <div>
                    <x-input-label for="confirmed_password" value="New password" />
                    <x-text-input id="confirmed_password" name="password" type="password" autocomplete="new-password" required class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->updatePassword->get('password')" />
                </div>
                <div>
                    <x-input-label for="confirmed_password_confirmation" value="Confirm new password" />
                    <x-text-input id="confirmed_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-1 block w-full" />
                </div>
                <x-primary-button>Set password</x-primary-button>
            </form>
        @endif
        <form method="post" action="{{ route('account-security.cancel') }}">
            @csrf
            <button type="submit" class="text-sm font-semibold text-ocean-600 underline dark:text-ocean-400">Cancel this change</button>
        </form>
    </div>
</x-guest-layout>
