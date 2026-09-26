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
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="FAQ management table">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">FAQ questions, visibility channels, and management actions</caption>
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-400">
                            <th class="py-3 px-4 font-bold">#</th>
                            <th class="py-3 px-4 font-bold w-[32%]">Question</th>
                            <th class="py-3 px-4 font-bold">Category</th>
                            <th class="py-3 px-4 font-bold">Keywords</th>
                            <th class="py-3 px-4 font-bold">Order</th>
                            <th class="py-3 px-4 font-bold">Status</th>
                            <th class="py-3 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($faqs as $index => $faq)
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition">
                                <td class="py-4 px-4 text-slate-400 font-black">{{ $faqs->firstItem() + $index }}</td>
                                <td class="py-4 px-4 max-w-md">
                                    <p class="font-bold text-slate-900 line-clamp-2">{{ $faq->question }}</p>
                                    <p class="text-[11px] text-slate-500 mt-1 line-clamp-2">{{ $faq->answer }}</p>
                                </td>
                                <td class="py-4 px-4 max-w-[120px]">
                                    @if($faq->category)
                                        <span class="inline-block max-w-full truncate align-middle text-[10px] uppercase tracking-wider font-extrabold text-sky-600 bg-sky-50 px-2 py-0.5 rounded border border-sky-100" title="{{ $faq->category }}">
                                            {{ $faq->category }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-xs text-slate-500 max-w-[150px] truncate" title="{{ $faq->keywords }}">{{ $faq->keywords ?: '—' }}</td>
                                <td class="py-4 px-4 text-xs text-slate-500">{{ $faq->sort_order }}</td>
                                <td class="py-4 px-4">
                                    <div class="flex w-max items-center gap-1.5" aria-label="FAQ visibility status">
                                        <span class="inline-flex h-8 items-center gap-1 whitespace-nowrap rounded-lg border px-2 text-[10px] font-extrabold uppercase tracking-wider {{ $faq->is_active ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-slate-200 bg-slate-50 text-slate-400' }}">
                                            <span class="material-symbols-outlined text-[14px] leading-none" aria-hidden="true">{{ $faq->is_active ? 'check_circle' : 'cancel' }}</span>
                                            SunnyBot: {{ $faq->is_active ? 'On' : 'Off' }}
                                        </span>
                                        <span class="inline-flex h-8 items-center gap-1 whitespace-nowrap rounded-lg border px-2 text-[10px] font-extrabold uppercase tracking-wider {{ $faq->show_on_landing ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-400' }}">
                                            <span class="material-symbols-outlined text-[14px] leading-none" aria-hidden="true">{{ $faq->show_on_landing ? 'check_circle' : 'cancel' }}</span>
                                            FAQ Page: {{ $faq->show_on_landing ? 'On' : 'Off' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <form action="{{ route('admin.faqs.toggle-visibility', $faq->id) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                    title="{{ $faq->is_active ? 'Hide from SunnyBot' : 'Show to SunnyBot' }}"
                                                    aria-label="{{ $faq->is_active ? 'Hide from SunnyBot' : 'Show to SunnyBot' }}"
                                                    class="flex h-8 w-8 items-center justify-center rounded-lg border transition cursor-pointer {{ $faq->is_active ? 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100' : 'border-slate-200 bg-white text-slate-400 hover:bg-slate-50' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">smart_toy</span>
                                            </button>
                                        </form>

                                        <form action="{{ route('admin.faqs.toggle-page-visibility', $faq->id) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                    title="{{ $faq->show_on_landing ? 'Hide from FAQ page' : 'Show on FAQ page' }}"
                                                    aria-label="{{ $faq->show_on_landing ? 'Hide from FAQ page' : 'Show on FAQ page' }}"
                                                    class="flex h-8 w-8 items-center justify-center rounded-lg border transition cursor-pointer {{ $faq->show_on_landing ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-200 bg-white text-slate-400 hover:bg-slate-50' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">help_center</span>
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.faqs.edit', $faq->id) }}"
                                           title="Edit FAQ"
                                           class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>
                                        </a>

                                        <form action="{{ route('admin.faqs.destroy', $faq->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this FAQ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete FAQ" aria-label="Delete FAQ" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 cursor-pointer">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
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
