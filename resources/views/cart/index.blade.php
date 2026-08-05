<x-frontend.layout title="Trip Basket | SunnyTrips">
    <div class="py-12 bg-slate-50 min-h-screen font-body">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Breadcrumb & Title --}}
            <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <nav class="flex text-xs font-semibold text-slate-400 gap-2 mb-2">
                        <a href="/" class="hover:text-sky-600">Home</a>
                        <span>/</span>
                        <span class="text-sky-700">Trip Basket</span>
                    </nav>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                        <span>Your Trip Itinerary Basket</span>
                        <span class="text-xs bg-sky-100 text-sky-800 font-bold px-3 py-1 rounded-full border border-sky-200">
                            {{ $cartItems->count() }} {{ Str::plural('Item', $cartItems->count()) }}
                        </span>
                    </h1>
                </div>

                <a href="{{ route('destinations.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-sky-600 hover:text-sky-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Continue Exploring</span>
                </a>
            </div>

            @if($cartItems->isEmpty())
                <div class="bg-white rounded-3xl p-12 text-center shadow-xs border border-slate-200/80 max-w-xl mx-auto my-12">
                    <div class="w-24 h-24 bg-sky-50 text-sky-600 rounded-full flex items-center justify-center mx-auto mb-6 border border-sky-100">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">Your basket is currently empty</h2>
                    <p class="text-sm text-slate-500 mb-8">Start curating your dream vacation package by browsing our accommodations, activities, and tour packages!</p>
                    
                    <div class="flex flex-wrap justify-center gap-3">
                        <a href="{{ route('rooms.index') }}" class="px-6 py-3 bg-sky-600 hover:bg-sky-700 text-white rounded-xl font-bold text-sm transition shadow-md shadow-sky-600/20">Explore Rooms</a>
                        <a href="{{ route('activities.index') }}" class="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-sm transition">Explore Activities</a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    
                    {{-- Left 2 Columns: Item Cards --}}
                    <div class="lg:col-span-2 space-y-4">
                        @foreach($cartItems as $item)
                            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md transition duration-200 flex flex-col sm:flex-row gap-5 items-start sm:items-center">
                                
                                {{-- Checkbox --}}
                                <form action="{{ route('cart.toggle', $item->id) }}" method="POST">
                                    @csrf
                                    <input type="checkbox" 
                                           onchange="this.form.submit()" 
                                           {{ $item->is_selected ? 'checked' : '' }} 
                                           class="w-5 h-5 text-sky-600 rounded border-slate-300 focus:ring-sky-500 cursor-pointer">
                                </form>

                                {{-- Image --}}
                                <img src="{{ $item->item_image }}" alt="{{ $item->item_title }}" class="w-full sm:w-28 h-28 rounded-xl object-cover border border-slate-100 shrink-0">

                                {{-- Details --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="text-[10px] uppercase tracking-wider font-extrabold px-2.5 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200">
                                            {{ strtoupper($item->item_type) }}
                                        </span>
                                        @if($item->location_name)
                                            <span class="text-xs font-semibold text-slate-500 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px] text-sky-600">location_on</span>
                                                <span>{{ $item->location_name }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    @if($item->hotel_name)
                                        <div class="flex items-center gap-1.5 text-xs font-extrabold text-sky-900 mb-0.5">
                                            <span class="material-symbols-outlined text-[16px] text-amber-500">hotel</span>
                                            <span>{{ $item->hotel_name }}</span>
                                        </div>
                                    @endif

                                    <h3 class="text-base font-bold text-slate-900 truncate">
                                        {{ $item->item_type === 'room' && $item->itemable ? $item->itemable->room_name : $item->item_title }}
                                    </h3>

                                    {{-- Feature Badges & Specs --}}
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @if($item->item_type === 'room' && $item->itemable)
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200/60">
                                                <span class="material-symbols-outlined text-[13px] text-slate-500">group</span>
                                                <span>Base: {{ $item->itemable->base_occupancy }} • Max: {{ $item->itemable->max_occupancy }} Pax</span>
                                            </span>
                                            @if($item->itemable->bed_configuration)
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200/60">
                                                    <span class="material-symbols-outlined text-[13px] text-slate-500">king_bed</span>
                                                    <span>{{ $item->itemable->bed_configuration }}</span>
                                                </span>
                                            @endif
                                        @endif

                                        @if($item->date_details)
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-800 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200">
                                                <span class="material-symbols-outlined text-[13px] text-sky-600">calendar_month</span>
                                                <span>{{ $item->date_details }}</span>
                                            </span>
                                        @endif

                                        @if($item->selected_pax && $item->item_type !== 'room')
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200/60">
                                                <span class="material-symbols-outlined text-[13px] text-slate-500">person</span>
                                                <span>{{ $item->selected_pax }} Pax</span>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                                        {{-- Quantity Update --}}
                                        <form action="{{ route('cart.update', $item->id) }}" method="POST" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <span class="text-xs font-semibold text-slate-500">Qty:</span>
                                            <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" 
                                                   onchange="this.form.submit()" 
                                                   class="w-16 text-center text-xs font-bold rounded-lg border-slate-200 py-1.5 focus:border-sky-500 focus:ring-sky-500">
                                        </form>

                                        {{-- Subtotal --}}
                                        <div class="text-right">
                                            <span class="text-xs text-slate-400 block">Subtotal</span>
                                            <span class="text-lg font-black text-slate-900">₱{{ number_format($item->subtotal, 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Remove Action --}}
                                <form action="{{ route('cart.remove', $item->id) }}" method="POST" class="self-start sm:self-center">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-400 hover:text-rose-500 transition rounded-lg hover:bg-rose-50">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    {{-- Right 1 Column: Checkout Card --}}
                    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-md sticky top-24 space-y-6">
                        <h2 class="text-lg font-bold text-slate-900 pb-4 border-b border-slate-100">Booking Summary</h2>

                        @php
                            $selectedCartItems = $cartItems->where('is_selected', true);
                            $selectedSubtotal = $selectedCartItems->sum(fn($i) => $i->subtotal);
                        @endphp

                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between text-slate-600">
                                <span>Selected Items</span>
                                <span class="font-bold text-slate-900">{{ $selectedCartItems->count() }} of {{ $cartItems->count() }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Subtotal</span>
                                <span class="font-bold text-slate-900">₱{{ number_format($selectedSubtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Service & Booking Fee</span>
                                <span class="text-emerald-600 font-bold">Waived (Promo)</span>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex justify-between items-baseline">
                            <span class="text-base font-extrabold text-slate-900">Estimated Total</span>
                            <span class="text-2xl font-black text-sky-700">₱{{ number_format($selectedSubtotal, 2) }}</span>
                        </div>

                        <a href="{{ route('checkout.index') }}" class="w-full py-4 bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white rounded-2xl font-extrabold text-sm shadow-lg shadow-sky-600/30 transition flex items-center justify-center gap-2 cursor-pointer">
                            <span>Proceed to Booking Checkout</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>

                </div>
            @endif

        </div>
    </div>
</x-frontend.layout>
