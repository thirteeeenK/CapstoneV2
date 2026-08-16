<x-guest-layout>

    {{-- Page heading --}}
    <div class="mb-8">
        <h1 class="font-headline text-[1.875rem] font-semibold text-ink-900 leading-tight">
            Create your account.
        </h1>
        <p class="mt-2 text-sm text-ink-500 leading-relaxed">
            Join thousands of travelers discovering the Philippines.
        </p>
    </div>

    {{-- ── Register Form ── --}}
    <form id="register-form" method="POST" action="{{ route('register') }}" class="space-y-5" novalidate>
        @csrf

        {{-- Name & Email — side by side on sm+ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
                <x-input-label for="name" :value="__('Full name')" />
                <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus
                    autocomplete="name" placeholder="Juan dela Cruz" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email address')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required
                    autocomplete="username" placeholder="you@example.com" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

        </div>

        {{-- Address --}}
        <div>
            <x-input-label for="address" :value="__('Address')" />
            <x-text-input id="address" type="text" name="address" :value="old('address')" required
                placeholder="Street, City, Province" />
            <x-input-error :messages="$errors->get('address')" />
        </div>

        {{-- Phone --}}
        <div>
            <x-input-label for="phone_number" :value="__('Phone number')" />
            <x-text-input id="phone_number" type="number" name="phone_number" :value="old('phone_number')" required
                placeholder="09XXXXXXXXX" />
            <x-input-error :messages="$errors->get('phone_number')" />
        </div>

        {{-- Password & Confirm — side by side on sm+ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <div class="relative">
                    <x-text-input id="password" type="password" name="password" required autocomplete="new-password"
                        placeholder="••••••••" class="pr-11" />
                    <button type="button" onclick="togglePasswordVisibility('password', 'eye-reg-pw')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-400 hover:text-ink-600 transition-colors"
                        aria-label="Toggle password visibility">
                        <span class="material-symbols-outlined select-none" style="font-size:20px"
                            id="eye-reg-pw">visibility</span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <div class="relative">
                    <x-text-input id="password_confirmation" type="password" name="password_confirmation" required
                        autocomplete="new-password" placeholder="••••••••" class="pr-11" />
                    <button type="button" onclick="togglePasswordVisibility('password_confirmation', 'eye-reg-confirm')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-400 hover:text-ink-600 transition-colors"
                        aria-label="Toggle confirm password visibility">
                        <span class="material-symbols-outlined select-none" style="font-size:20px"
                            id="eye-reg-confirm">visibility</span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>

        </div>

        {{-- Submit --}}
        <div class="pt-1">
            <x-primary-button type="button" class="w-full" x-data x-on:click.prevent="$dispatch('open-modal', 'confirm-submit-modal')">
                Create account
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

        {{-- Login link --}}
        <p class="text-center text-sm text-ink-500">
            Already have an account?
            <a href="{{ route('login') }}"
                class="font-semibold text-ocean-600 hover:text-ocean-700 transition-colors ml-1">
                Sign in
            </a>
        </p>

    </form>

    <script>
        function togglePasswordVisibility(fieldId, eyeId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(eyeId);
            if (field.type === 'password') {
                field.type = 'text';
                eye.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                eye.textContent = 'visibility';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const consentCheckboxes = document.querySelectorAll('.consent-checkbox');
            const modalSubmitBtn = document.getElementById('modal-create-account-btn');
            const consentFields = ['age_confirmed', 'terms_accepted', 'privacy_accepted', 'ai_disclosure_accepted'];

            const progressBar = document.getElementById('consent-progress');
            const progressCount = document.getElementById('consent-count');

            // Enable/disable the modal's Create account button based on all required consents.
            function updateModalSubmitButton() {
                const checked = Array.from(consentCheckboxes).filter(checkbox => checkbox.checked);
                const allChecked = checked.length === consentCheckboxes.length;
                modalSubmitBtn.disabled = !allChecked;

                if (progressBar && progressCount) {
                    const pct = consentCheckboxes.length ? Math.round((checked.length / consentCheckboxes.length) * 100) : 0;
                    progressBar.style.width = pct + '%';
                    progressCount.textContent = checked.length + ' of ' + consentCheckboxes.length + ' agreements confirmed';
                }
            }

            consentCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateModalSubmitButton);
            });

            updateModalSubmitButton();

            // Re-open the consent modal if the server rejected any consent agreements.
            const hasConsentErrors = consentFields.some(field => {
                const errorEl = document.querySelector('[data-error-for="' + field + '"]');
                return errorEl && errorEl.textContent.trim().length > 0;
            });

            if (hasConsentErrors) {
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'confirm-submit-modal' }));
            }
        });
    </script>

    {{-- Consent + confirmation modal (declared first so detail modals can stack above it) --}}
    <x-modal name="confirm-submit-modal" :show="false" maxWidth="2xl" align="right">
        <div class="p-6 sm:p-8">

            {{-- Header --}}
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-label text-xs font-bold uppercase tracking-[0.2em] text-ocean-600">
                        Confirm your agreements
                    </p>
                    <h2 class="mt-2 font-headline text-xl font-bold text-ink-900 tracking-tight">
                        One last step before we set sail.
                    </h2>
                    <p class="mt-2 text-sm font-body text-ink-500 leading-relaxed">
                        To create your account, please confirm the details below. These keep your experience safe, transparent, and fair.
                    </p>
                </div>
                <button type="button" x-on:click="$dispatch('close')"
                    class="shrink-0 -mr-1 -mt-1 inline-flex h-9 w-9 items-center justify-center rounded-lg text-ink-400 transition-colors hover:bg-ink-100 hover:text-ink-700 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 cursor-pointer"
                    aria-label="Close">
                    <span class="material-symbols-outlined text-[22px]">close</span>
                </button>
            </div>

            {{-- Progress hint --}}
            <div class="mt-5 flex items-center gap-3">
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink-100">
                    <div id="consent-progress" class="h-full w-0 rounded-full bg-ocean-500 transition-all duration-300"></div>
                </div>
                <span id="consent-count" class="shrink-0 font-label text-xs font-semibold text-ink-500">
                    0 of 4 agreements confirmed
                </span>
            </div>

            {{-- Consent cards --}}
            <div class="mt-5 space-y-3">

                {{-- Age confirmation --}}
                <label class="group flex cursor-pointer items-start gap-3 sm:gap-4 rounded-xl border border-ink-200 bg-white p-3.5 sm:p-4 transition-all duration-150 hover:border-ocean-300 hover:bg-ocean-50/40 has-[:checked]:border-ocean-500 has-[:checked]:bg-ocean-50/60 has-[:checked]:shadow-sm">
                    <span class="mt-0.5 inline-flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-lg bg-ocean-50 text-ocean-600 ring-1 ring-ocean-100 transition-colors group-has-[:checked]:bg-ocean-500 group-has-[:checked]:text-white">
                        <span class="material-symbols-outlined text-[20px]">verified_user</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-headline text-sm font-bold text-ink-900">Age confirmation</span>
                        <span class="mt-0.5 block text-sm font-body text-ink-500 leading-relaxed">
                            I confirm that I am at least 18 years old and legally capable of entering into contracts.
                        </span>
                    </span>
                    <span class="relative mt-1 inline-flex h-5 w-5 shrink-0">
                        <input type="checkbox" name="age_confirmed" value="1" form="register-form"
                            class="consent-checkbox peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-ink-300 bg-white transition-colors checked:border-ocean-500 checked:bg-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1"
                            {{ old('age_confirmed') ? 'checked' : '' }}>
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-white opacity-0 peer-checked:opacity-100 transition-opacity">
                            <span class="material-symbols-outlined text-[14px]">check</span>
                        </span>
                    </span>
                </label>
                <div data-error-for="age_confirmed" class="px-1">
                    <x-input-error :messages="$errors->get('age_confirmed')" />
                </div>

                {{-- Terms and Conditions --}}
                <label class="group flex cursor-pointer items-start gap-3 sm:gap-4 rounded-xl border border-ink-200 bg-white p-3.5 sm:p-4 transition-all duration-150 hover:border-ocean-300 hover:bg-ocean-50/40 has-[:checked]:border-ocean-500 has-[:checked]:bg-ocean-50/60 has-[:checked]:shadow-sm">
                    <span class="mt-0.5 inline-flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-lg bg-ocean-50 text-ocean-600 ring-1 ring-ocean-100 transition-colors group-has-[:checked]:bg-ocean-500 group-has-[:checked]:text-white">
                        <span class="material-symbols-outlined text-[20px]">description</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-headline text-sm font-bold text-ink-900">Terms &amp; Conditions</span>
                        <span class="mt-0.5 block text-sm font-body text-ink-500 leading-relaxed">
                            I agree to the
                            <a href="{{ route('terms') }}" target="_blank"
                                class="font-semibold text-ocean-600 hover:underline">Terms and Conditions</a>,
                            including the binding arbitration clause.
                        </span>
                    </span>
                    <span class="relative mt-1 inline-flex h-5 w-5 shrink-0">
                        <input type="checkbox" name="terms_accepted" value="1" form="register-form"
                            class="consent-checkbox peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-ink-300 bg-white transition-colors checked:border-ocean-500 checked:bg-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1"
                            {{ old('terms_accepted') ? 'checked' : '' }}>
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-white opacity-0 peer-checked:opacity-100 transition-opacity">
                            <span class="material-symbols-outlined text-[14px]">check</span>
                        </span>
                    </span>
                </label>
                <div data-error-for="terms_accepted" class="px-1">
                    <x-input-error :messages="$errors->get('terms_accepted')" />
                </div>

                {{-- Privacy Policy --}}
                <label class="group flex cursor-pointer items-start gap-3 sm:gap-4 rounded-xl border border-ink-200 bg-white p-3.5 sm:p-4 transition-all duration-150 hover:border-ocean-300 hover:bg-ocean-50/40 has-[:checked]:border-ocean-500 has-[:checked]:bg-ocean-50/60 has-[:checked]:shadow-sm">
                    <span class="mt-0.5 inline-flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-lg bg-ocean-50 text-ocean-600 ring-1 ring-ocean-100 transition-colors group-has-[:checked]:bg-ocean-500 group-has-[:checked]:text-white">
                        <span class="material-symbols-outlined text-[20px]">shield</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-headline text-sm font-bold text-ink-900">Privacy &amp; Data</span>
                        <span class="mt-0.5 block text-sm font-body text-ink-500 leading-relaxed">
                            I agree to the
                            <a href="{{ route('privacy-policy') }}" target="_blank"
                                class="font-semibold text-ocean-600 hover:underline">Privacy Policy</a>
                            and Data Disclosure summary.
                        </span>
                    </span>
                    <span class="relative mt-1 inline-flex h-5 w-5 shrink-0">
                        <input type="checkbox" name="privacy_accepted" value="1" form="register-form"
                            class="consent-checkbox peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-ink-300 bg-white transition-colors checked:border-ocean-500 checked:bg-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1"
                            {{ old('privacy_accepted') ? 'checked' : '' }}>
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-white opacity-0 peer-checked:opacity-100 transition-opacity">
                            <span class="material-symbols-outlined text-[14px]">check</span>
                        </span>
                    </span>
                </label>
                <div data-error-for="privacy_accepted" class="px-1">
                    <x-input-error :messages="$errors->get('privacy_accepted')" />
                </div>

                {{-- AI Disclosure --}}
                <label class="group flex cursor-pointer items-start gap-3 sm:gap-4 rounded-xl border border-ink-200 bg-white p-3.5 sm:p-4 transition-all duration-150 hover:border-ocean-300 hover:bg-ocean-50/40 has-[:checked]:border-ocean-500 has-[:checked]:bg-ocean-50/60 has-[:checked]:shadow-sm">
                    <span class="mt-0.5 inline-flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-lg bg-ocean-50 text-ocean-600 ring-1 ring-ocean-100 transition-colors group-has-[:checked]:bg-ocean-500 group-has-[:checked]:text-white">
                        <span class="material-symbols-outlined text-[20px]">smart_toy</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-headline text-sm font-bold text-ink-900">AI Usage Disclosure</span>
                        <span class="mt-0.5 block text-sm font-body text-ink-500 leading-relaxed">
                            I acknowledge the
                            <a href="{{ route('ai-disclosure') }}" target="_blank"
                                class="font-semibold text-ocean-600 hover:underline">AI Usage Disclosure</a>
                            and understand that AI recommendations may contain errors.
                        </span>
                    </span>
                    <span class="relative mt-1 inline-flex h-5 w-5 shrink-0">
                        <input type="checkbox" name="ai_disclosure_accepted" value="1" form="register-form"
                            class="consent-checkbox peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-ink-300 bg-white transition-colors checked:border-ocean-500 checked:bg-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 focus:ring-offset-1"
                            {{ old('ai_disclosure_accepted') ? 'checked' : '' }}>
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-white opacity-0 peer-checked:opacity-100 transition-opacity">
                            <span class="material-symbols-outlined text-[14px]">check</span>
                        </span>
                    </span>
                </label>
                <div data-error-for="ai_disclosure_accepted" class="px-1">
                    <x-input-error :messages="$errors->get('ai_disclosure_accepted')" />
                </div>
            </div>

            {{-- Footer --}}
            <div class="mt-7 border-t border-ink-100 pt-5 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" x-on:click="$dispatch('close')"
                    class="inline-flex items-center justify-center rounded-lg border border-sand-200 bg-white px-5 py-3 text-sm font-semibold text-ink-600 transition-all duration-150 hover:bg-sand-50 focus:outline-none focus:ring-2 focus:ring-ocean-400/40 cursor-pointer">
                    Cancel
                </button>
                <x-primary-button id="modal-create-account-btn" form="register-form" disabled class="sm:min-w-[12rem]">
                    Create account
                </x-primary-button>
            </div>
        </div>
    </x-modal>

</x-guest-layout>
