@extends('layouts.admin')

@section('title', 'Review Analytics | SunnyTrips Admin')

@section('content')
    @php
        $sentimentMeta = [
            'positive' => ['label' => 'Positive', 'icon' => 'sentiment_satisfied', 'bar' => 'bg-emerald-500'],
            'neutral' => ['label' => 'Neutral', 'icon' => 'sentiment_neutral', 'bar' => 'bg-amber-400'],
            'negative' => ['label' => 'Negative', 'icon' => 'sentiment_dissatisfied', 'bar' => 'bg-rose-500'],
        ];
        $positivePct = $sentimentTotal > 0 ? ($sentimentCounts['positive'] / $sentimentTotal) : 0;
        $neutralPct = $sentimentTotal > 0 ? ($sentimentCounts['neutral'] / $sentimentTotal) : 0;
        $negativePct = $sentimentTotal > 0 ? ($sentimentCounts['negative'] / $sentimentTotal) : 0;
        $donut = 'conic-gradient(
            #10b981 0deg,
            #10b981 ' . round($positivePct * 360) . 'deg,
            #fbbf24 ' . round($positivePct * 360) . 'deg,
            #fbbf24 ' . round(($positivePct + $neutralPct) * 360) . 'deg,
            #f43f5e ' . round(($positivePct + $neutralPct) * 360) . 'deg,
            #f43f5e 360deg
        )';
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Sentiment donut --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-ocean-50 text-ocean-600 border border-ocean-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">donut_small</span>
                    </span>
                    Sentiment Distribution
                </h2>
                <div class="flex items-center gap-6">
                    <div class="w-36 h-36 rounded-full shrink-0 shadow-inner ring-8 ring-slate-50" style="background: {{ $donut }};"></div>
                    <div class="space-y-3">
                        @foreach ($sentimentMeta as $key => $m)
                            <div class="flex items-center gap-2.5">
                                <span class="w-3 h-3 rounded-full {{ $m['bar'] }}"></span>
                                <span class="text-xs font-bold text-slate-700 w-16">{{ $m['label'] }}</span>
                                <span class="text-xs font-black text-slate-900">{{ $sentimentCounts[$key] }}</span>
                                <span class="text-[10px] text-slate-400 font-bold">
                                    {{ $sentimentTotal > 0 ? round(($sentimentCounts[$key] / $sentimentTotal) * 100) : 0 }}%
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Needs improvement alert --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 p-6 lg:col-span-2">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">notification_important</span>
                    </span>
                    Needs Improvement Alerts
                </h2>

                @if ($needsImprovement->isEmpty())
                    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6 text-center text-xs text-slate-500">
                        No listings flagged. Every entity currently sits under the 15% negative-sentiment threshold.
                    </div>
                @else
                    <div class="space-y-2.5">
                        @foreach ($needsImprovement as $alert)
                            <div class="flex items-center justify-between gap-3 p-3.5 rounded-2xl bg-rose-50/60 border border-rose-100">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-900 truncate">{{ $alert['label'] }}</p>
                                    <p class="text-[10px] text-slate-500">{{ $alert['total_reviews'] }} reviews · {{ number_format($alert['average_rating'], 1) }} ★ avg</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg bg-rose-600 text-white text-[10px] font-black shrink-0">
                                    {{ round($alert['negative_percentage']) }}% negative
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Leaderboards --}}
            <section class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">workspace_premium</span>
                    </span>
                    Top Rated Listings
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach (['hotels' => 'Hotels', 'rooms' => 'Rooms', 'activities' => 'Activities'] as $key => $label)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="font-label text-[9px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-3">{{ $label }}</p>
                            @if (empty($leaderboards[$key]))
                                <p class="text-[11px] text-slate-400">No reviews yet.</p>
                            @else
                                <ol class="space-y-3">
                                    @foreach ($leaderboards[$key] as $i => $item)
                                        <li class="flex items-center gap-2.5">
                                            <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 text-[10px] font-black flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[11px] font-bold text-slate-800 truncate">{{ $item['label'] }}</p>
                                                <p class="text-[10px] text-slate-400">{{ $item['review_count'] }} reviews</p>
                                            </div>
                                            <span class="text-xs font-black text-ocean-700 shrink-0">{{ number_format($item['average_rating'], 1) }} ★</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Keyword cloud --}}
            <section class="bg-white rounded-3xl border border-slate-200/80 p-6">
                <h2 class="font-headline text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-sand-100 text-slate-600 border border-sand-200 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[15px]">cloud</span>
                    </span>
                    Keyword Cloud
                </h2>
                @if (empty($keywordCloud))
                    <p class="text-[11px] text-slate-400">No AI keywords extracted yet.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @php
                            $maxKw = max($keywordCloud) ?: 1;
                        @endphp
                        @foreach ($keywordCloud as $kw => $count)
                            @php
                                $size = 11 + round(($count / $maxKw) * 7);
                                $tint = ['text-slate-500', 'text-slate-700', 'text-teal-700', 'text-ocean-700', 'text-slate-900'][min(4, floor(($count / $maxKw) * 4))];
                            @endphp
                            <span class="px-2.5 py-1 rounded-lg bg-sand-50 border border-sand-200 font-bold" style="font-size: {{ $size }}px;">
                                <span class="{{ $tint }}">{{ $kw }} <span class="text-slate-400 font-black">×{{ $count }}</span></span>
                            </span>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- Recent reviews table --}}
        <section class="mt-6 bg-white rounded-3xl border border-slate-200/80 overflow-hidden"
            x-data="reviewFilters({
                star: @js(request('star', '')),
                sentiment: @js(request('sentiment', '')),
                entityType: @js(request('entity_type', '')),
                destinationId: @js(request('destination_id', '')),
                hotelId: @js(request('hotel_id', '')),
                roomId: @js(request('room_id', '')),
                destinations: @js($destinations->toArray()),
                hotels: @js($hotels->toArray()),
                rooms: @js($rooms->toArray()),
            })">
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
                {{-- Row 1: Star + Sentiment --}}
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

            <div id="reviews-table">
                @include('admin.reviews._table', ['reviews' => $reviews, 'sentimentMeta' => $sentimentMeta ?? []])
            </div>
        </section>
    </div>

    <script>
        function reviewFilters(initial) {
            return {
                star: initial.star,
                sentiment: initial.sentiment,
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
                loading: false,

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
                    if (this.entityType) params.entity_type = this.entityType;
                    if (this.destinationId) params.destination_id = this.destinationId;
                    if (this.hotelId) params.hotel_id = this.hotelId;
                    if (this.roomId) params.room_id = this.roomId;
                    params.partial = 1;
                    return new URLSearchParams(params).toString();
                },

                async applyFilter() {
                    this.loading = true;
                    try {
                        const qs = this.buildParams();
                        const resp = await fetch(`{{ route('admin.reviews.index') }}?${qs}`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const html = await resp.text();
                        document.getElementById('reviews-table').innerHTML = html;

                        // Update total count from data attribute
                        const tableDiv = document.querySelector('#reviews-table .overflow-x-auto');
                        if (tableDiv && tableDiv.dataset.total !== undefined) {
                            this.totalReviews = parseInt(tableDiv.dataset.total);
                        }

                        // Update URL without reload
                        if (!this.navigating) {
                            const cleanParams = this.buildParams().replace(/[&?]partial=1/, '').replace(/^&/, '');
                            const url = cleanParams
                                ? `{{ route('admin.reviews.index') }}?${cleanParams}`
                                : `{{ route('admin.reviews.index') }}`;
                            history.pushState(null, '', url);
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
                        this.entityType = params.get('entity_type') || '';
                        this.destinationId = params.get('destination_id') || '';
                        this.hotelId = params.get('hotel_id') || '';
                        this.roomId = params.get('room_id') || '';
                        this.navigating = true;
                        this.applyFilter().then(() => {
                            this.navigating = false;
                        });
                    });
                }
            }
        }
    </script>
@endsection