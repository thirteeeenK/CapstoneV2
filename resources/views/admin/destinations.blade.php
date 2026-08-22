@extends('layouts.admin')

@section('title', 'Destinations | SunnyTrips Admin')

@section('content')

    <div x-data="{ 
                            isModalOpen: false, 
                            modalTitle: 'Add New Destination',
                    formAction: '{{ route('admin.store') }}',
                    destName: '',
                    destDescription: '',
                    destImageUrl: '',
                    methodType: 'POST',
                    searchQuery: '',
                    sortBy: 'id',
                    sortOrder: 'asc',

                    get filteredDestinations() {
                        let results = [...this.destinations];

                        if (this.searchQuery) {
                            const q = this.searchQuery.toLowerCase();
                            results = results.filter(d => d.name.toLowerCase().includes(q) || String(d.id).includes(q));
                        }

                        results.sort((a, b) => {
                            if (this.sortBy === 'id') {
                                return this.sortOrder === 'asc'
                                    ? Number(a.id) - Number(b.id)
                                    : Number(b.id) - Number(a.id);
                            }
                            const aVal = (a[this.sortBy] || '').toString().toLowerCase();
                            const bVal = (b[this.sortBy] || '').toString().toLowerCase();
                            return this.sortOrder === 'asc' 
                                ? aVal.localeCompare(bVal) 
                                : bVal.localeCompare(aVal);
                        });

                        return results;
                    },

                                                                                            destinations: {{ $destinations->toJson() }},

                    openCreate() {
                        this.modalTitle = 'Add New Destination';
                        this.formAction = '{{ route('admin.store') }}';
                        this.destName = '';
                        this.destDescription = '';
                        this.destImageUrl = '';
                        this.methodType = 'POST';
                        this.isModalOpen = true;
                        this.$nextTick(() => this.$refs.nameInput.focus());
                    },

                    openEdit(destination) {
                        this.modalTitle = 'Edit Destination';
                        this.formAction = '/admin/destinations/' + destination.id;
                        this.destName = destination.name;
                        this.destDescription = destination.description || '';
                        this.destImageUrl = destination.image && destination.image.startsWith('http') ? destination.image : '';
                        this.methodType = 'PUT';
                        this.isModalOpen = true;
                        this.$nextTick(() => this.$refs.nameInput.focus());
                    },

                                                                                            toggleSort() {
                                                                                                this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
                                                                                            }
                                                                                        }" class="pb-12">

        <!-- Header Section -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Destinations</h1>
                <p class="text-xs text-slate-500 mt-1">Manage your travel destinations</p>
            </div>

            <button @click="openCreate()"
                class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-ocean-600 hover:bg-ocean-700 text-white text-xs font-semibold shadow-sm transition-colors self-start sm:self-auto">
                <span class="material-symbols-outlined text-[16px]">add</span>
                Add Destination
            </button>
        </div>

        <!-- Alerts -->
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-transition
                class="mb-6 bg-emerald-50 border border-emerald-200/60 rounded-xl px-5 py-4 flex items-start gap-3 shadow-sm">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-emerald-900">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="text-emerald-400 hover:text-emerald-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        @if (isset($errors) && $errors->any())
            <div x-data="{ show: true }" x-show="show" x-transition
                class="mb-6 bg-rose-50 border border-rose-200/60 rounded-xl px-5 py-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-rose-900 mb-1">Please fix the following errors:</p>
                        <ul class="space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li class="text-xs text-rose-700 flex items-center gap-1.5">
                                    <span class="w-1 h-1 rounded-full bg-rose-400"></span>
                                    {{ $error }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <button @click="show = false" class="text-rose-400 hover:text-rose-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        <!-- Search & Stats Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-5">
            <div class="relative flex-1 max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input x-model="searchQuery" type="text" placeholder="Search destinations..."
                    class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all shadow-sm" />
                <div x-show="searchQuery" x-transition @click="searchQuery = ''"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                    <svg class="w-4 h-4 text-slate-400 hover:text-slate-600" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
            </div>

            <div
                class="flex items-center gap-2 text-xs text-slate-500 bg-white px-3 py-2 rounded-lg border border-slate-200 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span
                    x-text="filteredDestinations.length + ' destination' + (filteredDestinations.length !== 1 ? 's' : '')"></span>
            </div>
        </div>

        <!-- Destinations List -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Table Header -->
            <div
                class="hidden sm:grid grid-cols-[1fr_auto] items-center px-6 py-3.5 bg-slate-50/80 border-b border-slate-200/60">
                <button @click="toggleSort()"
                    class="flex items-center gap-2 text-xs font-semibold text-slate-600 uppercase tracking-wider hover:text-slate-900 transition-colors group">
                    Destination Name
                    <span class="flex flex-col gap-0">
                        <svg :class="sortOrder === 'asc' ? 'text-slate-900' : 'text-slate-300'"
                            class="w-2.5 h-2.5 -mb-0.5 transition-colors" fill="currentColor" viewBox="0 0 8 4">
                            <path d="M4 0L0 4h8L4 0z" />
                        </svg>
                        <svg :class="sortOrder === 'desc' ? 'text-slate-900' : 'text-slate-300'"
                            class="w-2.5 h-2.5 transition-colors" fill="currentColor" viewBox="0 0 8 4">
                            <path d="M4 4L0 0h8L4 4z" />
                        </svg>
                    </span>
                </button>
                <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider w-32 text-center">Actions</span>
            </div>

            <!-- Items -->
            <div class="divide-y divide-slate-100">
                <template x-for="destination in filteredDestinations" :key="destination.id">
                    <div class="group flex items-center px-6 py-4 hover:bg-slate-50/60 transition-colors duration-150">
                        <!-- Destination Info -->
                        <div class="flex-1 min-w-0 flex items-center gap-4">
                            <div
                                class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center flex-shrink-0 group-hover:from-blue-50 group-hover:to-indigo-100 transition-all duration-300">
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-blue-500 transition-colors duration-300"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-slate-900 truncate" x-text="destination.id + '. ' + destination.name">
                                </h3>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1 ml-4">
                            <button type="button" @click="openEdit(destination)"
                                class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all duration-200"
                                title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>

                            <form :action="'/admin/destinations/' + destination.id" method="POST" class="inline"
                                onsubmit="return confirm('Delete this destination? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-200"
                                    title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <template x-if="filteredDestinations.length === 0">
                    <div class="py-16 px-6 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 mb-1"
                            x-text="searchQuery ? 'No matching destinations' : 'No destinations yet'"></h3>
                        <p class="text-xs text-slate-500 mb-5 max-w-xs mx-auto"
                            x-text="searchQuery ? 'Try adjusting your search terms.' : 'Get started by adding your first travel destination.'">
                        </p>
                        <button @click="searchQuery ? searchQuery = '' : openCreate()"
                            class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors">
                            <span x-text="searchQuery ? 'Clear search' : 'Add your first destination'"></span>
                            <svg x-show="!searchQuery" class="w-4 h-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modal -->
    <div x-show="isModalOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">

        <!-- Backdrop -->
        <div @click="isModalOpen = false" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>

        <!-- Modal Content -->
        <div @click.away="isModalOpen = false" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="relative bg-white rounded-2xl w-full max-w-md shadow-2xl shadow-slate-900/20 overflow-hidden">

            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-md shadow-blue-500/20">
                        <svg x-show="methodType === 'POST'" class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <svg x-show="methodType === 'PUT'" class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900" x-text="modalTitle"></h2>
                        <p class="text-xs text-slate-500"
                            x-text="methodType === 'POST' ? 'Create a new destination' : 'Update destination details'">
                        </p>
                    </div>
                </div>
                <button @click="isModalOpen = false"
                    class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <form x-bind:action="formAction" method="POST" class="px-6 py-6">
                @csrf

                <template x-if="methodType === 'PUT'">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">
                        Destination Name
                        <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <input x-ref="nameInput" x-model="destName" name="name" type="text"
                            placeholder="e.g., Boracay, Aklan" required
                            class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" />
                    </div>
                    <p class="text-xs text-slate-400">Enter the full name of the destination.</p>
                </div>

                <div class="space-y-2 mt-4">
                    <label class="block text-sm font-semibold text-slate-700">
                        Description
                        <span class="text-xs font-normal text-slate-400">(optional)</span>
                    </label>
                    <textarea x-model="destDescription" name="description" rows="3"
                        placeholder="What makes this destination special?"
                        class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all resize-none"></textarea>
                </div>

                <div class="space-y-2 mt-4">
                    <label class="block text-sm font-semibold text-slate-700">
                        Image URL
                        <span class="text-xs font-normal text-slate-400">(optional)</span>
                    </label>
                    <input x-model="destImageUrl" name="image_url" type="url"
                        placeholder="https://example.com/boracay.jpg"
                        class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" />
                    <p class="text-xs text-slate-400">Paste an image link, or upload a file below instead.</p>
                </div>

                <div class="space-y-2 mt-4">
                    <label class="block text-sm font-semibold text-slate-700">
                        Upload Image
                        <span class="text-xs font-normal text-slate-400">(optional)</span>
                    </label>
                    <input type="file" name="image" accept="image/*"
                        class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-3 mt-8 pt-4 border-t border-slate-100">
                    <button type="button" @click="isModalOpen = false"
                        class="px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-xl transition-all">
                        Cancel
                    </button>
                    <button type="submit"
                        class="group inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 shadow-lg shadow-slate-900/20 hover:shadow-slate-900/30">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="methodType === 'POST' ? 'Create Destination' : 'Save Changes'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>
@endsection