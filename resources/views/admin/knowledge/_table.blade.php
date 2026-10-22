{{-- Live-search results partial: knowledge articles + pagination only.
    Rendered standalone for AJAX requests (X-Requested-With) and inline for full page loads. --}}

<div id="listings-results">
    @if ($articles->isEmpty())
        <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 text-slate-300" aria-hidden="true">menu_book</span>
            <p class="text-sm font-bold text-slate-700">No travel guidance matches these filters.</p>
            <a href="{{ route('admin.knowledge.create') }}" class="mt-3 inline-block text-xs font-bold text-sky-600 hover:underline">+ Write the first article</a>
        </div>
    @else
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Travel guidance table">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Travel guidance articles with review, expiry and search-index state</caption>
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-400">
                            <th scope="col" class="py-3 px-4 font-bold w-[34%]">Article</th>
                            <th scope="col" class="py-3 px-4 font-bold">Destination</th>
                            <th scope="col" class="py-3 px-4 font-bold">Source &amp; review</th>
                            <th scope="col" class="py-3 px-4 font-bold">Status</th>
                            <th scope="col" class="py-3 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($articles as $article)
                            @php
                                $expired = $article->expires_at && $article->expires_at->lt(today());
                                $stale = $article->embeddingIsStale();
                            @endphp
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition align-top">
                                <td class="py-4 px-4 max-w-md">
                                    <p class="font-bold text-slate-900 line-clamp-2">{{ $article->title }}</p>
                                    <p class="text-[11px] text-slate-500 mt-1 line-clamp-2">{{ $article->body }}</p>
                                    <span class="mt-1.5 inline-block text-[10px] uppercase tracking-wider font-extrabold text-sky-600 bg-sky-50 px-2 py-0.5 rounded border border-sky-100">{{ \App\Models\KnowledgeArticle::CATEGORIES[$article->category] ?? $article->category }}</span>
                                    @if ($article->hasInstructionLikeText())
                                        <p class="mt-1.5 text-[11px] font-bold text-rose-700">Contains instruction-like text; those sentences are never shown to travelers. Please remove them.</p>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-xs text-slate-600">{{ $article->destination?->name ?? 'General' }}</td>
                                <td class="py-4 px-4 text-xs text-slate-600">
                                    <p class="font-bold text-slate-800">{{ $article->source_label }}</p>
                                    @if ($article->source_url)
                                        <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-sky-600 hover:underline break-all">Reference link</a>
                                    @endif
                                    <p class="mt-1">{{ $article->reviewed_at ? 'Reviewed '.$article->reviewed_at->toDateString() : 'No review date' }}{{ $article->reviewed_by ? ' by '.$article->reviewed_by : '' }}</p>
                                    @if ($article->expires_at)
                                        <p class="{{ $expired ? 'text-amber-700 font-bold' : '' }}">{{ $expired ? 'Expired' : 'Expires' }} {{ $article->expires_at->toDateString() }}</p>
                                    @endif
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex flex-col gap-1.5 w-max">
                                        <span class="inline-flex items-center rounded-lg border px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider {{ $article->isCitable() ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                                            {{ $article->isCitable() ? 'Citable' : ucfirst($expired && $article->status === 'published' ? 'expired' : $article->status) }}
                                        </span>
                                        <span class="inline-flex items-center rounded-lg border px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider {{ $stale ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-white text-slate-500' }}"
                                              title="{{ $stale ? 'Semantic search is off for this article until it is re-embedded (php artisan embed:all --stale); keyword matching still works.' : 'Search index matches the current text.' }}">
                                            {{ $stale ? 'Index stale' : 'Index current' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <a href="{{ route('admin.knowledge.edit', $article) }}" title="Edit article" aria-label="Edit {{ $article->title }}"
                                           class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>
                                        </a>
                                        <form action="{{ route('admin.knowledge.destroy', $article) }}" method="POST" onsubmit="return confirm('Delete this article? Past chat answers will show it as withdrawn.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete article" aria-label="Delete {{ $article->title }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 cursor-pointer">
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
                {{ $articles->links() }}
            </div>
        </div>
    @endif
</div>
