@extends('layouts.admin')

@section('title', 'Create New Tour Package | SunnyTrips Admin')

@section('content')
    <div class="max-w-4xl mx-auto pb-12 font-body">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.packages.index') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    <span>Back to Packages List</span>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Create New Tour Package</h1>
            </div>
        </div>

        <form action="{{ route('admin.packages.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- Package Name --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Package Title / Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Boracay 3D2N Sunset Yacht & Beach Getaway" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Destination Select --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Island Destination *</label>
                    <select name="destination_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white">
                        <option value="">Select Destination</option>
                        @foreach($destinations as $dest)
                            <option value="{{ $dest->id }}">{{ $dest->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Package Type --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Package Type / Tag</label>
                    <input type="text" name="type" placeholder="e.g. Flight + Hotel + Transfer Promo" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Package Price --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Package Rate (₱ per pax) *</label>
                    <input type="number" name="price" step="0.01" min="0" required placeholder="e.g. 5999.00" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Minimum Pax --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Minimum Guests Required (Min Pax) *</label>
                    <input type="number" name="min_pax" value="2" min="1" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Duration: Days --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Number of Days</label>
                    <input type="number" name="days" value="3" min="1" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Duration: Nights --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Number of Nights</label>
                    <input type="number" name="nights" value="2" min="0" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Valid From --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Promo Valid From</label>
                    <input type="date" name="valid_from" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Valid To --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Promo Valid Until</label>
                    <input type="date" name="valid_to" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Package Image Upload --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Upload Image File</label>
                    <input type="file" name="image" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
                </div>

                {{-- Image URL --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Or Image URL</label>
                    <input type="url" name="image_url" placeholder="https://example.com/package.jpg" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                </div>

                {{-- Linked catalogue records --}}
                @include('admin.packages._relations')

                {{-- Inclusions as individual rows --}}
                @include('admin.packages._inclusions', ['inclusions' => old('inclusions', [])])

                {{-- Public Visibility Toggle --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Public Visibility Status *</label>
                    <select name="is_active" required class="w-full sm:w-64 px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white">
                        <option value="1">Visible to Public (Active)</option>
                        <option value="0">Hidden from Public (Draft / Inactive)</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('admin.packages.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-100 transition">Cancel</a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-md transition cursor-pointer flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    <span>Save & Publish Package</span>
                </button>
            </div>
        </form>
    </div>
@endsection
