{{-- Shared create/edit fields for a knowledge article. --}}
@if ($errors->any())
    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
        <p class="font-bold">Please correct the highlighted fields.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $input = 'w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20';
    $labelClass = 'block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5';
@endphp

<div>
    <label for="title" class="{{ $labelClass }}">Title *</label>
    <input id="title" type="text" name="title" required maxlength="255" value="{{ old('title', $article->title) }}" placeholder="e.g. Getting around El Nido town"
           @error('title') aria-invalid="true" aria-describedby="title-error" @enderror class="{{ $input }} font-bold">
    @error('title')<p id="title-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
</div>

<div>
    <label for="body" class="{{ $labelClass }}">Guidance *</label>
    <textarea id="body" name="body" required maxlength="3000" rows="6" placeholder="Short, factual guidance travelers can rely on."
              aria-describedby="body-hint @error('body') body-error @enderror" @error('body') aria-invalid="true" @enderror class="{{ $input }} leading-relaxed">{{ old('body', $article->body) }}</textarea>
    <p id="body-hint" class="text-[11px] text-slate-400 mt-1">Keep it short. Do not include prices, discounts or availability: SunnyBot quotes those only from the catalog. Instruction-like sentences are never shown.</p>
    @error('body')<p id="body-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <div>
        <label for="category" class="{{ $labelClass }}">Category *</label>
        <select id="category" name="category" required @error('category') aria-invalid="true" aria-describedby="category-error" @enderror class="{{ $input }} bg-white">
            @foreach (\App\Models\KnowledgeArticle::CATEGORIES as $value => $label)
                <option value="{{ $value }}" @selected(old('category', $article->category) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('category')<p id="category-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="destination_id" class="{{ $labelClass }}">Destination</label>
        <select id="destination_id" name="destination_id" aria-describedby="destination-hint" @error('destination_id') aria-invalid="true" @enderror class="{{ $input }} bg-white">
            <option value="">General (applies to every destination)</option>
            @foreach ($destinations as $destination)
                <option value="{{ $destination->id }}" @selected((string) old('destination_id', $article->destination_id) === (string) $destination->id)>{{ $destination->name }}</option>
            @endforeach
        </select>
        <p id="destination-hint" class="text-[11px] text-slate-400 mt-1">Destination guidance is only used for questions about that destination.</p>
        @error('destination_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<fieldset class="rounded-2xl border border-slate-200 p-4 sm:p-5">
    <legend class="px-1 text-xs font-bold uppercase tracking-wider text-slate-700">Source &amp; review</legend>
    <p class="text-[11px] text-slate-500 mb-4">Published articles need a responsible source, reviewer and review date. Reference links are shown to travelers; SunnyBot never fetches them.</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="source_label" class="{{ $labelClass }}">Source *</label>
            <input id="source_label" type="text" name="source_label" required maxlength="255" value="{{ old('source_label', $article->source_label) }}" placeholder="e.g. Municipal tourism office"
                   @error('source_label') aria-invalid="true" aria-describedby="source-label-error" @enderror class="{{ $input }}">
            @error('source_label')<p id="source-label-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="source_url" class="{{ $labelClass }}">Reference link</label>
            <input id="source_url" type="url" name="source_url" maxlength="2048" value="{{ old('source_url', $article->source_url) }}" placeholder="https://"
                   @error('source_url') aria-invalid="true" aria-describedby="source-url-error" @enderror class="{{ $input }}">
            @error('source_url')<p id="source-url-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="reviewed_by" class="{{ $labelClass }}">Reviewed by</label>
            <input id="reviewed_by" type="text" name="reviewed_by" maxlength="255" value="{{ old('reviewed_by', $article->reviewed_by) }}" placeholder="Name or team"
                   @error('reviewed_by') aria-invalid="true" aria-describedby="reviewed-by-error" @enderror class="{{ $input }}">
            @error('reviewed_by')<p id="reviewed-by-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="reviewed_at" class="{{ $labelClass }}">Review date</label>
            <input id="reviewed_at" type="date" name="reviewed_at" max="{{ today()->toDateString() }}" value="{{ old('reviewed_at', $article->reviewed_at?->toDateString()) }}"
                   @error('reviewed_at') aria-invalid="true" aria-describedby="reviewed-at-error" @enderror class="{{ $input }}">
            @error('reviewed_at')<p id="reviewed-at-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="expires_at" class="{{ $labelClass }}">Expires on</label>
            <input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at', $article->expires_at?->toDateString()) }}" aria-describedby="expires-hint"
                   @error('expires_at') aria-invalid="true" @enderror class="{{ $input }}">
            <p id="expires-hint" class="text-[11px] text-slate-400 mt-1">Optional. After this date SunnyBot stops citing the article.</p>
            @error('expires_at')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="{{ $labelClass }}">Status *</label>
            <select id="status" name="status" required @error('status') aria-invalid="true" aria-describedby="status-error" @enderror class="{{ $input }} bg-white">
                @foreach (['draft' => 'Draft (not used by SunnyBot)', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $article->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<p id="status-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
</fieldset>
