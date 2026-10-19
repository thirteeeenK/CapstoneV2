@props([
    'label' => 'Continue with Google',
    'consent' => true,
])

@if (filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
    @php
        // One source of truth with the server-side gate in GoogleAuthController:
        // when Turnstile is not fully configured no widget is rendered and no
        // token is verified, so the button never offers a click that must fail.
        $challenged = app(\App\Services\TurnstileService::class)->configured();
        $uid = 'google-signin-' . uniqid();
    @endphp

    <div>
        <form id="{{ $uid }}" method="POST" action="{{ route('auth.google.redirect') }}">
            @csrf

            @if ($challenged)
                <input type="hidden" name="cf-turnstile-response" id="{{ $uid }}-token">
            @endif

            {{-- aria-disabled rather than the disabled attribute: the button stays
                 focusable so a keyboard user can reach it and hear why it is not
                 ready yet. The submit handler does the actual blocking. --}}
            <button
                type="submit"
                id="{{ $uid }}-button"
                @if ($challenged) aria-disabled="true" aria-describedby="{{ $uid }}-status" @endif
                {{ $attributes->merge([
                    'class' => 'inline-flex w-full items-center justify-center gap-3 rounded-lg border border-ink-200 dark:border-slate-600 bg-white dark:bg-slate-900 px-5 py-3 font-body text-sm font-semibold text-ink-700 dark:text-slate-100 tracking-wide transition-all duration-150 hover:bg-sand-50 dark:hover:bg-slate-700/60 hover:border-ink-300 dark:hover:border-slate-500 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1 aria-disabled:opacity-60 aria-disabled:cursor-not-allowed',
                ]) }}
            >
                {{-- Official Google "G" mark. Inline so it renders before any CDN settles. --}}
                <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                    <path fill="#EA4335" d="M9 3.48c1.69 0 2.84.73 3.49 1.34l2.55-2.49C13.46.89 11.43 0 9 0 5.48 0 2.44 2.02.96 4.96l2.91 2.26C4.6 5.05 6.62 3.48 9 3.48Z"/>
                    <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.49h4.84a4.16 4.16 0 0 1-1.8 2.73l2.84 2.2c1.67-1.54 2.76-3.81 2.76-6.58Z"/>
                    <path fill="#FBBC05" d="M3.88 10.78A5.54 5.54 0 0 1 3.58 9c0-.62.11-1.22.29-1.78L.96 4.96A8.99 8.99 0 0 0 0 9c0 1.45.35 2.82.96 4.04l2.92-2.26Z"/>
                    <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.95-2.18l-2.84-2.2c-.76.53-1.78.9-3.11.9-2.38 0-4.4-1.57-5.13-3.74L.96 13.04C2.44 15.98 5.48 18 9 18Z"/>
                </svg>
                <span>{{ $label }}</span>
            </button>

            @if ($challenged)
                {{-- interaction-only keeps this empty unless Cloudflare wants a gesture. --}}
                <div id="{{ $uid }}-widget" class="mt-2 flex justify-center"></div>
                <p id="{{ $uid }}-status" role="status" aria-live="polite"
                    class="mt-2 text-center font-body text-[11px] leading-relaxed text-ink-500 dark:text-slate-400">
                    Verifying your browser…
                </p>
            @endif
        </form>

        @if ($consent)
            {{-- Clickwrap: Google returns no consent checkboxes, so the agreement a
                 password signup collects as four checkboxes is presented here and
                 stamped onto the account in GoogleAuthController@callback. --}}
            <p class="mt-3 text-center font-body text-[11px] leading-relaxed text-ink-500 dark:text-slate-400">
                By continuing you confirm you are 18 or older and agree to our
                <a href="{{ route('terms') }}" target="_blank" rel="noopener"
                    class="font-medium text-ocean-600 dark:text-ocean-400 underline-offset-2 hover:underline">Terms</a>,
                <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener"
                    class="font-medium text-ocean-600 dark:text-ocean-400 underline-offset-2 hover:underline">Privacy&nbsp;Policy</a>
                and
                <a href="{{ route('ai-disclosure') }}" target="_blank" rel="noopener"
                    class="font-medium text-ocean-600 dark:text-ocean-400 underline-offset-2 hover:underline">AI&nbsp;Disclosure</a>.
            </p>
        @endif
    </div>

    @if ($challenged)
        <x-turnstile-loader />

        <script>
            // Scoped to this instance's ids so the component can be rendered
            // more than once on a page without the widgets colliding.
            (function () {
                const form = document.getElementById(@js($uid));
                const button = document.getElementById(@js($uid . '-button'));
                const token = document.getElementById(@js($uid . '-token'));
                const status = document.getElementById(@js($uid . '-status'));
                let widgetId;

                function result(value, message) {
                    token.value = value;
                    button.setAttribute('aria-disabled', value ? 'false' : 'true');
                    status.textContent = message;
                }

                window.sunnyTurnstile.onError(() => result('', 'Security check could not load. Reload the page to continue.'));

                window.sunnyTurnstile.ready(turnstile => {
                    widgetId = turnstile.render('#' + @js($uid) + '-widget', {
                        sitekey: @js(config('services.turnstile.site_key')),
                        action: @js(\App\Services\TurnstileService::ACTION_GOOGLE),
                        appearance: 'interaction-only',
                        'response-field': false,
                        'refresh-expired': 'auto',
                        retry: 'auto',
                        callback: value => result(value, ''),
                        'expired-callback': () => result('', 'Verifying your browser…'),
                        'error-callback': () => result('', 'Security check failed. Reload the page to continue.'),
                    });
                });

                form.addEventListener('submit', event => {
                    if (!token.value) {
                        event.preventDefault();
                        status.textContent = 'Still verifying your browser — one moment.';
                        return;
                    }
                    button.setAttribute('aria-disabled', 'true');
                    status.textContent = 'Redirecting you to Google…';
                });

                // Turnstile tokens are single-use, so a Back navigation into a
                // cached page is holding a spent one.
                window.addEventListener('pageshow', event => {
                    if (event.persisted) {
                        result('', 'Verifying your browser…');
                        if (window.turnstile && widgetId !== undefined) {
                            window.turnstile.reset(widgetId);
                        }
                    }
                });
            })();
        </script>
    @endif
@endif
