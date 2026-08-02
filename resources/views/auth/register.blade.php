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

            // Enable/disable the modal's Create account button based on all required consents.
            function updateModalSubmitButton() {
                const allChecked = Array.from(consentCheckboxes).every(checkbox => checkbox.checked);
                modalSubmitBtn.disabled = !allChecked;
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
    <x-modal name="confirm-submit-modal" :show="false" maxWidth="2xl">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-ink-900">Confirm your agreement</h2>
            <p class="mt-2 text-sm text-ink-600">
                To create your account, please confirm that you are at least 18 years old and that you have read and agree to the following:
            </p>

            <div class="mt-5 space-y-4 rounded-lg bg-sand-100/50 p-4">
                {{-- Age confirmation --}}
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="age_confirmed" value="1" form="register-form"
                        class="consent-checkbox mt-1 h-4 w-4 rounded border-ink-300 text-ocean-600 focus:ring-ocean-500"
                        {{ old('age_confirmed') ? 'checked' : '' }}>
                    <span class="text-sm text-ink-600">
                        I confirm that I am at least 18 years old and legally capable of entering into contracts.
                    </span>
                </label>
                <div data-error-for="age_confirmed">
                    <x-input-error :messages="$errors->get('age_confirmed')" />
                </div>

                {{-- Terms and Conditions --}}
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="terms_accepted" value="1" form="register-form"
                        class="consent-checkbox mt-1 h-4 w-4 rounded border-ink-300 text-ocean-600 focus:ring-ocean-500"
                        {{ old('terms_accepted') ? 'checked' : '' }}>
                    <span class="text-sm text-ink-600">
                        I agree to the
                        <a href="{{ route('terms') }}" target="_blank"
                            class="font-semibold text-ocean-600 hover:underline">Terms and Conditions</a>,
                        including the binding arbitration clause.
                    </span>
                </label>
                <div data-error-for="terms_accepted">
                    <x-input-error :messages="$errors->get('terms_accepted')" />
                </div>

                {{-- Privacy Policy --}}
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="privacy_accepted" value="1" form="register-form"
                        class="consent-checkbox mt-1 h-4 w-4 rounded border-ink-300 text-ocean-600 focus:ring-ocean-500"
                        {{ old('privacy_accepted') ? 'checked' : '' }}>
                    <span class="text-sm text-ink-600">
                        I agree to the
                        <a href="{{ route('privacy-policy') }}" target="_blank"
                            class="font-semibold text-ocean-600 hover:underline">Privacy Policy</a>
                        and Data Disclosure summary.
                    </span>
                </label>
                <div data-error-for="privacy_accepted">
                    <x-input-error :messages="$errors->get('privacy_accepted')" />
                </div>

                {{-- AI Disclosure --}}
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="ai_disclosure_accepted" value="1" form="register-form"
                        class="consent-checkbox mt-1 h-4 w-4 rounded border-ink-300 text-ocean-600 focus:ring-ocean-500"
                        {{ old('ai_disclosure_accepted') ? 'checked' : '' }}>
                    <span class="text-sm text-ink-600">
                        I acknowledge the
                        <a href="{{ route('ai-disclosure') }}" target="_blank"
                            class="font-semibold text-ocean-600 hover:underline">AI Usage Disclosure</a>
                        and understand that AI recommendations may contain errors.
                    </span>
                </label>
                <div data-error-for="ai_disclosure_accepted">
                    <x-input-error :messages="$errors->get('ai_disclosure_accepted')" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                <x-primary-button id="modal-create-account-btn" form="register-form" disabled>
                    Create account
                </x-primary-button>
            </div>
        </div>
    </x-modal>

</x-guest-layout>
