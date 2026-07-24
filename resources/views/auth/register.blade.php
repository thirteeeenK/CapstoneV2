<x-guest-layout>

    {{-- Page heading --}}
    <div class="mb-8">
        <h1 class="font-headline text-[1.875rem] font-semibold text-ink-900 leading-tight">
            Create your account.
        </h1>
        <p class="mt-2 text-sm text-ink-500 leading-relaxed">
            Join thousands of travelers discovering the Philippines.
        </p>
    </div>

    {{-- ── Register Form ── --}}
    <form method="POST" action="{{ route('register') }}" class="space-y-5" novalidate>
        @csrf

        {{-- Name & Email — side by side on sm+ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
                <x-input-label for="name" :value="__('Full name')" />
                <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus
                    autocomplete="name" placeholder="Juan dela Cruz" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email address')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required
                    autocomplete="username" placeholder="you@example.com" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

        </div>

        {{-- Address --}}
        <div>
            <x-input-label for="address" :value="__('Address')" />
            <x-text-input id="address" type="text" name="address" :value="old('address')" required
                placeholder="Street, City, Province" />
            <x-input-error :messages="$errors->get('address')" />
        </div>

        {{-- Phone --}}
        <div>
            <x-input-label for="phone_number" :value="__('Phone number')" />
            <x-text-input id="phone_number" type="number" name="phone_number" :value="old('phone_number')" required
                placeholder="09XXXXXXXXX" />
            <x-input-error :messages="$errors->get('phone_number')" />
        </div>

        {{-- Password & Confirm — side by side on sm+ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <div class="relative">
                    <x-text-input id="password" type="password" name="password" required autocomplete="new-password"
                        placeholder="••••••••" class="pr-11" />
                    <button type="button" onclick="togglePasswordVisibility('password', 'eye-reg-pw')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-400 hover:text-ink-600 transition-colors"
                        aria-label="Toggle password visibility">
                        <span class="material-symbols-outlined select-none" style="font-size:20px"
                            id="eye-reg-pw">visibility</span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <div class="relative">
                    <x-text-input id="password_confirmation" type="password" name="password_confirmation" required
                        autocomplete="new-password" placeholder="••••••••" class="pr-11" />
                    <button type="button" onclick="togglePasswordVisibility('password_confirmation', 'eye-reg-confirm')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-400 hover:text-ink-600 transition-colors"
                        aria-label="Toggle confirm password visibility">
                        <span class="material-symbols-outlined select-none" style="font-size:20px"
                            id="eye-reg-confirm">visibility</span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>

        </div>

        {{-- Submit --}}
        <div class="pt-1">
            <x-primary-button class="w-full">
                Create account
            </x-primary-button>
        </div>

        {{-- Divider --}}
        <div class="relative my-1">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-ink-200"></div>
            </div>
            <div class="relative flex justify-center">
                <span class="bg-sand-50 px-3 text-xs text-ink-400">or</span>
            </div>
        </div>

        {{-- Login link --}}
        <p class="text-center text-sm text-ink-500">
            Already have an account?
            <a href="{{ route('login') }}"
                class="font-semibold text-ocean-600 hover:text-ocean-700 transition-colors ml-1">
                Sign in
            </a>
        </p>

    </form>

    <script>
        function togglePasswordVisibility(fieldId, eyeId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(eyeId);
            if (field.type === 'password') {
                field.type = 'text';
                eye.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                eye.textContent = 'visibility';
            }
        }
    </script>

</x-guest-layout>