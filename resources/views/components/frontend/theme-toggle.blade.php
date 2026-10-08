<div x-data="{
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try { localStorage.setItem('sunnytrips-theme', this.dark ? 'dark' : 'light'); } catch (e) {}
            window.dispatchEvent(new CustomEvent('sunnytrip:theme-changed', { detail: { dark: this.dark } }));
        }
    }"
    @sunnytrip:theme-changed.window="dark = $event.detail.dark">
    <button type="button" @click="toggle()"
            :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
            :title="dark ? 'Switch to light mode' : 'Switch to dark mode'"
            role="switch"
            :aria-checked="dark.toString()"
            class="flex items-center justify-center rounded-xl p-2 text-slate-700 transition-colors hover:bg-ocean-50 hover:text-ocean-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ocean-500 focus-visible:ring-offset-2 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-ocean-300 dark:focus-visible:ring-offset-slate-900">
        <span class="material-symbols-outlined text-[22px]" aria-hidden="true"
              x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
    </button>
</div>
