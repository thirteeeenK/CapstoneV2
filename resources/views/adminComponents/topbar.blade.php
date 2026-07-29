{{-- Top Navigation Bar Component --}}
<header class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-30 sm:pl-64 transition-all">
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <!-- Left: Context / Admin Indicator -->
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-ocean-600 text-2xl">admin_panel_settings</span>
            <div class="flex items-center gap-2">
                <span class="font-headline font-bold text-base sm:text-lg text-ocean-600 tracking-tight">SunnyTrips</span>
                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-ocean-700 bg-ocean-50 border border-ocean-200 px-2 py-0.5 rounded-md">Admin</span>
            </div>
        </div>

        <!-- Right: Logged-in Admin Profile & Actions -->
        <div class="flex items-center gap-3 sm:gap-4">
            <div class="flex items-center gap-2 text-xs sm:text-sm font-medium text-slate-700">
                <div class="w-7 h-7 rounded-full bg-ocean-600 text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-sm">
                    {{ strtoupper(substr(Auth::guard('admin')->user()?->name ?? Auth::guard('admin')->user()?->email ?? 'A', 0, 1)) }}
                </div>
                <span class="truncate max-w-[110px] sm:max-w-[200px] font-semibold text-slate-800" title="{{ Auth::guard('admin')->user()?->email }}">
                    {{ Auth::guard('admin')->user()?->name ?? Auth::guard('admin')->user()?->email ?? 'Admin User' }}
                </span>
            </div>

            <div class="h-4 w-px bg-slate-200"></div>

            <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 rounded-md transition-colors shadow-sm cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    <span class="hidden sm:inline">Sign out</span>
                </button>
            </form>
        </div>
    </div>
</header>
