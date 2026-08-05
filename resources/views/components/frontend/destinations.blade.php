<section class="py-24 bg-slate-50/50 border-t border-slate-200/60 overflow-hidden relative" id="destinations">
    
    {{-- Decorative Mesh Background Glow --}}
    <div class="absolute top-1/3 left-1/4 w-[600px] h-[350px] bg-sky-400/5 blur-3xl rounded-full pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12 relative z-10">
        
        {{-- Header Section --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 reveal-on-scroll">
            <div class="max-w-2xl space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-100/80 border border-sky-200 text-sky-800 font-label font-bold tracking-[0.2em] text-[11px] uppercase">
                    <span class="material-symbols-outlined text-[14px] text-sky-600">explore</span>
                    CURATED DESTINATIONS
                </span>
                <h2 class="font-headline text-3xl md:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">
                    The Essence of the Archipelago
                </h2>
                <p class="text-slate-600 text-xs md:text-sm leading-relaxed max-w-xl font-body">
                    Each sanctuary is handpicked for its breathtaking natural beauty, pristine beaches, and promise of luxury island living.
                </p>
            </div>

            <div class="hidden md:flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 bg-white px-4 py-2 rounded-full border border-slate-200/80 shadow-xs flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-sky-600">travel_explore</span>
                    <span>{{ $destinations->count() }} Island Sanctuaries</span>
                </span>
            </div>
        </div>

        {{-- Dynamic Grid Layout --}}
        @php
            $destCount = $destinations->count();
            $destGridClass = match(true) {
                $destCount === 1 => 'grid-cols-1 max-w-2xl mx-auto',
                $destCount === 2 => 'grid-cols-1 md:grid-cols-2',
                $destCount === 4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
                default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
            };
        @endphp
        <div class="grid {{ $destGridClass }} gap-8">

            @foreach($destinations as $index => $dest)
                @php
                    $imgSrc = $resolveDestinationImage($dest->image);
                    $hotelsCount = $dest->hotels ? $dest->hotels->count() : 0;
                    $activitiesCount = $dest->activities ? $dest->activities->count() : 0;
                @endphp
                <div class="group relative flex flex-col bg-white border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs hover:shadow-2xl transition-all duration-500 hover:-translate-y-1.5 reveal-on-scroll"
                    style="transition-delay: {{ ($index % 3) * 100 }}ms;">
                    
                    {{-- Destination Photo & Floating Glass Badges --}}
                    <div class="relative aspect-[4/3] w-full overflow-hidden bg-slate-950">
                        <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                            src="{{ $imgSrc }}" alt="{{ $dest->name }}" />
                        
                        {{-- Dark Gradient Vignette --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent"></div>

                        {{-- Floating Location Badge --}}
                        <div class="absolute top-3 left-3 bg-slate-950/75 backdrop-blur-md text-white text-[11px] font-bold px-3 py-1 rounded-full flex items-center gap-1.5 border border-white/20 shadow-md">
                            <span class="material-symbols-outlined text-[14px] text-sky-400">location_on</span>
                            <span>{{ $dest->name }}</span>
                        </div>

                        {{-- Counts Pill Overlay --}}
                        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="bg-white/20 backdrop-blur-md text-white text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-white/30 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[13px] text-sky-300">hotel</span>
                                    <span>{{ $hotelsCount }} Stays</span>
                                </span>
                                <span class="bg-white/20 backdrop-blur-md text-white text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-white/30 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[13px] text-emerald-300">hiking</span>
                                    <span>{{ $activitiesCount }} Experiences</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Destination Content --}}
                    <div class="p-6 flex flex-col flex-grow space-y-4">
                        <div class="space-y-1.5">
                            <h3 class="font-headline text-xl font-bold text-slate-900 group-hover:text-sky-600 transition-colors">
                                {{ $dest->name }}
                            </h3>
                            <p class="text-slate-500 text-xs leading-relaxed line-clamp-3">
                                {{ $dest->description }}
                            </p>
                        </div>

                        {{-- Card Footer --}}
                        <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between">
                            <a href="{{ route('destinations.show', $dest->id) }}"
                                class="w-full py-2.5 px-4 rounded-xl bg-slate-50 hover:bg-gradient-to-r hover:from-sky-600 hover:to-sky-700 hover:text-white text-slate-900 font-bold text-xs transition-all duration-300 flex items-center justify-between group/btn shadow-2xs">
                                <span>Discover Sanctuary</span>
                                <span class="material-symbols-outlined text-[18px] transition-transform group-hover/btn:translate-x-1">
                                    arrow_forward
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</section>