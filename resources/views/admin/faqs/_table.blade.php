{{-- Live-search results partial: FAQ table + pagination only.
    The filter controls (search + category/visibility selects) live in the wrapper so the input never loses focus on AJAX swaps.
    Rendered standalone for AJAX requests (X-Requested-With) and inline for full page loads. --}}

<div id="listings-results">
    {{-- FAQs Table --}}
    @if($faqs->isEmpty())
        <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">quiz</span>
            <p class="text-sm font-bold text-slate-700">No FAQs found matching your criteria.</p>
            <a href="{{ route('admin.faqs.create') }}" class="mt-3 inline-block text-xs font-bold text-sky-600 hover:underline">+ Create Your First FAQ</a>
        </div>
    @else
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-400">
                            <th class="py-3 px-6 font-bold">#</th>
                            <th class="py-3 px-6 font-bold">Question</th>
                            <th class="py-3 px-6 font-bold">Category</th>
                            <th class="py-3 px-6 font-bold">Keywords</th>
                            <th class="py-3 px-6 font-bold">Order</th>
                            <th class="py-3 px-6 font-bold">Status</th>
                            <th class="py-3 px-6 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($faqs as $index => $faq)
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition">
                                <td class="py-4 px-6 text-slate-400 font-black">{{ $faqs->firstItem() + $index }}</td>
                                <td class="py-4 px-6 max-w-md">
                                    <p class="font-bold text-slate-900 line-clamp-2">{{ $faq->question }}</p>
                                    <p class="text-[11px] text-slate-500 mt-1 line-clamp-2">{{ $faq->answer }}</p>
                                </td>
                                <td class="py-4 px-6">
                                    @if($faq->category)
                                        <span class="inline-block text-[10px] uppercase tracking-wider font-extrabold text-sky-600 bg-sky-50 px-2 py-0.5 rounded border border-sky-100">
                                            {{ $faq->category }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-xs text-slate-500 max-w-[220px] truncate">{{ $faq->keywords ?: '—' }}</td>
                                <td class="py-4 px-6 text-xs text-slate-500">{{ $faq->sort_order }}</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border {{ $faq->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                                        {{ $faq->is_active ? 'Visible' : 'Hidden' }}
                                    </span>
                                    @if($faq->is_active && !$faq->show_on_landing)
                                        <span class="ml-1 px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border bg-sky-50 text-sky-700 border-sky-200">
                                            Chatbot only
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <form action="{{ route('admin.faqs.toggle-visibility', $faq->id) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1.5 rounded-xl border text-xs font-bold transition flex items-center gap-1 cursor-pointer {{ $faq->is_active ? 'border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100' : 'border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-100' }}">
                                                <span class="material-symbols-outlined text-[15px]">{{ $faq->is_active ? 'visibility_off' : 'visibility' }}</span>
                                                <span>{{ $faq->is_active ? 'Hide' : 'Show' }}</span>
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.faqs.edit', $faq->id) }}"
                                           class="px-2.5 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold transition flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                            <span>Edit</span>
                                        </a>

                                        <form action="{{ route('admin.faqs.destroy', $faq->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this FAQ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition rounded-lg hover:bg-rose-50 cursor-pointer">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $faqs->links() }}
            </div>
        </div>
    @endif
</div>