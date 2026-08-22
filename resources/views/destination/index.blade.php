<x-frontend.layout title="All Island Sanctuaries — SunnyTrips">
    <div class="py-12 bg-sand-50/70 text-slate-900 min-h-screen relative overflow-hidden">
        
        {{-- Background Soft Ambient Mesh Glows --}}
        <div class="absolute top-10 left-1/3 w-[500px] h-[300px] bg-sky-200/40 blur-3xl rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-1/3 w-[400px] h-[300px] bg-indigo-200/30 blur-3xl rounded-full pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10 relative z-10">

            {{-- Header Banner --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-3">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-widest border border-sky-200">
                    <span class="material-symbols-outlined text-[16px] text-sky-600">location_on</span>
                    <span>Tropical Island Destinations</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-slate-900 font-headline tracking-tight">
                    Explore All Island Sanctuaries
                </h1>
                <p class="text-slate-500 text-xs sm:text-sm font-body max-w-2xl leading-relaxed">
                    Discover breathtaking paradise destinations across the Philippines. Select any sanctuary to view detailed hotels, luxury rooms, and curated experiences.
                </p>
            </div>

            {{-- Destinations Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($destinations as $dest)
                    @php
                        $destImg = App\Concerns\ResolvesImages::resolveImg(
                            $dest->image ?? null,
                            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80'
                        );
                    @endphp

                    <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 flex flex-col justify-between group">
                        <div>
                            {{-- Image Container --}}
                            <div class="relative h-56 overflow-hidden bg-slate-100">
                                <img src="{{ $destImg }}" alt="{{ $dest->name }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/70 via-slate-900/10 to-transparent"></div>

                                {{-- Name & Badges --}}
                                <div class="absolute bottom-3 left-3 right-3 text-white space-y-1">
                                    <h2 class="text-xl font-black font-headline tracking-tight">
                                        {{ $dest->name }}
                                    </h2>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-bold border border-white/10">
                                            {{ $dest->hotels_count }} Stays Available
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-sky-600/90 backdrop-blur-xs text-white text-[10px] font-bold">
                                            {{ $dest->activities_count }} Experiences
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Description --}}
                            <div class="p-6 space-y-3">
                                <p class="text-xs text-slate-600 leading-relaxed line-clamp-3">
                                    {{ $dest->description ?: 'Experience paradise beaches, pristine crystal waters, luxury resorts, and vibrant island culture.' }}
                                </p>
                            </div>
                        </div>

                        {{-- Action Footer --}}
                        <div class="p-6 pt-0">
                            <a href="{{ route('destinations.show', $dest->id) }}"
                                class="w-full py-3 px-4 rounded-2xl bg-gradient-to-r from-sky-500 to-sky-600 hover:from-sky-600 hover:to-sky-700 text-white font-extrabold text-xs shadow-xs transition-all duration-300 flex items-center justify-between group cursor-pointer">
                                <span>Explore {{ $dest->name }} Sanctuary Details</span>
                                <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</x-frontend.layout>
