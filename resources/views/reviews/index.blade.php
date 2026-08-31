<x-frontend.layout :title="'Guest Reviews — SunnyTrips'">
    @php
        $tabs = [
            ['key' => 'all', 'label' => 'All Reviews'],
            ['key' => 'hotels', 'label' => 'Hotels & Rooms'],
            ['key' => 'activities', 'label' => 'Activities'],
            ['key' => 'packages', 'label' => 'Packages'],
        ];
        $ratingFilters = [
            ['key' => 0, 'label' => 'All'],
            ['key' => 5, 'label' => '5★'],
            ['key' => 4, 'label' => '4★'],
            ['key' => 3, 'label' => '3★'],
            ['key' => 2, 'label' => '2★'],
            ['key' => 1, 'label' => '1★'],
        ];
        $sentimentFilters = [
            ['key' => '', 'label' => 'All Sentiments'],
            ['key' => 'positive', 'label' => 'Positive'],
            ['key' => 'neutral', 'label' => 'Neutral'],
            ['key' => 'negative', 'label' => 'Negative'],
        ];
        $sorts = [
            ['key' => 'recent', 'label' => 'Most Recent'],
            ['key' => 'highest', 'label' => 'Highest Rating'],
            ['key' => 'lowest', 'label' => 'Lowest Rating'],
            ['key' => 'helpful', 'label' => 'Most Helpful'],
        ];
        $entityIcon = [];
        $sentimentIcon = ['positive' => 'sentiment_satisfied', 'neutral' => 'sentiment_neutral', 'negative' => 'sentiment_dissatisfied'];
        $sentimentTint = ['positive' => 'bg-emerald-50 text-emerald-700 border-emerald-100', 'neutral' => 'bg-amber-50 text-amber-700 border-amber-100', 'negative' => 'bg-rose-50 text-rose-700 border-rose-100'];
    @endphp

    <div class="min-h-screen bg-sand-50/70 font-body">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

            {{-- Masthead --}}
            <div class="flex items-center justify-between mb-5">
                <p class="font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400">
                    SunnyTrips · Traveler Insights Hub
                </p>
                <!-- <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400">
                    {{ now()->format('M j, Y') }}
                </p> -->
            </div>

            <header class="mb-8 animate-fade-up">
                <h1 class="font-headline text-3xl sm:text-4xl font-black tracking-tight text-slate-900">
                    Verified Guest Reviews
                </h1>
                <p class="font-body text-xs sm:text-sm text-slate-500 leading-relaxed mt-2 max-w-xl">
                    Honest, booking-verified feedback across hotels, rooms, activities, and packages —
                    distilled with our AI Decision Support System.
                </p>
            </header>

            {{-- Platform overall summary --}}
            @if ($platform)
                <x-reviews.summary-box :summary="$platform" title="SunnyTrips Platform Overall" />
            @endif

            {{-- Controls + feed --}}
            <div x-data="reviewHub({{ Js::from($reviews) }})" x-init="$watch('lightbox', v => document.body.classList.toggle('overflow-hidden', !!v))" class="mt-8 animate-fade-up">
                {{-- Lightbox --}}
                <div x-show="lightbox" x-cloak @click="lightbox=null" @keydown.escape.window="lightbox=null" x-transition.opacity class="fixed inset-0 z-[80] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
                    <img :src="lightbox" class="max-w-full max-h-[85vh] rounded-2xl shadow-2xl border border-white/20">
                    <button type="button" @click="lightbox=null" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/90 text-slate-700 flex items-center justify-center cursor-pointer"><span class="material-symbols-outlined text-[18px]">close</span></button>
                </div>

                {{-- Tab bar --}}
                <div class="flex items-center gap-2 flex-wrap mb-4">
                    @foreach ($tabs as $t)
                        <button @click="setTab('{{ $t['key'] }}')"
                            :class="tab === '{{ $t['key'] }}' ? 'bg-slate-900 text-white border-slate-900 shadow-md' : 'bg-white text-slate-600 border-sand-200 hover:bg-sand-100'"
                            class="px-4 py-2 rounded-full border text-[11px] font-bold transition cursor-pointer">
                            {{ $t['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- Rating + sentiment + sort + search --}}
                <div class="bg-white rounded-3xl border border-sand-200/80 shadow-sm p-5 sm:p-6 mb-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        <div>
                            <p class="font-label text-[9px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">
                                Rating</p>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @foreach ($ratingFilters as $r)
                                    <button @click="setRating({{ $r['key'] }})"
                                        :class="minRating === {{ $r['key'] }} ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white text-slate-600 border-sand-200 hover:bg-sand-50'"
                                        class="px-3 py-1.5 rounded-xl border text-[10px] font-bold transition cursor-pointer">
                                        {{ $r['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="font-label text-[9px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">
                                Sentiment</p>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @foreach ($sentimentFilters as $s)
                                    <button @click="setSentiment('{{ $s['key'] }}')"
                                        :class="sentiment === '{{ $s['key'] }}' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-sand-200 hover:bg-sand-50'"
                                        class="px-3 py-1.5 rounded-xl border text-[10px] font-bold transition cursor-pointer">
                                        {{ $s['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="font-label text-[9px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">
                                Sort</p>
                            <select x-model="sort" @change="applySort()"
                                class="w-full rounded-xl border border-sand-200 bg-white text-xs font-bold text-slate-700 px-3 py-2.5 focus:border-ocean-400 focus:ring-ocean-100">
                                @foreach ($sorts as $s)
                                    <option value="{{ $s['key'] }}">{{ $s['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 relative">
                        <span
                            class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
                        <input x-model="search" @input="refresh()" type="search"
                            placeholder="Search by hotel, room, activity, or package name..."
                            class="w-full rounded-2xl border border-sand-200 bg-white pl-11 pr-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-ocean-400 focus:ring-ocean-100">
                    </div>
                </div>

                {{-- Result count --}}
                <p class="text-[11px] text-slate-500 mb-4 font-bold">
                    <span x-text="filtered.length"></span>
                    <span x-text="filtered.length === 1 ? 'review' : 'reviews'"></span> shown
                </p>

                {{-- Empty state --}}
                <div x-show="filtered.length === 0" x-cloak
                    class="bg-white rounded-3xl border border-sand-200/80 shadow-sm px-6 py-14 text-center">
                    <div
                        class="w-16 h-16 rounded-3xl bg-sand-100 border border-sand-200 flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-3xl text-slate-400">search_off</span>
                    </div>
                    <h3 class="font-headline text-sm font-bold text-slate-900">No reviews match</h3>
                    <p class="text-xs text-slate-500 mt-1">Try widening your filters or clearing the search.</p>
                </div>

                {{-- Feed --}}
                <div class="space-y-4">
                    <template x-for="review in filtered" :key="review.id">
                        <article class="bg-white rounded-3xl border border-sand-200/80 shadow-sm p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div
                                        class="w-11 h-11 rounded-2xl bg-ocean-50 border border-ocean-100 text-ocean-700 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-[20px]"
                                            x-text="iconFor(review.entity_type)"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-900 font-headline truncate"
                                            x-text="review.entity_label"></p>
                                        <p class="text-[11px] text-slate-400 truncate">
                                            <span
                                                x-text="review.entity_location ? review.entity_location + ' · ' : ''"></span>
                                            <span x-text="review.reviewer_alias"></span> · <span
                                                x-text="review.created_at_label"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center gap-0.5 text-amber-400">
                                        <template x-for="i in 5" :key="i">
                                            <span class="material-symbols-outlined text-[16px]"
                                                :style="'font-variation-settings: \'FILL\' ' + (i <= review.rating ? 1 : 0)">star</span>
                                        </template>
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold border"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 border-emerald-100': review.sentiment === 'positive',
                                            'bg-amber-50 text-amber-700 border-amber-100': review.sentiment === 'neutral',
                                            'bg-rose-50 text-rose-700 border-rose-100': review.sentiment === 'negative'
                                        }">
                                        <span class="material-symbols-outlined text-[12px]"
                                            x-text="review.sentiment === 'positive' ? 'sentiment_satisfied' : (review.sentiment === 'negative' ? 'sentiment_dissatisfied' : 'sentiment_neutral')"></span>
                                        <span
                                            x-text="review.sentiment.charAt(0).toUpperCase() + review.sentiment.slice(1)"></span>
                                    </span>
                                </div>
                            </div>

                            <p class="text-sm text-slate-600 leading-relaxed mt-4" x-text="review.comment"></p>

                            <template x-if="review.images && review.images.length">
                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    <template x-for="(img, idx) in review.images.slice(0,3)" :key="idx">
                                        <button type="button" @click="lightbox = img" class="group relative overflow-hidden rounded-xl border border-sand-200 cursor-zoom-in">
                                            <img :src="img" :alt="review.entity_label + ' photo ' + (idx+1)" class="w-full h-24 sm:h-28 object-cover group-hover:scale-105 transition">
                                        </button>
                                    </template>
                                </div>
                            </template>

                            <div class="mt-4 flex items-center gap-1.5 flex-wrap">
                                <template x-for="(tag, ti) in review.keywords.slice(0, 5)" :key="ti">
                                    <span
                                        class="px-2.5 py-1 rounded-lg bg-sand-100 border border-sand-200 text-slate-600 text-[10px] font-bold">#<span
                                            x-text="tag"></span></span>
                                </template>
                                <span
                                    class="ml-auto inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-teal-50 text-teal-700 border border-teal-100 text-[10px] font-bold">
                                    <span class="material-symbols-outlined text-[12px]">verified</span>
                                    Verified Booking
                                </span>
                            </div>
                        </article>
                    </template>
                </div>
            </div>

            {{-- Colophon --}}
            <p class="text-center font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400 pt-10">
                SunnyTrips · AI-assisted sentiment analysis on verified bookings
            </p>
        </div>
    </div>

    @once
        <script>
            function reviewHub(initialReviews) {
                return {
                    all: initialReviews,
                    filtered: initialReviews,
                    lightbox: null,
                    tab: 'all',
                    minRating: 0,
                    sentiment: '',
                    search: '',
                    sort: 'recent',

                    iconFor(type) {
                        const map = {
                            'App\\Models\\HotelModel': 'hotel',
                            'room': 'king_bed',
                            'activity': 'explore',
                            'package': 'card_travel'
                        };
                        return map[type] || 'reviews';
                    },
                    setTab(key) {
                        this.tab = key;
                        this.refresh();
                    },
                    setRating(key) {
                        this.minRating = key;
                        this.refresh();
                    },
                    setSentiment(key) {
                        this.sentiment = key;
                        this.refresh();
                    },
                    applySort() {
                        this.refresh();
                    },
                    matches(review) {
                        if (this.tab !== 'all') {
                            if (this.tab === 'hotels') {
                                if (!['App\\Models\\HotelModel', 'room'].includes(review.entity_type)) return false;
                            } else if (this.tab === 'rooms' && review.entity_type !== 'room') return false;
                            else if (this.tab === 'activities' && review.entity_type !== 'activity') return false;
                            else if (this.tab === 'packages' && review.entity_type !== 'package') return false;
                        }
                        if (this.minRating > 0 && review.rating !== this.minRating) return false;
                        if (this.sentiment && review.sentiment !== this.sentiment) return false;
                        if (this.search) {
                            const q = this.search.toLowerCase();
                            const hay = [
                                review.entity_label, review.entity_location || '',
                                review.comment, (review.keywords || []).join(' ')
                            ].join(' ').toLowerCase();
                            if (!hay.includes(q)) return false;
                        }
                        return true;
                    },
                    refresh() {
                        const filtered = this.all.filter(r => this.matches(r));
                        if (this.sort === 'highest') filtered.sort((a, b) => b.rating - a.rating || b.id - a.id);
                        else if (this.sort === 'lowest') filtered.sort((a, b) => a.rating - b.rating || b.id - a.id);
                        else if (this.sort === 'helpful') filtered.sort((a, b) => (b.keywords || []).length - (a.keywords || []).length || b.id - a.id);
                        else filtered.sort((a, b) => b.id - a.id);
                        this.filtered = filtered;
                    }
                };
            }
        </script>
    @endonce
</x-frontend.layout>