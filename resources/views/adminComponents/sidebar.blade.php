<aside onwheel="event.stopPropagation()"
    class="h-screen w-64 fixed left-0 top-0 bg-white border-r border-slate-200 flex flex-col py-6 z-40 transition-all duration-200 shadow-sm">

    <!-- Brand Header -->
    <div class="px-6 mb-6">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-1.5">
            <span class="font-headline font-black text-xl text-slate-900 tracking-tight">Sunny<span
                    class="text-ocean-600">Trips</span></span>
        </a>
        <p
            class="font-label text-[10.5px] uppercase tracking-[0.18em] text-ocean-700 font-extrabold mt-0.5 flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-ocean-500"></span>
            <span>Admin Portal</span>
        </p>
    </div>

    @php
        $navGroups = [
            'Overview' => [
                ['route' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'href' => route('admin.dashboard')],
                ['route' => 'admin.reports.*', 'icon' => 'analytics', 'label' => 'Generate Reports', 'href' => route('admin.reports.index')],
            ],
            'Operations' => [
                ['route' => 'admin.bookings.*', 'icon' => 'calendar_month', 'label' => 'Bookings', 'href' => route('admin.bookings.index')],
                ['route' => 'admin.users.*', 'icon' => 'group', 'label' => 'Registered Users', 'href' => route('admin.users.index')],
                ['route' => 'admin.support.*', 'icon' => 'support_agent', 'label' => 'Support Inbox', 'href' => route('admin.support.index')],
                ['route' => 'admin.faqs.*', 'icon' => 'quiz', 'label' => 'FAQ Manager', 'href' => route('admin.faqs.index')],
                ['route' => 'admin.onboarding-options.*', 'icon' => 'tune', 'label' => 'Onboarding Options', 'href' => route('admin.onboarding-options.index')],
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
                <h2 class="font-label px-3 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-2">
                    {{ $groupLabel }}
                </h2>
                <div class="space-y-1">
                    @foreach ($items as $item)
                        @php
                            $isActive = request()->routeIs($item['route']);
                        @endphp
                        <a href="{{ $item['href'] }}"
                            class="flex items-center gap-3 w-full h-10 px-3 rounded-xl transition-all duration-200 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ocean-500 {{ $isActive ? 'bg-ocean-50 text-ocean-800 font-bold border border-ocean-200 shadow-xs' : 'text-slate-700 hover:bg-sand-50 hover:text-slate-950 font-bold' }}">
                            <span
                                class="material-symbols-outlined text-[20px] shrink-0 transition-colors {{ $isActive ? 'text-ocean-600 font-bold' : 'text-slate-500 group-hover:text-ocean-600' }}">
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
</aside>