{{-- Shared create/edit fields. Money is entered in pesos and stored in centavos; times are Philippine time.
     Travelers never type anything: a winning Lucky attempt carries the discount to checkout. --}}
@php
    $manila = fn ($value) => $value?->timezone('Asia/Manila')->format('Y-m-d\TH:i');
    $pesos = fn ($cents) => $cents !== null ? number_format($cents / 100, 2, '.', '') : null;
    $input = 'w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20';
    $label = 'block text-sm font-bold text-slate-800 mb-1';
    $hint = 'text-xs text-slate-500 mt-1';
    $selectedDestinations = array_map('intval', old('destination_ids', $promotion->destination_ids ?? []));
    $initial = [
        'type' => old('discount_type', $promotion->discount_type ?? 'percent'),
        'name' => old('name', $promotion->name ?? ''),
        'win' => old('win_percent', $promotion->win_percent),
        'percent' => old('percent_off', $promotion->percent_off),
        'cap' => old('max_discount', $pesos($promotion->max_discount_cents)),
        'fixed' => old('fixed_off', $pesos($promotion->fixed_off_cents)),
        'maxUsers' => old('total_redemption_limit', $promotion->total_redemption_limit),
        'perTraveler' => $promotion->per_traveler_limit,
    ];
@endphp

@if($errors->any())
    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
        <p class="font-bold">Please correct the highlighted campaign fields.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div x-data="{
        ...@js($initial),
        peso(value) { return '₱' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 }); },
        summary() {
            const off = this.type === 'percent'
                ? (this.percent ? this.percent + '% off' + (this.cap ? ' (up to ' + this.peso(this.cap) + ')' : '') : null)
                : (this.fixed ? this.peso(this.fixed) + ' off' : null);
            if (! off) return 'Enter a discount to see what travelers can win.';
            const reach = this.maxUsers ? 'up to ' + Number(this.maxUsers).toLocaleString('en-US') + ' users can redeem it' : 'no limit on how many users can redeem it';
            const uses = Number(this.perTraveler) === 1 ? 'one use per traveler' : (this.perTraveler || 1) + ' uses per traveler';
            return 'Each attempt has a ' + (this.win === '' || this.win === null ? 80 : this.win) + '% chance to win ' + off + ' · ' + reach + ' · ' + uses + '.';
        },
    }" class="space-y-6">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="name" class="{{ $label }}">Campaign name *</label>
            <input id="name" name="name" required maxlength="120" x-model="name" placeholder="e.g. Summer Splash" class="{{ $input }}">
        </div>
        <div>
            <label for="win_percent" class="{{ $label }}">Win chance (%)</label>
            <input id="win_percent" name="win_percent" type="number" min="0" max="100" x-model="win" aria-describedby="win-chance-hint" class="{{ $input }}">
            <p id="win-chance-hint" class="{{ $hint }}">The chance a Lucky attempt wins this discount. Travelers who don't win still get the itinerary at the regular price.</p>
        </div>
    </div>

    <fieldset class="space-y-3">
        <legend class="{{ $label }}">Discount *</legend>
        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Discount type">
            @foreach(['percent' => 'Percentage off', 'fixed' => 'Fixed peso amount'] as $value => $text)
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold"
                       :class="type === @js($value) ? 'border-sky-500 bg-sky-50 text-sky-800' : 'border-slate-200 text-slate-700'">
                    <input type="radio" name="discount_type" value="{{ $value }}" x-model="type" class="text-sky-600">
                    {{ $text }}
                </label>
            @endforeach
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div x-show="type === 'percent'">
                <label for="percent_off" class="{{ $label }}">Percent off</label>
                <input id="percent_off" name="percent_off" type="number" min="1" max="100" x-model="percent" placeholder="e.g. 10" class="{{ $input }}">
            </div>
            <div x-show="type === 'percent'">
                <label for="max_discount" class="{{ $label }}">Maximum discount per booking (₱, optional)</label>
                <input id="max_discount" name="max_discount" type="number" min="1" step="0.01" x-model="cap" placeholder="No cap" class="{{ $input }}">
            </div>
            <div x-show="type === 'fixed'" x-cloak>
                <label for="fixed_off" class="{{ $label }}">Amount off (₱)</label>
                <input id="fixed_off" name="fixed_off" type="number" min="1" step="0.01" x-model="fixed" placeholder="e.g. 500" class="{{ $input }}">
            </div>
        </div>
    </fieldset>

    <fieldset class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <legend class="{{ $label }} sm:col-span-3">Campaign validity <span class="font-normal text-slate-500">(optional, Philippine time)</span></legend>
        <div>
            <label for="redeem_starts_at" class="text-xs font-semibold text-slate-600">Starts</label>
            <input id="redeem_starts_at" name="redeem_starts_at" type="datetime-local" value="{{ old('redeem_starts_at', $manila($promotion->redeem_starts_at)) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="duration_days" class="text-xs font-semibold text-slate-600">Duration (days)</label>
            <input id="duration_days" name="duration_days" type="number" min="1" max="3650" placeholder="No set duration" value="{{ old('duration_days', $promotion->duration_days) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="redeem_ends_at" class="text-xs font-semibold text-slate-600">Expiration date</label>
            <input id="redeem_ends_at" name="redeem_ends_at" type="datetime-local" value="{{ old('redeem_ends_at', $manila($promotion->redeem_ends_at)) }}" class="{{ $input }}">
        </div>
        <p class="{{ $hint }} sm:col-span-3 mt-0">Duration counts from the start date, or from when the campaign was created. If you set both, the campaign ends on whichever comes first.</p>
    </fieldset>

    <fieldset class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <legend class="{{ $label }} sm:col-span-2">Optional limits <span class="font-normal text-slate-500">(leave blank for no limit)</span></legend>
        <div>
            <label for="total_redemption_limit" class="text-xs font-semibold text-slate-600">Maximum redemptions (users)</label>
            <input id="total_redemption_limit" name="total_redemption_limit" type="number" min="1" placeholder="Unlimited" x-model="maxUsers" class="{{ $input }}">
        </div>
        <div>
            <label for="min_eligible" class="text-xs font-semibold text-slate-600">Minimum spend (₱)</label>
            <input id="min_eligible" name="min_eligible" type="number" min="0" step="0.01" placeholder="Any amount" value="{{ old('min_eligible', $pesos($promotion->min_eligible_cents ?: null)) }}" class="{{ $input }}">
        </div>
        <fieldset class="sm:col-span-2">
            <legend class="text-xs font-semibold text-slate-600">Destinations <span class="font-normal">(none ticked = all destinations)</span></legend>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($destinations as $destination)
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50">
                        <input type="checkbox" name="destination_ids[]" value="{{ $destination->id }}" @checked(in_array($destination->id, $selectedDestinations, true)) class="rounded border-slate-300 text-sky-600">
                        {{ $destination->name }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    </fieldset>

    <p class="rounded-xl bg-sky-50 border border-sky-100 px-4 py-3 text-sm text-sky-900" aria-live="polite" x-text="summary()"></p>

    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promotion->is_active)) class="mt-0.5 rounded border-slate-300 text-sky-600">
        <span class="text-sm text-slate-700"><span class="font-bold">Active</span> — Lucky attempts can win this discount. Leave off until the terms are final.</span>
    </label>
</div>
