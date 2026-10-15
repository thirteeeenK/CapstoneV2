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

            @include('adminAuth.partials.two-factor-controls')

        </div>

    </div>

</body>

</html>
