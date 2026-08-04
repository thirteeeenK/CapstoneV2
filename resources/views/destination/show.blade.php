<x-frontend.layout :title="$destination->name . ' — SunnyTrips'">

    @php
        $heroImage = App\Concerns\ResolvesImages::resolveImg(
            $destination->image,
            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80'
        );
    @endphp

    <div class="pt-32 sm:pt-36 pb-24 bg-slate-50 min-h-screen">

        {{-- HERO BANNER --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative rounded-3xl overflow-hidden shadow-2xl min-h-[420px] sm:min-h-[520px] flex items-end p-6 sm:p-12 group">
                <img src="{{ $heroImage }}" alt="{{ $destination->name }}"
                    class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-transparent"></div>

                <div class="relative z-10 space-y-3 max-w-3xl">
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-white text-[11px] font-extrabold uppercase tracking-widest border border-white/30">
                            Destination
                        </span>
                        <span class="px-3 py-1 rounded-full bg-ocean-600/80 backdrop-blur-md text-white text-[11px] font-bold uppercase tracking-wider border border-ocean-400/40 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">location_on</span>
                            {{ $destination->name }}
                        </span>
                    </div>
                    <h1 class="text-3xl sm:text-5xl font-black text-white font-headline tracking-tight leading-tight drop-shadow-md">
                        {{ $destination->name }}
                    </h1>
                    <p class="text-slate-200 text-sm sm:text-base leading-relaxed max-w-2xl">
                        {{ $destination->description }}
                    </p>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12 pt-12">

            {{-- ══════════════════════════════════════════
            HOTELS SECTION
            ══════════════════════════════════════════ --}}
            <section class="space-y-8">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">
                            Sanctuary Stays
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Handpicked accommodations at {{ $destination->name }}
                        </p>
                    </div>
                    <span class="text-xs font-bold text-ocean-600 bg-ocean-50 px-3 py-1.5 rounded-full border border-ocean-100">
                        {{ $destination->hotels->count() }} {{ Str::plural('Hotel', $destination->hotels->count()) }}
                    </span>
                </div>

                @if($destination->hotels->isEmpty())
                    <div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 text-sm">
                        No hotels listed for this destination yet. Check back soon.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($destination->hotels as $hotel)
                            @php
                                $hotelImg = App\Concerns\ResolvesImages::resolveImg(
                                    $hotel->images[0] ?? null,
                                    'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80'
                                );
                            @endphp
                            <a href="{{ route('hotels.show', $hotel->id) }}"
                                class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between group">
                                <div>
                                    <div class="relative h-56 sm:h-64 overflow-hidden bg-slate-100">
                                        <img src="{{ $hotelImg }}" alt="{{ $hotel->hotel_name }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        @if($hotel->type)
                                            <div class="absolute top-3 left-3 bg-slate-950/80 backdrop-blur-md text-white text-[10px] font-semibold px-2.5 py-1 rounded-md flex items-center gap-1 border border-white/20">
                                                {{ ucwords(str_replace('-', ' ', $hotel->type)) }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="p-5 space-y-3">
                                        <h3 class="text-base font-bold text-slate-900 group-hover:text-ocean-600 transition-colors font-headline">
                                            {{ $hotel->hotel_name }}
                                        </h3>
                                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                            {{ $hotel->hotel_description }}
                                        </p>
                                        @if(!empty($hotel->vibe_tags) && is_array($hotel->vibe_tags))
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                @foreach(array_slice($hotel->vibe_tags, 0, 3) as $tag)
                                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[10px] font-medium">
                                                        #{{ $tag }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="p-5 pt-0">
                                    <span class="w-full py-2.5 px-4 rounded-xl bg-slate-100 group-hover:bg-ocean-600 group-hover:text-white text-slate-800 font-bold text-xs transition-colors flex items-center justify-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                                        View Sanctuary
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ══════════════════════════════════════════
            ACTIVITIES SECTION
            ══════════════════════════════════════════ --}}
            <section class="space-y-8">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">
                            Curated Experiences
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500">
                            Unforgettable activities waiting for you at {{ $destination->name }}
                        </p>
                    </div>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-100">
                        {{ $destination->activities->count() }} {{ Str::plural('Activity', $destination->activities->count()) }}
                    </span>
                </div>

                @if($destination->activities->isEmpty())
                    <div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 text-sm">
                        No activities listed for this destination yet. Check back soon.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($destination->activities as $activity)
                            @php
                                $actImg = App\Concerns\ResolvesImages::resolveImg(
                                    $activity->images[0] ?? null,
                                    'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80'
                                );
                            @endphp
                            <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-xs hover:shadow-md transition-all duration-300 flex flex-col justify-between group">
                                <div>
                                    <div class="relative h-48 sm:h-56 overflow-hidden bg-slate-100">
                                        <img src="{{ $actImg }}" alt="{{ $activity->activity_name }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        @if($activity->category)
                                            <div class="absolute top-3 left-3 bg-slate-950/80 backdrop-blur-md text-white text-[10px] font-semibold px-2.5 py-1 rounded-md flex items-center gap-1 border border-white/20">
                                                <span class="material-symbols-outlined text-[14px]">{{ App\Concerns\ResolvesImages::getCategoryIcon($activity->category) }}</span>
                                                {{ $activity->category }}
                                            </div>
                                        @endif
                                        @if($activity->rate)
                                            <div class="absolute top-3 right-3 bg-slate-950/80 backdrop-blur-md text-emerald-400 font-extrabold text-xs px-2.5 py-1 rounded-lg border border-white/20">
                                                ₱{{ number_format((float) $activity->rate, 2) }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="p-5 space-y-3">
                                        <h3 class="text-base font-bold text-slate-900 group-hover:text-ocean-600 transition-colors font-headline">
                                            {{ $activity->activity_name }}
                                        </h3>
                                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                            {{ $activity->description }}
                                        </p>
                                        <div class="flex flex-wrap gap-2 text-[11px] text-slate-600 pt-1">
                                            @if($activity->duration)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">schedule</span>
                                                    {{ $activity->duration }}
                                                </span>
                                            @endif
                                            @if($activity->activity_level)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">signal_cellular_alt</span>
                                                    {{ $activity->activity_level }}
                                                </span>
                                            @endif
                                            @if($activity->capacity)
                                                <span class="bg-slate-100 px-2 py-1 rounded-md flex items-center gap-1 font-medium">
                                                    <span class="material-symbols-outlined text-[14px] text-slate-400">group</span>
                                                    Max {{ $activity->capacity }}
                                                </span>
                                            @endif
                                        </div>
                                        @if(!empty($activity->vibe_tags) && is_array($activity->vibe_tags))
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach(array_slice($activity->vibe_tags, 0, 3) as $tag)
                                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[10px] font-medium">
                                                        #{{ $tag }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

        </div>
    </div>

</x-frontend.layout>
