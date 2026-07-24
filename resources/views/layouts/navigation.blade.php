<nav x-data="{ open: false }" class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-breeze.application-logo />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-breeze.nav-link :href="route('user.dashboard')" :active="request()->routeIs('user.dashboard')">
                        {{ __('Dashboard') }}
                    </x-breeze.nav-link>

                    <x-breeze.nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Recommendations') }}
                    </x-breeze.nav-link>

                    <x-breeze.nav-link :href="route('accommodation.index')"
                        :active="request()->routeIs('accommodation.*')">
                        {{ __('Accommodation') }}
                    </x-breeze.nav-link>

                    <x-breeze.nav-link :href="route('activities.user.index')"
                        :active="request()->routeIs('activities.user.*')">
                        {{ __('Activities') }}
                    </x-breeze.nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-breeze.dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3.5 py-2 border border-slate-200 text-xs font-bold rounded-lg text-slate-700 bg-slate-50 hover:bg-slate-100 hover:text-slate-900 focus:outline-none transition ease-in-out duration-150 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span>
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1.5">
                                <svg class="fill-current h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-breeze.dropdown-link :href="route('profile.edit')">
                            {{ __('Profile & AI Preferences') }}
                        </x-breeze.dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-breeze.dropdown-link :href="route('logout')" onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-breeze.dropdown-link>
                        </form>
                    </x-slot>
                </x-breeze.dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-slate-500 hover:bg-slate-100 focus:outline-none focus:bg-slate-100 focus:text-slate-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}"
        class="hidden sm:hidden bg-white border-t border-slate-100 shadow-lg">
        <div class="pt-2 pb-3 space-y-1">
            <x-breeze.responsive-nav-link :href="route('user.dashboard')"
                :active="request()->routeIs('user.dashboard')">
                {{ __('Dashboard') }}
            </x-breeze.responsive-nav-link>

            <x-breeze.responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Recommendations') }}
            </x-breeze.responsive-nav-link>

            <x-breeze.responsive-nav-link :href="route('accommodation.index')"
                :active="request()->routeIs('accommodation.*')">
                {{ __('Accommodation') }}
            </x-breeze.responsive-nav-link>

            <x-breeze.responsive-nav-link :href="route('activities.user.index')"
                :active="request()->routeIs('activities.user.*')">
                {{ __('Activities') }}
            </x-breeze.responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-3 border-t border-slate-200">
            <div class="px-4">
                <div class="font-bold text-sm text-slate-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-xs text-slate-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-breeze.responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile & AI Preferences') }}
                </x-breeze.responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-breeze.responsive-nav-link :href="route('logout')" onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-breeze.responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>