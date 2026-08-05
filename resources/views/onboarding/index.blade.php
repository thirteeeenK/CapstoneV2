<x-frontend.layout title="Personalize Your Escape — SunnyTrips" :hide-nav-footer="true">
    <div x-data="{
        step: 1,
        vibes: [],
        destination: '',
        travelerType: '',
        amenities: [],
        notes: '',
        errorMessage: '',
        isSubmitting: false,
        toggleVibe(vibe) {
            this.errorMessage = '';
            if (this.vibes.includes(vibe)) {
                this.vibes = this.vibes.filter(v => v !== vibe);
            } else {
                this.vibes.push(vibe);
            }
        },
        toggleAmenity(amenity) {
            this.errorMessage = '';
            if (this.amenities.includes(amenity)) {
                this.amenities = this.amenities.filter(a => a !== amenity);
            } else {
                this.amenities.push(amenity);
            }
        },
        nextStep(currentStep) {
            this.errorMessage = '';
            if (currentStep === 1) {
                if (this.vibes.length === 0) {
                    this.errorMessage = 'Please select at least one atmosphere vibe to continue.';
                    return;
                }
                this.step = 2;
            } else if (currentStep === 2) {
                if (!this.destination) {
                    this.errorMessage = 'Please select a destination or \'Open to Anywhere\' to continue.';
                    return;
                }
                this.step = 3;
            } else if (currentStep === 3) {
                if (!this.travelerType) {
                    this.errorMessage = 'Please select who you are traveling with to continue.';
                    return;
                }
                this.step = 4;
            }
        },
        validateAndSubmit(e) {
            this.errorMessage = '';
            if (this.amenities.length === 0) {
                this.errorMessage = 'Please select at least one amenity or activity preference.';
                e.preventDefault();
                return false;
            }
            this.isSubmitting = true;
        }
    }" class="min-h-screen py-12 bg-slate-50 text-slate-900 relative overflow-hidden flex flex-col justify-center items-center">

        {{-- Background Soft Ambient Mesh Glows --}}
        <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-sky-200/50 blur-[120px] rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-1/4 w-[400px] h-[400px] bg-indigo-200/40 blur-[120px] rounded-full pointer-events-none"></div>

        <div class="max-w-3xl w-full mx-auto px-4 sm:px-6 relative z-10 space-y-4">

            {{-- Top Branding Header --}}
            <div class="flex items-center justify-end px-2">
                <a href="{{ route('landing') }}" class="flex items-center gap-2">
                    <span class="font-headline font-black text-xl text-slate-900 tracking-tight">Sunny<span class="text-sky-600">Trips</span></span>
                </a>
            </div>

            {{-- Card Container (Light Mode) --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-8">
                
                {{-- Header & Progress Bar --}}
                <div class="space-y-4 text-center">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-sky-50 border border-sky-200 text-sky-700 text-xs font-bold uppercase tracking-widest">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">auto_awesome</span>
                        <span>AI Travel Personalization</span>
                    </div>

                    <h1 class="text-2xl sm:text-4xl font-black text-slate-900 font-headline tracking-tight">
                        Craft Your Ideal Island Escape
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-lg mx-auto leading-relaxed">
                        Answer a few quick questions so our AI recommendation engine can match you with perfect sanctuaries, luxury rooms, and curated experiences.
                    </p>

                    {{-- Step Indicator --}}
                    <div class="flex items-center justify-center gap-2 pt-2">
                        <template x-for="i in 4" :key="i">
                            <div class="h-1.5 rounded-full transition-all duration-500"
                                :class="step === i ? 'w-8 bg-sky-600' : (step > i ? 'w-4 bg-sky-200' : 'w-4 bg-slate-200')">
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Validation Feedback Toast --}}
                <div x-show="errorMessage" x-cloak x-transition:enter="transition ease-out duration-300 transform opacity-0 -translate-y-2"
                    class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-rose-700 text-xs flex items-center gap-2.5 font-semibold shadow-xs">
                    <span class="material-symbols-outlined text-[20px] text-rose-500">error</span>
                    <span x-text="errorMessage"></span>
                </div>

                {{-- Form Submission --}}
                <form action="{{ route('onboarding.store') }}" method="POST" @submit="validateAndSubmit($event)">
                    @csrf

                    {{-- Hidden Form Fields --}}
                    <template x-for="vibe in vibes" :key="vibe">
                        <input type="hidden" name="vibes[]" :value="vibe">
                    </template>
                    <input type="hidden" name="destination" :value="destination">
                    <input type="hidden" name="traveler_type" :value="travelerType">
                    <template x-for="amenity in amenities" :key="amenity">
                        <input type="hidden" name="amenities[]" :value="amenity">
                    </template>
                    <input type="hidden" name="notes" :value="notes">

                    {{-- ══════════════════════════════════════════
                    STEP 1: ATMOSPHERE & VIBE PREFERENCES
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 1" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-6">
                        <div class="space-y-1">
                            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sky-600">palette</span>
                                <span>Step 1: What atmosphere do you crave?</span>
                            </h2>
                            <p class="text-xs text-slate-500">Select all vibes that match your ideal getaway mood.</p>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @php
                                $vibeOptions = [
                                    ['name' => 'Beachfront', 'icon' => 'beach_access', 'desc' => 'Oceanfront views & white sand'],
                                    ['name' => 'Luxury & Spa', 'icon' => 'spa', 'desc' => 'High-end pampering & resorts'],
                                    ['name' => 'Nightlife & Party', 'icon' => 'local_bar', 'desc' => 'Vibrant music & island bars'],
                                    ['name' => 'Nature & Eco', 'icon' => 'forest', 'desc' => 'Pristine greenery & wildlife'],
                                    ['name' => 'Quiet & Serene', 'icon' => 'self_improvement', 'desc' => 'Peaceful retreat away from crowds'],
                                    ['name' => 'Adventure & Thrills', 'icon' => 'hiking', 'desc' => 'Water sports, treks & diving'],
                                    ['name' => 'Culinary & Dining', 'icon' => 'restaurant', 'desc' => 'Seafood feasts & local cuisine'],
                                    ['name' => 'Family Friendly', 'icon' => 'family_restroom', 'desc' => 'Safe pools & kid activities'],
                                ];
                            @endphp
                            @foreach($vibeOptions as $vibe)
                                <button type="button" @click="toggleVibe('{{ $vibe['name'] }}')"
                                    :class="vibes.includes('{{ $vibe['name'] }}') ? 'bg-sky-50 border-sky-500 text-sky-900 shadow-xs' : 'bg-slate-50 border-slate-200 hover:bg-slate-100 hover:border-slate-300 text-slate-700'"
                                    class="p-4 rounded-2xl border text-left transition-all duration-300 flex flex-col justify-between space-y-2 group cursor-pointer">
                                    <div class="flex items-center justify-between">
                                        <span class="material-symbols-outlined text-2xl" :class="vibes.includes('{{ $vibe['name'] }}') ? 'text-sky-600' : 'text-slate-400'">{{ $vibe['icon'] }}</span>
                                        <span x-show="vibes.includes('{{ $vibe['name'] }}')" class="material-symbols-outlined text-sky-600 text-[18px]">check_circle</span>
                                    </div>
                                    <div>
                                        <div class="font-bold text-xs font-headline">{{ $vibe['name'] }}</div>
                                        <div class="text-[10px] text-slate-500 leading-tight mt-0.5">{{ $vibe['desc'] }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        {{-- Step 1 Controls --}}
                        <div class="flex items-center justify-between pt-4">
                            @if($canSkip)
                                <a href="{{ route('onboarding.skip') }}"
                                    class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px] text-slate-500">fast_forward</span>
                                    <span>Skip for now</span>
                                </a>
                            @else
                                <a href="{{ route('dashboard') }}"
                                    class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">close</span>
                                    <span>Cancel</span>
                                </a>
                            @endif

                            <button type="button" @click="nextStep(1)"
                                class="px-6 py-3 rounded-xl bg-gradient-to-r from-sky-500 to-sky-600 hover:from-sky-600 hover:to-sky-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                                <span>Next: Destination</span>
                                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    STEP 2: PREFERRED DESTINATION
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 2" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-6" style="display: none;">
                        <div class="space-y-1">
                            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sky-600">location_on</span>
                                <span>Step 2: Preferred Sanctuary Location</span>
                            </h2>
                            <p class="text-xs text-slate-500">Where would you love to spend your next vacation?</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <button type="button" @click="destination = 'Open to Any Destination'; errorMessage = '';"
                                :class="destination === 'Open to Any Destination' ? 'bg-sky-50 border-sky-500 text-sky-900 shadow-xs' : 'bg-slate-50 border-slate-200 hover:bg-slate-100 text-slate-700'"
                                class="p-4 rounded-2xl border text-left transition-all duration-300 space-y-2 cursor-pointer flex flex-col justify-between">
                                <span class="material-symbols-outlined text-2xl text-amber-500">travel_explore</span>
                                <div>
                                    <div class="font-bold text-xs">Open to Anywhere</div>
                                    <div class="text-[10px] text-slate-500">Show top recommendations across all islands</div>
                                </div>
                            </button>

                            @foreach($destinations as $dest)
                                <button type="button" @click="destination = '{{ $dest->name }}'; errorMessage = '';"
                                    :class="destination === '{{ $dest->name }}' ? 'bg-sky-50 border-sky-500 text-sky-900 shadow-xs' : 'bg-slate-50 border-slate-200 hover:bg-slate-100 text-slate-700'"
                                    class="p-4 rounded-2xl border text-left transition-all duration-300 space-y-2 cursor-pointer flex flex-col justify-between">
                                    <span class="material-symbols-outlined text-2xl text-sky-600">pin_drop</span>
                                    <div>
                                        <div class="font-bold text-xs font-headline">{{ $dest->name }}</div>
                                        <div class="text-[10px] text-slate-500 line-clamp-1">{{ $dest->description }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        {{-- Step 2 Controls --}}
                        <div class="flex items-center justify-between pt-4">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="step = 1; errorMessage = '';" class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                                    <span>Back</span>
                                </button>
                                @if($canSkip)
                                    <a href="{{ route('onboarding.skip') }}"
                                        class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px] text-slate-500">fast_forward</span>
                                        <span>Skip for now</span>
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}"
                                        class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                        <span>Cancel</span>
                                    </a>
                                @endif
                            </div>
                            <button type="button" @click="nextStep(2)" class="px-6 py-3 rounded-xl bg-gradient-to-r from-sky-500 to-sky-600 hover:from-sky-600 hover:to-sky-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                                <span>Next: Traveler Style</span>
                                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    STEP 3: TRAVEL COMPANION / GROUP TYPE
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 3" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-6" style="display: none;">
                        <div class="space-y-1">
                            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sky-600">group</span>
                                <span>Step 3: Who are you traveling with?</span>
                            </h2>
                            <p class="text-xs text-slate-500">Helps us recommend appropriate room sizes & activities.</p>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @php
                                $groupTypes = [
                                    ['name' => 'Solo Traveler', 'icon' => 'person', 'desc' => 'Independent & flexible'],
                                    ['name' => 'Couple / Honeymoon', 'icon' => 'favorite', 'desc' => 'Romantic & private'],
                                    ['name' => 'Family with Kids', 'icon' => 'family_restroom', 'desc' => 'Spacious & kid friendly'],
                                    ['name' => 'Friends Group', 'icon' => 'groups', 'desc' => 'Group suites & activities'],
                                ];
                            @endphp
                            @foreach($groupTypes as $gt)
                                <button type="button" @click="travelerType = '{{ $gt['name'] }}'; errorMessage = '';"
                                    :class="travelerType === '{{ $gt['name'] }}' ? 'bg-sky-50 border-sky-500 text-sky-900 shadow-xs' : 'bg-slate-50 border-slate-200 hover:bg-slate-100 text-slate-700'"
                                    class="p-4 rounded-2xl border text-left transition-all duration-300 space-y-2 cursor-pointer flex flex-col justify-between">
                                    <span class="material-symbols-outlined text-2xl text-sky-600">{{ $gt['icon'] }}</span>
                                    <div>
                                        <div class="font-bold text-xs">{{ $gt['name'] }}</div>
                                        <div class="text-[10px] text-slate-500">{{ $gt['desc'] }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        {{-- Step 3 Controls --}}
                        <div class="flex items-center justify-between pt-4">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="step = 2; errorMessage = '';" class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                                    <span>Back</span>
                                </button>
                                @if($canSkip)
                                    <a href="{{ route('onboarding.skip') }}"
                                        class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px] text-slate-500">fast_forward</span>
                                        <span>Skip for now</span>
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}"
                                        class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                        <span>Cancel</span>
                                    </a>
                                @endif
                            </div>
                            <button type="button" @click="nextStep(3)" class="px-6 py-3 rounded-xl bg-gradient-to-r from-sky-500 to-sky-600 hover:from-sky-600 hover:to-sky-700 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                                <span>Next: Amenities</span>
                                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════
                    STEP 4: AMENITIES & FINAL AI PROFILE
                    ══════════════════════════════════════════ --}}
                    <div x-show="step === 4" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-x-4" class="space-y-6" style="display: none;">
                        <div class="space-y-1">
                            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sky-600">tune</span>
                                <span>Step 4: Must-Have Amenities & Activities</span>
                            </h2>
                            <p class="text-xs text-slate-500">Select any specific features or activities you love.</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @php
                                $amenityPills = [
                                    'Private Pool', 'Beach Access', 'Infinity Pool', 'Scuba Diving',
                                    'Sunset Cruise', 'Buffet Breakfast', 'Airport Transfers', 'Spa Services',
                                    'Island Hopping', 'Free Wi-Fi', 'Balcony Ocean View', 'Water Sports'
                                ];
                            @endphp
                            @foreach($amenityPills as $am)
                                <button type="button" @click="toggleAmenity('{{ $am }}')"
                                    :class="amenities.includes('{{ $am }}') ? 'bg-sky-500 text-white border-sky-500 font-bold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                    class="px-3.5 py-2 rounded-xl text-xs border transition-all cursor-pointer flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[15px]" x-show="amenities.includes('{{ $am }}')">check</span>
                                    <span>{{ $am }}</span>
                                </button>
                            @endforeach
                        </div>

                        {{-- Additional Requests --}}
                        <div class="space-y-1.5 pt-2">
                            <label class="text-xs font-bold text-slate-700 block">Any custom requests or notes for AI matching? (Optional)</label>
                            <textarea x-model="notes" rows="2" placeholder="e.g. Quiet beachfront villa near Station 2 with romantic sunset views..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors"></textarea>
                        </div>

                        {{-- Step 4 Controls --}}
                        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="step = 3; errorMessage = '';" class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                                    <span>Back</span>
                                </button>
                                @if($canSkip)
                                    <a href="{{ route('onboarding.skip') }}"
                                        class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px] text-slate-500">fast_forward</span>
                                        <span>Skip for now</span>
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}"
                                        class="px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-outlined text-[16px]">close</span>
                                        <span>Cancel</span>
                                    </a>
                                @endif
                            </div>

                            <button type="submit" :disabled="isSubmitting"
                                class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-extrabold text-xs shadow-sm transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50">
                                <template x-if="!isSubmitting">
                                    <span class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                                        <span>Build My AI Travel Profile</span>
                                    </span>
                                </template>
                                <template x-if="isSubmitting">
                                    <span class="flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Generating Embedding...</span>
                                    </span>
                                </template>
                            </button>
                        </div>
                    </div>
                </form>

            </div>

        </div>
    </div>
</x-frontend.layout>
