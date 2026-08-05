<x-frontend.layout title="Transfers & Travel Add-ons — SunnyTrips">

    <div x-data="{ activeDestId: '{{ request('destination_id') ?: 'all' }}', previewAddon: null }" class="py-12 bg-slate-50 text-slate-900 min-h-screen relative overflow-hidden">
        
        {{-- Background Soft Ambient Mesh Glows --}}
        <div class="absolute top-10 left-1/3 w-[500px] h-[300px] bg-sky-200/40 blur-3xl rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-1/3 w-[400px] h-[400px] bg-indigo-200/30 blur-3xl rounded-full pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 relative z-10">

            {{-- Header Banner --}}
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2 max-w-xl">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-xs font-bold uppercase tracking-widest border border-sky-200">
                        <span class="material-symbols-outlined text-[16px] text-sky-600">extension</span>
                        <span>Full Transfers & Add-ons Catalog</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 font-headline tracking-tight">
                        Transfers & Travel Add-ons
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm font-body max-w-xl leading-relaxed">
                        Book all-in airport to hotel transfers, private speedboats, multicab shuttles, equipment rentals, and island travel add-ons for a seamless vacation.
                    </p>
                </div>

                {{-- Location Filter Tabs --}}
                <div class="flex items-center gap-2 overflow-x-auto max-w-full p-2 bg-slate-100/90 rounded-2xl border border-slate-200 shrink-0">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 shrink-0 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-400">location_on</span>
                        <span>Location:</span>
                    </span>
                    <button type="button" @click="activeDestId = 'all'"
                        :class="activeDestId === 'all' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                        class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 border cursor-pointer">
                        All Islands ({{ $addons->count() }})
                    </button>
                    @foreach($destinations as $dest)
                        @php
                            $destAddonCount = $addons->where('destination_id', $dest->id)->count();
                        @endphp
                        @if($destAddonCount > 0)
                            <button type="button" @click="activeDestId = '{{ $dest->id }}'"
                                :class="activeDestId === '{{ $dest->id }}' ? 'bg-sky-600 text-white font-extrabold shadow-xs border-sky-600' : 'bg-white text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 font-semibold border-slate-200/80'"
                                class="px-3.5 py-1.5 rounded-xl text-xs transition-all shrink-0 flex items-center gap-1.5 border cursor-pointer">
                                <span>{{ $dest->name }}</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px]" :class="activeDestId === '{{ $dest->id }}' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">
                                    {{ $destAddonCount }}
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Addons Grid --}}
            @if($addons->isEmpty())
                <div class="bg-white p-12 rounded-3xl border border-slate-200 text-center text-slate-400 text-sm shadow-xs">
                    <span class="material-symbols-outlined text-4xl text-slate-300 mb-2 block">extension_off</span>
                    No transfers or add-ons listed yet. Check back soon!
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($addons as $addon)
                        @php
                            $destName = $addon->destination->name ?? 'All Destinations';
                            $inclusionsRaw = is_array($addon->inclusions) ? $addon->inclusions : (is_string($addon->inclusions) ? array_filter(array_map('trim', explode(',', $addon->inclusions))) : []);
                            $pricingTiersRaw = is_array($addon->pricing_tiers) ? $addon->pricing_tiers : (is_string($addon->pricing_tiers) ? (json_decode($addon->pricing_tiers, true) ?: []) : []);
                            $surchargesRaw = is_array($addon->surcharges) ? $addon->surcharges : (is_string($addon->surcharges) ? (json_decode($addon->surcharges, true) ?: []) : []);

                            // Determine lowest rate for display badge
                            $lowestRate = null;
                            if (!empty($pricingTiersRaw)) {
                                $rates = array_column($pricingTiersRaw, 'rate');
                                $rates = array_filter(array_map('floatval', $rates));
                                if (!empty($rates)) {
                                    $lowestRate = min($rates);
                                }
                            }

                            $addonPayload = [
                                'id' => $addon->id,
                                'name' => $addon->name,
                                'type' => $addon->type,
                                'description' => $addon->description,
                                'destination_name' => $destName,
                                'destination_id' => $addon->destination_id,
                                'inclusions' => array_values($inclusionsRaw),
                                'pricing_tiers' => array_values($pricingTiersRaw),
                                'surcharges' => array_values($surchargesRaw),
                            ];
                        @endphp

                        <div x-show="activeDestId === 'all' || String(activeDestId) === '{{ $addon->destination_id }}'"
                            class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between group">
                            
                            <div class="space-y-4">
                                {{-- Header Badges --}}
                                <div class="flex items-center justify-between gap-2">
                                    <span class="px-2.5 py-1 rounded-full bg-sky-50 text-sky-700 text-[10px] font-bold uppercase tracking-wider border border-sky-200">
                                        {{ $addon->type ?: 'Add-on Service' }}
                                    </span>
                                    @if($destName)
                                        <span class="text-xs font-semibold text-slate-500 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px] text-sky-500">location_on</span>
                                            <span>{{ $destName }}</span>
                                        </span>
                                    @endif
                                </div>

                                {{-- Title & Rate --}}
                                <div class="space-y-1">
                                    <h3 class="text-lg font-bold text-slate-900 group-hover:text-sky-600 transition-colors font-headline">
                                        {{ $addon->name }}
                                    </h3>
                                    @if($lowestRate)
                                        <div class="text-xs font-extrabold text-emerald-600">
                                            From ₱{{ number_format($lowestRate, 2) }} <span class="text-[10px] font-normal text-slate-400">/ pax</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Description --}}
                                <p class="text-xs text-slate-500 line-clamp-3 leading-relaxed">
                                    {{ $addon->description ?: 'Hassle-free transfer and auxiliary travel service with full support.' }}
                                </p>

                                {{-- Key Inclusions Preview --}}
                                @if(!empty($inclusionsRaw))
                                    <div class="space-y-1 pt-1">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Service Inclusions:</span>
                                        <div class="space-y-1">
                                            @foreach(array_slice($inclusionsRaw, 0, 3) as $inc)
                                                <div class="flex items-center gap-1.5 text-xs text-slate-700 font-medium">
                                                    <span class="material-symbols-outlined text-[14px] text-emerald-500 shrink-0">check_circle</span>
                                                    <span class="line-clamp-1">{{ $inc }}</span>
                                                </div>
                                            @endforeach
                                            @if(count($inclusionsRaw) > 3)
                                                <span class="text-[10px] text-sky-600 font-bold block pt-0.5">
                                                    +{{ count($inclusionsRaw) - 3 }} more inclusions
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Card Footer --}}
                            <div class="pt-6 border-t border-slate-100 mt-4 flex items-center justify-between gap-3">
                                <button type="button"
                                    @click="previewAddon = {{ json_encode($addonPayload) }};"
                                    class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-sky-600 hover:text-white text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    <span>View Rates & Details</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        {{-- Dynamic Add-on Preview Modal --}}
        <div x-show="previewAddon" x-transition.opacity @keydown.escape.window="previewAddon = null"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/75 backdrop-blur-md" x-cloak style="display: none;">
            
            <div @click.away="previewAddon = null"
                class="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl relative flex flex-col">
                
                {{-- Modal Header --}}
                <div class="sticky top-0 bg-white/90 backdrop-blur-md px-6 py-4 border-b border-slate-200 flex items-center justify-between z-20">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-sky-600 text-xl">extension</span>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider" x-text="previewAddon?.type || 'Add-on'"></span>
                        <template x-if="previewAddon?.destination_name">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-sky-50 text-sky-700 text-[11px] font-bold border border-sky-200">
                                <span class="material-symbols-outlined text-[13px] text-sky-500">location_on</span>
                                <span x-text="previewAddon.destination_name"></span>
                            </span>
                        </template>
                    </div>
                    <button @click="previewAddon = null" class="p-1 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 space-y-6">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 font-headline" x-text="previewAddon?.name"></h2>
                        <p class="text-xs sm:text-sm text-slate-500 mt-1 leading-relaxed" x-text="previewAddon?.description"></p>
                    </div>

                    {{-- Pricing Tiers Table --}}
                    <template x-if="previewAddon?.pricing_tiers && previewAddon.pricing_tiers.length > 0">
                        <div class="space-y-2">
                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[15px] text-emerald-600">payments</span>
                                Rate Tiers per Passenger Count
                            </h4>
                            <div class="overflow-hidden border border-slate-200 rounded-2xl">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                        <tr>
                                            <th class="px-4 py-2.5">Passenger Pax Range</th>
                                            <th class="px-4 py-2.5 text-right">Rate / Pax</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(tier, idx) in previewAddon.pricing_tiers" :key="idx">
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="px-4 py-2 font-medium text-slate-800">
                                                    <span x-text="tier.min_pax === tier.max_pax ? tier.min_pax + ' Pax' : tier.min_pax + ' - ' + tier.max_pax + ' Pax'"></span>
                                                </td>
                                                <td class="px-4 py-2 text-right font-bold text-emerald-600 font-mono">
                                                    ₱<span x-text="Number(tier.rate).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    {{-- Surcharges --}}
                    <template x-if="previewAddon?.surcharges && previewAddon.surcharges.length > 0">
                        <div class="space-y-2 bg-amber-50/80 border border-amber-200/80 p-4 rounded-2xl text-xs text-amber-900">
                            <span class="font-bold block flex items-center gap-1">
                                <span class="material-symbols-outlined text-[15px]">info</span>
                                Optional Drop-off / Surcharges
                            </span>
                            <div class="space-y-1">
                                <template x-for="(surcharge, idx) in previewAddon.surcharges" :key="idx">
                                    <div class="flex items-center justify-between text-xs font-medium">
                                        <span x-text="surcharge.name"></span>
                                        <span class="font-bold font-mono">+₱<span x-text="surcharge.amount"></span></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Inclusions --}}
                    <template x-if="previewAddon?.inclusions && previewAddon.inclusions.length > 0">
                        <div class="space-y-2">
                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">All Included Services</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="(inc, idx) in previewAddon.inclusions" :key="idx">
                                    <div class="flex items-center gap-2 text-xs text-slate-700 bg-slate-50 px-3 py-2 rounded-xl border border-slate-200/80">
                                        <span class="material-symbols-outlined text-[15px] text-emerald-500">check_circle</span>
                                        <span x-text="inc"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Modal Footer --}}
                <div class="sticky bottom-0 bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-between gap-4">
                    <button @click="previewAddon = null" class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition-colors">
                        Close Details
                    </button>

                    <template x-if="previewAddon?.destination_id">
                        <a :href="'/destinations/' + previewAddon.destination_id"
                            class="px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-md transition-colors flex items-center gap-1.5">
                            <span>Explore Island Sanctuary</span>
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </a>
                    </template>
                </div>

            </div>
        </div>

    </div>
</x-frontend.layout>
