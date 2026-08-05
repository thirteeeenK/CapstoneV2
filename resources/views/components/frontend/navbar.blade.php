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
                <a href="/#experiences" data-nav-target="experiences"
                    class="px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200">Experiences</a>
                <!-- <a href="#"
                    class="px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all">Stays</a> -->
                <a href="/#brand-story" data-nav-target="brand-story"
                    class="px-5 py-2 text-slate-600 hover:bg-white/60 hover:text-sky-700 rounded-full font-semibold text-sm transition-all duration-200">Journals</a>
            </div>

            <!-- Auth Buttons -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- <a href="{{ route('login') }}"
                    class="hidden sm:inline-flex items-center justify-center font-semibold text-sm px-5 py-2.5 rounded-full text-slate-700 hover:bg-white/60 transition-all border border-transparent hover:border-white/60 backdrop-blur-sm">
                    Login
                </a> -->

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