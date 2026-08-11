{{-- Live-search results partial: package grid + pagination only.
    The filter controls (search + destination/visibility selects) live in the wrapper so the input never loses focus on AJAX swaps.
    Rendered standalone for AJAX requests (X-Requested-With) and inline for full page loads. --}}

<div id="listings-results">
    {{-- Packages Grid --}}
    @if($packages->isEmpty())
        <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">card_travel</span>
            <p class="text-sm font-bold text-slate-700">No tour packages found matching your criteria.</p>
            <a href="{{ route('admin.packages.create') }}" class="mt-3 inline-block text-xs font-bold text-sky-600 hover:underline">+ Create Your First Package</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($packages as $package)
                @php
                    $imagesArr = is_array($package->images) ? $package->images : (is_string($package->images) ? json_decode($package->images, true) : []);
                    $img = !empty($imagesArr) ? $imagesArr[0] : '/images/placeholder.jpg';
                @endphp

                <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs hover:shadow-md transition duration-300 flex flex-col justify-between group relative">

                    <div>
                        {{-- Package Image Header --}}
                        <div class="relative h-48 bg-slate-100 overflow-hidden">
                            <img src="{{ $img }}" alt="{{ $package->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">

                            {{-- Destination Badge --}}
                            @if($package->destination)
                                <div class="absolute top-3 left-3 bg-slate-950/80 backdrop-blur-md text-white font-extrabold text-[10px] px-2.5 py-1 rounded-lg flex items-center gap-1 border border-white/20">
                                    <span class="material-symbols-outlined text-[13px] text-sky-400">location_on</span>
                                    <span>{{ $package->destination->name }}</span>
                                </div>
                            @endif

                            {{-- Status Badge --}}
                            <div class="absolute top-3 right-3">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border shadow-sm backdrop-blur-md {{ $package->is_active ? 'bg-emerald-500/90 text-white border-emerald-400' : 'bg-amber-500/90 text-white border-amber-400' }}">
                                    {{ $package->is_active ? 'Visible' : 'Hidden' }}
                                </span>
                            </div>

                            {{-- Price Overlay --}}
                            <div class="absolute bottom-3 right-3 bg-slate-950/80 backdrop-blur-md text-emerald-300 font-black text-xs px-3 py-1 rounded-lg border border-white/20">
                                ₱{{ number_format($package->price, 2) }}
                            </div>
                        </div>

                        {{-- Info Body --}}
                        <div class="p-5 space-y-3">
                            <span class="text-[10px] uppercase tracking-wider font-extrabold text-sky-600 bg-sky-50 px-2 py-0.5 rounded border border-sky-100">
                                {{ $package->type ?: 'Tour Promo Package' }}
                            </span>

                            <h3 class="text-base font-bold text-slate-900 group-hover:text-sky-600 transition font-headline line-clamp-1">
                                {{ $package->name }}
                            </h3>

                            <div class="flex items-center gap-3 text-xs text-slate-500">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px] text-slate-400">schedule</span>
                                    {{ $package->days ?: 3 }}D / {{ $package->nights ?: 2 }}N
                                </span>
                                <span>•</span>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px] text-slate-400">group</span>
                                    Min {{ $package->min_pax }} Pax
                                </span>
                            </div>

                            {{-- Inclusions Snapshot --}}
                            @if(!empty($package->generic_inclusions) && is_array($package->generic_inclusions))
                                <div class="pt-2 border-t border-slate-100 space-y-1">
                                    @foreach(array_slice($package->generic_inclusions, 0, 3) as $inc)
                                        <div class="flex items-center gap-1.5 text-[11px] text-slate-600 truncate">
                                            <span class="material-symbols-outlined text-[13px] text-emerald-500">check_circle</span>
                                            <span class="truncate">{{ $inc }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Footer Actions --}}
                    <div class="p-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between gap-2">
                        {{-- Toggle Visibility Button --}}
                        <form action="{{ route('admin.packages.toggle-visibility', $package->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="px-3 py-1.5 rounded-xl border text-xs font-bold transition flex items-center gap-1 cursor-pointer {{ $package->is_active ? 'border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100' : 'border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-100' }}">
                                <span class="material-symbols-outlined text-[15px]">{{ $package->is_active ? 'visibility_off' : 'visibility' }}</span>
                                <span>{{ $package->is_active ? 'Hide' : 'Show' }}</span>
                            </button>
                        </form>

                        <div class="flex items-center gap-2">
                            {{-- Edit Button --}}
                            <a href="{{ route('admin.packages.edit', $package->id) }}"
                               class="px-3 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold transition flex items-center gap-1">
                                <span class="material-symbols-outlined text-[15px]">edit</span>
                                <span>Edit</span>
                            </a>

                            {{-- Delete Form --}}
                            <form action="{{ route('admin.packages.destroy', $package->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this package?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 transition rounded-lg hover:bg-rose-50 cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $packages->links() }}
        </div>
    @endif
</div>