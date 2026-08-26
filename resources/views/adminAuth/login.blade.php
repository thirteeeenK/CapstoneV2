<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SunnyTrips') }} — Admin Portal Login</title>

    {{-- Google Fonts: Sora (headlines) + DM Sans (body) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300;1,9..40,400;1,9..40,500&display=swap"
        rel="stylesheet">

    {{-- Material Symbols --}}
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <link rel="icon" type="image/png" href="{{ asset('images/favicon-sun.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-ink-900 antialiased bg-sand-50">

    <div class="min-h-screen flex flex-col justify-center items-center py-12 px-6 sm:px-8">

        <div class="w-full max-w-md bg-white rounded-2xl border border-ink-100 shadow-xl p-8 sm:p-10 animate-fade-up">

            {{-- Brand wordmark --}}
            <div class="flex justify-center mb-8">
                <a href="/" class="inline-flex items-center gap-2 group" aria-label="SunnyTrips — Go to homepage">
                    <span class="material-symbols-outlined text-ocean-500 text-[26px] leading-none"
                        aria-hidden="true">admin_panel_settings</span>
                    <span
                        class="font-headline text-[1.45rem] font-bold text-ocean-600 tracking-tight group-hover:text-ocean-700 transition-colors">
                        SunnyTrips <span
                            class="text-xs font-semibold uppercase tracking-wider text-ink-400 bg-sand-100 px-2 py-0.5 rounded-md ml-1">Admin</span>
                    </span>
                </a>
            </div>

            {{-- Session status --}}
            <x-auth-session-status :status="session('status')" />

            {{-- Success flash --}}
            @session('success')
                <div
                    class="mb-6 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    <span class="material-symbols-outlined text-emerald-500 shrink-0" style="font-size:18px">check_circle</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endsession

            {{-- Page heading --}}
            <div class="mb-8 text-center">
                <h1 class="font-headline text-2xl font-bold text-ink-900 leading-tight">
                    Admin Portal
                </h1>
                <p class="mt-2 text-sm text-ink-500 leading-relaxed">
                    Sign in with your administrator credentials.
                </p>
            </div>

            {{-- ── Admin Login Form ── --}}
            <form method="POST" action="{{ route('admin.login') }}" class="space-y-5" novalidate>
                @csrf

                {{-- Email address --}}
                <div>
                    <x-input-label for="email" :value="__('Email Address')" />
                    <x-text-input
                        id="email"
                        type="email"
                        name="email"
                        :value="old('email')"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="sunnytrips@gmail.com"
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
                            onclick="togglePasswordVisibility('password', 'eye-admin-login-pw')"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-400 hover:text-ink-600 transition-colors"
                            aria-label="Toggle password visibility"
                        >
                            <span class="material-symbols-outlined select-none" style="font-size:20px" id="eye-admin-login-pw">visibility</span>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password')" />

                    {{-- Forgot password link --}}
                    @if (Route::has('admin.password.request'))
                        <div class="mt-2 flex justify-end">
                            <a
                                href="{{ route('admin.password.request') }}"
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

                {{-- Submit Button --}}
                <div class="pt-2">
                    <x-primary-button class="w-full">
                        Sign in to Admin Portal
                    </x-primary-button>
                </div>

            </form>

        </div>

    </div>

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

</body>

</html>