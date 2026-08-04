<section class="py-24 px-8 max-w-7xl mx-auto overflow-hidden" id="destinations">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-16 gap-6 reveal-on-scroll">
        <div class="max-w-2xl">
            <span class="font-label text-primary font-bold tracking-[0.2em] text-xs uppercase mb-3 block">
                CURATED COLLECTIONS
            </span>
            <h2 class="font-headline text-3xl md:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">
                The Essence of the Archipelago
            </h2>
        </div>
        <p class="text-slate-500 text-xs md:text-sm leading-relaxed max-w-sm">
            Each destination is hand-picked for its soulful atmosphere, architectural integrity, and promise of absolute
            serenity. Beyond travel, these are transformative experiences.
        </p>
    </div>

    {{-- Dynamic Grid Layout --}}
    <div class="grid grid-cols-1 gap-8 md:gap-10"
        style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr));">

        @foreach($destinations as $index => $dest)
            @php
                $imgSrc = $resolveDestinationImage($dest->image);
            @endphp
            <div class="group relative flex flex-col bg-white border border-slate-200/60 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 reveal-on-scroll"
                style="transition-delay: {{ $index * 100 }}ms;">
                <div class="aspect-[4/3] w-full overflow-hidden bg-slate-100">
                    <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                        src="{{ $imgSrc }}" alt="{{ $dest->name }}" />
                </div>
                <div class="p-6 flex flex-col flex-grow">
                    <h3
                        class="font-headline text-xl font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors">
                        {{ $dest->name }}
                    </h3>
                    <p class="text-slate-500 text-xs leading-relaxed mb-6 flex-grow">
                        {{ $dest->description }}
                    </p>
                    <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between">
                        <a href="{{ route('destinations.show', $dest->id) }}"
                            class="text-xs font-bold text-slate-950 group-hover:text-primary transition-colors flex items-center gap-1">
                            <span>Discover Sanctuary</span>
                            <span
                                class="material-symbols-outlined text-[16px] transition-transform group-hover:translate-x-1"
                                style="vertical-align: middle;">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>