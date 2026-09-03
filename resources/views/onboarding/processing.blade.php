<x-frontend.layout title="Generating Recommendations — SunnyTrips" :hide-nav-footer="true">
    {{-- Meta refresh fallback: 6s → dashboard --}}
    @push('head')
        <meta http-equiv="refresh" content="6;url={{ route('dashboard') }}">
    @endpush

    <div
        x-data="{
            countdown: 5,
            stepIndex: 0,
            steps: ['Profile saved ✓', 'Ranking sanctuary stays…', 'Curating experiences…', 'Final touches…'],
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
                }, 1200);
            }
        }"
        class="min-h-screen py-12 bg-sand-50/70 text-slate-900 relative overflow-hidden flex flex-col justify-center items-center"
    >
        {{-- Background Soft Ambient Mesh Glows --}}
        <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-sky-200/50 blur-[120px] rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-1/4 w-[400px] h-[400px] bg-indigo-200/40 blur-[120px] rounded-full pointer-events-none"></div>

        <div class="max-w-xl w-full mx-auto px-4 sm:px-6 relative z-10 space-y-4">

            {{-- Top Branding --}}
            <div class="flex items-center justify-end px-2">
                <a href="{{ route('landing') }}" class="flex items-center gap-2">
                    <span class="font-headline font-black text-xl text-slate-900 tracking-tight">Sunny<span class="text-sky-600">Trips</span></span>
                </a>
            </div>

            {{-- Card --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-6 text-center">

                {{-- Orb + badge --}}
                <div class="flex flex-col items-center gap-4">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-sky-50 border border-sky-200 text-sky-700 text-xs font-bold uppercase tracking-widest">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">auto_awesome</span>
                        <span>AI is crafting your escape</span>
                    </div>

                    <div class="flex justify-center py-2">
                        <x-thinking-orb state="working" :size="72" />
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 font-headline tracking-tight">
                        Generating Your Personalized Recommendations
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
                        We're ranking every sanctuary stay &amp; experience for
                        <span class="font-bold text-slate-700">{{ $destinationName }}</span>
                        based on your vibe picks. This usually takes just a moment — please wait.
                    </p>
                </div>

                {{-- Fake steps progress --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-center gap-2 text-xs font-bold text-sky-700">
                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                        <span x-text="steps[stepIndex]"></span>
                    </div>
                    <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-sky-500 to-sky-600 rounded-full transition-all duration-1000"
                             :style="`width: ${((5 - countdown) / 5) * 100}%`"></div>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-400" x-text="`Redirecting to your dashboard in ${countdown}s…`">
                        Redirecting to your dashboard in 5s…
                    </p>
                </div>

                {{-- Soft nudge: encourage checking recommendations --}}
                <div class="text-left bg-sky-50 border border-sky-100 rounded-2xl px-4 py-4 flex gap-3">
                    <span class="material-symbols-outlined text-sky-500 text-[20px] shrink-0 mt-0.5">lightbulb</span>
                    <div class="space-y-1">
                        <p class="text-xs font-extrabold text-sky-900">Tip: Check your AI Recommendations first</p>
                        <p class="text-xs text-sky-800 leading-relaxed">
                            Travelers who open their personalized <span class="font-bold">Top 5</span> right away discover more relevant matches faster.
                            Your ranked sanctuaries &amp; experiences will be waiting on the dashboard — just switch to the <span class="font-bold">AI Recommendations</span> tab.
                        </p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    <a href="{{ route('dashboard') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-extrabold text-xs shadow-sm transition-colors">
                        <span>Go to My Dashboard</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                    <a href="{{ route('landing') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs transition-colors">
                        <span>Explore while you wait</span>
                    </a>
                </div>

                <p class="text-[11px] text-slate-400">
                    Not seeing results? Your profile is saved — refresh or head to your dashboard anytime.
                </p>
            </div>

            {{-- Flash success passthrough (optional) --}}
            @if(session('success'))
                <p class="text-center text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5">
                    {{ session('success') }}
                </p>
            @endif
        </div>
    </div>
</x-frontend.layout>
