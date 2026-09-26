<header x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false"
    class="fixed top-0 w-full z-50 p-3 sm:p-6 transition-all duration-300">
    <nav id="frontend-nav"
        class="mx-auto max-w-7xl bg-white/10 backdrop-blur-lg border border-white/50 shadow-lg shadow-sky-900/5 rounded-2xl sm:rounded-3xl flex justify-between items-center px-4 sm:px-8 py-2.5 sm:py-3.5 font-body tracking-tight transition-all">

        <!-- Logo -->
        <a href="/" class="flex items-center gap-1.5 shrink-0 group">
            <span
                class="text-lg sm:text-2xl font-black font-headline tracking-tight text-slate-900 drop-shadow-xs transition-transform duration-300 group-hover:scale-[1.02] whitespace-nowrap">
                Sunny<span class="text-ocean-600">Trips</span>
            </span>
        </a>

        <!-- Desktop Navigation Links -->
        <div
            class="hidden md:flex items-center gap-1.5 bg-white/30 p-1.5 rounded-full border border-white/50 backdrop-blur-md">
            <a href="/#destinations" data-nav-target="destinations"
                class="px-4 lg:px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200">Destinations</a>
            <a href="{{ route('activities.index') }}"
                class="px-4 lg:px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200 {{ request()->routeIs('activities.*') ? 'nav-active' : '' }}">Experiences</a>
            <a href="{{ route('packages.index') }}"
                class="px-4 lg:px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200 {{ request()->routeIs('packages.*') ? 'nav-active' : '' }}">Packages</a>
            <a href="/#testimonials" data-nav-target="testimonials"
                class="px-4 lg:px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200">Stories &amp; Reviews</a>
        </div>

        <!-- Right Cluster: Cart, Auth Buttons & Mobile Hamburger -->
        <div class="flex items-center gap-1.5 sm:gap-3">
            <!-- Basket Trigger -->
            <button onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))" title="View Trip Basket"
                aria-label="View Trip Basket"
                class="p-2 sm:p-2.5 text-slate-700 hover:text-sky-600 hover:bg-white/60 rounded-xl sm:rounded-full transition relative flex items-center justify-center border border-transparent hover:border-white/60">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </button>

            <!-- Desktop Auth Links -->
            <div class="hidden md:flex items-center gap-2 sm:gap-3">
                <a href="{{ route('login') }}"
                    class="inline-flex items-center justify-center px-5 sm:px-6 py-2.5 rounded-full font-bold text-sm bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-md shadow-sky-500/30 hover:shadow-lg hover:shadow-sky-500/40 hover:-translate-y-0.5 duration-200 transition-all border border-sky-400/50">
                    Login
                </a>

                <a href="{{ route('register') }}"
                    class="inline-flex items-center justify-center px-5 sm:px-6 py-2.5 rounded-full font-bold text-sm bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-md shadow-sky-500/30 hover:shadow-lg hover:shadow-sky-500/40 hover:-translate-y-0.5 duration-200 transition-all border border-sky-400/50">
                    Register
                </a>
            </div>

            <!-- Mobile Hamburger Toggle Button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" aria-label="Toggle navigation menu"
                :aria-expanded="mobileMenuOpen.toString()"
                class="md:hidden p-2 rounded-xl text-slate-800 hover:text-slate-900 bg-white/40 hover:bg-white/70 backdrop-blur-md border border-white/50 focus:outline-none flex items-center justify-center shadow-xs transition-colors">
                <span class="material-symbols-outlined text-[22px]"
                    x-text="mobileMenuOpen ? 'close' : 'menu'">menu</span>
            </button>
        </div>
    </nav>

    <!-- Mobile Slide-down Menu Drawer with Glassmorphism -->
    <div x-show="mobileMenuOpen" x-cloak @click.outside="mobileMenuOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-3 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-3 scale-[0.98]"
        class="md:hidden mt-2 mx-auto max-w-7xl max-h-[calc(100dvh-5rem)] overflow-y-auto bg-white/80 backdrop-blur-xl border border-white/60 shadow-xl shadow-sky-900/10 rounded-2xl sm:rounded-3xl p-4 sm:p-5 space-y-4">

        <!-- Navigation Links List -->
        <div class="space-y-1">
            <a href="/#destinations" @click="mobileMenuOpen = false"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-slate-700 hover:text-sky-700 hover:bg-white/60 font-bold text-sm transition-colors">
                <span class="material-symbols-outlined text-[20px] text-sky-600">location_on</span>
                <span>Destinations</span>
            </a>

            <a href="{{ route('activities.index') }}" @click="mobileMenuOpen = false"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-slate-700 hover:text-sky-700 hover:bg-white/60 font-bold text-sm transition-colors {{ request()->routeIs('activities.*') ? 'nav-active' : '' }}">
                <span class="material-symbols-outlined text-[20px] text-sky-600">explore</span>
                <span>Experiences & Tours</span>
            </a>

            <a href="{{ route('packages.index') }}" @click="mobileMenuOpen = false"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-slate-700 hover:text-sky-700 hover:bg-white/60 font-bold text-sm transition-colors {{ request()->routeIs('packages.*') ? 'nav-active' : '' }}">
                <span class="material-symbols-outlined text-[20px] text-sky-600">card_travel</span>
                <span>Tour Packages</span>
            </a>

            <a href="/#testimonials" @click="mobileMenuOpen = false"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-slate-700 hover:text-sky-700 hover:bg-white/60 font-bold text-sm transition-colors">
                <span class="material-symbols-outlined text-[20px] text-sky-600">auto_stories</span>
                <span>Stories &amp; Reviews</span>
            </a>

            <a href="{{ route('lucky.index') }}" @click="mobileMenuOpen = false"
                class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-amber-800 bg-amber-50/70 hover:bg-amber-100/80 border border-amber-200/60 font-bold text-sm transition-colors">
                <span class="material-symbols-outlined text-[20px] text-amber-600">casino</span>
                <span>I'm Feeling Lucky</span>
            </a>
        </div>

        <!-- Auth Action Buttons inside Mobile Menu -->
        <div class="pt-3 border-t border-white/50 grid grid-cols-2 gap-2.5">
            <a href="{{ route('login') }}"
                class="flex items-center justify-center py-2.5 px-4 rounded-full font-bold text-xs sm:text-sm bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-md shadow-sky-500/30 hover:shadow-lg hover:shadow-sky-500/40 transition-all border border-sky-400/50">
                Login
            </a>

            <a href="{{ route('register') }}"
                class="flex items-center justify-center py-2.5 px-4 rounded-full font-bold text-xs sm:text-sm bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-md shadow-sky-500/30 hover:shadow-lg hover:shadow-sky-500/40 transition-all border border-sky-400/50">
                Register
            </a>
        </div>
    </div>

    <!-- Active Nav Link (Scroll Spy) Script -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const nav = document.getElementById("frontend-nav");
            if (!nav) return;

            const links = Array.from(document.querySelectorAll("[data-nav-target]"));

            const setActive = (id) => {
                links.forEach((link) => {
                    const match = link.dataset.navTarget === id;
                    link.classList.toggle("nav-active", match);
                    if (match) {
                        link.setAttribute("aria-current", "true");
                    } else {
                        link.removeAttribute("aria-current");
                    }
                });
            };

            links.forEach((link) => {
                link.addEventListener("click", () => setActive(link.dataset.navTarget));
            });

            const onScroll = () => {
                let current = null;
                links.forEach((link) => {
                    const section = document.getElementById(link.dataset.navTarget);
                    if (!section) return;
                    if (section.getBoundingClientRect().top <= window.innerHeight * 0.4) {
                        current = section.id;
                    }
                });
                setActive(current);
            };

            window.addEventListener("scroll", onScroll, { passive: true });
            onScroll();
        });
    </script>
</header>