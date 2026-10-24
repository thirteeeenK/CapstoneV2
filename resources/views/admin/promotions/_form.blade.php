{{-- Shared create/edit fields. Money is entered in pesos and stored in centavos; times are Philippine time. --}}
@php
    $manila = fn ($value) => $value?->timezone('Asia/Manila')->format('Y-m-d\TH:i');
    $pesos = fn ($cents) => $cents !== null ? number_format($cents / 100, 2, '.', '') : null;
    $input = 'w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20';
    $label = 'block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5';
    $selectedDestinations = array_map('intval', old('destination_ids', $promotion->destination_ids ?? []));
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

<div x-data="{ type: @js(old('discount_type', $promotion->discount_type ?? 'percent')) }" class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="name" class="{{ $label }}">Campaign name *</label>
            <input id="name" name="name" required maxlength="120" value="{{ old('name', $promotion->name) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="code_prefix" class="{{ $label }}">Code prefix *</label>
            <input id="code_prefix" name="code_prefix" required maxlength="12" pattern="[A-Za-z0-9]{2,12}" value="{{ old('code_prefix', $promotion->code_prefix) }}" aria-describedby="code-prefix-hint" class="{{ $input }} uppercase font-mono">
            <p id="code-prefix-hint" class="text-[11px] text-slate-400 mt-1">Each code is this prefix plus six random characters, e.g. LUCKY-7KQ2MZ.</p>
        </div>
    </div>

    <fieldset class="grid grid-cols-1 sm:grid-cols-4 gap-5">
        <legend class="{{ $label }} sm:col-span-4">Discount *</legend>
        <div>
            <label for="discount_type" class="sr-only">Discount type</label>
            <select id="discount_type" name="discount_type" x-model="type" class="{{ $input }}">
                <option value="percent">Percentage</option>
                <option value="fixed">Fixed peso amount</option>
            </select>
        </div>
        <div x-show="type === 'percent'">
            <label for="percent_off" class="text-[11px] font-bold text-slate-600">Percent off (1–100)</label>
            <input id="percent_off" name="percent_off" type="number" min="1" max="100" value="{{ old('percent_off', $promotion->percent_off) }}" class="{{ $input }}">
        </div>
        <div x-show="type === 'percent'">
            <label for="max_discount" class="text-[11px] font-bold text-slate-600">Cap per code (₱, optional)</label>
            <input id="max_discount" name="max_discount" type="number" min="1" step="0.01" value="{{ old('max_discount', $pesos($promotion->max_discount_cents)) }}" class="{{ $input }}">
        </div>
        <div x-show="type === 'fixed'" x-cloak>
            <label for="fixed_off" class="text-[11px] font-bold text-slate-600">Amount off (₱)</label>
            <input id="fixed_off" name="fixed_off" type="number" min="1" step="0.01" value="{{ old('fixed_off', $pesos($promotion->fixed_off_cents)) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="min_eligible" class="text-[11px] font-bold text-slate-600">Minimum eligible subtotal (₱)</label>
            <input id="min_eligible" name="min_eligible" type="number" min="0" step="0.01" value="{{ old('min_eligible', $pesos($promotion->min_eligible_cents ?: null)) }}" class="{{ $input }}">
        </div>
    </fieldset>

    <fieldset class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <legend class="{{ $label }} sm:col-span-2">Windows (Philippine time; leave blank for open-ended)</legend>
        @foreach (['issue_starts_at' => 'Codes issued from', 'issue_ends_at' => 'Codes issued until', 'redeem_starts_at' => 'Redeemable from', 'redeem_ends_at' => 'Redeemable until'] as $field => $text)
            <div>
                <label for="{{ $field }}" class="text-[11px] font-bold text-slate-600">{{ $text }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="datetime-local" value="{{ old($field, $manila($promotion->{$field})) }}" class="{{ $input }}">
            </div>
        @endforeach
        <div>
            <label for="travel_starts_on" class="text-[11px] font-bold text-slate-600">Check-in on or after</label>
            <input id="travel_starts_on" name="travel_starts_on" type="date" value="{{ old('travel_starts_on', $promotion->travel_starts_on?->format('Y-m-d')) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="travel_ends_on" class="text-[11px] font-bold text-slate-600">Check-in on or before</label>
            <input id="travel_ends_on" name="travel_ends_on" type="date" value="{{ old('travel_ends_on', $promotion->travel_ends_on?->format('Y-m-d')) }}" class="{{ $input }}">
        </div>
    </fieldset>

    <fieldset class="grid grid-cols-2 sm:grid-cols-4 gap-5">
        <legend class="{{ $label }} col-span-2 sm:col-span-4">Itinerary conditions (optional)</legend>
        @foreach (['min_nights' => 'Min nights', 'max_nights' => 'Max nights', 'min_pax' => 'Min travelers', 'max_pax' => 'Max travelers'] as $field => $text)
            <div>
                <label for="{{ $field }}" class="text-[11px] font-bold text-slate-600">{{ $text }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="number" min="1" value="{{ old($field, $promotion->{$field}) }}" class="{{ $input }}">
            </div>
        @endforeach
        <div class="col-span-2 sm:col-span-4">
            <label for="destination_ids" class="text-[11px] font-bold text-slate-600">Destinations (none selected = all)</label>
            <select id="destination_ids" name="destination_ids[]" multiple size="5" class="{{ $input }}">
                @foreach($destinations as $destination)
                    <option value="{{ $destination->id }}" @selected(in_array($destination->id, $selectedDestinations, true))>{{ $destination->name }}</option>
                @endforeach
            </select>
        </div>
    </fieldset>

    <fieldset class="grid grid-cols-2 sm:grid-cols-5 gap-5">
        <legend class="{{ $label }} col-span-2 sm:col-span-5">Limits</legend>
        <div>
            <label for="total_redemption_limit" class="text-[11px] font-bold text-slate-600">Total redemptions</label>
            <input id="total_redemption_limit" name="total_redemption_limit" type="number" min="1" placeholder="Unlimited" value="{{ old('total_redemption_limit', $promotion->total_redemption_limit) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="per_traveler_limit" class="text-[11px] font-bold text-slate-600">Per traveler *</label>
            <input id="per_traveler_limit" name="per_traveler_limit" type="number" min="1" max="50" required value="{{ old('per_traveler_limit', $promotion->per_traveler_limit) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="codes_per_traveler_per_day" class="text-[11px] font-bold text-slate-600">Codes / traveler / day *</label>
            <input id="codes_per_traveler_per_day" name="codes_per_traveler_per_day" type="number" min="1" max="50" required value="{{ old('codes_per_traveler_per_day', $promotion->codes_per_traveler_per_day) }}" aria-describedby="allowance-hint" class="{{ $input }}">
        </div>
        <div>
            <label for="offer_valid_hours" class="text-[11px] font-bold text-slate-600">Code valid (hours) *</label>
            <input id="offer_valid_hours" name="offer_valid_hours" type="number" min="1" max="720" required value="{{ old('offer_valid_hours', $promotion->offer_valid_hours) }}" class="{{ $input }}">
        </div>
        <div>
            <label for="award_weight" class="text-[11px] font-bold text-slate-600">Award weight *</label>
            <input id="award_weight" name="award_weight" type="number" min="0" max="100" required value="{{ old('award_weight', $promotion->award_weight) }}" aria-describedby="allowance-hint" class="{{ $input }}">
        </div>
        <p id="allowance-hint" class="col-span-2 sm:col-span-5 text-[11px] text-slate-400">The daily allowance stops travelers from reshuffling for codes without limit; after it, itineraries are shown without a code. When several campaigns match, one is picked at random in proportion to its weight (0 never awards).</p>
    </fieldset>

    <div>
        <label for="terms" class="{{ $label }}">Additional terms shown to travelers</label>
        <textarea id="terms" name="terms" rows="3" maxlength="2000" class="{{ $input }}">{{ old('terms', $promotion->terms) }}</textarea>
    </div>

    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promotion->is_active)) class="mt-0.5 rounded border-slate-300 text-sky-600">
        <span class="text-sm text-slate-700"><span class="font-bold">Active</span> — new Lucky itineraries can receive codes and checkout accepts them. Leave off until the terms are final.</span>
    </label>
</div>
