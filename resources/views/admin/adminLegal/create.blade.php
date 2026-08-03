@extends('layouts.admin')

@section('title', "Add Legal Document | SunnyTrips Admin")

@section('content')
    <div class="pb-12 max-w-4xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.legal.index') }}" class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 font-semibold transition-colors mb-2">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Back to Legal Documents
                </a>
                <h1 class="text-xl font-bold text-slate-900">Add Legal Document</h1>
                <p class="text-xs text-slate-500 mt-1">Create a new legal document with multiple sections.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-xs px-4 py-3 rounded-md mb-6 space-y-1">
                <p class="font-bold">Please correct the errors below:</p>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white border border-slate-200 rounded-lg shadow-sm p-6">
            <form action="{{ route('admin.legal.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="key" class="block text-xs font-semibold text-slate-700">
                            Unique Key <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="key" name="key" value="{{ old('key') }}" required
                               placeholder="e.g. refund_policy"
                               class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                        <p class="text-[10px] text-slate-400">A unique identifier containing only letters, numbers, and underscores.</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="title" class="block text-xs font-semibold text-slate-700">
                            Page Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" required
                               placeholder="e.g. Refund Policy"
                               class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="version" class="block text-xs font-semibold text-slate-700">
                            Version <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="version" name="version" value="{{ old('version', '1.0') }}" required
                               placeholder="e.g. 1.0"
                               class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                    </div>
                </div>

                {{-- Sections repeater --}}
                <div
                    x-data="{
                        sections: {{ Js::from(old('sections', [['heading' => '', 'body' => '']])) }},
                        addSection() {
                            this.sections.push({ heading: '', body: '' });
                        },
                        removeSection(index) {
                            if (this.sections.length > 1) this.sections.splice(index, 1);
                        },
                        moveSection(index, dir) {
                            const target = index + dir;
                            if (target < 0 || target >= this.sections.length) return;
                            [this.sections[index], this.sections[target]] = [this.sections[target], this.sections[index]];
                        }
                    }"
                    class="space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Content Sections</h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">Each section has a heading and body. Body content supports basic HTML.</p>
                        </div>
                        <button type="button" x-on:click="addSection()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-ocean-600 text-white text-xs font-semibold hover:bg-ocean-700 transition-colors">
                            <span class="material-symbols-outlined text-[14px]">add</span>
                            Add Section
                        </button>
                    </div>

                    <template x-for="(section, index) in sections" :key="index">
                        <div class="border border-slate-200 rounded-lg p-4 space-y-3 bg-slate-50/40">
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <span class="material-symbols-outlined text-[14px]">notes</span>
                                    Section <span x-text="index + 1"></span>
                                </span>
                                <div class="flex items-center gap-1">
                                    <button type="button" x-on:click="moveSection(index, -1)"
                                            class="w-7 h-7 flex items-center justify-center rounded-md text-slate-500 hover:bg-slate-200 hover:text-slate-700 transition-colors"
                                            title="Move up">
                                        <span class="material-symbols-outlined text-[16px]">arrow_upward</span>
                                    </button>
                                    <button type="button" x-on:click="moveSection(index, 1)"
                                            class="w-7 h-7 flex items-center justify-center rounded-md text-slate-500 hover:bg-slate-200 hover:text-slate-700 transition-colors"
                                            title="Move down">
                                        <span class="material-symbols-outlined text-[16px]">arrow_downward</span>
                                    </button>
                                    <button type="button" x-on:click="removeSection(index)"
                                            x-bind:disabled="sections.length <= 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md text-rose-500 hover:bg-rose-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                            title="Remove section">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label :for="'heading-' + index" class="block text-[11px] font-semibold text-slate-600">Heading</label>
                                <input type="text"
                                       :name="`sections[${index}][heading]`"
                                       :id="'heading-' + index"
                                       x-model="section.heading"
                                       required
                                       placeholder="Section heading"
                                       class="w-full bg-white border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" />
                            </div>

                            <div class="space-y-1.5">
                                <label :for="'body-' + index" class="block text-[11px] font-semibold text-slate-600">Body (HTML allowed)</label>
                                <textarea :id="'body-' + index"
                                          :name="`sections[${index}][body]`"
                                          x-model="section.body"
                                          rows="4"
                                          required
                                          placeholder="Body content"
                                          class="w-full bg-white border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 font-mono focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"></textarea>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('admin.legal.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-md bg-slate-100 text-slate-700 text-xs font-semibold hover:bg-slate-200 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-md bg-ocean-600 text-white text-xs font-semibold hover:bg-ocean-700 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Save Document
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
