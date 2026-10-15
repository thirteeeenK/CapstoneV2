@php($headingTag = ($embedded ?? false) ? "h3" : "h1")
@if ($enabled)
    {{-- ── Enabled / manage state ── --}}
    <div class="mb-8 text-center">
        <{{ $headingTag }} class="font-headline text-2xl font-bold text-ink-900 leading-tight">
            Authenticator is on
        </{{ $headingTag }}>
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
        @if($embedded ?? false)<input type="hidden" name="return_to_settings" value="1">@endif
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
        @if($embedded ?? false)<input type="hidden" name="return_to_settings" value="1">@endif
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

    @unless($embedded ?? false)
    <div class="mt-6 text-center">
        <a href="{{ route('admin.dashboard') }}"
            class="text-xs font-medium text-ocean-600 hover:text-ocean-700 underline-offset-2 hover:underline transition-colors">
            Back to dashboard
        </a>
    </div>
    @endunless
@else
    {{-- ── Enrollment state ── --}}
    <div class="mb-8 text-center">
        <{{ $headingTag }} class="font-headline text-2xl font-bold text-ink-900 leading-tight">
            Protect your admin account
        </{{ $headingTag }}>
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
        @if($embedded ?? false)<input type="hidden" name="return_to_settings" value="1">@endif

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
                :autofocus="! ($embedded ?? false)"
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

    @unless($embedded ?? false)
    <div class="mt-6 text-center">
        <form method="POST" action="{{ route('admin.logout') }}" class="inline">
            @csrf
            <button type="submit" class="text-xs font-medium text-ink-400 hover:text-ink-600 underline-offset-2 hover:underline transition-colors">
                Sign out
            </button>
        </form>
    </div>
    @endunless
@endif
