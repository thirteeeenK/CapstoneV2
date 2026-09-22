<x-frontend.layout title="I'm Feeling Lucky | SunnyTrips">
    <div x-data="luckyManager()"
        class="min-h-screen bg-sand-50/70 text-slate-900 font-body py-10 sm:py-16 relative overflow-hidden">

        {{-- Background Soft Ambient Mesh Glows --}}
        <div
            class="absolute top-12 left-1/4 w-[500px] h-[300px] bg-ocean-200/40 blur-3xl rounded-full pointer-events-none">
        </div>
        <div
            class="absolute bottom-20 right-1/4 w-[450px] h-[350px] bg-sky-200/30 blur-3xl rounded-full pointer-events-none">
        </div>

        <div id="lucky-root" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10 relative z-10">

            {{-- Hero Header --}}
            <div class="text-center space-y-3 max-w-2xl mx-auto">
                {{-- <div
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-ocean-50 text-ocean-700 text-xs font-bold uppercase tracking-widest border border-ocean-200">
                    <span class="material-symbols-outlined text-[16px] text-ocean-600">auto_awesome</span>
                    <span>Smart Travel Generator</span>
                </div> --}}
                <h1 class="text-3xl sm:text-5xl font-black text-slate-900 font-headline tracking-tight">
                    Instant Random Itineraries
                </h1>
                <p class="text-slate-500 text-xs sm:text-sm font-body leading-relaxed">
                    Generate an instant, curated trip package — hotel stay and experiences matched to your budget and
                    travel preferences.
                </p>
            </div>

            {{-- Filter Control Bar --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-4">
                    <span class="w-1.5 h-5 bg-ocean-600 rounded-full"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-headline">
                        Trip Preferences & Budget Limits
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">

                    {{-- Destination --}}
                    <div>
                        <label for="lucky-destination"
                            class="block font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-ocean-600">location_on</span>
                            <span>Destination</span>
                        </label>
                        <select id="lucky-destination" x-model="filters.destination_id"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-medium text-slate-800 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all cursor-pointer">
                            <option value="">Any Island</option>
                            @foreach($destinations as $destination)
                                <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Max Budget --}}
                    <div>
                        <label for="lucky-budget" class="block font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-ocean-600">payments</span>
                            <span>Max Budget</span>
                        </label>
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">₱</span>
                            <input id="lucky-budget" type="number" min="500" step="500"
                                x-model.number="filters.max_budget"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 pl-7 pr-3 text-xs font-medium text-slate-800 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all">
                        </div>
                    </div>

                    {{-- Trip Duration --}}
                    <div>
                        <label for="lucky-nights" class="block font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-ocean-600">calendar_month</span>
                            <span>Trip Duration</span>
                        </label>
                        <select id="lucky-nights" x-model.number="filters.nights"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-medium text-slate-800 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all cursor-pointer">
                            <option value="1">1 night</option>
                            <option value="2">2 nights</option>
                            <option value="3">3 nights</option>
                            <option value="4">4 nights</option>
                            <option value="5">5 nights</option>
                            <option value="6">6 nights</option>
                            <option value="7">7 nights</option>
                        </select>
                    </div>

                    {{-- Travelers --}}
                    <div>
                        <label for="lucky-pax" class="block font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-ocean-600">group</span>
                            <span>Travelers</span>
                        </label>
                        <select id="lucky-pax" x-model.number="filters.pax"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-medium text-slate-800 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all cursor-pointer">
                            <option value="1">1 traveler</option>
                            <option value="2">2 travelers</option>
                            <option value="3">3 travelers</option>
                            <option value="4">4 travelers</option>
                        </select>
                    </div>

                    {{-- Hotel Category --}}
                    <div>
                        <label for="lucky-category"
                            class="block font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-ocean-600">hotel</span>
                            <span>Hotel Style</span>
                        </label>
                        <select id="lucky-category" x-model="filters.hotel_category"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-medium text-slate-800 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all cursor-pointer">
                            <option value="">Any Category</option>
                            <option value="budget">Budget (≤ ₱2,500/night)</option>
                            <option value="mid">Mid-range (₱2,500–₱6,000)</option>
                            <option value="luxury">Luxury (≥ ₱6,000)</option>
                        </select>
                    </div>

                    {{-- Activity Count --}}
                    <div>
                        <label for="lucky-activities"
                            class="block font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                            <span
                                class="material-symbols-outlined text-[15px] text-ocean-600">format_list_bulleted</span>
                            <span>Activities Count</span>
                        </label>
                        <select id="lucky-activities" x-model.number="filters.activity_count"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-medium text-slate-800 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all cursor-pointer">
                            <option value="1">1 Experience</option>
                            <option value="2">2 Experiences</option>
                            <option value="3">3 Experiences</option>
                            <option value="4">4 Experiences</option>
                            <option value="5">5 Experiences</option>
                        </select>
                    </div>

                    {{-- Activity Vibe --}}
                    <div>
                        <label for="lucky-level" class="block font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-ocean-600">hiking</span>
                            <span>Activity Vibe</span>
                        </label>
                        <select id="lucky-level" x-model="filters.activity_level"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-medium text-slate-800 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all cursor-pointer">
                            <option value="">Any Vibe</option>
                            <option value="Relaxing">Relaxing</option>
                            <option value="Sightseeing">Sightseeing</option>
                            <option value="Adventure">Adventure</option>
                            <option value="Extreme">Extreme</option>
                            <option value="Underwater">Underwater</option>
                        </select>
                    </div>

                    {{-- Start Date --}}
                    <div>
                        <label for="lucky-date" class="block font-bold text-slate-700 mb-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px] text-ocean-600">today</span>
                            <span>Start Date</span>
                        </label>
                        <input id="lucky-date" type="date" x-model="filters.start_date" :min="tomorrowStr"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-medium text-slate-800 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all">
                    </div>
                </div>
            </div>

            {{-- Shuffle Button CTA --}}
            <div class="text-center">
                <button type="button" @click="shuffle()" :disabled="loading || accepting"
                    class="inline-flex items-center gap-2.5 px-8 sm:px-12 py-3.5 rounded-2xl bg-ocean-600 hover:bg-ocean-700 text-white font-headline font-bold text-sm sm:text-base shadow-md shadow-ocean-600/20 hover:shadow-lg transition-all duration-200 cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                    <template x-if="loading">
                        <span class="w-4 h-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
                    </template>
                    <template x-if="!loading">
                        <span class="material-symbols-outlined text-[20px]">shuffle</span>
                    </template>
                    <span
                        x-text="loading ? 'Generating surprise package…' : (itinerary ? 'Shuffle New Itinerary' : 'Generate Surprise Itinerary')"></span>
                </button>

                <template x-if="error">
                    <div
                        class="mt-4 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 max-w-md mx-auto flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-rose-600">error</span>
                        <span x-text="error"></span>
                    </div>
                </template>
            </div>

            {{-- Generated Itinerary Result --}}
            <template x-if="itinerary">
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-md overflow-hidden animate-fade-up">

                    {{-- Destination Header --}}
                    <div class="relative bg-slate-900 text-white p-6 sm:p-8 overflow-hidden">
                        <div
                            class="absolute top-0 right-0 w-64 h-64 bg-ocean-500/10 rounded-full blur-3xl pointer-events-none">
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 relative z-10">
                            <div class="space-y-1">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-ocean-500/20 text-ocean-300 text-[10px] font-bold uppercase tracking-wider border border-ocean-400/30">
                                    <span class="material-symbols-outlined text-[14px]">explore</span>
                                    <span>Surprise Package Generated</span>
                                </span>
                                <h2 class="font-headline text-2xl sm:text-3xl font-black text-white"
                                    x-text="itinerary.destination.name"></h2>
                                <p class="text-xs text-slate-300 font-medium">
                                    <span
                                        x-text="itinerary.nights + ' night' + (itinerary.nights > 1 ? 's' : '')"></span>
                                    <span
                                        x-text="' • ' + itinerary.pax + ' guest' + (itinerary.pax > 1 ? 's' : '')"></span>
                                    <span
                                        x-text="' • ' + itinerary.check_in_date + ' → ' + itinerary.check_out_date"></span>
                                </p>
                            </div>

                            <div class="text-left sm:text-right space-y-1">
                                <template x-if="itinerary.budget_exceeded">
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-400/30 text-[10px] font-bold uppercase tracking-wider">
                                        <span class="material-symbols-outlined text-[13px]">warning</span>
                                        Over Budget
                                    </span>
                                </template>
                                <template x-if="!itinerary.budget_exceeded">
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-[10px] font-bold uppercase tracking-wider">
                                        <span class="material-symbols-outlined text-[13px]">check_circle</span>
                                        Within Budget
                                    </span>
                                </template>
                                <p class="font-headline text-3xl font-black text-emerald-400"
                                    x-text="itinerary.formatted_total"></p>
                                <p class="text-[11px] text-slate-400 font-medium"
                                    x-text="'Target budget: ' + itinerary.formatted_budget"></p>
                            </div>
                        </div>

                        <template x-if="itinerary.budget_notice">
                            <p class="mt-4 text-xs text-amber-200 bg-amber-950/40 border border-amber-400/30 rounded-xl p-3 flex items-center gap-2"
                                x-text="itinerary.budget_notice"></p>
                        </template>
                    </div>

                    {{-- Body Content --}}
                    <div class="p-6 sm:p-8 space-y-8">

                        {{-- Hotel & Room Stay --}}
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-2.5">
                                <span class="material-symbols-outlined text-[18px] text-ocean-600">king_bed</span>
                                <h3 class="font-headline text-xs font-bold text-slate-900 uppercase tracking-wider">
                                    Accommodation Details
                                </h3>
                            </div>

                            <div
                                class="flex flex-col sm:flex-row gap-4 bg-slate-50 rounded-2xl p-4 border border-slate-200/80 items-center sm:items-start">
                                <template x-if="itinerary.room.image || itinerary.hotel.image">
                                    <img :src="itinerary.room.image || itinerary.hotel.image"
                                        :alt="itinerary.hotel.hotel_name"
                                        onerror="this.onerror=null;this.src='{{ asset('images/placeholder.jpg') }}'"
                                        class="w-full sm:w-40 h-36 sm:h-28 rounded-xl object-cover border border-slate-200 shrink-0">
                                </template>
                                <div class="flex-1 min-w-0 space-y-1.5">
                                    <p class="text-xs font-bold text-ocean-600 uppercase tracking-wider"
                                        x-text="itinerary.hotel.hotel_name"></p>
                                    <h4 class="text-base font-bold text-slate-900 font-headline"
                                        x-text="itinerary.room.room_name"></h4>

                                    <div class="flex flex-wrap gap-1.5 pt-1">
                                        <template x-if="itinerary.hotel.type">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-md bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700 capitalize"
                                                x-text="itinerary.hotel.type"></span>
                                        </template>
                                        <template x-if="itinerary.room.bed_configuration">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-md bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                                <span
                                                    class="material-symbols-outlined text-[12px] text-slate-400">king_bed</span>
                                                <span x-text="itinerary.room.bed_configuration"></span>
                                            </span>
                                        </template>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-md bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                            <span
                                                class="material-symbols-outlined text-[12px] text-slate-400">group</span>
                                            <span x-text="'Up to ' + itinerary.room.max_occupancy + ' guests'"></span>
                                        </span>
                                    </div>
                                </div>
                                <div
                                    class="text-left sm:text-right shrink-0 border-t sm:border-t-0 border-slate-200 pt-2 sm:pt-0 w-full sm:w-auto">
                                    <span class="block text-[11px] text-slate-400 font-medium"
                                        x-text="itinerary.room.formatted_nightly_rate + ' / night'"></span>
                                    <span class="block text-lg font-black text-slate-900 font-headline mt-0.5"
                                        x-text="'₱' + (Number(itinerary.room.nightly_rate) * Number(itinerary.nights)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Experiences List --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px] text-ocean-600">explore</span>
                                    <h3 class="font-headline text-xs font-bold text-slate-900 uppercase tracking-wider">
                                        Included Experiences
                                    </h3>
                                </div>
                                <span
                                    class="text-xs font-bold text-ocean-600 bg-ocean-50 px-2.5 py-0.5 rounded-full border border-ocean-100"
                                    x-text="itinerary.activities.length + ' Activities'"></span>
                            </div>

                            <div class="grid grid-cols-1 gap-3">
                                <template x-for="(activity, i) in itinerary.activities" :key="activity.id">
                                    <div
                                        class="flex items-center gap-3.5 bg-slate-50 rounded-2xl p-4 border border-slate-200/80 hover:bg-slate-100/60 transition-colors">
                                        <template x-if="activity.image">
                                            <img :src="activity.image" :alt="activity.activity_name"
                                                onerror="this.style.display='none'"
                                                class="w-14 h-14 rounded-xl object-cover border border-slate-200 shrink-0">
                                        </template>
                                        <template x-if="!activity.image">
                                            <span
                                                class="w-7 h-7 rounded-xl bg-ocean-600 text-white text-xs font-bold flex items-center justify-center shrink-0 font-headline"
                                                x-text="i + 1"></span>
                                        </template>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs sm:text-sm font-bold text-slate-900 font-headline"
                                                x-text="activity.activity_name"></p>
                                            <div class="flex flex-wrap gap-1.5 mt-1">
                                                <span
                                                    class="inline-flex items-center rounded-md bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700 capitalize"
                                                    x-text="activity.category"></span>
                                                <span
                                                    class="inline-flex items-center rounded-md bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700 capitalize"
                                                    x-text="activity.activity_level"></span>
                                                <template x-if="activity.duration">
                                                    <span
                                                        class="inline-flex items-center rounded-md bg-white border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700"
                                                        x-text="activity.duration"></span>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <template x-if="activity.is_per_person">
                                                <span class="block text-[10px] text-slate-400 font-medium">for <span
                                                        x-text="itinerary.pax"></span> pax</span>
                                            </template>
                                            <template x-if="!activity.is_per_person">
                                                <span
                                                    class="block text-[10px] text-slate-400 font-medium">flat group rate</span>
                                            </template>
                                            <span
                                                class="block text-xs sm:text-sm font-bold text-slate-900 font-headline mt-0.5"
                                                x-text="activity.formatted_rate"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Total Summary & Acceptance Actions --}}
                        <div
                            class="rounded-2xl bg-slate-900 text-white p-6 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-lg">
                            <div class="text-center sm:text-left">
                                <p class="text-xs text-slate-400 font-medium">Estimated Itinerary Total</p>
                                <p class="font-headline text-3xl font-black text-emerald-400 mt-0.5"
                                    x-text="itinerary.formatted_total"></p>
                            </div>
                            <div class="flex flex-wrap justify-center gap-3">
                                <button type="button" @click="shuffle()" :disabled="loading || accepting"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs transition cursor-pointer disabled:opacity-60">
                                    <span class="material-symbols-outlined text-[16px]">shuffle</span>
                                    <span>Shuffle Again</span>
                                </button>
                                <button type="button" @click="acceptItinerary()" :disabled="loading || accepting"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-ocean-600 hover:bg-ocean-500 text-white font-bold text-xs shadow-md shadow-ocean-600/30 transition cursor-pointer disabled:opacity-60">
                                    <template x-if="accepting">
                                        <span
                                            class="w-4 h-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
                                    </template>
                                    <template x-if="!accepting">
                                        <span class="material-symbols-outlined text-[16px]">shopping_cart</span>
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

                // Earliest bookable start is tomorrow — same-day stays are blocked.
                get tomorrowStr() {
                    const d = new Date();
                    d.setDate(d.getDate() + 1);

                    return d.toISOString().split('T')[0];
                },

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
                            window.dispatchEvent(new CustomEvent('show-cart-modal', {
                                detail: {
                                    message: data.message || 'Your surprise itinerary is in your Trip Basket!',
                                    cartCount: data.cart_count,
                                    cartTotal: data.cart_total,
                                }
                            }));
                            this.accepting = false;
                            return;
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