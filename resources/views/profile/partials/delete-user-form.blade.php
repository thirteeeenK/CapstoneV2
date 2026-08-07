<div class="max-w-2xl">
    <button type="button" x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-coral-300 bg-coral-50 px-6 py-3 text-sm font-bold text-coral-600 transition-all duration-150 hover:bg-coral-100 focus:outline-none focus:ring-2 focus:ring-coral-400/40 cursor-pointer">
        <span class="material-symbols-outlined text-[18px]">delete_forever</span>
        Delete account
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-coral-50 border border-coral-100 text-coral-500 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[24px]">delete_forever</span>
                </div>
                <div class="space-y-1.5">
                    <h3 class="text-lg font-headline font-black text-ink-900 tracking-tight">
                        Are you sure you want to delete your account?
                    </h3>
                    <p class="text-sm font-body text-ink-500 leading-relaxed">
                        Once your account is deleted, all of its resources and data will be permanently removed.
                        Please enter your password to confirm you would like to permanently delete your account.
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" />
                <x-text-input id="password" name="password" type="password" class="mt-1.5 block w-full"
                    placeholder="{{ __('Enter your current password') }}" autocomplete="current-password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sand-200 bg-white px-5 py-2.5 text-sm font-semibold text-ink-600 hover:bg-sand-50 transition-all cursor-pointer">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-coral-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-coral-600 focus:outline-none focus:ring-2 focus:ring-coral-400/40 transition-all duration-150 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">delete_forever</span>
                    Delete account
                </button>
            </div>
        </form>
    </x-modal>
</div>