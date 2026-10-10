<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SunnyTrips') }} — Admin Two-Factor Setup</title>

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

            {{-- Success flash --}}
            @session('success')
                <div
                    class="mb-6 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    <span class="material-symbols-outlined text-emerald-500 shrink-0" style="font-size:18px">check_circle</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endsession

            @if ($enabled)
                {{-- ── Enabled / manage state ── --}}
                <div class="mb-8 text-center">
                    <h1 class="font-headline text-2xl font-bold text-ink-900 leading-tight">
                        Authenticator is on
                    </h1>
                    <p class="mt-2 text-sm text-ink-500 leading-relaxed">
                        Your account requires a code from your authenticator app on every sign in.
                    </p>
                </div>

                @if (! empty($codes))
                    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-sm font-semibold text-amber-800">Recovery codes — save these now</p>
                        <p class="mt-1 text-xs text-amber-700">Each code works once. They will not be shown again.</p>
                        <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-ink-900">
                            @foreach ($codes as $recoveryCode)
                                <li class="rounded bg-white px-2 py-1.5 text-center border border-amber-200">{{ $recoveryCode }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.two-factor.codes.regenerate') }}" class="space-y-5" novalidate>
                    @csrf
                    <div>
                        <x-input-label for="regen-password" :value="__('Confirm password for new codes')" />
                        <x-text-input
                            id="regen-password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                        <x-input-error :messages="$errors->get('password')" />
                    </div>
                    <x-primary-button class="w-full">
                        Get new recovery codes
                    </x-primary-button>
                </form>

                <form method="POST" action="{{ route('admin.two-factor.destroy') }}" class="mt-6 space-y-5" novalidate>
                    @csrf
                    @method('DELETE')
                    <div>
                        <x-input-label for="disable-password" :value="__('Confirm password to turn off')" />
                        <x-text-input
                            id="disable-password"
                            type="password"
                            name="disable_password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                        <x-input-error :messages="$errors->get('disable_password')" />
                    </div>
                    <div>
                        <x-input-label for="disable-code" :value="__('Authenticator or recovery code')" />
                        <x-text-input
                            id="disable-code"
                            type="text"
                            name="code"
                            required
                            autocomplete="one-time-code"
                            placeholder="123456 or ABCDE-FGHIJ"
                            aria-describedby="disable-code-hint"
                        />
                        <p id="disable-code-hint" class="mt-1 text-xs text-ink-500">
                            Confirm this security change with your authenticator or one recovery code.
                        </p>
                        <x-input-error :messages="$errors->get('code')" />
                    </div>
                    <button type="submit"
                        onclick="return confirm('Turn off two-factor authentication for your admin account?')"
                        class="w-full inline-flex justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100 transition-colors">
                        Turn off two-factor
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <a href="{{ route('admin.dashboard') }}"
                        class="text-xs font-medium text-ocean-600 hover:text-ocean-700 underline-offset-2 hover:underline transition-colors">
                        Back to dashboard
                    </a>
                </div>
            @else
                {{-- ── Enrollment state ── --}}
                <div class="mb-8 text-center">
                    <h1 class="font-headline text-2xl font-bold text-ink-900 leading-tight">
                        Protect your admin account
                    </h1>
                    <p class="mt-2 text-sm text-ink-500 leading-relaxed">
                        Scan the code with your authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password), then enter the 6-digit code below.
                    </p>
                </div>

                <div class="mb-6 flex justify-center">
                    <div class="rounded-xl border border-ink-100 bg-white p-3">
                        {!! $qrSvg !!}
                    </div>
                </div>

                <div class="mb-6 rounded-lg bg-sand-100 px-4 py-3 text-center">
                    <p class="text-xs text-ink-500">Can't scan? Enter this key manually:</p>
                    <p class="mt-1 font-mono text-sm font-semibold text-ink-900 break-all">{{ $manualKey }}</p>
                </div>

                <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="space-y-5" novalidate>
                    @csrf

                    <div>
                        <x-input-label for="password" :value="__('Current password')" />
                        <x-text-input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                        <x-input-error :messages="$errors->get('password')" />
                    </div>

                    <div>
                        <x-input-label for="code" :value="__('6-digit code')" />
                        <x-text-input
                            id="code"
                            type="text"
                            name="code"
                            :value="old('code')"
                            required
                            autofocus
                            autocomplete="one-time-code"
                            inputmode="numeric"
                            placeholder="123456"
                            class="text-center tracking-[0.3em]"
                        />
                        <x-input-error :messages="$errors->get('code')" />
                    </div>

                    <div class="pt-2">
                        <x-primary-button class="w-full">
                            Turn on two-factor
                        </x-primary-button>
                    </div>

                </form>

                <div class="mt-6 text-center">
                    <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-ink-400 hover:text-ink-600 underline-offset-2 hover:underline transition-colors">
                            Sign out
                        </button>
                    </form>
                </div>
            @endif

        </div>

    </div>

</body>

</html>
