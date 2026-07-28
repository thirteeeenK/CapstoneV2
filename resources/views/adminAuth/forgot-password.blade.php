<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SunnyTrips') }} — Admin Password Reset Request</title>

    {{-- Google Fonts: Sora (headlines) + DM Sans (body) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300;1,9..40,400;1,9..40,500&display=swap"
        rel="stylesheet">

    {{-- Material Symbols --}}
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

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

            {{-- Page heading --}}
            <div class="mb-6 text-center">
                <h1 class="font-headline text-2xl font-bold text-ink-900 leading-tight">
                    Forgot Password?
                </h1>
                <p class="mt-2 text-sm text-ink-500 leading-relaxed">
                    Forgot your administrator password? No problem. Enter your email address and we will send you a password reset link.
                </p>
            </div>

            {{-- Session status --}}
            <x-auth-session-status class="mb-4" :status="session('status')" />

            {{-- ── Forgot Password Form ── --}}
            <form method="POST" action="{{ route('admin.password.email') }}" class="space-y-5" novalidate>
                @csrf

                {{-- Email Address --}}
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

                {{-- Submit Button --}}
                <div class="pt-2">
                    <x-primary-button class="w-full">
                        Email Password Reset Link
                    </x-primary-button>
                </div>

                {{-- Back to login link --}}
                <p class="text-center text-sm text-ink-500 pt-2">
                    Remembered your password?
                    <a href="{{ route('admin.login') }}" class="font-semibold text-ocean-600 hover:text-ocean-700 transition-colors ml-1">
                        Back to sign in
                    </a>
                </p>

            </form>

        </div>

    </div>

</body>

</html>
