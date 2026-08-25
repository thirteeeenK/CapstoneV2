{{-- Live-search results partial: onboarding options table + pagination only. --}}

<div id="listings-results">
    @if($options->isEmpty())
        <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">tune</span>
            <p class="text-sm font-bold text-slate-700">No onboarding options found matching your criteria.</p>
            <a href="{{ route('admin.onboarding-options.create') }}" class="mt-3 inline-block text-xs font-bold text-sky-600 hover:underline">+ Add Your First Option</a>
        </div>
    @else
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[10px] uppercase tracking-wider text-slate-400">
                            <th class="py-3 px-6 font-bold">#</th>
                            <th class="py-3 px-6 font-bold">Name</th>
                            <th class="py-3 px-6 font-bold">Type</th>
                            <th class="py-3 px-6 font-bold">Image</th>
                            <th class="py-3 px-6 font-bold">Order</th>
                            <th class="py-3 px-6 font-bold">Status</th>
                            <th class="py-3 px-6 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($options as $index => $option)
                            @php
                                $typeColor = match($option->type) {
                                    'vibe' => 'bg-sky-50 text-sky-700 border-sky-200',
                                    'traveler_type' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    default => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                };
                                $thumb = $option->resolved_image_url;
                            @endphp
                            <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition">
                                <td class="py-4 px-6 text-slate-400 font-black">{{ $options->firstItem() + $index }}</td>
                                <td class="py-4 px-6 max-w-md">
                                    <p class="font-bold text-slate-900 line-clamp-2">{{ $option->name }}</p>
                                    @if($option->description)
                                        <p class="text-[11px] text-slate-500 mt-1 line-clamp-2">{{ $option->description }}</p>
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    <span class="inline-block text-[10px] uppercase tracking-wider font-extrabold px-2 py-0.5 rounded border {{ $typeColor }}">
                                        {{ $types[$option->type] ?? ucwords(str_replace('_', ' ', $option->type)) }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    @if($thumb)
                                        <img src="{{ $thumb }}" alt="{{ $option->name }}" class="w-12 h-12 object-cover rounded-lg border border-slate-200">
                                    @elseif($option->icon)
                                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 bg-slate-100 px-2 py-1 rounded-lg border border-slate-200">
                                            <span class="material-symbols-outlined text-[16px]">{{ $option->icon }}</span>
                                            <span>{{ $option->icon }}</span>
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-xs text-slate-500">{{ $option->sort_order }}</td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border {{ $option->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                                        {{ $option->is_active ? 'Visible' : 'Hidden' }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <form action="{{ route('admin.onboarding-options.toggle-active', $option->id) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1.5 rounded-xl border text-xs font-bold transition flex items-center gap-1 cursor-pointer {{ $option->is_active ? 'border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100' : 'border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-100' }}">
                                                <span class="material-symbols-outlined text-[15px]">{{ $option->is_active ? 'visibility_off' : 'visibility' }}</span>
                                                <span>{{ $option->is_active ? 'Hide' : 'Show' }}</span>
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.onboarding-options.edit', $option->id) }}"
                                           class="px-2.5 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold transition flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[15px]">edit</span>
                                            <span>Edit</span>
                                        </a>

                                        <form action="{{ route('admin.onboarding-options.destroy', $option->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this option?')">
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
                {{ $options->links() }}
            </div>
        </div>
    @endif
</div>
