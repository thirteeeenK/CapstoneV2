<div x-data="{
    target: { url: @js(route('admin.users.destroy', (int) old('target_id', 0))), id: @js((int) old('target_id', 0)), name: @js(old('target_name', 'Selected customer')), tab: @js(old('tab', 'all')), search: @js(old('search', '')) },
    mode: @js(old('mode', ''))
}" x-on:delete-customer.window="target = $event.detail; mode = ''; $refs.deletionForm.reset(); $refs.deletionDialog.showModal()"
    @if($errors->hasAny(['confirm_email', 'reason', 'mode'])) x-init="$nextTick(() => $refs.deletionDialog.showModal())" @endif>
    <dialog x-ref="deletionDialog" aria-labelledby="delete-customer-title" aria-describedby="delete-customer-description"
        x-on:click="if ($event.target === $el) $el.close()"
        class="m-auto max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 text-ink-900 shadow-xl backdrop:bg-ink-900/60 sm:p-8">
        <form method="POST" x-ref="deletionForm" :action="target.url" class="space-y-5 font-body">
            @csrf
            @method('DELETE')
            <input type="hidden" name="target_id" :value="target.id">
            <input type="hidden" name="target_name" :value="target.name">
            <input type="hidden" name="tab" :value="target.tab || 'all'">
            <input type="hidden" name="search" :value="target.search || ''">
            <h2 id="delete-customer-title" class="font-headline text-xl font-bold">Delete customer account</h2>
            <p id="delete-customer-description" class="text-sm leading-relaxed text-slate-600">Choose how to delete <strong x-text="target.name"></strong>. Booking and payment history will be retained. Permanent deletion removes the account, preferences, and account-bound moderation flags. Use a ban to restrict abusive users.</p>
            @if($errors->any())
                <div role="alert" class="rounded-lg bg-coral-50 p-3 text-sm text-coral-700"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div>
                <x-input-label for="customer-delete-mode" value="Deletion mode" />
                <select id="customer-delete-mode" name="mode" x-model="mode" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="" disabled>Choose an action</option>
                    <option value="scheduled">Schedule deletion — five days</option>
                    <option value="permanent">Permanently delete now</option>
                </select>
                <p class="mt-2 text-sm text-coral-700" x-show="mode === 'permanent'">This cannot be undone. The customer can register again as a new account.</p>
                <p class="mt-2 text-sm text-slate-500" x-show="mode === 'scheduled'">The customer can log in before the deadline to recover their account. An existing deadline will not be extended.</p>
            </div>
            <div><x-input-label for="customer-confirm-email" value="Type the customer’s email to confirm" /><x-text-input id="customer-confirm-email" name="confirm_email" type="email" maxlength="254" :value="old('confirm_email')" required autocomplete="off" class="mt-1 w-full" aria-describedby="customer-confirm-email-error" /><x-input-error id="customer-confirm-email-error" :messages="$errors->get('confirm_email')" class="mt-1" /></div>
            <div><x-input-label for="customer-delete-reason" value="Reason for deletion" /><textarea id="customer-delete-reason" name="reason" rows="3" maxlength="500" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('reason') }}</textarea></div>
            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">
                <button type="button" x-on:click="$refs.deletionDialog.close()" class="min-h-[44px] rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600">Cancel</button>
                <button type="submit" class="min-h-[44px] rounded-lg bg-coral-600 px-4 py-2 text-sm font-bold text-white hover:bg-coral-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral-600">Confirm deletion</button>
            </div>
        </form>
    </dialog>
</div>
