<div class="max-w-2xl">
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Full name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1.5 block w-full" :value="old('name', $user->name)"
                required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email address')" />
            <x-text-input id="email" name="email" type="email" class="mt-1.5 block w-full"
                :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 rounded-xl bg-sand-50 border border-sand-200 p-4 text-sm">
                    <p class="text-ink-700">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification"
                            class="ml-1 font-bold text-ocean-600 underline underline-offset-2 hover:text-ocean-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ocean-500 cursor-pointer">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-semibold text-ocean-700">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-ocean-600 px-6 py-3 text-sm font-bold text-white tracking-wide transition-all duration-150 hover:bg-ocean-700 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1 active:bg-ocean-800 cursor-pointer">
                Save changes
            </button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm text-ocean-700 font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">check_circle</span>
                    {{ __('Saved.') }}
                </p>
            @endif
        </div>
    </form>
</div>