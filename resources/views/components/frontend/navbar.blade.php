<header class="fixed top-0 w-full z-50 p-4 sm:p-6 transition-all duration-300">
    <nav id="frontend-nav"
        class="mx-auto max-w-7xl bg-white/10 backdrop-blur-lg border border-white/50 shadow-lg shadow-sky-900/5 rounded-3xl flex justify-between items-center px-4 sm:px-8 py-3 font-body tracking-tight">

        <!-- Logo -->
        <a href="/" class="flex items-center gap-1">
            <span
                class="text-xl sm:text-2xl font-black tracking-tighter text-black drop-shadow-sm transition-transform duration-300 group-hover:scale-[1.02]">
                {{ env('NAME') }}
            </span>

            <!-- Desktop Links -->
            <div
                class="hidden md:flex items-center gap-1.5 bg-white/30 p-1.5 rounded-full border border-white/50 backdrop-blur-md">
                <a href="/#destinations" data-nav-target="destinations"
                    class="px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200">Destinations</a>
                <a href="{{ route('activities.index') }}"
                    class="px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200 {{ request()->routeIs('activities.*') ? 'nav-active' : '' }}">Experiences</a>
                <a href="{{ route('packages.index') }}"
                    class="px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200 {{ request()->routeIs('packages.*') ? 'nav-active' : '' }}">Packages</a>
                <!-- <a href="#"
                    class="px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all">Stays</a> -->
                <a href="/#brand-story" data-nav-target="brand-story"
                    class="px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200">Journals</a>
            </div>

            <!-- Auth Buttons & Cart -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- <a href="{{ route('lucky.index') }}"
                   title="Generate a random surprise itinerary"
                   class="hidden md:inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full font-bold text-sm bg-gradient-to-br from-coral-500 to-amber-500 text-white shadow-md shadow-coral-500/30 hover:shadow-lg hover:shadow-coral-500/40 hover:-translate-y-0.5 duration-200 transition-all border border-white/40">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.023 9.348C7.247 6.894 9.712 5.143 12.596 5.143c4.097 0 7.404 3.217 7.404 7.203 0 1.845-.755 3.52-1.975 4.79-.73.76.324 1.905 1.137 1.159 1.584-1.521 2.589-3.624 2.589-5.949 0-4.886-4.16-8.846-9.305-8.846-3.598 0-6.665 1.936-8.3 4.88-.812 1.458.882 2.502 2.027 1.128l.32-.359zM13.92 16.93c-.564 1.803-2.244 3.07-4.211 3.07-2.493 0-4.514-1.939-4.514-4.337 0-1.11.45-2.118 1.177-2.884.44-.463-.195-1.147-1.036-.859-.713.245-1.536.588-2.07.537-1.002-.096-1.41-1.256-.716-1.822l.449-.366c.977-.797 2.303-.853 3.384-.346.772.363 1.331 1.017 1.653 1.762 1.379-.727 2.889-.978 4.46-.574.906.233 1.75.655 2.486 1.219 1.167.896 1.938 2.256 1.938 3.832 0 3.037-2.59 5.5-5.785 5.5-1.51 0-2.888-.53-3.977-1.413-.759-.616-1.856.361-1.192 1.11.895 1.009 2.163 1.742 3.594 1.876-.546.302-1.163.472-1.815.472-1.132 0-2.17-.43-2.944-1.134-.82-.746-2.206.419-1.434 1.274.932 1.033 2.31 1.675 3.823 1.675 2.043 0 3.88-.898 5.144-2.33.735.305 1.556.472 2.414.472 1.698 0 3.242-.657 4.391-1.732.79-.738-.391-1.874-1.182-1.137-.973.906-2.247 1.419-3.629 1.419-.776 0-1.514-.158-2.184-.436z"/>
                    </svg>
                    <span>I'm Feeling Lucky</span>
                </a> -->

                <button onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))" title="View Trip Basket"
                    class="p-2.5 text-slate-700 hover:text-sky-600 hover:bg-white/60 rounded-full transition relative flex items-center justify-center border border-transparent hover:border-white/60">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </button>

                <a href="{{ route('login') }}"
                    class="inline-flex items-center justify-center px-5 sm:px-6 py-2.5 rounded-full font-bold text-sm bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-md shadow-sky-500/30 hover:shadow-lg hover:shadow-sky-500/40 hover:-translate-y-0.5 duration-200 transition-all border border-sky-400/50">
                    Login
                </a>

                <a href="{{ route('register') }}"
                    class="inline-flex items-center justify-center px-5 sm:px-6 py-2.5 rounded-full font-bold text-sm bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-md shadow-sky-500/30 hover:shadow-lg hover:shadow-sky-500/40 hover:-translate-y-0.5 duration-200 transition-all border border-sky-400/50">
                    Register
                </a>
            </div>

    </nav>

    <!-- Active Nav Link (Scroll Spy) Script -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const nav = document.getElementById("frontend-nav");
            if (!nav) return;

            const links = Array.from(nav.querySelectorAll("[data-nav-target]"));

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