@extends('layouts.admin')

@section('title', 'Review Analytics | SunnyTrips Admin')

@section('content')
    @php
        $sentimentMeta = [
            'positive' => ['label' => 'Positive', 'icon' => 'sentiment_satisfied', 'bar' => 'bg-emerald-500'],
            'neutral' => ['label' => 'Neutral', 'icon' => 'sentiment_neutral', 'bar' => 'bg-amber-400'],
            'negative' => ['label' => 'Negative', 'icon' => 'sentiment_dissatisfied', 'bar' => 'bg-rose-500'],
        ];
    @endphp

    <div class="pb-12 font-body">
        {{-- Header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Review Analytics & Sentiment</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">AI-assisted DSS metrics across all verified guest feedback.</p>
            </div>
            <div class="flex items-center gap-2 text-xs font-bold">
                <span class="px-3 py-1.5 rounded-xl bg-teal-50 text-teal-700 border border-teal-200">{{ $totalReviews }} reviews</span>
                <span class="px-3 py-1.5 rounded-xl bg-ocean-50 text-ocean-700 border border-ocean-200">★ {{ number_format($avgRating, 2) }} avg</span>
            </div>
        </div>

        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-2xl mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <script id="reviews-chart-data" type="application/json">{!! json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>

        <div id="reviews-charts" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Sentiment donut --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-2 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-ocean-50 text-ocean-600 border border-ocean-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">donut_small</span>
                    </span>
                    Sentiment Distribution
                </h2>
                <p class="text-[11px] text-slate-400 mb-2">AI-classified tone of guest feedback</p>
                <div id="chart-sentiment-donut" class="min-h-[260px]"></div>
            </section>

            {{-- Rating distribution --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-2 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">star</span>
                    </span>
                    Rating Distribution
                </h2>
                <p class="text-[11px] text-slate-400 mb-2">Star ratings across all reviews</p>
                <div id="chart-rating-dist" class="min-h-[260px]"></div>
            </section>

            {{-- Needs improvement alert --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-2 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">notification_important</span>
                    </span>
                    Needs Improvement
                </h2>
                <p class="text-[11px] text-slate-400 mb-2">Listings above the 15% negative-sentiment threshold</p>
                @if ($needsImprovement->isEmpty())
                    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6 text-center text-xs text-slate-500">
                        No listings flagged. Every entity currently sits under the threshold.
                    </div>
                @else
                    <div id="chart-needs-improvement" class="min-h-[260px]"></div>
                @endif
            </section>

            {{-- Leaderboards --}}
            <section class="lg:col-span-3 bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-1 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-teal-50 text-teal-600 border border-teal-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">workspace_premium</span>
                    </span>
                    Top Rated Listings
                </h2>
                <p class="text-[11px] text-slate-400 mb-2">Highest average rating per listing type (0–5 scale)</p>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach (['hotels' => 'Hotels', 'rooms' => 'Rooms', 'activities' => 'Activities'] as $key => $label)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="font-label text-[9px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">{{ $label }}</p>
                            @if (empty($leaderboards[$key]))
                                <p class="text-[11px] text-slate-400 py-6 text-center">No reviews yet.</p>
                            @else
                                <div id="chart-leaderboard-{{ $key }}" class="min-h-[240px]"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Keyword treemap (hidden) --
            <section class="lg:col-span-3 bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-1 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-sand-100 text-slate-600 border border-sand-200 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">cloud</span>
                    </span>
                    Keyword Cloud
                </h2>
                <p class="text-[11px] text-slate-400 mb-2">Most-mentioned topics across AI summaries — larger blocks = more mentions</p>
                @if (empty($keywordCloud))
                    <p class="text-[11px] text-slate-400">No AI keywords extracted yet.</p>
                @else
                    <div id="chart-keyword-treemap" class="min-h-[260px]"></div>
                @endif
            </section>
            --}}
        </div>

        {{-- Recent reviews table --}}
        <section class="mt-6 bg-white rounded-3xl border border-slate-200/80 overflow-hidden"
            x-data="reviewFilters({
                star: @js(request('star', '')),
                sentiment: @js(request('sentiment', '')),
                featured: @js(request('featured', '')),
                entityType: @js(request('entity_type', '')),
                destinationId: @js(request('destination_id', '')),
                hotelId: @js(request('hotel_id', '')),
                roomId: @js(request('room_id', '')),
                destinations: @js($destinations->toArray()),
                hotels: @js($hotels->toArray()),
                rooms: @js($rooms->toArray()),
            })"
            @click="handleNavClick($event)">
            <header class="px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h2 class="font-headline text-sm font-bold text-slate-900">All Reviews</h2>
                <div class="flex items-center gap-3">
                    <span class="font-label text-[10px] uppercase font-bold tracking-[0.15em] text-slate-400">
                        <span x-text="totalReviews"></span> total
                    </span>
                    <a href="{{ route('admin.reviews.create') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-ocean-600 hover:bg-ocean-700 text-white text-[10px] font-bold transition cursor-pointer">
                        <span class="material-symbols-outlined text-[13px]">add_comment</span>
                        Create review
                    </a>
                </div>
            </header>

            {{-- Filter bar --}}
            <div class="px-6 py-3 border-b border-slate-100 bg-slate-50/60 space-y-2.5">
                {{-- Row 1: Star + Sentiment + Featured --}}
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Star</span>
                        <button @click="setFilter('star', '')"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                            :class="star === '' ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                            All
                        </button>
                        @for ($s = 5; $s >= 1; $s--)
                            <button @click="setFilter('star', '{{ $s }}')"
                                class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                                :class="star === '{{ $s }}' ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                                {{ $s }}★
                            </button>
                        @endfor
                    </div>
                    <div class="w-px h-4 bg-slate-200"></div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sentiment</span>
                        <button @click="setFilter('sentiment', '')"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                            :class="sentiment === '' ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                            All
                        </button>
                        @foreach (['positive' => 'Positive', 'neutral' => 'Neutral', 'negative' => 'Negative'] as $val => $lbl)
                            <button @click="setFilter('sentiment', '{{ $val }}')"
                                class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                                :class="sentiment === '{{ $val }}' ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                                {{ $lbl }}
                            </button>
                        @endforeach
                    </div>
                    <div class="w-px h-4 bg-slate-200"></div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Featured</span>
                        <button @click="setFilter('featured', '')"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                            :class="featured === '' ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                            All
                        </button>
                        <button @click="setFilter('featured', '1')"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                            :class="featured === '1' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                            ★ Featured
                        </button>
                        <button @click="setFilter('featured', '0')"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                            :class="featured === '0' ? 'bg-slate-600 text-white border-slate-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                            Not Featured
                        </button>
                    </div>
                </div>
                {{-- Row 2: Entity Type + Destination + Hotel + Room --}}
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Entity</span>
                        <button @click="setFilter('entityType', '')"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                            :class="entityType === '' ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                            All
                        </button>
                        @foreach (['hotel' => 'Hotel', 'room' => 'Room', 'activity' => 'Activity', 'package' => 'Package'] as $val => $lbl)
                            <button @click="setFilter('entityType', '{{ $val }}')"
                                class="px-2 py-0.5 rounded-md text-[10px] font-bold border transition cursor-pointer"
                                :class="entityType === '{{ $val }}' ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'">
                                {{ $lbl }}
                            </button>
                        @endforeach
                    </div>
                    <div class="w-px h-4 bg-slate-200"></div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Destination</span>
                        <select x-model="destinationId" @change="onDestinationChange()"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border bg-white text-slate-700 border-slate-200 hover:bg-slate-100 transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-ocean-500/30 focus:border-ocean-400">
                            <option value="">All</option>
                            <template x-for="d in allDestinations" :key="d.id">
                                <option :value="d.id" x-text="d.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Hotel</span>
                        <select x-model="hotelId" @change="onHotelChange()"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border bg-white text-slate-700 border-slate-200 hover:bg-slate-100 transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-ocean-500/30 focus:border-ocean-400">
                            <option value="">All</option>
                            <template x-for="h in filteredHotels" :key="h.id">
                                <option :value="h.id" x-text="h.hotel_name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Room</span>
                        <select x-model="roomId" @change="applyFilter()"
                            class="px-2 py-0.5 rounded-md text-[10px] font-bold border bg-white text-slate-700 border-slate-200 hover:bg-slate-100 transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-ocean-500/30 focus:border-ocean-400">
                            <option value="">All</option>
                            <template x-for="r in filteredRooms" :key="r.id">
                                <option :value="r.id" x-text="r.room_name"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <div id="reviews-table" class="relative">
                <div x-show="loading" class="absolute inset-0 bg-white/70 backdrop-blur-xs flex items-center justify-center z-10" style="display: none;">
                    <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white shadow-md border border-slate-200 text-xs font-bold text-slate-700">
                        <svg class="animate-spin h-4 w-4 text-ocean-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Loading reviews...</span>
                    </div>
                </div>
                @include('admin.reviews._table', ['reviews' => $reviews, 'sentimentMeta' => $sentimentMeta ?? []])
            </div>
        </section>
    </div>

    <script>
        function reviewFilters(initial) {
            return {
                star: initial.star,
                sentiment: initial.sentiment,
                featured: initial.featured,
                entityType: initial.entityType,
                destinationId: initial.destinationId,
                hotelId: initial.hotelId,
                roomId: initial.roomId,
                allDestinations: initial.destinations,
                allHotels: initial.hotels,
                allRooms: initial.rooms,
                totalReviews: {{ $reviews->total() }},
                loading: false,
                navigating: false,

                get filteredHotels() {
                    if (!this.destinationId) return this.allHotels;
                    return this.allHotels.filter(h => String(h.destination_id) === String(this.destinationId));
                },

                get filteredRooms() {
                    if (!this.hotelId) {
                        if (!this.destinationId) return this.allRooms;
                        const destHotelIds = this.filteredHotels.map(h => String(h.id));
                        return this.allRooms.filter(r => destHotelIds.includes(String(r.hotel_id)));
                    }
                    return this.allRooms.filter(r => String(r.hotel_id) === String(this.hotelId));
                },

                setFilter(key, value) {
                    this[key] = value;
                    if (key === 'entityType') {
                        this.destinationId = '';
                        this.hotelId = '';
                        this.roomId = '';
                    }
                    this.applyFilter();
                },

                onDestinationChange() {
                    this.hotelId = '';
                    this.roomId = '';
                    this.applyFilter();
                },

                onHotelChange() {
                    this.roomId = '';
                    this.applyFilter();
                },

                buildParams() {
                    const params = {};
                    if (this.star) params.star = this.star;
                    if (this.sentiment) params.sentiment = this.sentiment;
                    if (this.featured) params.featured = this.featured;
                    if (this.entityType) params.entity_type = this.entityType;
                    if (this.destinationId) params.destination_id = this.destinationId;
                    if (this.hotelId) params.hotel_id = this.hotelId;
                    if (this.roomId) params.room_id = this.roomId;
                    params.partial = 1;
                    return new URLSearchParams(params).toString();
                },

                handleNavClick(e) {
                    const a = e.target.closest('a');
                    if (!a || !a.href || !a.href.includes('page=')) return;
                    e.preventDefault();
                    this.applyFilter(a.href);
                },

                async applyFilter(url = null) {
                    this.loading = true;
                    try {
                        let target = url;
                        if (!target) {
                            const qs = this.buildParams();
                            target = `{{ route('admin.reviews.index') }}?${qs}`;
                        } else {
                            const u = new URL(target, window.location.origin);
                            u.searchParams.set('partial', '1');
                            target = u.toString();
                        }

                        const resp = await fetch(target, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const html = await resp.text();
                        const container = document.getElementById('reviews-table');
                        if (container) {
                            container.innerHTML = html;
                        }

                        // Update total count from data attribute
                        const tableDiv = document.querySelector('#reviews-table .overflow-x-auto');
                        if (tableDiv && tableDiv.dataset.total !== undefined) {
                            this.totalReviews = parseInt(tableDiv.dataset.total);
                        }

                        // Update URL without reload
                        if (!this.navigating) {
                            const cleanUrl = target.replace(/[&?]partial=1/, '').replace(/&$/, '');
                            history.pushState(null, '', cleanUrl);
                        }
                    } catch (e) {
                        console.error('Filter fetch failed:', e);
                    } finally {
                        this.loading = false;
                    }
                },

                init() {
                    window.addEventListener('popstate', () => {
                        const params = new URLSearchParams(window.location.search);
                        this.star = params.get('star') || '';
                        this.sentiment = params.get('sentiment') || '';
                        this.featured = params.get('featured') || '';
                        this.entityType = params.get('entity_type') || '';
                        this.destinationId = params.get('destination_id') || '';
                        this.hotelId = params.get('hotel_id') || '';
                        this.roomId = params.get('room_id') || '';
                        this.navigating = true;
                        this.applyFilter(window.location.href).then(() => {
                            this.navigating = false;
                        });
                    });
                }
            }
        }
    </script>

    @push('scripts')
        @vite('resources/js/reviews-charts.js')
    @endpush
@endsection