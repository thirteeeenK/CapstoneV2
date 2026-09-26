<x-frontend.layout title="Finding Your Matches — SunnyTrips" :hide-nav-footer="true" :hide-chat-widget="true">
    {{-- Meta refresh fallback: 6s → dashboard --}}
    @push('head')
        <meta http-equiv="refresh" content="6;url={{ route('dashboard') }}">
    @endpush

    <div
        x-data="{
            countdown: 5,
            stepIndex: 0,
            steps: ['Profile saved', 'Finding stays you will love…', 'Picking experiences for you…', 'Adding final touches…'],
            init() {
                setInterval(() => {
                    if (this.countdown > 0) {
                        this.countdown--;
                    } else {
                        window.location.href = '{{ route('dashboard') }}';
                    }
                }, 1000);
                setInterval(() => {
                    this.stepIndex = (this.stepIndex + 1) % this.steps.length;
                }, 1400);
            }
        }"
        class="min-h-dvh py-6 sm:py-12 bg-sand-50 text-ink-900 relative overflow-hidden flex flex-col justify-center items-center"
    >
        {{-- Background oceanic radial + ambient glows --}}
        <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(ellipse 80% 60% at 50% 30%, rgba(18,148,200,0.12) 0%, transparent 70%);"></div>
        <div class="absolute top-1/4 left-1/4 w-[300px] h-[300px] sm:w-[500px] sm:h-[500px] bg-ocean-200/50 blur-[100px] sm:blur-[120px] rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-1/4 w-[240px] h-[240px] sm:w-[400px] sm:h-[400px] bg-coral-200/40 blur-[100px] sm:blur-[120px] rounded-full pointer-events-none"></div>

        <div class="max-w-xl w-full mx-auto px-4 sm:px-6 relative z-10 space-y-4">

            {{-- Top Branding --}}
            <div class="flex items-center justify-end px-1 sm:px-2">
                <a href="{{ route('landing') }}" class="flex items-center gap-2 min-h-[44px]">
                    <span class="font-headline font-black text-xl text-ink-900 tracking-tight">Sunny<span class="text-ocean-600">Trips</span></span>
                </a>
            </div>

            {{-- Card --}}
            <div class="bg-white border border-sand-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-6 text-center">

                {{-- Orb + badge with pulse rings --}}
                <div class="flex flex-col items-center gap-4">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-ocean-50 border border-ocean-100 text-ocean-700 text-xs font-bold uppercase tracking-widest font-label">
                        <span class="material-symbols-outlined text-[16px] text-ocean-600">auto_awesome</span>
                        <span>Finding your matches</span>
                    </div>

                    <div class="relative flex justify-center items-center py-4">
                        <span class="absolute inline-flex w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-ocean-200/50 animate-ping" style="animation-duration: 2s;"></span>
                        <span class="absolute inline-flex w-24 h-24 sm:w-28 sm:h-28 rounded-full border-2 border-ocean-200/60"></span>
                        <x-thinking-orb state="working" :size="72" />
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-2xl sm:text-3xl font-black text-ink-900 font-headline tracking-tight">
                        Finding Stays You'll Love
                    </h1>
                    <p class="text-sm sm:text-sm text-ink-500 max-w-md mx-auto leading-relaxed font-body">
                        We're hand-picking the best spots in
                        <span class="font-bold text-ink-700">{{ $destinationName }}</span>
                        based on your picks. This usually takes just a moment — please wait.
                    </p>
                </div>

                {{-- Friendly progress steps --}}
                <div class="space-y-3" aria-live="polite">
                    <ol class="flex flex-col gap-1.5 text-left max-w-sm mx-auto">
                        <template x-for="(label, i) in steps" :key="label">
                            <li class="flex items-center gap-2.5 text-sm font-body"
                                :class="i < stepIndex ? 'text-ocean-700 font-bold' : (i === stepIndex ? 'text-ink-900 font-bold' : 'text-ink-400')">
                                <span class="material-symbols-outlined text-[18px] shrink-0"
                                    :class="i < stepIndex ? 'text-ocean-600' : (i === stepIndex ? 'text-ocean-500 animate-pulse' : 'text-sand-200')"
                                    x-text="i < stepIndex ? 'check_circle' : (i === stepIndex ? 'progress_activity' : 'radio_button_unchecked')"></span>
                                <span x-text="label"></span>
                            </li>
                        </template>
                    </ol>
                    <div class="h-1.5 bg-sand-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-ocean-500 to-teal-500 rounded-full transition-all duration-1000"
                             :style="`width: ${((5 - countdown) / 5) * 100}%`"></div>
                    </div>
                    <p class="text-xs font-semibold text-ink-500 font-body" x-text="`Taking you to your dashboard in ${countdown}s…`">
                        Taking you to your dashboard in 5s…
                    </p>
                </div>

                {{-- Soft nudge: encourage checking recommendations --}}
                <div class="text-left bg-ocean-50 border border-ocean-100 rounded-2xl px-4 py-4 flex gap-3">
                    <span class="material-symbols-outlined text-ocean-500 text-[20px] shrink-0 mt-0.5">lightbulb</span>
                    <div class="space-y-1">
                        <p class="text-sm font-extrabold text-ocean-900 font-headline">Tip: Check your matches first</p>
                        <p class="text-sm text-ocean-800 leading-relaxed font-body">
                            Travelers who open their personalized <span class="font-bold">Top 5</span> right away find their perfect stay faster.
                            Your ranked stays &amp; experiences will be waiting on the dashboard.
                        </p>
                    </div>
                </div>

                {{-- Actions: stacked full-width on phones --}}
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2.5 sm:gap-3 pt-2">
                    <a href="{{ route('dashboard') }}"
                       class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-ocean-600 hover:bg-ocean-700 text-white font-extrabold text-sm shadow-sm transition-colors font-body">
                        <span>Go to My Dashboard</span>
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </a>
                    <a href="{{ route('landing') }}"
                       class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-sand-200 text-ink-500 hover:bg-sand-100 font-bold text-sm transition-colors font-body">
                        <span>Explore while you wait</span>
                    </a>
                </div>

                <p class="text-xs text-ink-400 font-body">
                    Not seeing results? Your profile is saved — refresh or head to your dashboard anytime.
                </p>
            </div>

            {{-- Flash success passthrough (optional) --}}
            @if(session('success'))
                <p class="text-center text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5 font-body">
                    {{ session('success') }}
                </p>
            @endif
        </div>
    </div>
</x-frontend.layout>
