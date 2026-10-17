@extends('layouts.admin')

@section('title', 'Settings | SunnyTrips Admin')

@section('content')
    @php
        $titles = ['business' => 'Business information', 'accounts' => 'Account deletion', 'payments' => 'Booking & payments', 'support' => 'Support availability', 'chatbot' => 'Guest chatbot limits', 'notifications' => 'Admin email notifications'];
        $descriptions = ['business' => 'Contact details shown to travelers.', 'accounts' => 'Choose how new customer deletion requests are handled.', 'payments' => 'Set payment windows for new approvals. Existing bookings keep their agreed deadlines.', 'support' => 'Control when travelers can request a human agent.', 'chatbot' => 'Manage the message allowance for visitors who are not signed in.', 'notifications' => 'Choose which operational emails your team receives.'];
    @endphp
    <div class="mx-auto max-w-5xl pb-12 font-body">
        <div class="mb-8">
            <p class="mb-2 font-label text-xs font-bold uppercase tracking-widest text-ocean-700">Administration</p>
            <h1 class="font-headline text-3xl font-bold text-ink-900">Settings</h1>
            <p class="mt-2 text-sm text-slate-500">Manage platform preferences and security for your admin account. Save each section separately.</p>
        </div>
        @if(session('success'))
            <p role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" tabindex="-1" x-data x-init="$el.focus()" class="mb-6 rounded-xl border border-coral-200 bg-coral-50 p-4 text-sm text-coral-700">
                <p class="font-bold">Review these changes before saving.</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <nav aria-label="Settings sections" class="mb-6 flex flex-wrap gap-2">
            @foreach($titles as $key => $title)
                <a href="#{{ $key }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-ocean-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600">{{ $title }}</a>
            @endforeach
            <a href="#security" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-ocean-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ocean-600">Security &amp; 2FA</a>
        </nav>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            @foreach($titles as $section => $title)
                @php
                    $values = old('section') === $section ? old('values', $sections[$section]['values']) : $sections[$section]['values'];
                    $revision = old('section') === $section ? old('revision', $sections[$section]['revision']) : $sections[$section]['revision'];
                @endphp
                <section id="{{ $section }}" aria-labelledby="{{ $section }}-title" class="scroll-mt-24 border-b border-slate-200 p-5 last:border-b-0 sm:p-8">
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div>
                            <p aria-hidden="true" class="mb-2 font-label text-xs font-bold text-ocean-600">0{{ $loop->iteration }}</p>
                            <h2 id="{{ $section }}-title" class="font-headline text-lg font-bold text-ink-900">{{ $title }}</h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $descriptions[$section] }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.settings.update', $section) }}" class="min-w-0 space-y-5 lg:col-span-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="section" value="{{ $section }}">
                            <input type="hidden" name="revision" value="{{ $revision }}">
                            @if($section === 'business')
                                @foreach(['display_name' => ['Business name', 'text', 120], 'support_email' => ['Support email', 'email', 254], 'phone' => ['Phone number', 'text', 40], 'facebook_url' => ['Facebook URL', 'url', 2048], 'instagram_url' => ['Instagram URL', 'url', 2048], 'website_url' => ['Website URL', 'url', 2048]] as $key => [$label, $type, $maximum])
                                    <div>
                                        <x-input-label :for="$section.'-'.$key" :value="$label" />
                                        <x-text-input :id="$section.'-'.$key" :type="$type" name="values[{{ $key }}]" :value="$values[$key] ?? ''" :maxlength="$maximum" :required="$key === 'display_name'" class="mt-1 block w-full" aria-describedby="{{ $section }}-{{ $key }}-error" :aria-invalid="$errors->has('values.'.$key) ? 'true' : 'false'" />
                                        <x-input-error :id="$section.'-'.$key.'-error'" :messages="$errors->get('values.'.$key)" class="mt-1" />
                                    </div>
                                @endforeach
                                <div>
                                    <x-input-label for="business-address" value="Office address" />
                                    <textarea id="business-address" name="values[address]" maxlength="500" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-ocean-500 focus:ring-ocean-500">{{ $values['address'] ?? '' }}</textarea>
                                </div>
                                <p class="text-xs leading-relaxed text-slate-500">Social links must start with https://. Empty optional contact fields are hidden. Mail sender details and sign-in branding are managed separately.</p>
                            @elseif($section === 'payments')
                                <div class="rounded-xl bg-ocean-50 p-4 text-sm text-ocean-900">
                                    Customers can pay in full or reserve with a fixed 50% deposit when their payment deadlines and booking total allow it.
                                </div>
                                <input type="hidden" name="values[installments_enabled]" value="0">
                                <label class="flex min-h-11 items-center gap-3">
                                    <input type="checkbox" name="values[installments_enabled]" value="1" @checked($values['installments_enabled']) class="rounded border-slate-300 text-ocean-700">
                                    <span class="text-sm font-semibold">Offer full payment or 50% deposit on new approvals</span>
                                </label>
                                <input type="hidden" name="values[service_cutoff_hours]" value="{{ $values['service_cutoff_hours'] }}">
                                @foreach(['initial_window_hours' => ['Initial payment window after reservation submission (hours)', 1, 720], 'deposit_min_advance_days' => ['Deposit allowed only when booked more than this many days ahead', 1, 90], 'balance_lead_days' => ['Remaining balance due before trip or fixed service (days)', 1, 90], 'undated_balance_days' => ['Balance window after approval for bookings without travel dates (days)', 1, 90]] as $field => [$label, $minimum, $maximum])
                                    <div>
                                        <x-input-label :for="'payments-'.$field" :value="$label" />
                                        <x-text-input :id="'payments-'.$field" type="number" :name="'values['.$field.']'" :value="$values[$field]" :min="$minimum" :max="$maximum" step="1" required class="mt-1 block w-full" :aria-describedby="'payments-'.$field.'-error'" />
                                        <x-input-error :id="'payments-'.$field.'-error'" :messages="$errors->get('values.'.$field)" class="mt-1" />
                                    </div>
                                @endforeach
                                <p class="text-xs leading-relaxed text-slate-500">New hotel arrivals must be at least two Philippine calendar days ahead. Dated bookings submitted within the deposit threshold, including exactly that many days ahead, require full payment. Initial payment is due within the submission window and before check-in; short-notice stays have a shorter window. Hotel check-in time is used when available, with 9 AM as the date-only fallback. Undated activities retain the balance window after approval. Existing approved terms are preserved; QR expiry does not extend a deadline.</p>
                            @elseif($section === 'accounts')

                                <fieldset class="space-y-3">
                                    <legend class="mb-3 text-sm font-semibold text-slate-800">When a customer deletes their account</legend>
                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                                        <input type="radio" name="values[grace_period]" value="1" @checked(filter_var($values['grace_period'], FILTER_VALIDATE_BOOLEAN)) class="mt-1 text-ocean-600 focus:ring-ocean-500">
                                        <span><span class="block text-sm font-bold">Five-day recovery period</span><span class="text-sm text-slate-500">Logging in before the deadline restores the account.</span></span>
                                    </label>
                                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                                        <input type="radio" name="values[grace_period]" value="0" @checked(! filter_var($values['grace_period'], FILTER_VALIDATE_BOOLEAN)) class="mt-1 text-coral-500 focus:ring-coral-500">
                                        <span><span class="block text-sm font-bold">Delete immediately</span><span class="text-sm text-slate-500">Permanent deletion. The account cannot be recovered.</span></span>
                                    </label>
                                </fieldset>
                                <p class="text-xs leading-relaxed text-slate-500">{{ $sections['accounts']['revision'] ? 'Using the saved admin policy.' : 'Using the environment fallback until this section is saved.' }} Previously scheduled accounts keep their original deadline.</p>
                            @elseif($section === 'support')
                                @foreach(['accept_requests' => 'Accept new human-support requests', 'enforce_hours' => 'Accept requests only during office hours'] as $key => $label)
                                    <input type="hidden" name="values[{{ $key }}]" value="0">
                                    <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="values[{{ $key }}]" value="1" @checked(filter_var($values[$key], FILTER_VALIDATE_BOOLEAN)) class="rounded text-ocean-600 focus:ring-ocean-500">{{ $label }}</label>
                                @endforeach
                                <fieldset class="space-y-3">
                                    <legend class="mb-3 text-sm font-semibold">Office hours — Philippine time (Asia/Manila)</legend>
                                    @foreach(['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'] as $day => $label)
                                        @php($hours = $values['schedule'][$day])
                                        <div class="grid grid-cols-2 items-center gap-3 border-b border-slate-100 pb-3 sm:grid-cols-4">
                                            <input type="hidden" name="values[schedule][{{ $day }}][enabled]" value="0">
                                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="values[schedule][{{ $day }}][enabled]" value="1" @checked(filter_var($hours['enabled'], FILTER_VALIDATE_BOOLEAN)) class="rounded text-ocean-600">{{ $label }}</label>
                                            <input type="hidden" name="values[schedule][{{ $day }}][all_day]" value="0">
                                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="values[schedule][{{ $day }}][all_day]" value="1" @checked(filter_var($hours['all_day'], FILTER_VALIDATE_BOOLEAN)) class="rounded text-ocean-600">All day</label>
                                            <div><label for="{{ $day }}-opens" class="block text-xs text-slate-500">{{ $label }} opens</label><input id="{{ $day }}-opens" type="time" name="values[schedule][{{ $day }}][opens]" value="{{ $hours['opens'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                                            <div><label for="{{ $day }}-closes" class="block text-xs text-slate-500">{{ $label }} closes</label><input id="{{ $day }}-closes" type="time" name="values[schedule][{{ $day }}][closes]" value="{{ $hours['closes'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></div>
                                        </div>
                                    @endforeach
                                </fieldset>
                                <div><x-input-label for="support-unavailable" value="Unavailable message" /><textarea id="support-unavailable" name="values[unavailable_message]" maxlength="500" required rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ $values['unavailable_message'] }}</textarea></div>
                                <p class="text-xs text-slate-500">Existing support conversations continue when intake closes. SunnyBot stays available. Overnight and split shifts are not supported.</p>
                            @elseif($section === 'chatbot')
                                @foreach(['daily_limit' => ['Messages per day', 1000], 'minute_limit' => ['Messages per minute', 10]] as $key => [$label, $maximum])
                                    <div><x-input-label :for="$key" :value="$label" /><x-text-input :id="$key" type="number" min="1" :max="$maximum" name="values[{{ $key }}]" :value="$values[$key]" required class="mt-1 w-full" /></div>
                                @endforeach
                                <p class="text-xs leading-relaxed text-slate-500">Guest allowances are per IP address. Daily allowances reset at midnight in Philippine time. Saving preserves usage already consumed. The independent AI limit remains 10 requests per minute.</p>
                            @else
                                <fieldset class="space-y-3">
                                    <legend class="mb-3 text-sm font-semibold">Email alerts</legend>
                                    @foreach(['booking_requested' => 'New customer booking request', 'payment_submitted' => 'GoTyme payment submitted for review', 'cancellation_requested' => 'Cancellation request', 'support_requested' => 'New customer support inquiry'] as $key => $label)
                                        <input type="hidden" name="values[{{ $key }}]" value="0">
                                        <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="values[{{ $key }}]" value="1" @checked(filter_var($values[$key], FILTER_VALIDATE_BOOLEAN)) class="rounded text-ocean-600">{{ $label }}</label>
                                    @endforeach
                                </fieldset>
                                <div><x-input-label for="notification-recipients" value="Who receives these emails?" /><select id="notification-recipients" name="values[recipient_mode]" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="all" @selected($values['recipient_mode'] === 'all')>All admins</option><option value="selected" @selected($values['recipient_mode'] === 'selected')>Selected admins below</option></select></div>
                                <fieldset class="space-y-3"><legend class="mb-3 text-sm font-semibold">Selected recipients</legend>
                                    @foreach($admins as $admin)
                                        <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="values[admin_ids][]" value="{{ $admin->id }}" @checked(in_array($admin->id, $values['admin_ids'] ?? [])) class="mt-1 rounded text-ocean-600"><span class="min-w-0 break-words">{{ $admin->name }}<span class="block text-xs text-slate-500">{{ $admin->email }}</span></span></label>
                                    @endforeach
                                </fieldset>
                                <p class="text-xs leading-relaxed text-slate-500">Customer emails, account security emails, and existing admin inbox notifications continue independently of these email preferences.</p>
                            @endif
                            <div class="border-t border-slate-100 pt-5"><button type="submit" class="min-h-[44px] rounded-lg bg-ocean-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-ocean-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ocean-600">Save {{ strtolower($title) }}</button></div>
                        </form>
                    </div>
                </section>
            @endforeach
            <section id="security" aria-labelledby="security-title" class="scroll-mt-24 p-5 sm:p-8">
                <div class="grid gap-6 lg:grid-cols-3">
                    <div>
                        <p aria-hidden="true" class="mb-2 font-label text-xs font-bold text-ocean-600">07</p>
                        <h2 id="security-title" class="font-headline text-lg font-bold text-ink-900">Security &amp; 2FA</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">Manage two-factor authentication and recovery codes for your own admin account.</p>
                    </div>
                    <div class="min-w-0 lg:col-span-2">
                        @if(session('two_factor_disabled'))
                            <div role="status" class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                                <p class="font-bold">Two-factor authentication disabled</p>
                                <p class="mt-1">{{ session('two_factor_disabled') }}</p>
                            </div>
                        @endif
                        @include('adminAuth.partials.two-factor-controls', [...$security, 'embedded' => true])
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
