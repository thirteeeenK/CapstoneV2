@extends('layouts.admin')

@section('title', 'Create Onboarding Option | SunnyTrips Admin')

@section('content')
    <div class="max-w-4xl mx-auto pb-12 font-body">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.onboarding-options.index') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    <span>Back to Onboarding Options</span>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Create Onboarding Option</h1>
                <p class="text-xs text-slate-500 mt-1">New options appear immediately on the AI onboarding quiz.</p>
            </div>
        </div>

        <form action="{{ route('admin.onboarding-options.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Option Type *</label>
                    <select name="type" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white">
                        <option value="">Select type...</option>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Name *</label>
                    <input type="text" name="name" required value="{{ old('name') }}" placeholder="e.g. Beachfront"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                    @error('name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Material Icon</label>
                    <input type="text" name="icon" value="{{ old('icon') }}" placeholder="e.g. beach_access"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                    <p class="text-[11px] text-slate-400 mt-1">Optional. Use a <a href="https://fonts.google.com/icons" target="_blank" class="text-sky-600 hover:underline">Material Symbols</a> icon name.</p>
                    @error('icon')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                    <p class="text-[11px] text-slate-400 mt-1">Lower numbers appear first.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Description</label>
                <input type="text" name="description" value="{{ old('description') }}" placeholder="Short helper text shown under the option name"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                <p class="text-[11px] text-slate-400 mt-1">Optional. Useful for vibes and traveler types.</p>
                @error('description')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Image Section --}}
            <div class="space-y-5">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Image</label>
                    <span class="text-[11px] text-slate-400">Either URL or upload — URL takes precedence</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Image URL</label>
                        <input type="url" name="image_url" value="{{ old('image_url') }}" placeholder="https://images.unsplash.com/photo-... or any CDN URL"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" id="image_url_input">
                        <p class="text-[11px] text-slate-400 mt-1">Paste an external image URL (Unsplash, CDN, etc.). Takes priority over upload.</p>
                        @error('image_url')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Or Upload Image</label>
                        <div class="relative">
                            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white cursor-pointer" id="image_file_input">
                            <p class="text-[11px] text-slate-400 mt-1">JPG, PNG, WebP — max 2MB. Stored locally as fallback if URL fails.</p>
                            @error('image_file')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Preview --}}
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <p class="text-xs text-slate-500 mb-2">Preview:</p>
                    <img id="image_preview" src="" alt="Preview" class="max-h-48 w-auto object-contain hidden rounded-lg border border-slate-200" style="max-width: 100%;">
                    <p id="no_preview" class="text-xs text-slate-400">No image selected</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Visibility</label>
                <select name="is_active" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white">
                    <option value="1" {{ old('is_active', true) ? 'selected' : '' }}>Visible on Onboarding</option>
                    <option value="0" {{ !old('is_active', true) ? 'selected' : '' }}>Hidden</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <a href="{{ route('admin.onboarding-options.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition">Save Option</button>
            </div>
        </form>

    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlInput = document.getElementById('image_url_input');
    const fileInput = document.getElementById('image_file_input');
    const preview = document.getElementById('image_preview');
    const noPreview = document.getElementById('no_preview');

    function updatePreview(src) {
        if (src) {
            preview.src = src;
            preview.classList.remove('hidden');
            noPreview.classList.add('hidden');
        } else {
            preview.classList.add('hidden');
            noPreview.classList.remove('hidden');
        }
    }

    urlInput?.addEventListener('input', function() {
        updatePreview(this.value);
    });

    fileInput?.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                updatePreview(e.target.result);
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            updatePreview(urlInput?.value || '');
        }
    });

    // Initial preview from old input (if any)
    if (urlInput?.value) {
        updatePreview(urlInput.value);
    }
});
</script>
@endpush
