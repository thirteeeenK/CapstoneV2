<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SunnyTrips') }}</title>

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

    <div class="min-h-screen lg:grid lg:grid-cols-2">

        {{-- ══════════════════════════════════════════
        LEFT PANEL — Photo + Brand (Desktop only)
        ══════════════════════════════════════════ --}}
        <div class="hidden lg:flex relative overflow-hidden bg-ocean-900">

            {{-- Hero photograph --}}
            <img src="{{ asset('images/auth-hero.jpg') }}" alt="El Nido, Palawan — Philippines"
                class="absolute inset-0 w-full h-full object-cover object-center">

            {{-- Gradient overlay: transparent top → dark bottom --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/10 to-transparent"></div>

            {{-- Bottom brand statement --}}
            <div class="relative z-10 self-end w-full px-12 pb-14">
                <p class="font-headline text-white/90 text-[1.75rem] leading-snug font-semibold">
                    "Discover the Islands<br>
                    <em class="font-light">Worth Getting Lost In."</em>
                </p>
                <div class="mt-4 flex items-center gap-2">
                    <span class="block w-6 h-px bg-white/40"></span>
                    <p class="text-white/55 text-xs tracking-widest uppercase font-medium">
                        El Nido, Palawan &middot; Philippines
                    </p>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════
        RIGHT PANEL — Form Area
        ══════════════════════════════════════════ --}}
        <div class="flex min-h-screen flex-col justify-center py-12 px-8 sm:px-14 lg:px-16 bg-sand-50">
            <div class="w-full max-w-md mx-auto">

                {{-- Brand wordmark --}}
                <a href="/" class="inline-flex items-baseline gap-1.5 mb-4 group"
                    aria-label="SunnyTrips — Go to homepage">
                    <span class="material-symbols-outlined text-ocean-500 text-[22px] leading-none"
                        aria-hidden="true">wb_sunny</span>
                    <span
                        class="font-headline text-[1.35rem] font-bold text-ocean-600 tracking-tight group-hover:text-ocean-700 transition-colors duration-150">
                        SunnyTrips
                    </span>
                </a>

                {{-- Page content (login / register form) --}}
                <div class="animate-fade-up">
                    {{ $slot }}
                </div>

            </div>
        </div>

    </div>

</body>

</html>