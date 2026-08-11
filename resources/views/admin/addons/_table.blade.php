{{-- Live-search results partial: add-ons table only.
    The filter controls (location tabs + search) live in the wrapper so the input never loses focus on AJAX swaps.
    Rendered standalone for AJAX requests (X-Requested-With) and inline for full page loads. --}}

<div id="listings-results">
    <!-- Add-ons Table -->
    <div class="overflow-hidden bg-white border border-slate-200 rounded-lg shadow-sm">
        <table class="w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 w-16">
                        #
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Name
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Type
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Pricing Tiers
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Visibility
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
                @php
                    $currentDestination = null;
                    $counter = 0;
                @endphp

                @forelse($addons as $addon)
                    @php
                        $destName = $addon->destination->name ?? 'Unassigned';
                    @endphp

                    {{-- Location Grouping Header --}}
                    @if(!$selectedDestinationId && $currentDestination !== $destName)
                        @php
                            $currentDestination = $destName;
                            $counter = 0;
                        @endphp
                        <tr class="bg-slate-100/80">
                            <td colspan="6" class="px-6 py-2.5 text-xs font-bold text-slate-700 tracking-wide uppercase border-y border-slate-200">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-ocean-600">location_on</span>
                                    <span>{{ $currentDestination }}</span>
                                </div>
                            </td>
                        </tr>
                    @endif

                    @php
                        $counter++;
                    @endphp

                    <tr class="hover:bg-slate-50/55 transition-colors duration-150">
                        <td class="px-6 py-4 text-sm font-medium text-slate-500">
                            {{ $counter }}
                        </td>

                        <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span>{{ $addon->name }}</span>
                                @if(!empty($addon->embedding))
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/60" title="AI Vector Embedding Active">
                                        <span class="material-symbols-outlined text-[13px]">psychology</span> AI Embedded
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500 border border-slate-200" title="Embedding pending or not generated">
                                        <span class="material-symbols-outlined text-[13px]">sensors_off</span> Pending AI
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="px-6 py-4 text-sm font-semibold text-slate-700">
                            {{ $addon->type }}
                        </td>

                        <td class="px-6 py-4 text-sm text-slate-600">
                            @if(is_array($addon->pricing_tiers) && count($addon->pricing_tiers) > 0)
                                {{ count($addon->pricing_tiers) }} Tiers
                            @else
                                -
                            @endif
                        </td>

                        <td class="px-6 py-4 text-sm">
                            @if($addon->is_shown)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Visible
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Hidden
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.addons.edit', $addon->id) }}"
                                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded bg-white border border-ocean-300 hover:border-ocean-600 text-ocean-600 hover:bg-ocean-50 text-xs font-medium transition-colors shadow-sm">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                    Edit
                                </a>

                                <div x-data="{ openModal: false }">
                                    <button @click="openModal = true" type="button"
                                        class="inline-flex items-center gap-1.5 h-8 px-3 rounded bg-rose-50 border border-rose-200/80 hover:bg-rose-100 hover:border-rose-300 text-rose-700 text-xs font-medium transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                        Delete
                                    </button>

                                    <!-- Delete Modal -->
                                    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" x-cloak style="display: none;">
                                        <div @click.away="openModal = false" class="bg-white rounded-lg p-6 w-full max-w-sm border border-slate-200 shadow-lg whitespace-normal text-left">
                                            <h3 class="text-sm font-semibold text-slate-900">Delete Add-on</h3>
                                            <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                                Are you sure you want to delete <span class="font-semibold text-slate-900 break-words">{{ $addon->name }}</span>? This action cannot be undone.
                                            </p>

                                            <div class="flex gap-3 mt-6">
                                                <button @click="openModal = false" type="button" class="flex-1 h-8 px-3 bg-white border border-slate-300 text-slate-700 text-xs font-medium rounded hover:bg-slate-50 transition-colors">
                                                    Cancel
                                                </button>

                                                <form action="{{ route('admin.addons.destroy', $addon->id) }}" method="POST" class="flex-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="w-full h-8 px-3 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded transition-colors">
                                                        Confirm Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-xs text-slate-500">
                            No add-ons found matching your search or selected location.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>