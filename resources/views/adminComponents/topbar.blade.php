{{-- Top Navigation Bar Component --}}
<header class="bg-white border-b border-ink-100 shadow-sm sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-ocean-500 text-2xl">admin_panel_settings</span>
            <span class="font-headline font-bold text-lg text-ocean-600">SunnyTrips</span>
            <span class="text-xs font-semibold uppercase tracking-wider text-ink-500 bg-sand-100 px-2 py-0.5 rounded">Admin</span>
        </div>

        <div class="flex items-center gap-4">
            <span class="text-sm font-medium text-ink-600">
                {{ Auth::guard('admin')->user()?->name ?? Auth::guard('admin')->user()?->email }}
            </span>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-sm">logout</span>
                    Sign out
                </button>
            </form>
        </div>
    </div>
</header>
