@extends('layouts.admin')

@section('title', 'Manage Tour Packages & Promos | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body">
        
        {{-- Header & Create Action --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Manage Tour Packages & Promos</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Create, edit, and control public visibility for island tour packages and bundled promos.</p>
            </div>

            <a href="{{ route('admin.packages.create') }}" 
               class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>+ Create New Package</span>
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
                    <span class="material-symbols-outlined text-2xl">card_travel</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-medium uppercase tracking-wider">Total Packages</span>
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
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
            <form action="{{ route('admin.packages.index') }}" method="GET" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                {{-- Search --}}
                <div class="relative w-full sm:w-64">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm">search</span>
                    <input type="text" 
                           name="search" 
                           value="{{ $search }}" 
                           placeholder="Search packages..." 
                           class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Destination Filter --}}
                <select name="destination_id" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Destinations</option>
                    @foreach($destinations as $dest)
                        <option value="{{ $dest->id }}" {{ (string)$destinationId === (string)$dest->id ? 'selected' : '' }}>{{ $dest->name }}</option>
                    @endforeach
                </select>

                {{-- Visibility Filter --}}
                <select name="visibility" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 bg-white">
                    <option value="">All Statuses</option>
                    <option value="visible" {{ $visibility === 'visible' ? 'selected' : '' }}>Visible Only</option>
                    <option value="hidden" {{ $visibility === 'hidden' ? 'selected' : '' }}>Hidden Only</option>
                </select>

                @if($search || $destinationId || $visibility)
                    <a href="{{ route('admin.packages.index') }}" class="text-xs font-bold text-rose-600 hover:underline px-2">Clear Filters</a>
                @endif
            </form>
        </div>

        {{-- Packages Grid --}}
        @if($packages->isEmpty())
            <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400">
                <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">card_travel</span>
                <p class="text-sm font-bold text-slate-700">No tour packages found matching your criteria.</p>
                <a href="{{ route('admin.packages.create') }}" class="mt-3 inline-block text-xs font-bold text-sky-600 hover:underline">+ Create Your First Package</a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($packages as $package)
                    @php
                        $imagesArr = is_array($package->images) ? $package->images : (is_string($package->images) ? json_decode($package->images, true) : []);
                        $img = !empty($imagesArr) ? $imagesArr[0] : '/images/placeholder.jpg';
                    @endphp

                    <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs hover:shadow-md transition duration-300 flex flex-col justify-between group relative">
                        
                        <div>
                            {{-- Package Image Header --}}
                            <div class="relative h-48 bg-slate-100 overflow-hidden">
                                <img src="{{ $img }}" alt="{{ $package->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                
                                {{-- Destination Badge --}}
                                @if($package->destination)
                                    <div class="absolute top-3 left-3 bg-slate-950/80 backdrop-blur-md text-white font-extrabold text-[10px] px-2.5 py-1 rounded-lg flex items-center gap-1 border border-white/20">
                                        <span class="material-symbols-outlined text-[13px] text-sky-400">location_on</span>
                                        <span>{{ $package->destination->name }}</span>
                                    </div>
                                @endif

                                {{-- Status Badge --}}
                                <div class="absolute top-3 right-3">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border shadow-sm backdrop-blur-md {{ $package->is_active ? 'bg-emerald-500/90 text-white border-emerald-400' : 'bg-amber-500/90 text-white border-amber-400' }}">
                                        {{ $package->is_active ? 'Visible' : 'Hidden' }}
                                    </span>
                                </div>

                                {{-- Price Overlay --}}
                                <div class="absolute bottom-3 right-3 bg-slate-950/80 backdrop-blur-md text-emerald-300 font-black text-xs px-3 py-1 rounded-lg border border-white/20">
                                    ₱{{ number_format($package->price, 2) }}
                                </div>
                            </div>

                            {{-- Info Body --}}
                            <div class="p-5 space-y-3">
                                <span class="text-[10px] uppercase tracking-wider font-extrabold text-sky-600 bg-sky-50 px-2 py-0.5 rounded border border-sky-100">
                                    {{ $package->type ?: 'Tour Promo Package' }}
                                </span>

                                <h3 class="text-base font-bold text-slate-900 group-hover:text-sky-600 transition font-headline line-clamp-1">
                                    {{ $package->name }}
                                </h3>

                                <div class="flex items-center gap-3 text-xs text-slate-500">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px] text-slate-400">schedule</span>
                                        {{ $package->days ?: 3 }}D / {{ $package->nights ?: 2 }}N
                                    </span>
                                    <span>•</span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px] text-slate-400">group</span>
                                        Min {{ $package->min_pax }} Pax
                                    </span>
                                </div>

                                {{-- Inclusions Snapshot --}}
                                @if(!empty($package->generic_inclusions) && is_array($package->generic_inclusions))
                                    <div class="pt-2 border-t border-slate-100 space-y-1">
                                        @foreach(array_slice($package->generic_inclusions, 0, 3) as $inc)
                                            <div class="flex items-center gap-1.5 text-[11px] text-slate-600 truncate">
                                                <span class="material-symbols-outlined text-[13px] text-emerald-500">check_circle</span>
                                                <span class="truncate">{{ $inc }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Footer Actions --}}
                        <div class="p-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between gap-2">
                            {{-- Toggle Visibility Button --}}
                            <form action="{{ route('admin.packages.toggle-visibility', $package->id) }}" method="POST">
                                @csrf
                                <button type="submit" 
                                        class="px-3 py-1.5 rounded-xl border text-xs font-bold transition flex items-center gap-1 cursor-pointer {{ $package->is_active ? 'border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100' : 'border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-100' }}">
                                    <span class="material-symbols-outlined text-[15px]">{{ $package->is_active ? 'visibility_off' : 'visibility' }}</span>
                                    <span>{{ $package->is_active ? 'Hide' : 'Show' }}</span>
                                </button>
                            </form>

                            <div class="flex items-center gap-2">
                                {{-- Edit Button --}}
                                <a href="{{ route('admin.packages.edit', $package->id) }}" 
                                   class="px-3 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold transition flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px]">edit</span>
                                    <span>Edit</span>
                                </a>

                                {{-- Delete Form --}}
                                <form action="{{ route('admin.packages.destroy', $package->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this package?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition rounded-lg hover:bg-rose-50 cursor-pointer">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $packages->links() }}
            </div>
        @endif

    </div>
@endsection
