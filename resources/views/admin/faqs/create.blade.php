@extends('layouts.admin')

@section('title', 'Create New FAQ | SunnyTrips Admin')

@section('content')
    <div class="max-w-4xl mx-auto pb-12 font-body">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.faqs.index') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    <span>Back to FAQ List</span>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Create New FAQ</h1>
                <p class="text-xs text-slate-500 mt-1">SunnyBot will use this predefined answer when a user asks a matching question.</p>
            </div>
        </div>

        <form action="{{ route('admin.faqs.store') }}" method="POST" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6">
            @csrf

            @if($errors->any())
                <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    <p class="font-bold">Please correct the highlighted FAQ fields.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="question" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Question *</label>
                <input id="question" type="text" name="question" required maxlength="255" value="{{ old('question') }}" placeholder="e.g. Does SunnyTrips support airline ticket booking?"
                       @error('question') aria-invalid="true" aria-describedby="question-error" @enderror
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                @error('question')<p id="question-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="answer" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Predefined Answer *</label>
                <textarea id="answer" name="answer" required maxlength="5000" rows="6" placeholder="Write the exact answer SunnyBot should give..."
                          aria-describedby="answer-hint @error('answer') answer-error @enderror" @error('answer') aria-invalid="true" @enderror
                          class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 leading-relaxed focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">{{ old('answer') }}</textarea>
                <p id="answer-hint" class="text-[11px] text-slate-400 mt-1">You may use **bold**, ### headings, and - bullets. They render nicely in the chat widget.</p>
                @error('answer')<p id="answer-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="keywords" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Keywords / Aliases</label>
                    <input id="keywords" type="text" name="keywords" maxlength="500" value="{{ old('keywords') }}" placeholder="e.g. airline, flight, plane ticket, book flights"
                           aria-describedby="keywords-hint @error('keywords') keywords-error @enderror" @error('keywords') aria-invalid="true" @enderror
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                    <p id="keywords-hint" class="text-[11px] text-slate-400 mt-1">Comma-separated words and phrases users might use. Improves keyword matching.</p>
                    @error('keywords')<p id="keywords-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="category" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Category</label>
                    <input id="category" type="text" name="category" maxlength="100" value="{{ old('category') }}" placeholder="e.g. Booking, Services, Policies"
                           @error('category') aria-invalid="true" aria-describedby="category-error" @enderror
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                    @error('category')<p id="category-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="sort_order" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Sort Order</label>
                    <input id="sort_order" type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                           aria-describedby="sort-order-hint @error('sort_order') sort-order-error @enderror" @error('sort_order') aria-invalid="true" @enderror
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                    <p id="sort-order-hint" class="text-[11px] text-slate-400 mt-1">Lower numbers appear first.</p>
                    @error('sort_order')<p id="sort-order-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>

            </div>

            <fieldset>
                <legend class="text-xs font-bold uppercase tracking-wider text-slate-700">Visibility channels</legend>
                <p class="mt-1 text-[11px] text-slate-500">These settings are independent. Enable either channel, both, or neither.</p>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="is_active" class="flex min-h-24 items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 cursor-pointer transition hover:border-sky-300 focus-within:ring-2 focus-within:ring-sky-500/30">
                            <input type="hidden" name="is_active" value="0">
                            <input id="is_active" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', true)) @error('is_active') aria-invalid="true" aria-describedby="is-active-error" @enderror class="mt-0.5 h-5 w-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <span>
                                <span class="flex items-center gap-1.5 text-sm font-bold text-slate-900"><span class="material-symbols-outlined text-[18px] text-sky-600" aria-hidden="true">smart_toy</span>Show to SunnyBot</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Allows the chatbot to match and return this answer.</span>
                            </span>
                        </label>
                        @error('is_active')<p id="is-active-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="show_on_landing" class="flex min-h-24 items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 cursor-pointer transition hover:border-emerald-300 focus-within:ring-2 focus-within:ring-emerald-500/30">
                            <input type="hidden" name="show_on_landing" value="0">
                            <input id="show_on_landing" type="checkbox" name="show_on_landing" value="1" @checked((bool) old('show_on_landing', true)) @error('show_on_landing') aria-invalid="true" aria-describedby="show-on-landing-error" @enderror class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>
                                <span class="flex items-center gap-1.5 text-sm font-bold text-slate-900"><span class="material-symbols-outlined text-[18px] text-emerald-600" aria-hidden="true">help_center</span>Show on FAQ page</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">Displays this answer in the public FAQ section.</span>
                            </span>
                        </label>
                        @error('show_on_landing')<p id="show-on-landing-error" class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <a href="{{ route('admin.faqs.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition">Save FAQ</button>
            </div>
        </form>

    </div>
@endsection
