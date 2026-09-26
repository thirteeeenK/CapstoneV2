<footer class="w-full pt-12 sm:pt-16 lg:pt-20 pb-8 sm:pb-12 px-4 sm:px-6 lg:px-8 bg-sand-50/80 border-t border-slate-200/80 font-body relative overflow-hidden">
    {{-- Soft Ambient Glow --}}
    <div class="absolute bottom-0 right-0 w-96 max-w-[100vw] h-96 bg-ocean-500/5 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10 space-y-10 sm:space-y-12">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12">

            <!-- Brand Column -->
            <div class="md:col-span-5 lg:col-span-4 space-y-3.5">
                <a href="{{ route('landing') }}" class="inline-block text-2xl sm:text-3xl font-headline font-black text-slate-900 tracking-tight">
                    Sunny<span class="text-ocean-600">Trips</span>
                </a>
                <p class="text-slate-500 text-xs sm:text-sm leading-relaxed max-w-sm">
                    Redefining tropical exploration through curated luxury, intelligent recommendations, and seamless travel booking across the Philippine archipelago.
                </p>
                <div class="pt-1 flex items-center gap-2 text-xs font-semibold text-slate-500">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>AI-Powered Concierge Ready</span>
                </div>
            </div>

            <!-- Spacer for large screens -->
            <div class="hidden lg:block lg:col-span-1"></div>

            <!-- Navigation Link Columns Grid (2 columns on mobile, 3 on tablet/desktop) -->
            <div class="md:col-span-7 lg:col-span-7 grid grid-cols-2 sm:grid-cols-3 gap-6 sm:gap-8">
                
                <!-- Column: Explore -->
                <div class="space-y-3 sm:space-y-4">
                    <h4 class="font-headline text-slate-900 text-xs font-extrabold tracking-widest uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px] text-ocean-600">explore</span>
                        <span>Explore</span>
                    </h4>
                    <div class="flex flex-col gap-2.5 text-slate-600 text-xs sm:text-sm font-medium">
                        <a href="{{ route('destinations.index') }}" class="hover:text-ocean-600 transition-colors">Islands</a>
                        <a href="{{ route('hotels.index') }}" class="hover:text-ocean-600 transition-colors">Stays & Resorts</a>
                        <a href="{{ route('activities.index') }}" class="hover:text-ocean-600 transition-colors">Tours & Activities</a>
                        <a href="{{ route('packages.index') }}" class="hover:text-ocean-600 transition-colors">Tour Packages</a>
                        <a href="{{ route('lucky.index') }}" class="hover:text-ocean-600 transition-colors flex items-center gap-1">
                            <span>Feeling Lucky</span>
                            <span class="text-[10px] px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded-md font-bold leading-none">AI</span>
                        </a>
                    </div>
                </div>

                <!-- Column: Concierge -->
                <div class="space-y-3 sm:space-y-4">
                    <h4 class="font-headline text-slate-900 text-xs font-extrabold tracking-widest uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px] text-ocean-600">support_agent</span>
                        <span>Concierge</span>
                    </h4>
                    <div class="flex flex-col gap-2.5 text-slate-600 text-xs sm:text-sm font-medium">
                        <a href="{{ route('reviews.index') }}" class="hover:text-ocean-600 transition-colors">Traveler Reviews</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="hover:text-ocean-600 transition-colors">My Dashboard</a>
                            <a href="{{ route('bookings.index') }}" class="hover:text-ocean-600 transition-colors">My Bookings</a>
                        @else
                            <a href="{{ route('login') }}" class="hover:text-ocean-600 transition-colors">Sign In</a>
                            <a href="{{ route('register') }}" class="hover:text-ocean-600 transition-colors">Create Account</a>
                        @endauth
                        <button type="button" @click="$dispatch('open-cart')" class="text-left hover:text-ocean-600 transition-colors cursor-pointer">
                            Trip Basket
                        </button>
                    </div>
                </div>

                <!-- Column: The Office / HQ -->
                <div class="col-span-2 sm:col-span-1 space-y-3 sm:space-y-4">
                    <h4 class="font-headline text-slate-900 text-xs font-extrabold tracking-widest uppercase flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px] text-ocean-600">business</span>
                        <span>The Office</span>
                    </h4>
                    <p class="text-slate-600 text-xs sm:text-sm leading-relaxed">
                        Brgy. San Agustin,<br />
                        Pili, Camarines Sur,<br />
                        Philippines, 4418
                    </p>
                    <div class="text-[11px] text-slate-500 font-medium">
                        Daily Concierge: 8:00 AM – 8:00 PM PHT
                    </div>
                </div>

            </div>

        </div>

        {{-- Divider --}}
        <hr class="border-slate-200/80" />

        {{-- Bottom Copyright & Legal Links --}}
        <div class="flex flex-col-reverse sm:flex-row justify-between items-center gap-4 text-slate-400 text-xs font-medium text-center sm:text-left">
            <p>© {{ date('Y') }} {{ config('app.name', 'SunnyTrips') }}. All rights reserved. Made for sun-seekers.</p>
            <div class="flex flex-wrap justify-center sm:justify-end gap-x-5 gap-y-2">
                @php
                    $legalDocs = \App\Models\LegalDocument::orderBy('title')->get();
                @endphp
                @forelse($legalDocs as $doc)
                    <a href="{{ route('legal.show', $doc->key) }}" class="hover:text-slate-700 transition-colors">{{ $doc->title }}</a>
                @empty
                    <a href="{{ route('terms') }}" class="hover:text-slate-700 transition-colors">Terms of Service</a>
                    <a href="{{ route('privacy-policy') }}" class="hover:text-slate-700 transition-colors">Privacy Policy</a>
                    <a href="{{ route('ai-disclosure') }}" class="hover:text-slate-700 transition-colors">AI Disclosure</a>
                @endforelse
            </div>
        </div>
    </div>
</footer>