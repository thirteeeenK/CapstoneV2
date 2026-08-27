<div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
    {{-- Mobile Top Bar --}}
    <header class="md:hidden sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-4 py-3 flex items-center justify-between shadow-xs transition-colors">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <span class="font-headline font-black text-lg text-slate-900 tracking-tight">Sunny<span class="text-ocean-600">Trips</span></span>
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-ocean-700 bg-ocean-50 px-2 py-0.5 rounded-lg border border-ocean-200">Traveler</span>
        </a>

        <div class="flex items-center gap-1.5 sm:gap-2">
            <button onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))"
                    aria-label="Open Trip Basket"
                    class="p-2 rounded-xl text-slate-700 hover:text-ocean-600 hover:bg-ocean-50 focus:outline-none flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-[22px]">shopping_basket</span>
            </button>

            <button @click="sidebarOpen = !sidebarOpen"
                    aria-label="Toggle Menu"
                    :aria-expanded="sidebarOpen.toString()"
                    class="p-2 rounded-xl text-slate-700 hover:text-slate-900 hover:bg-slate-100 focus:outline-none flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-[24px]" x-text="sidebarOpen ? 'close' : 'menu'">menu</span>
            </button>
        </div>
    </header>

    {{-- Mobile Backdrop --}}
    <div x-show="sidebarOpen" 
         x-cloak 
         @click="sidebarOpen = false" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0" 
         class="md:hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40">
    </div>

    {{-- User Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
        class="h-screen w-72 max-w-[85vw] md:w-64 fixed left-0 top-0 bg-white border-r border-slate-200 flex flex-col py-5 sm:py-6 z-50 transition-transform duration-300 ease-in-out shadow-lg md:shadow-none">

        {{-- Brand Header --}}
        <div class="px-5 sm:px-6 mb-5 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-1.5">
                    <span class="font-headline font-black text-xl text-slate-900 tracking-tight">Sunny<span class="text-ocean-600">Trips</span></span>
                </a>
                <p class="font-label text-[10.5px] uppercase tracking-[0.18em] text-ocean-700 font-extrabold mt-0.5 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>Traveler Portal</span>
                </p>
            </div>
            <button @click="sidebarOpen = false" 
                    aria-label="Close navigation"
                    class="md:hidden p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        {{-- Logged-in User Card Preview (Mobile & Desktop) --}}
        <div class="mx-3.5 mb-4 p-3 rounded-2xl bg-sand-50/80 border border-sand-200/70 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-ocean-100 border border-ocean-200 text-ocean-800 font-headline font-extrabold text-sm flex items-center justify-center shrink-0">
                {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-slate-800 truncate leading-tight">{{ Auth::user()->name }}</p>
                <p class="text-[11px] text-slate-500 truncate leading-tight mt-0.5">{{ Auth::user()->email }}</p>
            </div>
        </div>

        @php
            $navGroups = [
                'Main Navigation' => [
                    ['route' => 'dashboard', 'icon' => 'space_dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                    ['route' => 'lucky.*', 'icon' => 'casino', 'label' => "I'm Feeling Lucky", 'href' => route('lucky.index')],
                    ['route' => 'cart.*', 'icon' => 'shopping_basket', 'label' => 'My Trip Basket', 'href' => route('cart.index'), 'drawer' => true],
                    ['route' => 'booking.*', 'icon' => 'auto_stories', 'label' => 'My Bookings', 'href' => route('booking.index')],
                    ['route' => 'reviews.*', 'icon' => 'reviews', 'label' => 'Guest Reviews', 'href' => route('reviews.index')],
                ],
                'Explore Catalog' => [
                    ['route' => 'destinations.*', 'icon' => 'location_on', 'label' => 'Island Destinations', 'href' => route('destinations.index')],
                    ['route' => 'hotels.*', 'icon' => 'hotel', 'label' => 'Hotels & Sanctuaries', 'href' => route('hotels.index')],
                    ['route' => 'rooms.*', 'icon' => 'king_bed', 'label' => 'Rooms & Stays', 'href' => route('rooms.index')],
                    ['route' => 'activities.*', 'icon' => 'explore', 'label' => 'Activities & Tours', 'href' => route('activities.index')],
                    ['route' => 'addons.*', 'icon' => 'extension', 'label' => 'Transfers & Add-ons', 'href' => route('addons.index')],
                    ['route' => 'packages.*', 'icon' => 'card_travel', 'label' => 'Tour Packages & Promos', 'href' => route('packages.index')],
                ],
                'Account' => [
                    ['route' => 'profile.edit', 'icon' => 'manage_accounts', 'label' => 'Account Profile', 'href' => route('profile.edit')],
                ],
            ];
        @endphp

        {{-- Navigation Links --}}
        <nav class="flex-1 overflow-y-auto overscroll-contain px-3 space-y-5">
            @foreach ($navGroups as $groupLabel => $items)
                <div>
                    <h2 class="font-label px-3 text-[11px] font-extrabold text-slate-400 uppercase tracking-wider mb-2">
                        {{ $groupLabel }}
                    </h2>
                    <div class="space-y-1">
                        @foreach ($items as $item)
                            @php
                                $isActive = request()->routeIs($item['route']);
                                $isCart = isset($item['drawer']) && $item['drawer'];
                            @endphp
                            <a href="{{ $item['href'] }}"
                                @if($isCart) onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer')); return false;" @endif
                                class="flex items-center gap-3 w-full min-h-[42px] px-3 py-2 rounded-xl transition-all duration-200 group {{ $isActive ? 'bg-ocean-50 text-ocean-800 font-bold border border-ocean-200 shadow-xs' : 'text-slate-700 hover:bg-sand-50 hover:text-slate-950 font-bold' }}">
                                <span
                                    class="material-symbols-outlined text-[20px] shrink-0 transition-colors {{ $isActive ? 'text-ocean-600' : 'text-slate-500 group-hover:text-ocean-600' }}">
                                    {{ $item['icon'] }}
                                </span>
                                <span class="font-body text-[13px] tracking-tight flex-1">
                                    {{ $item['label'] }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- Lower Left User Profile & Logout --}}
        <div class="mt-auto px-4 pt-4 border-t border-slate-200 space-y-3">
            {{-- Logout Form Button positioned at lower left --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full flex items-center justify-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-bold text-rose-600 bg-rose-50/80 hover:bg-rose-100 hover:text-rose-700 border border-rose-200/80 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                    <span>Log Out</span>
                </button>
            </form>
        </div>
    </aside>
</div>