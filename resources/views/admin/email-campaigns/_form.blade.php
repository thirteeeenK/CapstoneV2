{{-- Shared create/edit fields. Content is plain text: placeholders are filled in and everything is escaped
     when the email renders. Times are Philippine time. --}}
@php
    $input = 'w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20';
    $label = 'block text-sm font-bold text-slate-800 mb-1';
    $hint = 'text-xs text-slate-500 mt-1';
    $audience = $campaign->audienceFilters();
    $initial = [
        'mode' => old('send_mode', $campaign->send_mode ?? 'manual'),
        'trigger' => old('trigger', $campaign->trigger ?? ''),
        'target' => old('cta_target', $campaign->cta_target ?? 'lucky'),
        'segment' => old('audience.segment', $audience['segment']),
        'subject' => old('subject', $campaign->subject ?? ''),
        'headline' => old('headline', $campaign->headline ?? ''),
        'body' => old('body', $campaign->body ?? ''),
        'ctaLabel' => old('cta_label', $campaign->cta_label ?? ''),
    ];
    $templates = [
        'promotion_live' => [
            'subject' => 'Try your luck: {discount} on island trips',
            'headline' => '{promotion} is live!',
            'body' => "Our {promotion} promotion just started. Shuffle a surprise itinerary on the I'm Feeling Lucky page for a chance to win {discount}.\n\nA won discount is applied to that itinerary automatically at checkout, with nothing to type. Your attempts reset every day at midnight.",
            'ctaLabel' => 'Try your luck',
        ],
        'package_published' => [
            'subject' => 'New tour package: {package}',
            'headline' => 'Discover {package} in {destination}',
            'body' => "We just added {package}, a new tour package in {destination} from {price}.\n\nSee what's included and pick your dates before the best ones fill up.",
            'ctaLabel' => 'See the package',
        ],
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
        templates: @js($templates),
        previewHtml: '',
        previewTimer: null,
        recipients: null,
        countTimer: null,
        applyTemplate() {
            const template = this.templates[this.trigger];
            if (! template) return;
            if (! this.subject || ! this.headline || ! this.body || confirm('Replace the subject, headline, body and button text with the suggested wording for this trigger?')) {
                this.subject = template.subject; this.headline = template.headline; this.body = template.body; this.ctaLabel = template.ctaLabel;
            }
        },
        formData() {
            const data = new FormData(this.$root.closest('form'));
            data.delete('image');
            data.delete('_method');
            return data;
        },
        refreshPreview() {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(async () => {
                try {
                    const response = await fetch(@js(route('admin.email-campaigns.preview')), { method: 'POST', body: this.formData(), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                    if (response.ok) this.previewHtml = await response.text();
                } catch (error) { /* keep the last preview */ }
            }, 350);
        },
        refreshCount() {
            clearTimeout(this.countTimer);
            this.countTimer = setTimeout(async () => {
                try {
                    const response = await fetch(@js(route('admin.email-campaigns.audience-count')), { method: 'POST', body: this.formData(), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                    if (response.ok) this.recipients = (await response.json()).count;
                } catch (error) { this.recipients = null; }
            }, 300);
        },
    }"
    x-init="refreshPreview(); refreshCount()"
    @input.debounce.100ms="refreshPreview()" @change="refreshPreview(); refreshCount()"
    class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] gap-6">

    <div class="space-y-6 min-w-0">
        @if($campaign->exists)
            <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
        @endif

        <section class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-5">
            <h2 class="font-headline text-base font-bold text-slate-900">How it sends</h2>
            <fieldset>
                <legend class="sr-only">Sending mode</legend>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach ([
                        'manual' => ['Manual', 'send', 'Preview, test, then press Send now.'],
                        'scheduled' => ['Scheduled', 'schedule_send', 'Sends once at a date and time you choose.'],
                        'automatic' => ['Automatic', 'bolt', 'Sends whenever its trigger happens, while switched on.'],
                    ] as $value => [$title, $icon, $desc])
                        <label class="flex cursor-pointer gap-3 rounded-2xl border p-4 transition"
                               :class="mode === @js($value) ? 'border-sky-500 bg-sky-50/60 ring-2 ring-sky-500/20' : 'border-slate-200 hover:bg-slate-50'">
                            <input type="radio" name="send_mode" value="{{ $value }}" x-model="mode" class="mt-1 accent-sky-600">
                            <span>
                                <span class="flex items-center gap-1.5 text-sm font-bold text-slate-900"><span class="material-symbols-outlined text-[18px] text-sky-600" aria-hidden="true">{{ $icon }}</span>{{ $title }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div x-show="mode === 'scheduled'" x-cloak>
                <label for="scheduled_at" class="{{ $label }}">Send on (Philippine time)</label>
                <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $campaign->scheduled_at?->timezone('Asia/Manila')->format('Y-m-d\TH:i')) }}" class="{{ $input }} sm:w-72">
            </div>

            <div x-show="mode === 'automatic'" x-cloak class="space-y-2">
                <label for="trigger" class="{{ $label }}">Send when</label>
                <select id="trigger" name="trigger" x-model="trigger" @change="applyTemplate()" class="{{ $input }} sm:w-96">
                    <option value="">Choose a trigger…</option>
                    @foreach (\App\Models\EmailCampaign::TRIGGERS as $value => $text)
                        <option value="{{ $value }}">{{ $text }}</option>
                    @endforeach
                </select>
                <p class="{{ $hint }}">Each promotion or package is announced once. Switching the campaign on never announces ones that went live before.</p>
            </div>
        </section>

        <section class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-5">
            <h2 class="font-headline text-base font-bold text-slate-900">Email content</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="name" class="{{ $label }}">Campaign name (admins only) *</label>
                    <input id="name" name="name" required maxlength="120" value="{{ old('name', $campaign->name) }}" placeholder="e.g. Holy Week getaway" class="{{ $input }}">
                </div>
                <div>
                    <label for="subject" class="{{ $label }}">Subject line *</label>
                    <input id="subject" name="subject" required maxlength="150" x-model="subject" class="{{ $input }}">
                </div>
            </div>
            <div>
                <label for="preheader" class="{{ $label }}">Preview text</label>
                <input id="preheader" name="preheader" maxlength="150" value="{{ old('preheader', $campaign->preheader) }}" aria-describedby="preheader-hint" class="{{ $input }}">
                <p id="preheader-hint" class="{{ $hint }}">The grey line inboxes show after the subject.</p>
            </div>
            <div>
                <label for="headline" class="{{ $label }}">Headline *</label>
                <input id="headline" name="headline" required maxlength="150" x-model="headline" class="{{ $input }}">
            </div>
            <div>
                <label for="body" class="{{ $label }}">Message *</label>
                <textarea id="body" name="body" required maxlength="5000" rows="7" x-model="body" aria-describedby="body-hint" class="{{ $input }}"></textarea>
                <p id="body-hint" class="{{ $hint }}">Plain text; leave a blank line between paragraphs. Placeholders: <code>{first_name}</code>, <code>{promotion}</code>, <code>{discount}</code>, <code>{package}</code>, <code>{destination}</code>, <code>{price}</code>. The email always starts with “Hi {first_name},”.</p>
            </div>
            <div>
                <label for="image" class="{{ $label }}">Image</label>
                @if($campaign->image_path)
                    <div class="mb-2 flex items-center gap-3">
                        <img src="{{ asset('storage/'.$campaign->image_path) }}" alt="Current campaign image" class="h-16 w-28 rounded-lg object-cover border border-slate-200">
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="checkbox" name="remove_image" value="1" class="accent-rose-600"> Remove image</label>
                    </div>
                @endif
                <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-describedby="image-hint" class="block text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-bold">
                <p id="image-hint" class="{{ $hint }}">Optional. JPG, PNG or WebP up to 2 MB; about 1200 × 600 looks best. A new image shows in the preview after saving.</p>
            </div>
        </section>

        <section class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-5">
            <h2 class="font-headline text-base font-bold text-slate-900">Button</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="cta_label" class="{{ $label }}">Button text</label>
                    <input id="cta_label" name="cta_label" maxlength="60" x-model="ctaLabel" placeholder="Explore now" class="{{ $input }}">
                </div>
                <div>
                    <label for="cta_target" class="{{ $label }}">Button opens</label>
                    <select id="cta_target" name="cta_target" x-model="target" class="{{ $input }}">
                        @foreach (\App\Models\EmailCampaign::CTA_TARGETS as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                    <p x-show="mode === 'automatic' && trigger === 'package_published'" x-cloak class="{{ $hint }}">New-package emails always open the announced package.</p>
                </div>
            </div>
            <div x-show="target === 'destination'" x-cloak>
                <label for="cta_destination" class="{{ $label }}">Destination</label>
                <select id="cta_destination" name="cta_target_id" :disabled="target !== 'destination'" class="{{ $input }}">
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected((int) old('cta_target_id', $campaign->cta_target_id) === $destination->id)>{{ $destination->name }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="target === 'package'" x-cloak>
                <label for="cta_package" class="{{ $label }}">Tour package</label>
                <select id="cta_package" name="cta_target_id" :disabled="target !== 'package'" class="{{ $input }}">
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected((int) old('cta_target_id', $campaign->cta_target_id) === $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="target === 'custom'" x-cloak>
                <label for="cta_path" class="{{ $label }}">Page path</label>
                <input id="cta_path" name="cta_path" maxlength="255" value="{{ old('cta_path', $campaign->cta_path) }}" placeholder="/destinations" class="{{ $input }}">
                <p class="{{ $hint }}">A page on this site, starting with “/”.</p>
            </div>
            <div x-show="mode !== 'automatic'" x-cloak>
                <label for="promotion_id" class="{{ $label }}">Linked Lucky promotion</label>
                <select id="promotion_id" name="promotion_id" class="{{ $input }}" aria-describedby="promotion-hint">
                    <option value="">None</option>
                    @foreach ($promotions as $promotion)
                        <option value="{{ $promotion->id }}" @selected((int) old('promotion_id', $campaign->promotion_id) === $promotion->id)>{{ $promotion->name }}{{ $promotion->is_active ? '' : ' (paused)' }}</option>
                    @endforeach
                </select>
                <p id="promotion-hint" class="{{ $hint }}">Fills <code>{promotion}</code> and <code>{discount}</code>. Emails stop going out if the promotion ends while the campaign is still sending.</p>
            </div>
        </section>

        <section class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-xs space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-headline text-base font-bold text-slate-900">Audience</h2>
                <p class="text-sm font-bold text-sky-700" aria-live="polite" x-text="recipients === null ? '' : recipients.toLocaleString('en-US') + ' opted-in traveler' + (recipients === 1 ? '' : 's') + ' match right now'"></p>
            </div>
            <p class="text-xs text-slate-500">Only travelers who opted in to deals and destination ideas, are not restricted, and have not unsubscribed. An automatic campaign recounts its audience each time it sends.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="segment" class="{{ $label }}">Who</label>
                    <select id="segment" name="audience[segment]" x-model="segment" class="{{ $input }}">
                        @foreach (\App\Models\EmailCampaign::SEGMENTS as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="segment === 'inactive'" x-cloak>
                    <label for="inactive_days" class="{{ $label }}">No booking request in the last (days)</label>
                    <input id="inactive_days" type="number" name="audience[inactive_days]" min="7" max="730" value="{{ old('audience.inactive_days', $audience['inactive_days']) }}" class="{{ $input }}">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <fieldset>
                    <legend class="{{ $label }}">Preferred destination <span class="font-normal text-slate-500">(any if none ticked)</span></legend>
                    <div class="max-h-44 overflow-y-auto rounded-xl border border-slate-200 p-3 space-y-1.5">
                        @foreach ($destinations as $destination)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="audience[destinations][]" value="{{ $destination->name }}" class="accent-sky-600" @checked(in_array($destination->name, old('audience.destinations', $audience['destinations']), true))>
                                {{ $destination->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="{{ $label }}">Traveler type <span class="font-normal text-slate-500">(any if none ticked)</span></legend>
                    <div class="max-h-44 overflow-y-auto rounded-xl border border-slate-200 p-3 space-y-1.5">
                        @forelse ($travelerTypes as $type)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="audience[traveler_types][]" value="{{ $type }}" class="accent-sky-600" @checked(in_array($type, old('audience.traveler_types', $audience['traveler_types']), true))>
                                {{ $type }}
                            </label>
                        @empty
                            <p class="text-xs text-slate-500">No traveler types are set up in Onboarding Options.</p>
                        @endforelse
                    </div>
                </fieldset>
            </div>
            <p class="text-xs text-slate-500">Destination and traveler type come from each traveler’s onboarding answers.</p>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <button type="submit" name="intent" value="draft" class="min-h-11 px-5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 font-bold text-sm cursor-pointer"
                    x-text="mode === 'automatic' ? 'Save, keep off' : 'Save draft'">Save draft</button>
            <button type="submit" name="intent" value="schedule" x-show="mode === 'scheduled'" x-cloak class="min-h-11 px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm cursor-pointer">Schedule</button>
            <button type="submit" name="intent" value="activate" x-show="mode === 'automatic'" x-cloak class="min-h-11 px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm cursor-pointer">Save and switch on</button>
        </div>
        <p x-show="mode === 'manual'" class="text-right text-xs text-slate-500">After saving, preview the email and send yourself a test before pressing Send now.</p>
    </div>

    <aside class="min-w-0 xl:sticky xl:top-6 self-start">
        <div class="rounded-3xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="border-b border-slate-100 px-4 py-3">
                <h2 class="text-sm font-bold text-slate-900">Live preview</h2>
                <p class="text-[11px] text-slate-500">With sample values for the placeholders.</p>
            </div>
            <iframe title="Email preview" :srcdoc="previewHtml" sandbox="" class="h-[640px] w-full border-0 bg-slate-50"></iframe>
        </div>
    </aside>
</div>
