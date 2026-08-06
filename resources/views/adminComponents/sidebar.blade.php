<aside onwheel="event.stopPropagation()"
    class="h-screen w-64 fixed left-0 top-0 bg-slate-50 border-r border-slate-200/80 flex flex-col py-6 z-40 transition-all duration-200">

    <!-- Brand Header -->
    <div class="px-6 mb-6">
        <h1 class="font-headline text-lg font-bold text-ocean-600 tracking-tight">{{ env('APP_NAME') }}</h1>
        <p class="font-label text-[10px] uppercase tracking-[0.15em] text-slate-400 font-bold mt-0.5">Admin Portal</p>
    </div>

    @php
        $navGroups = [
            'Overview' => [
                ['route' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'href' => route('admin.dashboard')],
                ['route' => 'admin.insights.*', 'icon' => 'psychology', 'label' => 'AI Insights', 'href' => '#'],
                ['route' => 'admin.reports.*', 'icon' => 'analytics', 'label' => 'Generate Reports', 'href' => '#'],
            ],
            'Operations' => [
                ['route' => 'admin.bookings.*', 'icon' => 'calendar_month', 'label' => 'Bookings', 'href' => route('admin.bookings.index')],
                ['route' => 'admin.users.*', 'icon' => 'group', 'label' => 'Registered Users', 'href' => route('admin.users.index')],
                ['route' => 'admin.inventory.*', 'icon' => 'inventory', 'label' => 'Manage Inventory', 'href' => Route::has('admin.inventory.index') ? route('admin.inventory.index') : '#'],
                ['route' => 'admin.passenger-rules.*', 'icon' => 'tune', 'label' => 'Passenger Rules & Discounts', 'href' => route('admin.passenger-rules.index')],
            ],
            'Catalog' => [
                ['route' => 'admin.destinations', 'icon' => 'place', 'label' => 'Destinations', 'href' => route('admin.destinations')],
                ['route' => 'manage-hotels', 'icon' => 'house', 'label' => 'Add Hotel Information', 'href' => Route::has('manage-hotels') ? route('manage-hotels') : '#'],
                ['route' => 'view-listings', 'icon' => 'hotel', 'label' => 'Show Hotel Listing', 'href' => route('view-listings')],
                ['route' => 'admin.packages.*', 'icon' => 'card_travel', 'label' => 'Tour Packages & Promos', 'href' => route('admin.packages.index')],
                ['route' => 'admin.activities.*', 'icon' => 'explore', 'label' => 'Activities & Tours', 'href' => Route::has('admin.activities.index') ? route('admin.activities.index') : '#'],
                ['route' => 'admin.addons.*', 'icon' => 'extension', 'label' => 'Transfers and Add-ons', 'href' => route('admin.addons.index')],
                ['route' => 'admin.ai.*', 'icon' => 'psychology', 'label' => 'Manage AI', 'href' => '#'],
            ],
            'Legal' => [
                ['route' => 'admin.legal.*', 'icon' => 'gavel', 'label' => 'Legal Documents', 'href' => route('admin.legal.index')],
            ],
            'Community' => [
                ['route' => 'admin.reviews.*', 'icon' => 'reviews', 'label' => 'Reviews & Sentiment', 'href' => route('admin.reviews.index')],
            ]
        ];
    @endphp

    <!-- Navigation Links -->
    <nav class="flex-1 overflow-y-auto overscroll-contain px-3 space-y-6">
        @foreach ($navGroups as $groupLabel => $items)
            <div>
                <h2 class="font-label px-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">
                    {{ $groupLabel }}
                </h2>
                <div class="space-y-0.5">
                    @foreach ($items as $item)
                            @php
                                $isActive = request()->routeIs($item['route']);
                            @endphp
                            <a href="{{ $item['href'] }}" class="flex items-center gap-3 w-full h-9 px-3 rounded-md transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ocean-500 group
                                                                                                                                        {{ $isActive
                        ? 'bg-ocean-50 text-ocean-600 font-semibold'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium' }}">
                                <span
                                    class="material-symbols-outlined text-[18px] shrink-0 {{ $isActive ? 'text-ocean-600' : 'text-slate-400 group-hover:text-slate-600' }}"
                                    style="vertical-align: middle;">
                                    {{ $item['icon'] }}
                                </span>
                                <span class="font-sans text-sm tracking-tight">
                                    {{ $item['label'] }}
                                </span>
                            </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>