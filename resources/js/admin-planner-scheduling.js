export default (config) => {
    let requestSequence = 0;
    let controller = null;
    let timer = null;
    let failedRequest = null;
    let destroyed = false;

    return {
        search: config.search || '', destinationId: String(config.destinationId || ''), tab: config.type || 'activity',
        loading: false, error: '', appliedFilters: null,
        init() { this.appliedFilters = this.filters(); },
        filters() { return { search: this.search, destinationId: this.destinationId, tab: this.tab }; },
        restoreFilters(filters) { Object.assign(this, filters); },
        filterUrl(filters = this.filters()) {
            const url = new URL(config.base);
            url.searchParams.set('type', filters.tab);
            if (filters.search) url.searchParams.set('search', filters.search);
            if (filters.destinationId) url.searchParams.set('destination_id', filters.destinationId);
            return url.href;
        },
        inventoryUrl() {
            const url = new URL(config.inventory);
            url.searchParams.set('tab', this.tab === 'hotel' ? 'hotels' : 'activities');
            if (this.destinationId) url.searchParams.set('destination_id', this.destinationId);
            return url.href;
        },
        hasUnsavedChanges() { return Boolean(this.$refs.results.querySelector('[data-scheduling-form][data-dirty="true"]')); },
        resetEditor(form) {
            for (const input of form.querySelectorAll('[data-saved-value]')) {
                if (input.type === 'checkbox') input.checked = input.dataset.savedValue === '1';
                else input.value = input.dataset.savedValue;
            }
        },
        async fetchResults(url = null) {
            clearTimeout(timer);
            if (destroyed) return;
            if (this.hasUnsavedChanges() && !window.confirm('Discard unsaved scheduling changes to update the list?')) {
                this.restoreFilters(this.appliedFilters); return;
            }
            const filters = this.filters();
            const target = url || this.filterUrl(filters);
            const sequence = ++requestSequence;
            controller?.abort(); controller = new AbortController();
            this.loading = true; this.error = '';
            try {
                const response = await fetch(target, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
                if (!response.ok || response.redirected) throw new Error('Could not load scheduling details. Previous results are unchanged. Please retry.');
                const html = await response.text();
                if (destroyed || sequence !== requestSequence) return;
                this.$refs.results.innerHTML = html;
                this.appliedFilters = filters; failedRequest = null;
                window.history.replaceState(window.history.state, '', target);
            } catch (error) {
                if (destroyed || sequence !== requestSequence || error.name === 'AbortError') return;
                this.restoreFilters(this.appliedFilters);
                failedRequest = { url: target, filters };
                this.error = 'Could not load scheduling details. Previous results are unchanged. Please retry.';
            } finally {
                if (!destroyed && sequence === requestSequence) this.loading = false;
            }
        },
        debouncedSearch() { clearTimeout(timer); timer = setTimeout(() => this.fetchResults(), 300); },
        setTab(tab) { this.tab = tab; return this.fetchResults(); },
        resetFilters() { this.search = ''; this.destinationId = ''; return this.fetchResults(); },
        retryResults() {
            if (!failedRequest) return;
            this.restoreFilters(failedRequest.filters); return this.fetchResults(failedRequest.url);
        },
        handleNavClick(event) {
            const link = event.target.closest('nav[role="navigation"] a');
            if (link && this.$refs.results.contains(link)) {
                event.preventDefault();
                if (!this.loading) return this.fetchResults(link.href);
            }
        },
        destroy() { destroyed = true; requestSequence++; clearTimeout(timer); controller?.abort(); },
    };
};
