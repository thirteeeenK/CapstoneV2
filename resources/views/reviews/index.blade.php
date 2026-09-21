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

    <div class="pt-20 sm:pt-28 pb-12 min-h-screen bg-sand-50/70 font-body">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

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
                    Guest Reviews
                </h1>
                <p class="font-body text-xs sm:text-sm text-slate-500 leading-relaxed mt-2 max-w-xl">
                    Honest guest feedback across hotels, rooms, activities, and packages —
                    distilled with our AI Decision Support System.
                </p>
            </header>

            {{-- Platform overall summary --}}
            @if ($platform)
                <x-reviews.summary-box :summary="$platform" title="SunnyTrips Platform Overall" />
            @endif

            {{-- Controls + feed --}}
            <div x-data="reviewHub({{ Js::from($reviews) }}, {{ Js::from(['tab' => $tab, 'minRating' => $minRating, 'sentiment' => $sentiment ?? '', 'sort' => $sort, 'search' => $search]) }})" x-init="$watch('lightbox', v => document.body.classList.toggle('overflow-hidden', !!v))" class="mt-8 animate-fade-up">
                {{-- Lightbox Modal -- Teleported to body to avoid ancestor transform/animation containing-block traps --}}
                <template x-teleport="body">
                    <div x-show="lightbox" x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        @click.self="closeLightbox()"
                        @keydown.escape.window="if (lightbox) closeLightbox()"
                        @keydown.left.window="if (lightbox) prevLightboxImage()"
                        @keydown.right.window="if (lightbox) nextLightboxImage()"
                        class="fixed inset-0 z-[120] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
                        <div class="relative bg-white rounded-3xl shadow-2xl border border-sand-200 w-full max-w-[min(94vw,680px)] overflow-hidden flex flex-col my-auto"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            @click.stop>

                            {{-- Header --}}
                            <div class="px-5 py-4 border-b border-sand-100 flex items-center justify-between gap-3 bg-white">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-xl bg-ocean-50 border border-ocean-100 text-ocean-700 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-[18px]" x-text="lightboxReview ? iconFor(lightboxReview.entity_type) : 'photo'"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-xs sm:text-sm font-bold text-slate-900 font-headline truncate"
                                            x-text="lightboxReview?.entity_label || 'Guest Review Photo'"></h4>
                                        <p class="text-[11px] text-slate-400 truncate">
                                            <span x-text="lightboxReview?.entity_location ? lightboxReview.entity_location + ' · ' : ''"></span>
                                            <span x-text="lightboxReview?.reviewer_alias || 'Guest'"></span>
                                            <span x-text="lightboxReview?.created_at_label ? ' · ' + lightboxReview.created_at_label : ''"></span>
                                        </p>
                                    </div>
                                </div>
                                <button type="button" @click="closeLightbox()"
                                    class="w-8 h-8 rounded-xl bg-sand-100 hover:bg-sand-200 text-slate-500 hover:text-slate-900 flex items-center justify-center transition cursor-pointer shrink-0">
                                    <span class="material-symbols-outlined text-[18px]">close</span>
                                </button>
                            </div>

                            {{-- Image Container with Navigation --}}
                            <div class="relative bg-slate-950 flex items-center justify-center p-2 min-h-[220px] max-h-[60vh] sm:max-h-[66vh] overflow-hidden">
                                <img :src="lightboxImg" :alt="(lightboxReview?.entity_label || 'Review') + ' photo'"
                                    class="max-w-full max-h-[58vh] sm:max-h-[64vh] w-auto h-auto object-contain rounded-xl block mx-auto">

                                {{-- Previous Image button --}}
                                <template x-if="lightboxReview && lightboxReview.images && lightboxReview.images.length > 1">
                                    <button type="button" @click.stop="prevLightboxImage()"
                                        class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-slate-900/70 hover:bg-slate-900 text-white flex items-center justify-center transition cursor-pointer shadow-lg backdrop-blur-xs">
                                        <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                                    </button>
                                </template>

                                {{-- Next Image button --}}
                                <template x-if="lightboxReview && lightboxReview.images && lightboxReview.images.length > 1">
                                    <button type="button" @click.stop="nextLightboxImage()"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-slate-900/70 hover:bg-slate-900 text-white flex items-center justify-center transition cursor-pointer shadow-lg backdrop-blur-xs">
                                        <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                                    </button>
                                </template>

                                {{-- Photo counter indicator --}}
                                <template x-if="lightboxReview && lightboxReview.images && lightboxReview.images.length > 1">
                                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 px-2.5 py-1 rounded-full bg-slate-950/70 backdrop-blur-sm text-[10px] font-bold text-white tracking-wide border border-white/10">
                                        <span x-text="(lightboxImgIdx + 1)"></span> / <span x-text="lightboxReview.images.length"></span>
                                    </div>
                                </template>
                            </div>

                            {{-- Review Details Footer --}}
                            <template x-if="lightboxReview">
                                <div class="p-4 sm:p-5 bg-white border-t border-sand-100">
                                    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="flex items-center gap-0.5 text-amber-400">
                                                <template x-for="i in 5" :key="i">
                                                    <span class="material-symbols-outlined text-[15px]"
                                                        :style="'font-variation-settings: \'FILL\' ' + (i <= lightboxReview.rating ? 1 : 0)">star</span>
                                                </template>
                                            </span>
                                            <span class="text-xs font-bold text-slate-700 font-headline" x-text="lightboxReview.rating + '/5'"></span>
                                        </div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold border"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 border-emerald-100': lightboxReview.sentiment === 'positive',
                                                'bg-amber-50 text-amber-700 border-amber-100': lightboxReview.sentiment === 'neutral',
                                                'bg-rose-50 text-rose-700 border-rose-100': lightboxReview.sentiment === 'negative'
                                            }">
                                            <span class="material-symbols-outlined text-[12px]"
                                                x-text="lightboxReview.sentiment === 'positive' ? 'sentiment_satisfied' : (lightboxReview.sentiment === 'negative' ? 'sentiment_dissatisfied' : 'sentiment_neutral')"></span>
                                            <span x-text="lightboxReview.sentiment ? (lightboxReview.sentiment.charAt(0).toUpperCase() + lightboxReview.sentiment.slice(1)) : ''"></span>
                                        </span>
                                    </div>
                                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed" x-text="lightboxReview.comment"></p>

                                    {{-- Thumbnails strip if multiple photos --}}
                                    <template x-if="lightboxReview.images && lightboxReview.images.length > 1">
                                        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-sand-100">
                                            <template x-for="(tImg, tIdx) in lightboxReview.images" :key="tIdx">
                                                <button type="button" @click="lightboxImg = tImg; lightboxImgIdx = tIdx"
                                                    :class="lightboxImgIdx === tIdx ? 'ring-2 ring-ocean-500 border-transparent' : 'opacity-60 hover:opacity-100 border-sand-200'"
                                                    class="w-12 h-12 rounded-lg overflow-hidden border bg-sand-50 transition cursor-pointer shrink-0">
                                                    <img :src="tImg" class="w-full h-full object-cover">
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

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

                    <form method="GET" action="{{ route('reviews.index') }}" class="mt-5 relative">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <input type="hidden" name="rating" value="{{ $minRating }}">
                        <input type="hidden" name="sentiment" value="{{ $sentiment }}">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <span
                            class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
                        <input name="search" value="{{ $search }}" type="search"
                            placeholder="Search by hotel, room, activity, or package name..."
                            class="w-full rounded-2xl border border-sand-200 bg-white pl-11 pr-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-ocean-400 focus:ring-ocean-100">
                    </form>
                </div>

                {{-- Result count --}}
                <p class="text-[11px] text-slate-500 mb-4 font-bold">
                    Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}
                    of {{ $paginator->total() }}
                    {{ $paginator->total() === 1 ? 'review' : 'reviews' }}
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
                                <div class="mt-3.5 grid grid-cols-3 sm:grid-cols-4 gap-2.5">
                                    <template x-for="(img, idx) in review.images.slice(0,3)" :key="idx">
                                        <button type="button" @click="openLightbox(review, img)"
                                            class="group relative aspect-[4/3] w-full overflow-hidden rounded-xl border border-sand-200 bg-sand-100/50 flex items-center justify-center hover:border-ocean-300 hover:shadow-md transition-all cursor-zoom-in">
                                            <img :src="img" :alt="review.entity_label + ' photo ' + (idx+1)"
                                                class="w-full h-full object-cover rounded-xl group-hover:scale-105 transition-transform duration-300"
                                                loading="lazy"
                                                x-on:error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'">
                                            <div style="display:none" class="w-full h-full items-center justify-center flex flex-col gap-1 text-[10px] font-bold text-slate-400 bg-sand-100 rounded-xl">
                                                <span class="material-symbols-outlined text-[16px]">broken_image</span>
                                                <span>No image</span>
                                            </div>
                                            <div class="absolute inset-0 bg-slate-950/0 group-hover:bg-slate-950/20 transition-colors rounded-xl flex items-center justify-center opacity-0 group-hover:opacity-100">
                                                <span class="material-symbols-outlined text-white text-[20px] drop-shadow-md">zoom_in</span>
                                            </div>
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
                            </div>
                        </article>
                    </template>
                </div>

                {{-- Pagination --}}
                @if ($paginator->hasPages())
                    <div class="mt-6">
                        {{ $paginator->appends(request()->except('page'))->links() }}
                    </div>
                @endif
            </div>

            {{-- Colophon --}}
            <p class="text-center font-label text-[10px] uppercase font-bold tracking-[0.25em] text-slate-400 pt-10">
                SunnyTrips · AI-assisted sentiment analysis on guest reviews
            </p>
        </div>
    </div>

    @once
        <script>
            function reviewHub(initialReviews, state) {
                return {
                    all: initialReviews,
                    filtered: initialReviews,
                    lightbox: false,
                    lightboxReview: null,
                    lightboxImg: null,
                    lightboxImgIdx: 0,
                    tab: state.tab,
                    minRating: state.minRating,
                    sentiment: state.sentiment,
                    search: state.search,
                    sort: state.sort,

                    openLightbox(review, img) {
                        this.lightboxReview = review;
                        this.lightboxImg = img;
                        this.lightboxImgIdx = (review.images || []).indexOf(img);
                        if (this.lightboxImgIdx < 0) this.lightboxImgIdx = 0;
                        this.lightbox = true;
                    },
                    closeLightbox() {
                        this.lightbox = false;
                        this.lightboxReview = null;
                        this.lightboxImg = null;
                        this.lightboxImgIdx = 0;
                    },
                    nextLightboxImage() {
                        if (!this.lightboxReview || !this.lightboxReview.images || this.lightboxReview.images.length <= 1) return;
                        this.lightboxImgIdx = (this.lightboxImgIdx + 1) % this.lightboxReview.images.length;
                        this.lightboxImg = this.lightboxReview.images[this.lightboxImgIdx];
                    },
                    prevLightboxImage() {
                        if (!this.lightboxReview || !this.lightboxReview.images || this.lightboxReview.images.length <= 1) return;
                        this.lightboxImgIdx = (this.lightboxImgIdx - 1 + this.lightboxReview.images.length) % this.lightboxReview.images.length;
                        this.lightboxImg = this.lightboxReview.images[this.lightboxImgIdx];
                    },
                    iconFor(type) {
                        const map = {
                            'hotel': 'hotel',
                            'room': 'king_bed',
                            'activity': 'explore',
                            'package': 'card_travel'
                        };
                        return map[type] || 'reviews';
                    },
                    setTab(key) {
                        this.go({ tab: key });
                    },
                    setRating(key) {
                        this.go({ rating: key });
                    },
                    setSentiment(key) {
                        this.go({ sentiment: key });
                    },
                    applySort() {
                        this.go({});
                    },
                    go(overrides) {
                        const q = new URLSearchParams({
                            tab: this.tab,
                            rating: this.minRating,
                            sentiment: this.sentiment,
                            sort: this.sort,
                            search: this.search,
                            ...overrides,
                        });
                        for (const [k, v] of [...q]) {
                            if (v === '' || v === null || v === undefined) q.delete(k);
                        }
                        window.location.search = q.toString();
                    },
                    refresh() {
                        this.filtered = [...this.all];
                    }
                };
            }
        </script>
    @endonce
</x-frontend.layout>