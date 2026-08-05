<x-frontend.layout title="Trip Basket | SunnyTrips">
    <div class="min-h-screen bg-sand-50 font-body">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">

            {{-- Breadcrumb & Title --}}
            <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <nav class="flex items-center gap-1.5 text-sm text-ink-400 mb-3" aria-label="Breadcrumb">
                        <a href="/" class="hover:text-ocean-600 transition">Home</a>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-ink-600 font-medium">Trip Basket</span>
                    </nav>
                    <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight text-ink-900">
                        Your Trip Basket
                    </h1>
                    <p class="text-sm text-ink-500 mt-2">
                        {{ $cartItems->count() }} {{ Str::plural('item', $cartItems->count()) }}
                        in your basket — {{ $cartItems->where('is_selected', true)->count() }} selected for booking.
                    </p>
                </div>

                <a href="{{ route('destinations.index') }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-ocean-700 hover:text-ocean-900 transition group">
                    <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Continue exploring</span>
                </a>
            </div>

            @if($cartItems->isEmpty())
                <div class="bg-white rounded-3xl shadow-sm shadow-ocean-900/5 p-12 sm:p-16 text-center max-w-xl mx-auto my-12">
                    <svg viewBox="0 0 96 96" fill="none" aria-hidden="true" class="w-28 h-28 mx-auto mb-6">
                        <circle cx="48" cy="48" r="45" class="stroke-sand-200" stroke-width="1.5"/>
                        <circle cx="48" cy="41" r="8" class="fill-ocean-200"/>
                        <path d="M48 26v-3M48 56v-3M31 41h-3M68 41h-3M36 29l-2-2M62 53l-2-2M60 29l2-2M34 53l2-2"
                              class="stroke-ocean-300" stroke-width="2" stroke-linecap="round"/>
                        <path d="M23 68c7-5.5 14-5.5 21 0s14 5.5 21 0" class="stroke-ocean-400" stroke-width="2.5" stroke-linecap="round"/>
                        <path d="M30 76c5.5-4.3 11-4.3 16.5 0s11 4.3 16.5 0" class="stroke-ocean-200" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                    <h2 class="font-display text-2xl font-bold text-ink-900 mb-2">Your basket is currently empty</h2>
                    <p class="text-sm text-ink-500 mb-8 max-w-sm mx-auto leading-relaxed">Start curating your dream vacation package by browsing our accommodations, activities, and tour packages.</p>

                    <div class="flex flex-wrap justify-center gap-3">
                        <a href="{{ route('rooms.index') }}"
                           class="px-6 py-3 rounded-full bg-ocean-600 hover:bg-ocean-500 text-white font-semibold text-sm shadow-sm shadow-ocean-600/25 transition cursor-pointer">
                            Explore Rooms
                        </a>
                        <a href="{{ route('activities.index') }}"
                           class="px-6 py-3 rounded-full bg-ink-900 hover:bg-ink-700 text-white font-semibold text-sm transition cursor-pointer">
                            Explore Activities
                        </a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

                    {{-- Left 2 Columns: Item Cards --}}
                    <div class="lg:col-span-2 space-y-4">
                        @foreach($cartItems as $item)
                            <div class="bg-white rounded-2xl ring-1 ring-sand-200 p-5 sm:p-6 flex flex-col sm:flex-row gap-5 transition-all duration-200 hover:ring-ocean-200 hover:shadow-md hover:shadow-ocean-900/5 {{ $item->is_selected ? '' : 'opacity-60' }}">

                                {{-- Checkbox --}}
                                <form action="{{ route('cart.toggle', $item->id) }}" method="POST" class="pt-0.5">
                                    @csrf
                                    <input type="checkbox"
                                           onchange="this.form.submit()"
                                           {{ $item->is_selected ? 'checked' : '' }}
                                           aria-label="Select {{ $item->item_title }}"
                                           class="w-5 h-5 rounded accent-ocean-600 text-ocean-600 border-sand-300 focus:ring-ocean-500 cursor-pointer">
                                </form>

                                {{-- Image --}}
                                <img src="{{ $item->item_image }}" alt="{{ $item->item_title }}"
                                     class="w-full sm:w-36 h-48 sm:h-32 rounded-xl object-cover ring-1 ring-sand-200 shrink-0">

                                {{-- Details --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="inline-flex items-center rounded-full bg-ocean-50 text-ocean-800 border border-ocean-100 px-2.5 py-0.5 text-[11px] font-semibold tracking-wide">
                                            {{ Str::ucfirst($item->item_type) }}
                                        </span>
                                        @if($item->location_name)
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-ink-500">
                                                <span class="material-symbols-outlined text-sm text-ocean-600">location_on</span>
                                                <span>{{ $item->location_name }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    @if($item->hotel_name)
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-ocean-700 mb-1">
                                            <span class="material-symbols-outlined text-sm text-ocean-600">hotel</span>
                                            <span>{{ $item->hotel_name }}</span>
                                        </div>
                                    @endif

                                    <h3 class="text-base font-semibold text-ink-900 truncate">
                                        {{ $item->item_type === 'room' && $item->itemable ? $item->itemable->room_name : $item->item_title }}
                                    </h3>

                                    {{-- Feature Badges & Specs --}}
                                    <div class="flex flex-wrap gap-1.5 mt-2.5">
                                        @if($item->item_type === 'room' && $item->itemable)
                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-600 bg-sand-100 px-2.5 py-1 rounded-full border border-sand-200">
                                                <span class="material-symbols-outlined text-[13px] text-ink-400">group</span>
                                                <span>Base {{ $item->itemable->base_occupancy }} • Max {{ $item->itemable->max_occupancy }} Pax</span>
                                            </span>
                                            @if($item->itemable->bed_configuration)
                                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-600 bg-sand-100 px-2.5 py-1 rounded-full border border-sand-200">
                                                    <span class="material-symbols-outlined text-[13px] text-ink-400">king_bed</span>
                                                    <span>{{ $item->itemable->bed_configuration }}</span>
                                                </span>
                                            @endif
                                        @endif

                                        @if($item->date_details)
                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ocean-800 bg-ocean-50 px-2.5 py-1 rounded-full border border-ocean-100">
                                                <span class="material-symbols-outlined text-[13px] text-ocean-600">calendar_month</span>
                                                <span>{{ $item->date_details }}</span>
                                            </span>
                                        @endif

                                        @if($item->selected_pax && $item->item_type !== 'room')
                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-600 bg-sand-100 px-2.5 py-1 rounded-full border border-sand-200">
                                                <span class="material-symbols-outlined text-[13px] text-ink-400">person</span>
                                                <span>{{ $item->selected_pax }} Pax</span>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                                        {{-- Quantity Stepper --}}
                                        <form method="POST" action="{{ route('cart.update', $item->id) }}"
                                              x-data="qtyStepper({{ $item->quantity }})"
                                              class="flex items-center gap-2.5">
                                            @csrf
                                            @method('PATCH')
                                            <span class="text-xs font-medium text-ink-500">Qty</span>
                                            <input type="hidden" name="quantity" :value="qty">
                                            <div class="flex items-center rounded-full border border-sand-200 bg-white p-1">
                                                <button type="button"
                                                        @click="qty = Math.max(1, qty - 1)"
                                                        :disabled="qty <= 1"
                                                        :aria-label="'Decrease quantity of {{ $item->item_title }}'"
                                                        class="w-8 h-8 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/>
                                                    </svg>
                                                </button>
                                                <span class="w-9 text-center text-sm font-semibold text-ink-900" x-text="qty"></span>
                                                <button type="button"
                                                        @click="qty += 1"
                                                        :aria-label="'Increase quantity of {{ $item->item_title }}'"
                                                        class="w-8 h-8 rounded-full flex items-center justify-center text-ink-600 hover:bg-sand-100 transition cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12M6 12h12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </form>

                                        {{-- Subtotal --}}
                                        <div class="text-right">
                                            @if($item->quantity > 1)
                                                <span class="block text-xs text-ink-400">₱{{ number_format($item->unit_rate, 2) }} each</span>
                                            @endif
                                            <span class="block text-lg font-bold text-ink-900">₱{{ number_format($item->subtotal, 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Remove Action --}}
                                <form action="{{ route('cart.remove', $item->id) }}" method="POST" class="self-start sm:self-center">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            aria-label="Remove {{ $item->item_title }}"
                                            class="w-9 h-9 rounded-full flex items-center justify-center text-ink-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    {{-- Right 1 Column: Summary Card --}}
                    <div class="lg:sticky lg:top-8">
                        <div class="bg-white rounded-2xl shadow-md shadow-ocean-900/5 p-6 sm:p-7 space-y-5">

                            <h2 class="font-display text-lg font-bold text-ink-900">Booking Summary</h2>

                            @php
                                $selectedCartItems = $cartItems->where('is_selected', true);
                                $selectedSubtotal = $selectedCartItems->sum(fn($i) => $i->subtotal);
                            @endphp

                            <div class="space-y-2.5 text-sm">
                                <div class="flex justify-between text-ink-500">
                                    <span>Selected items</span>
                                    <span class="font-semibold text-ink-700">{{ $selectedCartItems->count() }} of {{ $cartItems->count() }}</span>
                                </div>
                                <div class="flex justify-between text-ink-500">
                                    <span>Subtotal</span>
                                    <span class="font-semibold text-ink-700">₱{{ number_format($selectedSubtotal, 2) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-ink-500">
                                    <span>Service & booking fee</span>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 px-2.5 py-0.5 text-xs font-semibold">
                                        Waived (Promo)
                                    </span>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-sand-200 flex justify-between items-baseline">
                                <span class="font-display text-sm font-bold text-ink-900">Estimated total</span>
                                <span class="font-display text-2xl font-bold text-ocean-700">₱{{ number_format($selectedSubtotal, 2) }}</span>
                            </div>

                            <a href="{{ route('checkout.index') }}"
                               class="w-full py-3.5 rounded-full bg-ocean-600 hover:bg-ocean-500 text-white text-sm font-semibold shadow-sm shadow-ocean-600/25 transition flex items-center justify-center gap-2 cursor-pointer">
                                <span>Proceed to Booking Checkout</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>

                            <p class="text-xs text-ink-400 text-center leading-relaxed">You'll add your guest details and confirm your stay at checkout.</p>
                        </div>
                    </div>

                </div>
            @endif

        </div>
    </div>

    <script>
    function qtyStepper(base) {
        return {
            qty: base,
            base: base,
            init() {
                this.$watch('qty', (v) => {
                    if (v >= 1 && v !== this.base) {
                        requestAnimationFrame(() => this.$el.requestSubmit());
                    }
                });
            }
        };
    }
    </script>
</x-frontend.layout>