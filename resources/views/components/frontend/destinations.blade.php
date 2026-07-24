<section class="py-24 px-8 max-w-7xl mx-auto overflow-hidden" id="destinations">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-16 gap-6 reveal-on-scroll">
        <div class="max-w-2xl">
            <span class="font-label text-primary font-bold tracking-[0.2em] text-xs uppercase mb-3 block">CURATED
                COLLECTIONS</span>
            <h2 class="font-headline text-3xl md:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">
                The Essence of the Archipelago</h2>
        </div>
        <p class="text-slate-500 text-xs md:text-sm leading-relaxed max-w-sm">
            Each destination is hand-picked for its soulful atmosphere, architectural integrity, and promise of absolute
            serenity. Beyond travel, these are transformative experiences.
        </p>
    </div>

    {{-- Grid Layout --}}
    <div class="grid grid-cols-1 gap-8 md:gap-10"
        style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr));">
        {{-- Card 1: Palawan --}}
        <div
            class="group relative flex flex-col bg-white border border-slate-200/60 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 reveal-on-scroll">
            <div class="aspect-[4/3] w-full overflow-hidden bg-slate-100">
                <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                    data-alt="dramatic limestone karst cliffs rising from emerald green waters in el nido palawan with a lone wooden boat"
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuCx9ZKh1RhvoVvM-yB8XzhA0L3Zsz0PAoGAzHgcNUzQjYComv_5tEj2tp9hn2W1GMeV0GE4O-FXsnFatIoGCV6uqGj3MufPbVlOHNOPX0KW5HnbD5Xufwol6_nH2ilfeESeNGg5F9NizpIAaPoYNMOJ1qBAx-VwWI_kmWWXAvQ5wZJz0i2HX0AzAqux0olCALoSdVsMeKzwAqIEZyUrTKciEBXPjSjhJ96X8lkOJjWAAes9qKtkarL62Nknqk0zmoWcPUxx1x1wMCc" />
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <span
                    class="font-label text-primary font-bold text-[10px] tracking-[0.15em] uppercase mb-2">PALAWAN</span>
                <h3
                    class="font-headline text-lg font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors">
                    El Nido Sanctuary</h3>
                <p class="text-slate-500 text-xs leading-relaxed mb-6 flex-grow">
                    Whispers of limestone giants and hidden lagoons. Explore secret beaches accessible only by swimming
                    through subterranean tunnels.
                </p>
                <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span
                        class="text-xs font-bold text-slate-950 group-hover:text-primary transition-colors flex items-center gap-1 cursor-pointer">
                        Discover Sanctuary
                        <span
                            class="material-symbols-outlined text-[16px] transition-transform group-hover:translate-x-1"
                            style="vertical-align: middle;">arrow_forward</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Card 2: Boracay --}}
        <div class="group relative flex flex-col bg-white border border-slate-200/60 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 reveal-on-scroll"
            style="transition-delay: 100ms;">
            <div class="aspect-[4/3] w-full overflow-hidden bg-slate-100">
                <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                    data-alt="pristine white sand beach with leaning palm trees and blue water in boracay during daylight"
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuCvDqCJuQIaskC_A_yB8hWbtKzcx8d5wGzsfM7fU1wT_XuP-Db3tZxhASnDkpr16DpX_oOCX6ahOyKDhNc6ilvjxVZvaRJa3RPT24JnqVkBQstRyWA0U8DnnZe4ZimQkYrqqpe4us1ZcbPEAVNlbo3S5sd6Rh90nATZ4ljs0v_OXKq_Rm1RXwMFUlsXIt_IJYZ8j5eH8sZSnRI8z4ISHq3x_Z0cELtcUX9OOQpCvqo6cUrHaSSlLXX5mt-BEnwiIhh6n46bkRs1hzg" />
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <span
                    class="font-label text-primary font-bold text-[10px] tracking-[0.15em] uppercase mb-2">AKLAN</span>
                <h3
                    class="font-headline text-lg font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors">
                    Boracay Sands</h3>
                <p class="text-slate-500 text-xs leading-relaxed mb-6 flex-grow">
                    Where the sun greets the softest flour-white sands on Earth. A perfect balance of island energy and
                    serene wellness retreats.
                </p>
                <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span
                        class="text-xs font-bold text-slate-950 group-hover:text-primary transition-colors flex items-center gap-1 cursor-pointer">
                        Discover Sanctuary
                        <span
                            class="material-symbols-outlined text-[16px] transition-transform group-hover:translate-x-1"
                            style="vertical-align: middle;">arrow_forward</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- {{-- Card 3: Amanpulo / Pamalican Retret --}}
        <div class="group relative flex flex-col bg-white border border-slate-200/60 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 reveal-on-scroll" style="transition-delay: 200ms;">
            <div class="aspect-[4/3] w-full overflow-hidden bg-slate-100">
                <img class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" data-alt="aerial cinematic view of a luxury tropical private beach island surrounded by clear shallow turquoise ocean" src="https://images.unsplash.com/photo-1544735716-392fe2489ffa?auto=format&fit=crop&w=800&q=80"/>
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <span class="font-label text-primary font-bold text-[10px] tracking-[0.15em] uppercase mb-2">SULU SEA</span>
                <h3 class="font-headline text-lg font-bold text-slate-900 mb-2 group-hover:text-primary transition-colors">Pamalican Retreat</h3>
                <p class="text-slate-500 text-xs leading-relaxed mb-6 flex-grow">
                    A secluded paradise surrounded by pristine coral reefs. Experience absolute tranquility, crystal clear waters, and ultimate privacy.
                </p>
                <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-950 group-hover:text-primary transition-colors flex items-center gap-1 cursor-pointer">
                        Discover Sanctuary
                        <span class="material-symbols-outlined text-[16px] transition-transform group-hover:translate-x-1" style="vertical-align: middle;">arrow_forward</span>
                    </span>
                </div>
            </div>
        </div> -->
    </div>
</section>