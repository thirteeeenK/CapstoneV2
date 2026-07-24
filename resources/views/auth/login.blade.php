<x-guest-layout>

    {{-- Session status (e.g. password reset link sent) --}}
    <x-auth-session-status :status="session('status')" />

    {{-- Success flash --}}
    @session('success')
        <div class="mb-6 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            <span class="material-symbols-outlined text-emerald-500 shrink-0" style="font-size:18px">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endsession

    {{-- Page heading --}}
    <div class="mb-8">
        <h1 class="font-display text-[1.875rem] font-semibold text-ink-900 leading-tight">
            Welcome back.
        </h1>
        <p class="mt-2 text-sm text-ink-500 leading-relaxed">
            Sign in to continue planning your next escape.
        </p>
    </div>

    {{-- ── Login Form ── --}}
    <form method="POST" action="{{ route('login') }}" class="space-y-5" novalidate>
        @csrf

        {{-- Email --}}
        <div>
            <x-input-label for="email" :value="__('Email address')" />
            <x-text-input
                id="email"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
                placeholder="you@example.com"
            />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        {{-- Password --}}
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative">
                <x-text-input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="pr-11"
                />
                {{-- Show / hide toggle --}}
                <button
                    type="button"
                    onclick="togglePasswordVisibility('password', 'eye-login-pw')"
                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-400 hover:text-ink-600 transition-colors"
                    aria-label="Toggle password visibility"
                >
                    <span class="material-symbols-outlined select-none" style="font-size:20px" id="eye-login-pw">visibility</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />

            {{-- Forgot password link sits neatly below the field --}}
            @if (Route::has('password.request'))
                <div class="mt-2 flex justify-end">
                    <a
                        href="{{ route('password.request') }}"
                        class="text-xs font-medium text-ocean-600 hover:text-ocean-700 underline-offset-2 hover:underline transition-colors"
                    >
                        Forgot your password?
                    </a>
                </div>
            @endif
        </div>

        {{-- Remember me --}}
        <div class="flex items-center gap-2.5">
            <input
                id="remember_me"
                type="checkbox"
                name="remember"
                class="h-4 w-4 rounded border-ink-300 text-ocean-500 focus:ring-ocean-400/30 focus:ring-offset-0 cursor-pointer"
            >
            <label for="remember_me" class="text-sm text-ink-600 cursor-pointer select-none">
                Keep me signed in
            </label>
        </div>

        {{-- Submit --}}
        <div class="pt-1">
            <x-primary-button class="w-full">
                Sign in
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

        {{-- Register link --}}
        <p class="text-center text-sm text-ink-500">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-semibold text-ocean-600 hover:text-ocean-700 transition-colors ml-1">
                Create one
            </a>
        </p>

    </form>

    <script>
        function togglePasswordVisibility(fieldId, eyeId) {
            const field = document.getElementById(fieldId);
            const eye   = document.getElementById(eyeId);
            if (field.type === 'password') {
                field.type   = 'text';
                eye.textContent = 'visibility_off';
            } else {
                field.type   = 'password';
                eye.textContent = 'visibility';
            }
        }
    </script>

</x-guest-layout>
