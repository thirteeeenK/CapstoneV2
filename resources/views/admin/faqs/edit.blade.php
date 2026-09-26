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

            </div>

            <fieldset>
                <legend class="text-xs font-bold uppercase tracking-wider text-slate-700">Visibility channels</legend>
                <p class="mt-1 text-[11px] text-slate-500">These settings are independent. Enable either channel, both, or neither.</p>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label for="is_active" class="flex min-h-24 items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 cursor-pointer transition hover:border-sky-300 focus-within:ring-2 focus-within:ring-sky-500/30">
                        <input type="hidden" name="is_active" value="0">
                        <input id="is_active" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $faq->is_active)) class="mt-0.5 h-5 w-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        <span>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-slate-900"><span class="material-symbols-outlined text-[18px] text-sky-600" aria-hidden="true">smart_toy</span>Show to SunnyBot</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">Allows the chatbot to match and return this answer.</span>
                        </span>
                    </label>
                    <label for="show_on_landing" class="flex min-h-24 items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 cursor-pointer transition hover:border-emerald-300 focus-within:ring-2 focus-within:ring-emerald-500/30">
                        <input type="hidden" name="show_on_landing" value="0">
                        <input id="show_on_landing" type="checkbox" name="show_on_landing" value="1" @checked((bool) old('show_on_landing', $faq->show_on_landing)) class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>
                            <span class="flex items-center gap-1.5 text-sm font-bold text-slate-900"><span class="material-symbols-outlined text-[18px] text-emerald-600" aria-hidden="true">help_center</span>Show on FAQ page</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">Displays this answer in the public FAQ section.</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <a href="{{ route('admin.faqs.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition">Save Changes</button>
            </div>
        </form>

    </div>
@endsection
