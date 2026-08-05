<div x-data="{ sidebarOpen: false }">
    {{-- Mobile Top Bar --}}
    <div
        class="md:hidden sticky top-0 z-30 bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between shadow-xs">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <span class="font-headline font-black text-lg text-slate-900 tracking-tight">Sunny<span
                    class="text-sky-600">Trips</span></span>
            <span
                class="text-[10px] font-bold uppercase tracking-widest text-sky-600 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">Traveler</span>
        </a>
        <button @click="sidebarOpen = !sidebarOpen"
            class="p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none">
            <span class="material-symbols-outlined text-2xl">menu</span>
        </button>
    </div>

    {{-- Mobile Backdrop --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="md:hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40">
    </div>

    {{-- User Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
        class="h-screen w-64 fixed left-0 top-0 bg-white border-r border-slate-200/90 flex flex-col py-6 z-50 transition-transform duration-300 ease-in-out shadow-sm">

        {{-- Brand Header --}}
        <div class="px-6 mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-1.5">
                    <span class="font-headline font-black text-xl text-slate-900 tracking-tight">Sunny<span
                            class="text-sky-600">Trips</span></span>
                </a>
                <p
                    class="font-label text-[10px] uppercase tracking-[0.18em] text-sky-600 font-bold mt-0.5 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>Traveler Portal</span>
                </p>
            </div>
            <button @click="sidebarOpen = false" class="md:hidden p-1 rounded-md text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        @php
            $navGroups = [
                'Main Navigation' => [
                    ['route' => 'dashboard', 'icon' => 'space_dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ],
                'Explore Catalog' => [
                    ['route' => 'destinations.*', 'icon' => 'location_on', 'label' => 'Island Destinations', 'href' => route('destinations.index')],
                    ['route' => 'hotels.*', 'icon' => 'hotel', 'label' => 'Hotels & Sanctuaries', 'href' => route('hotels.index')],
                    ['route' => 'rooms.*', 'icon' => 'king_bed', 'label' => 'Rooms & Stays', 'href' => route('rooms.index')],
                    ['route' => 'activities.*', 'icon' => 'explore', 'label' => 'Activities & Tours', 'href' => route('activities.index')],
                    ['route' => 'addons.*', 'icon' => 'extension', 'label' => 'Transfers & Add-ons', 'href' => route('addons.index')],
                ],
                'Personalization' => [
                    ['route' => 'onboarding.*', 'icon' => 'tune', 'label' => 'AI Preferences Quiz', 'href' => route('onboarding.index')],
                    ['route' => 'profile.edit', 'icon' => 'manage_accounts', 'label' => 'Account Profile', 'href' => route('profile.edit')],
                ],
            ];
        @endphp

        {{-- Navigation Links --}}
        <nav class="flex-1 overflow-y-auto overscroll-contain px-3 space-y-6">
            @foreach ($navGroups as $groupLabel => $items)
                <div>
                    <h2 class="font-label px-3 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2">
                        {{ $groupLabel }}
                    </h2>
                    <div class="space-y-1">
                        @foreach ($items as $item)
                            @php
                                $isActive = request()->routeIs($item['route']);
                            @endphp
                            <a href="{{ $item['href'] }}"
                                class="flex items-center gap-3 w-full h-10 px-3 rounded-xl transition-all duration-200 group {{ $isActive ? 'bg-sky-50 text-sky-700 font-bold border border-sky-200/80 shadow-xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold' }}">
                                <span
                                    class="material-symbols-outlined text-[20px] shrink-0 {{ $isActive ? 'text-sky-600' : 'text-slate-400 group-hover:text-sky-600' }}">
                                    {{ $item['icon'] }}
                                </span>
                                <span class="font-sans text-xs tracking-tight">
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
            <div class="flex items-center gap-3 px-2">
                <div
                    class="w-9 h-9 rounded-xl bg-sky-500/10 border border-sky-200 text-sky-700 font-bold flex items-center justify-center text-xs shrink-0">
                    {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="overflow-hidden">
                    <div class="font-bold text-xs text-slate-900 truncate">{{ Auth::user()->name }}</div>
                    <div class="text-[10px] text-slate-400 truncate">{{ Auth::user()->email }}</div>
                </div>
            </div>

            {{-- Logout Form Button positioned at lower left --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-rose-600 bg-rose-50/80 hover:bg-rose-100 hover:text-rose-700 border border-rose-200/80 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                    <span>Log Out</span>
                </button>
            </form>
        </div>
    </aside>
</div>