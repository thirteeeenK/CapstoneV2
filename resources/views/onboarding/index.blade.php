<x-frontend.layout title="Personalize Your Escape — SunnyTrips" :hide-nav-footer="true" :hide-chat-widget="true">
    <div x-data="{
        step: 1,
        maxStep: 1,
        vibes: [],
        destination: '',
        travelerType: '',
        activities: [],
        amenities: [],
        notes: '',
        errorMessage: '',
        isSubmitting: false,
        toggleVibe(vibe) {
            this.errorMessage = '';
            if (this.vibes.includes(vibe)) {
                this.vibes = this.vibes.filter(v => v !== vibe);
            } else {
                if (this.vibes.length >= 5) {
                    this.errorMessage = 'You can select up to 5 vibes only.';
                    return;
                }
                this.vibes.push(vibe);
            }
        },
        toggleActivity(activity) {
            this.errorMessage = '';
            if (this.activities.includes(activity)) {
                this.activities = this.activities.filter(a => a !== activity);
            } else {
                if (this.activities.length >= 5) {
                    this.errorMessage = 'You can select up to 5 activities only.';
                    return;
                }
                this.activities.push(activity);
            }
        },
        toggleAmenity(amenity) {
            this.errorMessage = '';
            if (this.amenities.includes(amenity)) {
                this.amenities = this.amenities.filter(a => a !== amenity);
            } else {
                if (this.amenities.length >= 5) {
                    this.errorMessage = 'You can select up to 5 amenities only.';
                    return;
                }
                this.amenities.push(amenity);
            }
        },
        goToStep(s) {
            if (s <= this.maxStep) {
                this.step = s;
                this.errorMessage = '';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },
        nextStep(currentStep) {
            this.errorMessage = '';
            if (currentStep === 1) {
                if (!this.destination) {
                    this.errorMessage = 'Please select a destination to continue.';
                    return;
                }
                this.step = 2;
            } else if (currentStep === 2) {
                if (!this.travelerType) {
                    this.errorMessage = 'Please select who you are traveling with to continue.';
                    return;
                }
                this.step = 3;
            } else if (currentStep === 3) {
                if (this.vibes.length === 0) {
                    this.errorMessage = 'Please select at least one atmosphere vibe to continue.';
                    return;
                }
                this.step = 4;
            } else if (currentStep === 4) {
                if (this.activities.length === 0) {
                    this.errorMessage = 'Please select at least one activity or experience.';
                    return;
                }
                this.step = 5;
            }
            this.maxStep = Math.max(this.maxStep, this.step);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        handleEnter(e) {
            if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT')) {
                return;
            }
            if (this.step < 5) {
                this.nextStep(this.step);
            }
        },
        removeVibe(v) {
            this.vibes = this.vibes.filter(x => x !== v);
        },
        removeActivity(a) {
            this.activities = this.activities.filter(x => x !== a);
        },
        appendNote(chip) {
            const base = (this.notes || '').replace(/\s+$/, '');
            this.notes = (base ? base + ' ' : '') + chip + ' ';
        },
        validateAndSubmit(e) {
            this.errorMessage = '';
            if (this.amenities.length === 0) {
                this.errorMessage = 'Please select at least one amenity preference.';
                e.preventDefault();
                return false;
            }
            this.isSubmitting = true;
        }
    }" class="min-h-dvh py-6 sm:py-12 bg-sand-50 text-ink-900 relative overflow-hidden flex flex-col items-center">

        {{-- Background Soft Ambient Mesh Glows (brand ocean/coral) --}}
        <div class="absolute top-1/4 left-1/4 w-[300px] h-[300px] sm:w-[500px] sm:h-[500px] bg-ocean-200/50 blur-[100px] sm:blur-[120px] rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-1/4 w-[240px] h-[240px] sm:w-[400px] sm:h-[400px] bg-coral-200/40 blur-[100px] sm:blur-[120px] rounded-full pointer-events-none"></div>

        @php
            $milestones = [
                ['n' => 1, 'label' => 'Destination', 'icon' => 'location_on'],
                ['n' => 2, 'label' => 'Travel Party', 'icon' => 'group'],
                ['n' => 3, 'label' => 'Vibe & Mood', 'icon' => 'palette'],
                ['n' => 4, 'label' => 'Experiences', 'icon' => 'explore'],
                ['n' => 5, 'label' => 'Comforts', 'icon' => 'tune'],
            ];
        @endphp

        <div class="max-w-4xl w-full mx-auto px-4 sm:px-6 relative z-10 space-y-4 pb-8 sm:pb-12">

            {{-- Registration success flash (new accounts land here via CheckUserOnboarding) --}}
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-transition.opacity.duration.300ms
                    class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 shadow-xs">
                    <span class="material-symbols-outlined text-emerald-500 shrink-0" style="font-size:20px">check_circle</span>
                    <span class="flex-1 leading-relaxed">{{ session('success') }}</span>
                    <button type="button" @click="show = false" class="p-1 rounded-full hover:bg-emerald-100 text-emerald-600 transition-colors shrink-0 min-w-[44px] min-h-[44px] flex items-center justify-center" aria-label="Dismiss">
                        <span class="material-symbols-outlined" style="font-size:18px">close</span>
                    </button>
                </div>
            @endif

            {{-- Brand Bar: logo + step counter + time estimate --}}
            <div class="flex items-center justify-between px-1 sm:px-2">
                <a href="{{ route('landing') }}" class="flex items-center gap-2 min-h-[44px]">
                    <span class="font-headline font-black text-xl text-ink-900 tracking-tight">Sunny<span class="text-ocean-600">Trips</span></span>
                </a>
                <div class="flex items-center gap-2 text-xs font-label font-bold text-ink-500">
                    <span class="hidden sm:inline">~90 sec</span>
                </div>
            </div>

            {{-- Card Container --}}
            <div class="bg-white border border-sand-200 rounded-3xl p-5 sm:p-10 shadow-sm space-y-6 sm:space-y-8">

                {{-- Header & Interactive Milestone Tracker --}}
                <div class="space-y-3 sm:space-y-4 text-center">
                    <h1 class="text-xl sm:text-4xl font-black text-ink-900 font-headline tracking-tight">
                        Craft Your Ideal Island Escape
                    </h1>
                    <p class="text-[13px] sm:text-sm text-ink-500 max-w-lg mx-auto leading-relaxed font-body">
                        Answer a few quick questions so our AI can match you with perfect stays and experiences.
                    </p>

                    {{-- Milestones: icon-only on phones, labeled on sm+ --}}
                    <div class="pt-1">
                        <div class="flex items-center gap-1 sm:gap-2" role="list" aria-label="Onboarding progress">
                            @foreach($milestones as $m)
                                <button type="button" role="listitem" @click="goToStep({{ $m['n'] }})"
                                    :aria-current="step === {{ $m['n'] }} ? 'step' : 'false'"
                                    :aria-disabled="{{ $m['n'] }} > maxStep"
                                    :class="{{ $m['n'] }} > maxStep ? 'opacity-50' : ''"
                                    class="flex-1 min-w-0 flex flex-col sm:flex-row items-center justify-center gap-1 rounded-xl px-1 sm:px-2 py-2 text-[10px] sm:text-[11px] font-bold transition-colors min-h-[44px]"
                                    :class="step === {{ $m['n'] }} ? 'bg-ocean-600 text-white shadow-sm' : ({{ $m['n'] }} < step ? 'bg-ocean-50 text-ocean-700' : 'bg-sand-100 text-ink-500')">
                                    <span class="material-symbols-outlined text-[18px]" x-text="({{ $m['n'] }} < step) ? 'check_circle' : '{{ $m['icon'] }}'">{{ $m['icon'] }}</span>
                                    <span class="hidden sm:inline whitespace-nowrap">{{ $m['label'] }}</span>
                                    <span class="sm:hidden font-label">{{ $m['n'] }}</span>
                                </button>
                                @if(!$loop->last)
                                    <div class="hidden sm:block w-3 h-0.5 rounded-full shrink-0" :class="{{ $m['n'] }} < step ? 'bg-ocean-500' : 'bg-sand-200'"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="flex items-center gap-2 pt-2">
                            <div class="flex-1 h-1.5 bg-sand-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-ocean-500 to-ocean-600 rounded-full transition-all duration-500" :style="`width: ${(step / 5) * 100}%`"></div>
                            </div>
                            <span class="text-[11px] font-bold text-ocean-700 tabular-nums" x-text="`${Math.round((step / 5) * 100)}%`">20%</span>
                        </div>
                    </div>
                </div>

                {{-- Validation Feedback Toast --}}
                <div x-show="errorMessage" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 -translate-y-2"
                    class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-sm flex items-center gap-2.5 font-semibold shadow-xs" role="alert">
                    <span class="material-symbols-outlined text-[20px] text-rose-500 shrink-0">error</span>
                    <span x-text="errorMessage"></span>
                </div>

                {{-- Form Submission --}}
                <form action="{{ route('onboarding.store') }}" method="POST" @submit="validateAndSubmit($event)" @keydown.enter.prevent="handleEnter($event)">
                    @csrf

                    {{-- Hidden Form Fields (payload unchanged) --}}
                    <template x-for="vibe in vibes" :key="vibe">
                        <input type="hidden" name="vibes[]" :value="vibe">
                    </template>
                    <input type="hidden" name="destination" :value="destination">
                    <input type="hidden" name="traveler_type" :value="travelerType">
                    <template x-for="act in activities" :key="act">
                        <input type="hidden" name="activities[]" :value="act">
                    </template>
                    <template x-for="amenity in amenities" :key="amenity">
                        <input type="hidden" name="amenities[]" :value="amenity">
                    </template>
                    <input type="hidden" name="notes" :value="notes">

                    {{-- Live Trip DNA Ribbon --}}
                    <div x-show="destination || travelerType || vibes.length || activities.length" x-cloak
                        class="rounded-2xl bg-sand-100 border border-sand-200 px-3 py-2.5 mb-6">
                        <div class="text-[10px] font-label font-bold uppercase tracking-widest text-ink-500 pb-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-coral-500">auto_awesome</span>
                            <span>Your Trip DNA</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-if="destination">
                                <span class="inline-flex shrink-0 items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-white border border-ocean-200 text-ocean-700 text-xs font-bold">
                                    <span class="material-symbols-outlined text-[14px]">location_on</span>
                                    <span x-text="destination" class="max-w-[140px] truncate"></span>
                                    <button type="button" @click="destination = ''" class="w-6 h-6 rounded-full hover:bg-ocean-50 flex items-center justify-center" aria-label="Remove destination">
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                    </button>
                                </span>
                            </template>
                            <template x-if="travelerType">
                                <span class="inline-flex shrink-0 items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-white border border-ocean-200 text-ocean-700 text-xs font-bold">
                                    <span class="material-symbols-outlined text-[14px]">group</span>
                                    <span x-text="travelerType" class="max-w-[140px] truncate"></span>
                                    <button type="button" @click="travelerType = ''" class="w-6 h-6 rounded-full hover:bg-ocean-50 flex items-center justify-center" aria-label="Remove travel party">
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                    </button>
                                </span>
                            </template>
                            <template x-for="v in vibes" :key="'dna-v-'+v">
                                <span class="inline-flex shrink-0 items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-white border border-coral-200 text-coral-600 text-xs font-bold">
                                    <span class="material-symbols-outlined text-[14px]">palette</span>
                                    <span x-text="v" class="max-w-[120px] truncate"></span>
                                    <button type="button" @click="removeVibe(v)" class="w-6 h-6 rounded-full hover:bg-coral-50 flex items-center justify-center" aria-label="Remove vibe">
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                    </button>
                                </span>
                            </template>
                            <template x-for="a in activities" :key="'dna-a-'+a">
                                <span class="inline-flex shrink-0 items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-white border border-ocean-200 text-ocean-700 text-xs font-bold">
                                    <span class="material-symbols-outlined text-[14px]">explore</span>
                                    <span x-text="a" class="max-w-[120px] truncate"></span>
                                    <button type="button" @click="removeActivity(a)" class="w-6 h-6 rounded-full hover:bg-ocean-50 flex items-center justify-center" aria-label="Remove activity">
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                    </button>
                                </span>
                            </template>
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    STEP 1: PREFERRED DESTINATION
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 1" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-5 sm:space-y-6">
                        <div class="space-y-1">
                            <p class="text-[11px] font-label font-bold uppercase tracking-widest text-ocean-600">Destination</p>
                            <h2 class="text-lg sm:text-xl font-bold text-ink-900 font-headline flex items-center gap-2">
                                <span class="material-symbols-outlined text-ocean-600">location_on</span>
                                <span>Where to next?</span>
                            </h2>
                            <p class="text-sm text-ink-500 font-body">Where would you love to spend your next vacation?</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 sm:gap-3">
                            {{-- Open to Anywhere: full-width passport banner on phones --}}
                            <button type="button" @click="destination = 'Open to Any Destination'; errorMessage = ''; nextStep(1);"
                                :class="destination === 'Open to Any Destination' ? 'ring-2 ring-ocean-500 ring-offset-2' : ''"
                                class="col-span-1 relative overflow-hidden rounded-2xl border border-sand-200 transition-all duration-300 cursor-pointer bg-gradient-to-br from-sand-100 via-sand-50 to-ocean-50 min-h-[88px] sm:min-h-0 sm:aspect-[4/3] flex sm:flex-col flex-row items-center gap-3 p-4 sm:p-0 text-left">
                                <div class="sm:absolute sm:inset-0 flex items-center justify-center shrink-0 w-12 h-12 sm:w-auto sm:h-auto rounded-full sm:rounded-none bg-ocean-600/10">
                                    <span class="material-symbols-outlined text-3xl text-ocean-600">travel_explore</span>
                                </div>
                                <div class="sm:absolute sm:bottom-0 sm:left-0 sm:right-0 sm:p-3 flex-1">
                                    <div class="font-bold text-sm font-headline text-ink-900">Open to Anywhere</div>
                                    <div class="text-xs text-ink-500 font-body">AI-curated pan-island tour across all islands</div>
                                </div>
                                <div x-show="destination === 'Open to Any Destination'" class="absolute top-2 right-2 z-10" x-cloak>
                                    <span class="material-symbols-outlined text-white text-[22px] bg-ocean-600/90 backdrop-blur-sm rounded-full p-1">check_circle</span>
                                </div>
                            </button>

                            @foreach($destinationCards as $dest)
                                <button type="button" @click="destination = @js($dest['name']); errorMessage = '';"
                                    :class="destination === @js($dest['name']) ? 'ring-2 ring-ocean-500 ring-offset-2 scale-[1.02] shadow-md shadow-ocean-500/20' : ''"
                                    class="relative overflow-hidden rounded-2xl border border-sand-200 transition-all duration-300 cursor-pointer bg-white aspect-[16/9] sm:aspect-[4/3] flex flex-col min-h-[132px]">
                                    <div class="relative flex-1 overflow-hidden">
                                        <img :src="@js($dest['image'])" :alt="@js($dest['name'])"
                                             class="w-full h-full object-cover transition-transform duration-300"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                             loading="lazy">
                                        <div class="hidden absolute inset-0 flex items-center justify-center bg-ocean-50">
                                            <span class="material-symbols-outlined text-3xl text-ocean-400">pin_drop</span>
                                        </div>
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                                        <div x-show="destination === @js($dest['name'])" class="absolute top-2 right-2 z-10" x-cloak>
                                            <span class="material-symbols-outlined text-white text-[22px] bg-ocean-600/90 backdrop-blur-sm rounded-full p-1">check_circle</span>
                                        </div>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 p-2.5 sm:p-3 text-white text-left">
                                        <div class="font-bold text-xs sm:text-sm font-headline leading-tight">{{ $dest['name'] }}</div>
                                        <div class="text-[11px] sm:text-[11px] text-slate-200 line-clamp-2 font-body">{{ $dest['desc'] }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    STEP 2: TRAVEL COMPANION / GROUP TYPE
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 2" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-5 sm:space-y-6" style="display: none;">
                        <div class="space-y-1">
                            <p class="text-[11px] font-label font-bold uppercase tracking-widest text-ocean-600">Travel Party</p>
                            <h2 class="text-lg sm:text-xl font-bold text-ink-900 font-headline flex items-center gap-2">
                                <span class="material-symbols-outlined text-ocean-600">group</span>
                                <span>Who's coming along?</span>
                            </h2>
                            <p class="text-sm text-ink-500 font-body">Helps us match room sizes and activities to your group.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                            @foreach($groupTypes as $gt)
                                <button type="button" @click="travelerType = @js($gt['name']); errorMessage = '';"
                                    :class="travelerType === @js($gt['name']) ? 'ring-2 ring-ocean-500 ring-offset-2 scale-[1.02] shadow-md shadow-ocean-500/20' : ''"
                                    class="relative overflow-hidden rounded-2xl border border-sand-200 transition-all duration-300 cursor-pointer bg-white aspect-[16/9] sm:aspect-[4/3] flex flex-col min-h-[132px]">
                                    <div class="relative flex-1 overflow-hidden">
                                        <img :src="@js($gt['image'] ?? '')" :alt="@js($gt['name'])"
                                             class="w-full h-full object-cover transition-transform duration-300"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                             loading="lazy">
                                        <div class="hidden absolute inset-0 flex items-center justify-center bg-ocean-50">
                                            <span class="material-symbols-outlined text-3xl text-ocean-400">{{ $gt['icon'] }}</span>
                                        </div>
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                                        <div x-show="travelerType === @js($gt['name'])" class="absolute top-2 right-2 z-10" x-cloak>
                                            <span class="material-symbols-outlined text-white text-[22px] bg-ocean-600/90 backdrop-blur-sm rounded-full p-1">check_circle</span>
                                        </div>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 p-2.5 sm:p-3 text-white text-left">
                                        <div class="font-bold text-xs sm:text-sm font-headline leading-tight">{{ $gt['name'] }}</div>
                                        <div class="text-[11px] sm:text-[11px] text-slate-200 line-clamp-2 font-body">{{ $gt['desc'] }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    STEP 3: ATMOSPHERE & VIBE PREFERENCES
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 3" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-5 sm:space-y-6" style="display: none;">
                        <div class="space-y-1">
                            <p class="text-[11px] font-label font-bold uppercase tracking-widest text-ocean-600">Atmosphere &amp; Mood</p>
                            <h2 class="text-lg sm:text-xl font-bold text-ink-900 font-headline flex items-center gap-2">
                                <span class="material-symbols-outlined text-ocean-600">palette</span>
                                <span>What vibes define your getaway?</span>
                            </h2>
                            <p class="text-sm text-ink-500 font-body">Select up to 5 atmospheres that speak to you.</p>
                            <div class="flex justify-end pt-1">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full transition-colors font-body"
                                    :class="vibes.length >= 5 ? 'bg-coral-50 text-coral-600 border border-coral-200' : (vibes.length > 0 ? 'bg-ocean-50 text-ocean-700 border border-ocean-200' : 'bg-sand-100 text-ink-500 border border-sand-200')"
                                    x-text="vibes.length >= 5 ? 'Maximum 5 selected' : (vibes.length + '/5 selected')">0/5 selected</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-3">
                            @foreach($vibeOptions as $vibe)
                                <button type="button" @click="toggleVibe(@js($vibe['name']))"
                                    :class="vibes.includes(@js($vibe['name'])) ? 'ring-2 ring-ocean-500 ring-offset-2 scale-[1.02] shadow-md shadow-ocean-500/20' : ''"
                                    class="relative overflow-hidden rounded-2xl border border-sand-200 transition-all duration-300 cursor-pointer bg-white aspect-[16/9] sm:aspect-[4/3] flex flex-col min-h-[132px]">
                                    <div class="relative flex-1 overflow-hidden">
                                        <img :src="@js($vibe['image'] ?? '')" :alt="@js($vibe['name'])"
                                             class="w-full h-full object-cover transition-transform duration-300"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                             loading="lazy">
                                        <div class="hidden absolute inset-0 flex items-center justify-center bg-ocean-50">
                                            <span class="material-symbols-outlined text-3xl text-ocean-400">{{ $vibe['icon'] }}</span>
                                        </div>
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                                        <div x-show="vibes.includes(@js($vibe['name']))" class="absolute top-2 right-2 z-10" x-cloak>
                                            <span class="material-symbols-outlined text-white text-[22px] bg-ocean-600/90 backdrop-blur-sm rounded-full p-1">check_circle</span>
                                        </div>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 p-2.5 sm:p-3 text-white text-left">
                                        <div class="font-bold text-xs sm:text-sm font-headline leading-tight">{{ $vibe['name'] }}</div>
                                        <div class="text-[11px] sm:text-[11px] text-slate-200 leading-tight mt-0.5 line-clamp-2 font-body">{{ $vibe['desc'] }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    STEP 4: EXPERIENCES & ACTIVITIES
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 4" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-5 sm:space-y-6" style="display: none;">
                        <div class="space-y-1">
                            <p class="text-[11px] font-label font-bold uppercase tracking-widest text-ocean-600">Experiences</p>
                            <h2 class="text-lg sm:text-xl font-bold text-ink-900 font-headline flex items-center gap-2">
                                <span class="material-symbols-outlined text-ocean-600">explore</span>
                                <span>What experiences excite you?</span>
                            </h2>
                            <p class="text-sm text-ink-500 font-body">Pick up to 5 activities — images help you picture each one.</p>
                            <div class="flex justify-end pt-1">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full transition-colors font-body"
                                    :class="activities.length >= 5 ? 'bg-coral-50 text-coral-600 border border-coral-200' : (activities.length > 0 ? 'bg-ocean-50 text-ocean-700 border border-ocean-200' : 'bg-sand-100 text-ink-500 border border-sand-200')"
                                    x-text="activities.length >= 5 ? 'Maximum 5 selected' : (activities.length + '/5 selected')">0/5 selected</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-3">
                            @foreach($activityOptions as $act)
                                <button type="button" @click="toggleActivity(@js($act['name']))"
                                    :class="activities.includes(@js($act['name'])) ? 'ring-2 ring-ocean-500 ring-offset-2 scale-[1.02] shadow-md shadow-ocean-500/20' : ''"
                                    class="relative overflow-hidden rounded-2xl border border-sand-200 transition-all duration-300 cursor-pointer bg-white aspect-[16/9] sm:aspect-[4/3] flex flex-col min-h-[132px]">
                                    <div class="relative flex-1 overflow-hidden">
                                        <img :src="@js($act['image'] ?? '')" :alt="@js($act['name'])"
                                             class="w-full h-full object-cover transition-transform duration-300"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                             loading="lazy">
                                        <div class="hidden absolute inset-0 flex items-center justify-center bg-ocean-50">
                                            <span class="material-symbols-outlined text-3xl text-ocean-400">{{ $act['icon'] ?? 'explore' }}</span>
                                        </div>
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                                        <div x-show="activities.includes(@js($act['name']))" class="absolute top-2 right-2 z-10" x-cloak>
                                            <span class="material-symbols-outlined text-white text-[22px] bg-ocean-600/90 backdrop-blur-sm rounded-full p-1">check_circle</span>
                                        </div>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 p-2.5 sm:p-3 text-white text-left">
                                        <div class="font-bold text-xs sm:text-sm font-headline leading-tight">{{ $act['name'] }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    STEP 5: AMENITIES & FINAL AI PROFILE
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 5" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-5 sm:space-y-6" style="display: none;">
                        <div class="space-y-1">
                            <p class="text-[11px] font-label font-bold uppercase tracking-widest text-ocean-600">Comforts</p>
                            <h2 class="text-lg sm:text-xl font-bold text-ink-900 font-headline flex items-center gap-2">
                                <span class="material-symbols-outlined text-ocean-600">tune</span>
                                <span>Must-have comforts?</span>
                            </h2>
                            <p class="text-sm text-ink-500 font-body">Select the hotel amenities you can't travel without.</p>
                            <div class="flex justify-end pt-1">
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full transition-colors font-body"
                                    :class="amenities.length >= 5 ? 'bg-coral-50 text-coral-600 border border-coral-200' : (amenities.length > 0 ? 'bg-ocean-50 text-ocean-700 border border-ocean-200' : 'bg-sand-100 text-ink-500 border border-sand-200')"
                                    x-text="amenities.length >= 5 ? 'Maximum 5 selected' : (amenities.length + '/5 selected')">0/5 selected</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach($amenityOptions as $am)
                                <button type="button" @click="toggleAmenity(@js($am['name']))"
                                    :class="amenities.includes(@js($am['name'])) ? 'bg-ocean-600 text-white border-ocean-600 font-bold shadow-sm shadow-ocean-500/30' : 'bg-sand-50 text-ink-700 border-sand-200'"
                                    class="min-h-[44px] px-3.5 py-2 rounded-xl text-sm border transition-all cursor-pointer flex items-center gap-1.5 font-body">
                                    <span class="material-symbols-outlined text-[18px]">{{ $am['icon'] }}</span>
                                    <span x-show="amenities.includes(@js($am['name']))" class="material-symbols-outlined text-[16px]" x-cloak>check</span>
                                    <span>{{ $am['name'] }}</span>
                                </button>
                            @endforeach
                        </div>

                        {{-- Concierge notes --}}
                        <div class="space-y-2 pt-2">
                            <label for="onboarding-notes" class="text-sm font-bold text-ink-700 font-body block">Any special requests for your stay? <span class="font-normal text-ink-500">(Optional)</span></label>
                            <textarea id="onboarding-notes" x-model="notes" rows="3" placeholder="e.g. Quiet beachfront room with sunset views..."
                                class="w-full bg-sand-50 border border-sand-200 rounded-xl px-3.5 py-3 text-base sm:text-sm text-ink-900 placeholder-ink-400 focus:outline-none focus:border-ocean-500 focus:bg-white transition-colors font-body"></textarea>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="chip in ['Quiet room', 'Sunset view', 'Near diving spots', 'High floor', 'Family-friendly']" :key="chip">
                                    <button type="button" @click="appendNote(chip)"
                                        class="shrink-0 min-h-[36px] px-3 rounded-full bg-ocean-50 border border-ocean-100 text-ocean-700 text-xs font-bold hover:bg-ocean-100 transition-colors font-body"
                                        x-text="'+ ' + chip"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    UNIFIED BOTTOM NAVIGATION DOCK (inside form)
                    ══════════════════════════════════════════ --}}
                    <div class="sticky bottom-0 -mx-5 sm:-mx-10 px-5 sm:px-10 pt-3 bg-white/95 backdrop-blur border-t border-sand-200 mt-8" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                        <div class="flex flex-wrap items-center gap-2">
                            {{-- Back (hidden on step 1) --}}
                            <button type="button" x-show="step > 1" x-cloak @click="goToStep(step - 1)"
                                class="min-h-[44px] min-w-[44px] sm:min-w-0 sm:px-4 px-2.5 py-3 rounded-xl border border-sand-200 text-ink-700 hover:bg-sand-100 font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer font-body whitespace-nowrap" aria-label="Previous step">
                                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                                <span>Back</span>
                            </button>

                            @if($canSkip)
                                <a href="{{ route('onboarding.skip') }}"
                                    class="min-h-[44px] px-2.5 sm:px-4 py-3 rounded-xl border border-sand-200 text-ink-500 hover:text-ink-900 hover:bg-sand-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer font-body whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[16px]">fast_forward</span>
                                    <span class="sm:hidden">Skip</span>
                                    <span class="hidden sm:inline">Skip for now</span>
                                </a>
                            @else
                                <a href="{{ route('dashboard') }}"
                                    class="min-h-[44px] px-2.5 sm:px-4 py-3 rounded-xl border border-sand-200 text-ink-500 hover:text-ink-900 hover:bg-sand-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer font-body whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[16px]">close</span>
                                    <span>Cancel</span>
                                </a>
                            @endif

                            <div class="hidden sm:block flex-1"></div>

                            {{-- Continue (steps 1-4) --}}
                            <template x-if="step < 5">
                                <button type="button" @click="nextStep(step)"
                                    class="flex-1 min-w-[140px] sm:flex-none sm:min-w-0 min-h-[44px] px-3 sm:px-6 py-3 rounded-xl bg-gradient-to-r from-ocean-500 to-ocean-600 hover:from-ocean-600 hover:to-ocean-700 text-white font-bold text-[13px] sm:text-sm shadow-sm transition-all flex items-center justify-center gap-1.5 cursor-pointer font-body whitespace-nowrap">
                                    <span x-text="step === 1 ? 'Find my style' : (step === 2 ? 'Pick my vibes' : (step === 3 ? 'See activities' : 'Pick comforts'))">Continue</span>
                                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                                </button>
                            </template>

                            {{-- Submit (step 5) --}}
                            <template x-if="step === 5">
                                <button type="submit" :disabled="isSubmitting"
                                    class="flex-1 min-w-[140px] sm:flex-none sm:min-w-0 min-h-[44px] px-3 sm:px-8 py-3.5 rounded-xl bg-gradient-to-r from-ocean-600 to-teal-600 hover:from-ocean-700 hover:to-teal-700 text-white font-extrabold text-[13px] sm:text-sm shadow-sm transition-all flex items-center justify-center gap-1.5 sm:gap-2 cursor-pointer disabled:opacity-50 font-body whitespace-nowrap">
                                    <template x-if="!isSubmitting">
                                        <span class="flex items-center gap-1.5 sm:gap-2">
                                            <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                                            <span class="sm:hidden">Build My Profile</span>
                                            <span class="hidden sm:inline">Build My Travel Profile</span>
                                        </span>
                                    </template>
                                    <template x-if="isSubmitting">
                                        <span class="flex items-center gap-1.5 sm:gap-2">
                                            <x-thinking-orb state="working" :size="16" light />
                                            <span class="sm:hidden">Creating…</span>
                                            <span class="hidden sm:inline">Creating your profile…</span>
                                        </span>
                                    </template>
                                </button>
                            </template>
                        </div>
                        <p class="hidden sm:block text-center text-[11px] text-ink-400 pt-2 font-body">Press <kbd class="px-1.5 py-0.5 rounded bg-sand-100 border border-sand-200 font-bold">Enter ↵</kbd> to continue</p>
                    </div>
                </form>

            </div>

        </div>
    </div>
</x-frontend.layout>
