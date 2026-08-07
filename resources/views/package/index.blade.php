<x-frontend.layout title="Tour Packages & Vacation Deals — SunnyTrips">
    @php
        $selectedDestId = request('destination_id');
    @endphp

    <div x-data="{
        activeDestId: '{{ $selectedDestId ?: 'all' }}',
        previewPackage: null,
        activeImgIdx: 0,
        matchesPackage(destId) {
            if (this.activeDestId !== 'all' && String(this.activeDestId) !== String(destId)) {
                return false;
            }
            return true;
        }
    }" class="py-12 bg-slate-50 text-slate-900 min-h-screen relative overflow-hidden">

        {{-- Background Soft Ambient Mesh Glows --}}
        <div
            class="absolute top-10 left-1/3 w-[500px] h-[300px] bg-sky-200/40 blur-3xl rounded-full pointer-events-none">
        </div>
        <div
            class="absolute bottom-10 right-1/3 w-[400px] h-[400px] bg-indigo-200/30 blur-3xl rounded-full pointer-events-none">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 relative z-10">

            {{-- Header Banner --}}
            <div
                class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2 max-w-xl">
                    <div
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-bold uppercase tracking-widest border border-amber-200">
                        <span class="material-symbols-outlined text-[16px] text-amber-600">card_travel</span>
                        <span>Curated Tour Packages & Promos</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 font-headline tracking-tight">
                        All-Inclusive Vacation Deals
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm font-body max-w-xl leading-relaxed">
                        Save big on complete island getaways combining flights, luxury hotel stays, roundtrip airport
                        transfers, and guided island hopping adventures.
                    </p>
                </div>

                {{-- Location Filter Tabs --}}
                <div
                    class="flex items-center gap-2 overflow-x-auto max-w-full p-2 bg-slate-100/90 rounded-2xl border border-slate-200 shrink-0">
                    <span
                        class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 shrink-0 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-400">location_on</span>
                        <span>Location:</span>
                    </span>
                    <button type="button" @click="activeDestId = 'all'"
                        :class="activeDestId === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 border cursor-pointer">
                        All Islands ({{ $packages->count() }})
                    </button>
                    @foreach($destinations as $dest)
                        @php
                            $destPkgCount = $packages->where('destination_id', $dest->id)->count();
                        @endphp
                        @if($destPkgCount > 0)
                            <button type="button" @click="activeDestId = '{{ $dest->id }}'"
                                :class="activeDestId === '{{ $dest->id }}' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer">
                                <span>{{ $dest->name }}</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]"
                                    :class="activeDestId === '{{ $dest->id }}' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">
                                    {{ $destPkgCount }}
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Packages Grid --}}
            @if($packages->isEmpty())
                <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center text-slate-400 text-sm shadow-xs">
                    <span class="material-symbols-outlined text-4xl text-slate-300 mb-2 block">card_travel</span>
                    No tour packages listed yet. Check back soon!
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($packages as $pkg)
                        @php
                            $destName = $pkg->destination->name ?? 'Philippines';
                            $imagesRaw = is_array($pkg->images) ? $pkg->images : (is_string($pkg->images) ? (json_decode($pkg->images, true) ?: []) : []);
                            $resolvedImages = array_map(function ($img) {
                                return App\Concerns\ResolvesImages::resolveImg($img, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80');
                            }, $imagesRaw);
                            if (empty($resolvedImages)) {
                                $resolvedImages = [App\Concerns\ResolvesImages::resolveImg(null, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80')];
                            }
                            $coverImg = $resolvedImages[0];

                            $inclusionsRaw = is_array($pkg->generic_inclusions) ? $pkg->generic_inclusions : (is_string($pkg->generic_inclusions) ? array_filter(array_map('trim', explode(',', $pkg->generic_inclusions))) : []);

                            $pkgPayload = [
                                'id' => $pkg->id,
                                'name' => $pkg->name,
                                'type' => $pkg->type,
                                'price' => '₱' . number_format((float) $pkg->price, 2),
                                'days' => $pkg->days,
                                'nights' => $pkg->nights,
                                'min_pax' => $pkg->min_pax,
                                'destination_name' => $destName,
                                'destination_id' => $pkg->destination_id,
                                'images' => $resolvedImages,
                                'generic_inclusions' => array_values($inclusionsRaw),
                                'hotels' => $pkg->hotels->map(fn($h) => ['id' => $h->id, 'name' => $h->hotel_name])->toArray(),
                                'activities' => $pkg->activities->map(fn($a) => ['id' => $a->id, 'name' => $a->activity_name])->toArray(),
                            ];
                        @endphp

                        <div x-show="matchesPackage('{{ $pkg->destination_id }}')"
                            class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs hover:shadow-md hover:border-amber-400 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">

                            <div>
                                {{-- Card Image & Badges --}}
                                <div class="relative h-52 overflow-hidden bg-slate-900">
                                    <img src="{{ $coverImg }}" alt="{{ $pkg->name }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                    <div
                                        class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent">
                                    </div>

                                    {{-- Price Badge --}}
                                    <div
                                        class="absolute top-3 right-3 bg-slate-950/90 text-emerald-400 font-extrabold text-xs px-3 py-1 rounded-xl border border-white/10 shadow-xs z-10">
                                        ₱{{ number_format((float) $pkg->price, 2) }} <span
                                            class="text-[10px] font-normal text-slate-300">/ pax</span>
                                    </div>

                                    {{-- Destination & Duration Badge --}}
                                    <div
                                        class="absolute bottom-3 left-3 right-3 flex items-center justify-between text-white text-xs z-10">
                                        <span
                                            class="font-bold flex items-center gap-1 bg-black/40 backdrop-blur-xs px-2 py-0.5 rounded-md">
                                            <span
                                                class="material-symbols-outlined text-[14px] text-amber-400">location_on</span>
                                            <span>{{ $destName }}</span>
                                        </span>

                                        @if($pkg->days && $pkg->nights)
                                            <span class="font-bold bg-white/20 backdrop-blur-xs px-2 py-0.5 rounded-md">
                                                {{ $pkg->days }}D / {{ $pkg->nights }}N
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Card Content --}}
                                <div class="p-5 space-y-3">
                                    {{-- Type Badge (Full Text, No Overlap/Truncation) --}}
                                    @if($pkg->type)
                                        <div
                                            class="inline-block px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 text-[11px] font-extrabold tracking-wide border border-amber-200/80">
                                            {{ $pkg->type }}
                                        </div>
                                    @endif

                                    <div class="flex items-start justify-between gap-2">
                                        <h3
                                            class="text-lg font-black text-slate-900 group-hover:text-amber-600 transition-colors font-headline">
                                            {{ $pkg->name }}
                                        </h3>
                                        <span
                                            class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md shrink-0 border border-slate-200">
                                            Min {{ $pkg->min_pax }} Pax
                                        </span>
                                    </div>

                                    {{-- Generic Inclusions Checklist --}}
                                    @if(!empty($inclusionsRaw))
                                        <div class="space-y-1.5 pt-1 border-t border-slate-100">
                                            <span
                                                class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Package
                                                Inclusions:</span>
                                            <div class="space-y-1">
                                                @foreach(array_slice($inclusionsRaw, 0, 4) as $inc)
                                                    <div class="flex items-center gap-2 text-xs text-slate-700 font-medium">
                                                        <span
                                                            class="material-symbols-outlined text-[15px] text-emerald-500 shrink-0">check_circle</span>
                                                        <span class="line-clamp-1">{{ $inc }}</span>
                                                    </div>
                                                @endforeach
                                                @if(count($inclusionsRaw) > 4)
                                                    <span class="text-[10px] text-amber-600 font-bold block pt-0.5">
                                                        +{{ count($inclusionsRaw) - 4 }} additional inclusions
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Card Footer --}}
                            <div class="p-5 pt-0 flex items-center gap-2">
                                <button type="button"
                                    @click="window.addToCart('package', {{ $pkg->id }})"
                                    class="flex-1 py-2.5 px-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition-colors flex items-center justify-center gap-1.5 shadow-xs cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]">shopping_cart</span>
                                    <span>Add to Trip Basket</span>
                                </button>

                                <button type="button"
                                    @click="previewPackage = {{ json_encode($pkgPayload) }}; activeImgIdx = 0;"
                                    class="py-2.5 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200/80 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">visibility</span>
                                    <span>View Details</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        {{-- Dynamic Package Preview Modal --}}
        <div x-show="previewPackage" x-transition.opacity @keydown.escape.window="previewPackage = null"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/75 backdrop-blur-md"
            x-cloak style="display: none;">

            <div @click.away="previewPackage = null"
                class="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl relative flex flex-col">

                {{-- Modal Header --}}
                <div
                    class="sticky top-0 bg-white/90 backdrop-blur-md px-6 py-4 border-b border-slate-200 flex items-center justify-between z-20">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-600 text-xl">card_travel</span>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                            x-text="previewPackage?.type || 'Tour Package'"></span>
                        <template x-if="previewPackage?.destination_name">
                            <span
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-sky-50 text-sky-700 text-[11px] font-bold border border-sky-200">
                                <span class="material-symbols-outlined text-[13px] text-sky-500">location_on</span>
                                <span x-text="previewPackage.destination_name"></span>
                            </span>
                        </template>
                    </div>
                    <button @click="previewPackage = null"
                        class="p-1 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 space-y-6">
                    <div
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-2xl font-black text-slate-900 font-headline" x-text="previewPackage?.name">
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5"
                                x-text="previewPackage?.days + ' Days / ' + previewPackage?.nights + ' Nights • Minimum ' + previewPackage?.min_pax + ' Passengers'">
                            </p>
                        </div>
                        <span
                            class="text-2xl font-black text-emerald-600 font-mono bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200/80 inline-block w-fit"
                            x-text="previewPackage?.price"></span>
                    </div>

                    {{-- Image Carousel Preview --}}
                    <template x-if="previewPackage?.images && previewPackage.images.length > 0">
                        <div class="space-y-3">
                            <div class="relative h-64 sm:h-72 rounded-2xl overflow-hidden bg-slate-900 shadow-inner">
                                <img :src="previewPackage.images[activeImgIdx]" class="w-full h-full object-cover">
                                <span
                                    class="absolute bottom-3 right-3 bg-slate-950/80 text-white text-[10px] font-bold px-2.5 py-1 rounded-full backdrop-blur-xs border border-white/10">
                                    <span x-text="activeImgIdx + 1"></span> / <span
                                        x-text="previewPackage.images.length"></span>
                                </span>
                            </div>
                        </div>
                    </template>

                    {{-- Inclusions --}}
                    <template x-if="previewPackage?.generic_inclusions && previewPackage.generic_inclusions.length > 0">
                        <div class="space-y-2">
                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Complete Package
                                Inclusions</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="(inc, idx) in previewPackage.generic_inclusions" :key="idx">
                                    <div
                                        class="flex items-center gap-2 text-xs text-slate-700 bg-slate-50 px-3 py-2 rounded-xl border border-slate-200/80">
                                        <span
                                            class="material-symbols-outlined text-[15px] text-emerald-500">check_circle</span>
                                        <span x-text="inc"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Modal Footer --}}
                <div
                    class="sticky bottom-0 bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-between gap-4">
                    <button @click="previewPackage = null"
                        class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition-colors">
                        Close Details
                    </button>

                    <button type="button" @click="window.addToCart('package', previewPackage.id); previewPackage = null;"
                        class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-md transition-colors flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-base">shopping_cart</span>
                        <span>Add Package to Trip Basket</span>
                    </button>
                </div>

            </div>
        </div>

    </div>
</x-frontend.layout>