@extends('layouts.admin')

@section('title', 'Edit FAQ | SunnyTrips Admin')

@section('content')
    <div class="max-w-4xl mx-auto pb-12 font-body">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.faqs.index') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    <span>Back to FAQ List</span>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Edit FAQ</h1>
            </div>
        </div>

        <form action="{{ route('admin.faqs.update', $faq->id) }}" method="POST" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Question *</label>
                <input type="text" name="question" required value="{{ old('question', $faq->question) }}" placeholder="e.g. Does SunnyTrips support airline ticket booking?"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                @error('question')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Predefined Answer *</label>
                <textarea name="answer" required rows="6" placeholder="Write the exact answer SunnyBot should give..."
                          class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 leading-relaxed focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">{{ old('answer', $faq->answer) }}</textarea>
                <p class="text-[11px] text-slate-400 mt-1">You may use **bold**, ### headings, and - bullets. They render nicely in the chat widget.</p>
                @error('answer')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Keywords / Aliases</label>
                    <input type="text" name="keywords" value="{{ old('keywords', $faq->keywords) }}" placeholder="e.g. airline, flight, plane ticket, book flights"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                    <p class="text-[11px] text-slate-400 mt-1">Comma-separated words and phrases users might use. Improves keyword matching.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Category</label>
                    <input type="text" name="category" value="{{ old('category', $faq->category) }}" placeholder="e.g. Booking, Services, Policies"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $faq->sort_order) }}" min="0"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                    <p class="text-[11px] text-slate-400 mt-1">Lower numbers appear first.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Visibility</label>
                    <select name="is_active" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white">
                        <option value="1" {{ old('is_active', $faq->is_active) ? 'selected' : '' }}>Visible to SunnyBot</option>
                        <option value="0" {{ !old('is_active', $faq->is_active) ? 'selected' : '' }}>Hidden</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Show on landing page</label>
                <select name="show_on_landing" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white">
                    <option value="1" {{ old('show_on_landing', $faq->show_on_landing) ? 'selected' : '' }}>Show on FAQ page</option>
                    <option value="0" {{ !old('show_on_landing', $faq->show_on_landing) ? 'selected' : '' }}>Chatbot only (hide from FAQ page)</option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Chatbot-only entries stay queryable by SunnyBot via RAG but never appear on the landing page.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <a href="{{ route('admin.faqs.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition">Save Changes</button>
            </div>
        </form>

    </div>
@endsection
