@props([
    'code' => '404',
    'subtitle' => 'Page Not Found',
    'title' => '',
    'message' => '',
    'icon' => 'explore_off',
    'theme' => 'sunset',
    'actions' => [],
    'retryAfter' => null,
])

@php
    // Dusk-seascape palettes — same CSS-variable system as the booking journal.
    $themes = [
        'sunset' => '--t-sky1:#fdf6e3; --t-sky2:#fde68a; --t-sky3:#fdba74; --t-sun:#f59e0b; --t-sea1:#0e7490; --t-sea2:#0f5e6b; --t-isle:#134e4a; --t-isle-far:#115e59; --t-palm:#14532d; --t-birds:#92400e;',
        'sky'    => '--t-sky1:#eff6ff; --t-sky2:#93c5fd; --t-sky3:#38bdf8; --t-sun:#fbbf24; --t-sea1:#155e75; --t-sea2:#0e4f6b; --t-isle:#064e3b; --t-isle-far:#065f46; --t-palm:#166534; --t-birds:#1e40af;',
        'amber'  => '--t-sky1:#fffbeb; --t-sky2:#fde68a; --t-sky3:#fcd34d; --t-sun:#f59e0b; --t-sea1:#a16207; --t-sea2:#854d0e; --t-isle:#713f12; --t-isle-far:#854d0e; --t-palm:#4d7c0f; --t-birds:#92400e;',
        'ocean'  => '--t-sky1:#f0f9ff; --t-sky2:#b8e3f6; --t-sky3:#79cbed; --t-sun:#fbbf24; --t-sea1:#0a78a8; --t-sea2:#075e83; --t-isle:#134e4a; --t-isle-far:#115e59; --t-palm:#166534; --t-birds:#0a78a8;',
        'coral'  => '--t-sky1:#fff1f2; --t-sky2:#fecdd3; --t-sky3:#fda4af; --t-sun:#f43f5e; --t-sea1:#9f1239; --t-sea2:#881337; --t-isle:#4c0519; --t-isle-far:#701a3c; --t-palm:#7c2d12; --t-birds:#be123c;',
        'storm'  => '--t-sky1:#f1f5f9; --t-sky2:#cbd5e1; --t-sky3:#94a3b8; --t-sun:#f87171; --t-sea1:#334155; --t-sea2:#1e293b; --t-isle:#1e293b; --t-isle-far:#334155; --t-palm:#3f2d1d; --t-birds:#64748b;',
        'fog'    => '--t-sky1:#fafaf9; --t-sky2:#e7e5e4; --t-sky3:#d6d3d1; --t-sun:#d6d3d1; --t-sea1:#78716c; --t-sea2:#57534e; --t-isle:#44403c; --t-isle-far:#57534e; --t-palm:#44403c; --t-birds:#57534e;',
    ];

    // Auth flags are best-effort: error pages must still render if the DB is down.
    $isAdminArea = request()->is('admin') || request()->is('admin/*');
    $authed = false;
    $adminAuthed = false;
    try { $authed = Auth::check(); } catch (\Throwable $e) { $authed = false; }
    try { $adminAuthed = Auth::guard('admin')->check(); } catch (\Throwable $e) { $adminAuthed = false; }

    $vars = $themes[$theme] ?? $themes['sunset'];

    $labels = [
        'home'         => $adminAuthed ? 'Back to Admin Dashboard' : ($authed ? 'Return to Dashboard' : 'Return Home'),
        'back'         => 'Go Back',
        'destinations' => 'Browse Destinations',
        'signin'       => 'Sign In',
        'dashboard'    => $adminAuthed ? 'Back to Admin Dashboard' : ($authed ? 'Return to Dashboard' : 'Sign In'),
        'reload'       => 'Refresh Page',
        'countdown'    => 'Try Again Later',
        'support'      => 'Contact Support',
    ];

    $actionIcons = [
        'home'         => 'home',
        'back'         => 'arrow_back',
        'destinations' => 'explore',
        'signin'       => 'login',
        'dashboard'    => 'space_dashboard',
        'reload'       => 'refresh',
        'countdown'    => 'hourglass_top',
        'support'      => 'support_agent',
    ];

    $resolved = [];
    foreach ($actions as $i => $action) {
        $token = is_string($action) ? $action : ($action[0] ?? 'home');
        $label = is_array($action) && isset($action[1]) ? $action[1] : ($labels[$token] ?? 'Continue');
        $style = is_array($action) && isset($action[2]) ? $action[2] : ($i === 0 ? 'primary' : 'secondary');

        $href = null;
        $js = null;

        switch ($token) {
            case 'home':
                if ($adminAuthed) {
                    $href = route('admin.dashboard');
                } elseif ($authed) {
                    $href = route('dashboard');
                } else {
                    $href = url('/');
                }
                break;

            case 'back':
                $js = "window.history.length > 1 ? window.history.back() : (window.location.href = '" . url('/') . "')";
                break;

            case 'destinations':
                $href = route('destinations.index');
                break;

            case 'signin':
                $href = route($isAdminArea ? 'admin.login' : 'login', ['redirect' => request()->fullUrl()]);
                break;

            case 'dashboard':
                if ($adminAuthed) {
                    $href = route('admin.dashboard');
                } elseif ($authed) {
                    $href = route('dashboard');
                } else {
                    $href = route($isAdminArea ? 'admin.login' : 'login', ['redirect' => request()->fullUrl()]);
                }
                break;

            case 'reload':
            case 'countdown':
                $js = 'window.location.reload()';
                break;

            case 'support':
                $href = 'mailto:support@sunnytripts.ph';
                break;
        }

        $resolved[] = compact('token', 'label', 'style', 'href', 'js');
    }

    $btnStyles = [
        'primary'   => 'px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition flex items-center gap-1.5 shadow-lg shadow-slate-900/20 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed',
        'secondary' => 'px-5 py-3 rounded-2xl bg-white/60 hover:bg-white/90 backdrop-blur text-slate-900 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer',
    ];
@endphp

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>{{ $code }} · {{ $title }} — SunnyTrips</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300;1,9..40,400;1,9..40,500&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <link rel="icon" type="image/png" href="{{ asset('images/favicon-sun.png') }}">

    @vite(['resources/css/app.css'])

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
    </style>
</head>
<body class="min-h-screen bg-sand-50/70 font-body antialiased text-on-surface">

    <div class="min-h-screen flex flex-col">
        <div class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 flex flex-col justify-center">

            {{-- Journal masthead --}}
            <div class="flex items-center justify-between mb-5 animate-fade-in">
                <p class="font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400">
                    SunnyTrips · Traveler Console
                </p>
                <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400">
                    {{ now()->format('M j, Y') }}
                </p>
            </div>

            {{-- ============ HERO: THE ISLAND SCENE ============ --}}
            <section class="relative overflow-hidden rounded-[2rem] shadow-xl shadow-slate-900/5 border border-sand-200/80 animate-fade-up"
                     style="{{ $vars }}">

                <svg viewBox="0 0 1200 340" preserveAspectRatio="xMidYMax slice" class="absolute inset-0 w-full h-full">
                    <defs>
                        <linearGradient id="ep-sky" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--t-sky1)"/>
                            <stop offset="70%" stop-color="var(--t-sky2)"/>
                            <stop offset="100%" stop-color="var(--t-sky3)"/>
                        </linearGradient>
                        <linearGradient id="ep-sea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--t-sea1)"/>
                            <stop offset="100%" stop-color="var(--t-sea2)"/>
                        </linearGradient>
                    </defs>

                    <rect width="1200" height="340" fill="url(#ep-sky)"/>

                    <circle cx="955" cy="128" r="58" fill="var(--t-sun)" opacity="0.92"/>
                    <circle cx="955" cy="128" r="78" fill="var(--t-sun)" opacity="0.18"/>

                    <path d="M 178 340 Q 226 168 300 340 Z" fill="var(--t-isle-far)" opacity="0.45"/>
                    <path d="M 168 340 Q 216 158 296 340 Z" fill="var(--t-isle)"/>

                    <path d="M 217 340 Q 209 262 203 206" stroke="#3f2d1d" stroke-width="8" fill="none" stroke-linecap="round"/>
                    <ellipse cx="204" cy="197" rx="36" ry="13" fill="var(--t-palm)" transform="rotate(-16 204 197)"/>
                    <ellipse cx="174" cy="209" rx="31" ry="11" fill="var(--t-palm)" transform="rotate(-48 174 209)"/>
                    <ellipse cx="234" cy="209" rx="31" ry="11" fill="var(--t-palm)" transform="rotate(28 234 209)"/>
                    <path d="M 203 206 q 10 12 22 14" stroke="#3f2d1d" stroke-width="4" fill="none" stroke-linecap="round"/>

                    <path d="M 0 250 Q 160 222 320 250 T 640 250 T 960 250 T 1280 250 V 340 H 0 Z" fill="url(#ep-sea)"/>
                    <path d="M 0 276 Q 200 252 400 276 T 800 276 T 1200 276 V 340 H 0 Z" fill="var(--t-sea2)" opacity="0.5"/>

                    @if ($theme === 'storm')
                        {{-- Rain --}}
                        <g stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" opacity="0.35">
                            <line x1="160" y1="60" x2="130" y2="120"/>
                            <line x1="260" y1="84" x2="230" y2="144"/>
                            <line x1="360" y1="56" x2="330" y2="116"/>
                            <line x1="480" y1="92" x2="450" y2="152"/>
                            <line x1="580" y1="60" x2="550" y2="120"/>
                            <line x1="700" y1="88" x2="670" y2="148"/>
                            <line x1="820" y1="52" x2="790" y2="112"/>
                            <line x1="920" y1="96" x2="890" y2="156"/>
                            <line x1="1040" y1="64" x2="1010" y2="124"/>
                            <line x1="1140" y1="90" x2="1110" y2="150"/>
                        </g>
                    @elseif ($theme === 'fog')
                        {{-- Fog bands --}}
                        <rect x="0" y="150" width="1200" height="26" fill="#ffffff" opacity="0.28"/>
                        <rect x="0" y="196" width="1200" height="38" fill="#ffffff" opacity="0.22"/>
                        <rect x="0" y="252" width="1200" height="22" fill="#ffffff" opacity="0.18"/>
                    @endif

                    <path d="M 590 96 q 11 -13 22 0 q 11 -13 22 0" stroke="var(--t-birds)" stroke-width="3.5" fill="none" stroke-linecap="round" opacity="0.7"/>
                    <path d="M 668 128 q 9 -11 18 0 q 9 -11 18 0" stroke="var(--t-birds)" stroke-width="2.5" fill="none" stroke-linecap="round" opacity="0.6"/>
                </svg>

                {{-- Vignette for text legibility --}}
                <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-black/25 pointer-events-none"></div>

                {{-- Hero content --}}
                <div class="relative z-10 px-6 sm:px-10 pt-16 sm:pt-20 pb-9 text-center text-slate-900">
                    <p class="font-label text-[10px] uppercase font-bold tracking-[0.3em] text-slate-800/70 mb-6">
                        {{ $code }} · {{ $subtitle }}
                    </p>

                    <div class="mx-auto mb-6 w-32 h-32 -rotate-6 rounded-[1.4rem] border-4 border-double border-white/80 bg-white/20 backdrop-blur-md flex flex-col items-center justify-center shadow-lg">
                        <span class="material-symbols-outlined text-[30px] text-white">{{ $icon }}</span>
                        <span class="font-headline text-[26px] font-black tracking-tight text-white mt-1">{{ $code }}</span>
                    </div>

                    <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 font-headline">
                        {{ $title }}
                    </h1>

                    <p class="text-sm sm:text-[15px] text-slate-800/80 mt-3 max-w-xl mx-auto font-medium leading-relaxed">
                        {{ $message }}
                    </p>

                    {{-- Actions --}}
                    <div class="mt-8 flex items-center justify-center gap-3 flex-wrap animate-fade-in">
                        @foreach ($resolved as $action)
                            @if ($action['href'])
                                <a href="{{ $action['href'] }}"
                                   class="{{ $btnStyles[$action['style']] }} {{ $action['style'] === 'secondary' ? 'border border-white/50' : '' }}">
                                    <span class="material-symbols-outlined text-[16px]">{{ $actionIcons[$action['token']] }}</span>
                                    <span>{{ $action['label'] }}</span>
                                </a>
                            @else
                                <button type="button"
                                        @if ($action['token'] === 'countdown' && $retryAfter) data-countdown="{{ $retryAfter }}" @endif
                                        onclick="{{ $action['js'] }}"
                                        class="{{ $btnStyles[$action['style']] }} {{ $action['style'] === 'secondary' ? 'border border-white/50' : '' }}">
                                    <span class="material-symbols-outlined text-[16px]">{{ $actionIcons[$action['token']] }}</span>
                                    <span @if ($action['token'] === 'countdown' && $retryAfter) data-countdown-label @endif>{{ $action['label'] }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        {{-- Colophon --}}
        <p class="text-center font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400 pb-8">
            SunnyTrips · Curated island escapes, verified before payment
        </p>
    </div>

    {{-- 429 countdown --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const button = document.querySelector('[data-countdown]');
            if (!button) return;

            let remaining = parseInt(button.dataset.countdown, 10) || 30;
            const label = button.querySelector('[data-countdown-label]');

            const tick = () => {
                if (remaining <= 0) {
                    button.disabled = false;
                    label.textContent = 'Try Again Now';
                    return;
                }
                label.textContent = 'Try again in ' + remaining + 's';
                remaining--;
                setTimeout(tick, 1000);
            };

            button.disabled = true;
            tick();
        });
    </script>
</body>
</html>
