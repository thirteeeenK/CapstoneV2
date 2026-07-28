<!-- {{-- Dashboard Statistics Cards Component --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

    {{-- Administrator Account Card --}}
    <div class="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 flex items-start gap-4">
        <div class="p-3 rounded-xl bg-ocean-50 text-ocean-600 shrink-0">
            <span class="material-symbols-outlined text-2xl">admin_panel_settings</span>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Current Session</p>
            <p class="font-headline font-bold text-lg text-ink-900 mt-0.5 truncate max-w-[200px]">
                {{ Auth::guard('admin')->user()?->name ?? 'Administrator' }}
            </p>
            <p class="text-xs text-ink-500 mt-1 truncate max-w-[200px]">
                {{ Auth::guard('admin')->user()?->email }}
            </p>
        </div>
    </div>

    {{-- System Status Card --}}
    <div class="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 flex items-start gap-4">
        <div class="p-3 rounded-xl bg-emerald-50 text-emerald-600 shrink-0">
            <span class="material-symbols-outlined text-2xl">verified_user</span>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Authentication Guard</p>
            <p class="font-headline font-bold text-lg text-ink-900 mt-0.5">Admin Guard</p>
            <p class="text-xs text-emerald-600 font-medium mt-1">Authenticated Session</p>
        </div>
    </div>

    {{-- Quick Security Card --}}
    <div class="bg-white rounded-2xl border border-ink-100 shadow-sm p-6 flex items-start gap-4">
        <div class="p-3 rounded-xl bg-amber-50 text-amber-600 shrink-0">
            <span class="material-symbols-outlined text-2xl">security</span>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Security Mode</p>
            <p class="font-headline font-bold text-lg text-ink-900 mt-0.5">Active</p>
            <p class="text-xs text-ink-500 mt-1">Breeze Auth Conventions</p>
        </div>
    </div>

</div> -->