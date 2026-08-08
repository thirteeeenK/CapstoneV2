@extends('layouts.admin')

@section('title', 'FAQ Manager | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body">

        {{-- Header & Create Action --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">FAQ Manager</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Store common questions and predefined answers so SunnyBot can answer them instantly and consistently.</p>
            </div>

            <a href="{{ route('admin.faqs.create') }}"
               class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>+ Create New FAQ</span>
            </a>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">quiz</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">Total FAQs</span>
                    <h3 class="text-2xl font-black text-slate-900">{{ $stats['total'] }}</h3>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">visibility</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">Publicly Visible</span>
                    <h3 class="text-2xl font-black text-emerald-700">{{ $stats['visible'] }}</h3>
                </div>
            </div>

            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">visibility_off</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">Hidden from Public</span>
                    <h3 class="text-2xl font-black text-amber-700">{{ $stats['hidden'] }}</h3>
                </div>
            </div>
        </div>

        {{-- Filter Controls --}}
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs mb-6">
            <form action="{{ route('admin.faqs.index') }}" method="GET" class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:w-72">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm">search</span>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Search questions, answers, keywords..."
                           class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                <select name="category" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>

                <select name="visibility" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Statuses</option>
                    <option value="visible" {{ $visibility === 'visible' ? 'selected' : '' }}>Visible Only</option>
                    <option value="hidden" {{ $visibility === 'hidden' ? 'selected' : '' }}>Hidden Only</option>
                </select>

                @if($search || $category || $visibility)
                    <a href="{{ route('admin.faqs.index') }}" class="text-xs font-bold text-rose-600 hover:underline px-2">Clear Filters</a>
                @endif
            </form>
        </div>

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
@endsection
