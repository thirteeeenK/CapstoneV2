<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SunnyTrips') }} — Admin Verification</title>

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
                <span class="inline-flex items-center gap-2" aria-label="SunnyTrips Admin">
                    <span class="material-symbols-outlined text-ocean-500 text-[26px] leading-none"
                        aria-hidden="true">admin_panel_settings</span>
                    <span
                        class="font-headline text-[1.45rem] font-bold text-ocean-600 tracking-tight">
                        SunnyTrips <span
                            class="text-xs font-semibold uppercase tracking-wider text-ink-400 bg-sand-100 px-2 py-0.5 rounded-md ml-1">Admin</span>
                    </span>
                </span>
            </div>

            {{-- Page heading --}}
            <div class="mb-8 text-center">
                <h1 class="font-headline text-2xl font-bold text-ink-900 leading-tight">
                    Check your authenticator
                </h1>
                <p class="mt-2 text-sm text-ink-500 leading-relaxed">
                    Enter the 6-digit code from your authenticator app. Lost your device? Use a recovery code instead.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.two-factor.challenge.store') }}" class="space-y-5" novalidate>
                @csrf

                <div>
                    <x-input-label for="code" :value="__('Verification code')" />
                    <x-text-input
                        id="code"
                        type="text"
                        name="code"
                        :value="old('code')"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        inputmode="text"
                        placeholder="123456 or XXXX-XXXXX"
                        class="text-center tracking-[0.3em]"
                    />
                    <x-input-error :messages="$errors->get('code')" />
                </div>

                <div class="pt-2">
                    <x-primary-button class="w-full">
                        Verify and sign in
                    </x-primary-button>
                </div>

            </form>

            <div class="mt-6 text-center">
                <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-ink-400 hover:text-ink-600 underline-offset-2 hover:underline transition-colors">
                        Cancel and back to sign in
                    </button>
                </form>
            </div>

        </div>

    </div>

</body>

</html>
