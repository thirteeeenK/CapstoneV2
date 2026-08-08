<x-frontend.layout title="I'm Feeling Lucky | SunnyTrips">
    <div x-data="luckyManager()" class="min-h-screen bg-sand-50 font-body">
        <div id="lucky-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">

            {{-- Hero --}}
            <div class="text-center mb-10">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-coral-50 text-coral-600 border border-coral-200 px-3 py-1 text-[11px] font-bold tracking-widest uppercase mb-4">
                    <span class="material-symbols-outlined text-sm">casino</span>
                    Surprise Me
                </span>
                <h1 class="font-display text-3xl sm:text-5xl font-extrabold tracking-tight text-ink-900">
                    I'm Feeling <span class="bg-gradient-to-r from-ocean-600 to-coral-500 bg-clip-text text-transparent">Lucky</span>
                </h1>
                <p class="text-sm sm:text-base text-ink-500 mt-3 max-w-xl mx-auto leading-relaxed">
                    One click generates a complete surprise itinerary — hotel and activities
                    in a single destination, priced to fit your budget. Shuffle as many times as you like.
                </p>
            </div>

            {{-- Filters --}}
            <div class="bg-white rounded-2xl ring-1 ring-sand-200 shadow-sm shadow-ocean-900/5 p-5 sm:p-7 mb-8">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">

                    <div class="col-span-2 sm:col-span-1">
                        <label for="lucky-destination" class="block text-xs font-semibold text-ink-600 mb-1.5">Destination</label>
                        <select id="lucky-destination" x-model="filters.destination_id"
                                class="w-full rounded-xl border-sand-300 bg-sand-50 text-sm focus:ring-ocean-500 focus:border-ocean-500 cursor-pointer">
                            <option value="">Anywhere</option>
                            @foreach($destinations as $destination)
                                <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="lucky-budget" class="block text-xs font-semibold text-ink-600 mb-1.5">Max budget</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-ink-400">₱</span>
                            <input id="lucky-budget" type="number" min="500" step="500" x-model.number="filters.max_budget"
                                   class="w-full rounded-xl border-sand-300 bg-sand-50 pl-8 text-sm focus:ring-ocean-500 focus:border-ocean-500">
                        </div>
                    </div>

                    <div>
                        <label for="lucky-activities" class="block text-xs font-semibold text-ink-600 mb-1.5">Activities</label>
                        <select id="lucky-activities" x-model.number="filters.activity_count"
                                class="w-full rounded-xl border-sand-300 bg-sand-50 text-sm focus:ring-ocean-500 focus:border-ocean-500 cursor-pointer">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>

                    <div>
                        <label for="lucky-nights" class="block text-xs font-semibold text-ink-600 mb-1.5">Trip duration</label>
                        <select id="lucky-nights" x-model.number="filters.nights"
                                class="w-full rounded-xl border-sand-300 bg-sand-50 text-sm focus:ring-ocean-500 focus:border-ocean-500 cursor-pointer">
                            <option value="1">1 night</option>
                            <option value="2">2 nights</option>
                            <option value="3">3 nights</option>
                            <option value="4">4 nights</option>
                            <option value="5">5 nights</option>
                            <option value="6">6 nights</option>
                            <option value="7">7 nights</option>
                        </select>
                    </div>

                    <div>
                        <label for="lucky-category" class="block text-xs font-semibold text-ink-600 mb-1.5">Hotel category</label>
                        <select id="lucky-category" x-model="filters.hotel_category"
                                class="w-full rounded-xl border-sand-300 bg-sand-50 text-sm focus:ring-ocean-500 focus:border-ocean-500 cursor-pointer">
                            <option value="">Any</option>
                            <option value="budget">Budget (≤ ₱2,500/night)</option>
                            <option value="mid">Mid-range (₱2,500–6,000)</option>
                            <option value="luxury">Luxury (≥ ₱6,000)</option>
                        </select>
                    </div>

                    <div>
                        <label for="lucky-pax" class="block text-xs font-semibold text-ink-600 mb-1.5">Travelers</label>
                        <select id="lucky-pax" x-model.number="filters.pax"
                                class="w-full rounded-xl border-sand-300 bg-sand-50 text-sm focus:ring-ocean-500 focus:border-ocean-500 cursor-pointer">
                            <option value="1">1 traveler</option>
                            <option value="2">2 travelers</option>
                            <option value="3">3 travelers</option>
                            <option value="4">4 travelers</option>
                        </select>
                    </div>

                    <div>
                        <label for="lucky-level" class="block text-xs font-semibold text-ink-600 mb-1.5">Activity vibe</label>
                        <select id="lucky-level" x-model="filters.activity_level"
                                class="w-full rounded-xl border-sand-300 bg-sand-50 text-sm focus:ring-ocean-500 focus:border-ocean-500 cursor-pointer">
                            <option value="">Any</option>
                            <option value="Relaxing">Relaxing</option>
                            <option value="Sightseeing">Sightseeing</option>
                            <option value="Adventure">Adventure</option>
                            <option value="Extreme">Extreme</option>
                            <option value="Underwater">Underwater</option>
                        </select>
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label for="lucky-date" class="block text-xs font-semibold text-ink-600 mb-1.5">Start date</label>
                        <input id="lucky-date" type="date" x-model="filters.start_date"
                               class="w-full rounded-xl border-sand-300 bg-sand-50 text-sm focus:ring-ocean-500 focus:border-ocean-500">
                    </div>
                </div>
            </div>

            {{-- Shuffle Button --}}
            <div class="text-center mb-10">
                <button type="button" @click="shuffle()" :disabled="loading || accepting"
                        class="inline-flex items-center gap-2.5 px-10 sm:px-14 py-4 rounded-full bg-gradient-to-r from-ocean-700 to-sky-500 text-white font-display font-bold text-base sm:text-lg shadow-lg shadow-ocean-600/30 hover:shadow-xl hover:shadow-ocean-600/40 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer disabled:opacity-60 disabled:cursor-wait disabled:hover:translate-y-0">
                    <template x-if="loading">
                        <x-thinking-orb state="working" :size="20" light class="shrink-0" />
                    </template>
                    <template x-if="!loading">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 3l-7 7m-3-3h3m-3 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 012-2h2m9-6h3a2 2 0 012 2v3a2 2 0 01-2 2h-2m-6 6h3m-3 4h3"/>
                        </svg>
                    </template>
                    <span x-text="loading ? 'Rolling the dice…' : (itinerary ? 'Shuffle Again' : 'Generate My Surprise')"></span>
                </button>

                <template x-if="error">
                    <p class="mt-4 text-sm font-semibold text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 inline-block" x-text="error"></p>
                </template>
            </div>

            {{-- Result --}}
            <template x-if="itinerary">
                <div class="bg-white rounded-3xl ring-1 ring-sand-200 shadow-md shadow-ocean-900/5 overflow-hidden animate-fade-up">

                    {{-- Destination header --}}
                    <div class="relative bg-gradient-to-br from-ocean-800 to-sky-600 px-6 sm:px-8 py-6 text-white">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/15 border border-white/25 px-3 py-1 text-[11px] font-bold tracking-widest uppercase">
                                    <span class="material-symbols-outlined text-sm">explore</span>
                                    Your surprise itinerary
                                </span>
                                <h2 class="font-display text-2xl sm:text-3xl font-bold mt-2.5" x-text="itinerary.destination.name"></h2>
                                <p class="text-sm text-white/80 mt-1">
                                    <span x-text="itinerary.nights + ' night' + (itinerary.nights > 1 ? 's' : '')"></span>
                                    <span x-text="' • ' + itinerary.pax + ' traveler' + (itinerary.pax > 1 ? 's' : '')"></span>
                                    <span x-text="' • ' + itinerary.check_in_date + ' → ' + itinerary.check_out_date"></span>
                                </p>
                            </div>
                            <div class="text-right">
                                <template x-if="itinerary.budget_exceeded">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-400/20 text-amber-100 border border-amber-300/40 px-3 py-1 text-[11px] font-bold uppercase tracking-wide mb-1">
                                        <span class="material-symbols-outlined text-sm">warning</span>
                                        Over budget
                                    </span>
                                </template>
                                <template x-if="!itinerary.budget_exceeded">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-400/20 text-emerald-100 border border-emerald-300/40 px-3 py-1 text-[11px] font-bold uppercase tracking-wide mb-1">
                                        <span class="material-symbols-outlined text-sm">check_circle</span>
                                        Within budget
                                    </span>
                                </template>
                                <p class="font-display text-3xl font-extrabold" x-text="itinerary.formatted_total"></p>
                                <p class="text-xs text-white/75" x-text="'Budget: ' + itinerary.formatted_budget"></p>
                            </div>
                        </div>
                        <template x-if="itinerary.budget_notice">
                            <p class="mt-3 text-xs text-amber-100 bg-amber-500/20 border border-amber-300/30 rounded-xl px-3 py-2" x-text="itinerary.budget_notice"></p>
                        </template>
                    </div>

                    <div class="p-6 sm:p-8 space-y-8">

                        {{-- Stay --}}
                        <div>
                            <h3 class="font-display text-sm font-bold text-ink-900 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <span class="material-symbols-outlined text-ocean-600">king_bed</span>
                                Your stay
                            </h3>
                            <div class="flex flex-col sm:flex-row gap-4 rounded-2xl ring-1 ring-sand-200 bg-sand-50 p-4">
                                <template x-if="itinerary.room.image || itinerary.hotel.image">
                                    <img :src="itinerary.room.image || itinerary.hotel.image"
                                         :alt="itinerary.hotel.hotel_name"
                                         class="w-full sm:w-36 h-40 sm:h-28 rounded-xl object-cover ring-1 ring-sand-200 shrink-0">
                                </template>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-ocean-700" x-text="itinerary.hotel.hotel_name"></p>
                                    <p class="text-sm font-semibold text-ink-900 mt-1" x-text="itinerary.room.room_name"></p>
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        <template x-if="itinerary.hotel.type">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-white border border-sand-300 px-2.5 py-0.5 text-[11px] font-medium text-ink-600 capitalize" x-text="itinerary.hotel.type"></span>
                                        </template>
                                        <template x-if="itinerary.room.bed_configuration">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-white border border-sand-300 px-2.5 py-0.5 text-[11px] font-medium text-ink-600">
                                                <span class="material-symbols-outlined text-[12px] text-ink-400">king_bed</span>
                                                <span x-text="itinerary.room.bed_configuration"></span>
                                            </span>
                                        </template>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-white border border-sand-300 px-2.5 py-0.5 text-[11px] font-medium text-ink-600">
                                            <span class="material-symbols-outlined text-[12px] text-ink-400">group</span>
                                            <span x-text="'Up to ' + itinerary.room.max_occupancy + ' guests'"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="block text-xs text-ink-500 font-medium" x-text="itinerary.room.formatted_nightly_rate + ' / night'"></span>
                                    <span class="block text-lg font-bold text-ink-900 mt-0.5" x-text="'₱' + (Number(itinerary.room.nightly_rate) * Number(itinerary.nights)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Activities --}}
                        <div>
                            <h3 class="font-display text-sm font-bold text-ink-900 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <span class="material-symbols-outlined text-ocean-600">explore</span>
                                <span x-text="'Your experiences (' + itinerary.activities.length + ')'"></span>
                            </h3>
                            <div class="space-y-3">
                                <template x-for="(activity, i) in itinerary.activities" :key="activity.id">
                                    <div class="flex items-center gap-4 rounded-2xl ring-1 ring-sand-200 bg-sand-50 p-4">
                                        <span class="w-8 h-8 rounded-full bg-ocean-600 text-white text-sm font-bold flex items-center justify-center shrink-0" x-text="i + 1"></span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-ink-900" x-text="activity.activity_name"></p>
                                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                                <span class="inline-flex items-center rounded-full bg-white border border-sand-300 px-2.5 py-0.5 text-[11px] font-medium text-ink-600 capitalize" x-text="activity.category"></span>
                                                <span class="inline-flex items-center rounded-full bg-white border border-sand-300 px-2.5 py-0.5 text-[11px] font-medium text-ink-600 capitalize" x-text="activity.activity_level"></span>
                                                <template x-if="activity.duration">
                                                    <span class="inline-flex items-center rounded-full bg-white border border-sand-300 px-2.5 py-0.5 text-[11px] font-medium text-ink-600" x-text="activity.duration"></span>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="block text-xs text-ink-500 font-medium">for <span x-text="itinerary.pax"></span> pax</span>
                                            <span class="block text-sm font-bold text-ink-900 mt-0.5" x-text="activity.formatted_rate"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Totals + actions --}}
                        <div class="rounded-2xl bg-gradient-to-r from-sand-100 to-white ring-1 ring-sand-200 p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="text-center sm:text-left">
                                <p class="text-xs text-ink-500 font-medium">Estimated itinerary total</p>
                                <p class="font-display text-2xl font-extrabold text-ink-900" x-text="itinerary.formatted_total"></p>
                            </div>
                            <div class="flex flex-wrap justify-center gap-3">
                                <button type="button" @click="shuffle()" :disabled="loading || accepting"
                                        class="inline-flex items-center gap-2 px-6 py-3 rounded-full border border-sand-300 bg-white text-ink-700 hover:bg-sand-100 font-semibold text-sm transition cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <span>Shuffle</span>
                                </button>
                                <button type="button" @click="acceptItinerary()" :disabled="loading || accepting"
                                        class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-ocean-600 hover:bg-ocean-500 text-white font-semibold text-sm shadow-sm shadow-ocean-600/25 transition cursor-pointer disabled:opacity-60">
                                    <template x-if="accepting">
                                        <span class="w-4 h-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
                                    </template>
                                    <template x-if="!accepting">
                                        <span class="material-symbols-outlined text-[18px]">shopping_basket</span>
                                    </template>
                                    <span x-text="accepting ? 'Adding to basket…' : 'Add to Trip Basket'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <script>
    function luckyManager() {
        return {
            filters: {
                destination_id: '',
                max_budget: 20000,
                activity_count: 2,
                nights: 2,
                hotel_category: '',
                pax: 2,
                activity_level: '',
                start_date: '',
            },
            loading: false,
            accepting: false,
            error: null,
            itinerary: null,

            async shuffle() {
                this.loading = true;
                this.error = null;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('{{ route('lucky.generate') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(this.filters)
                    });
                    const data = await res.json();
                    if (data.success && data.itinerary) {
                        this.itinerary = data.itinerary;
                        document.querySelector('#lucky-root')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        this.error = data.message || 'Could not generate an itinerary. Please adjust your filters.';
                    }
                } catch (err) {
                    console.error('Error generating itinerary:', err);
                    this.error = 'Something went wrong. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            async acceptItinerary() {
                if (!this.itinerary) return;
                this.accepting = true;
                this.error = null;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('{{ route('lucky.accept') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            destination_id: this.itinerary.destination.id,
                            room_id: this.itinerary.room.id,
                            activity_ids: this.itinerary.activities.map(a => a.id),
                            nights: this.itinerary.nights,
                            pax: this.itinerary.pax,
                            max_budget: this.filters.max_budget,
                            budget_exceeded: this.itinerary.budget_exceeded,
                            check_in_date: this.itinerary.check_in_date,
                            check_out_date: this.itinerary.check_out_date,
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                        window.location.href = data.redirect_url || '{{ route('cart.index') }}';
                    } else {
                        this.error = data.message || 'Could not add the itinerary to your basket.';
                    }
                } catch (err) {
                    console.error('Error accepting itinerary:', err);
                    this.error = 'Something went wrong. Please try again.';
                } finally {
                    this.accepting = false;
                }
            }
        };
    }
    </script>
</x-frontend.layout>
