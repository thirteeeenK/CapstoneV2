// Live search shared by the admin list pages. The index view keeps the filter
// controls; the controller returns the results partial (rooted at resultsId)
// for AJAX requests. Outdated requests are cancelled, a late or failed response
// never replaces the current list, and a failure keeps the last good results
// with a retry. Pages pass their filter values and the query key for each.
export default (config) => {
    let requestSequence = 0;
    let controller = null;
    let timer = null;
    let failedUrl = null;
    let destroyed = false;
    const resultsId = config.resultsId || 'listings-results';
    const defaults = config.defaults || {};

    return {
        ...config.filters,
        loading: false, error: '',
        resultsElement() { return document.getElementById(resultsId); },
        filterUrl() {
            const url = new URL(config.base, window.location.href);
            for (const [name, key] of Object.entries(config.params)) {
                const value = this[name] || defaults[name];
                if (value) url.searchParams.set(key, value);
            }
            return url.href;
        },
        async fetchResults(target = null) {
            clearTimeout(timer);
            if (destroyed) return;
            target ||= this.filterUrl();
            const sequence = ++requestSequence;
            controller?.abort(); controller = new AbortController();
            this.loading = true; this.error = '';
            try {
                const response = await fetch(target, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
                // A redirect means the session expired; never paste the login page into the list.
                if (!response.ok || response.redirected) throw new Error('The results request failed.');
                const html = await response.text();
                if (destroyed || sequence !== requestSequence) return;
                const results = this.resultsElement();
                if (results) results.outerHTML = html;
                failedUrl = null;
            } catch (error) {
                if (destroyed || sequence !== requestSequence || error.name === 'AbortError') return;
                failedUrl = target;
                this.error = 'Could not load results. The list below is unchanged. Please retry.';
            } finally {
                if (!destroyed && sequence === requestSequence) this.loading = false;
            }
        },
        debouncedSearch() { clearTimeout(timer); timer = setTimeout(() => this.fetchResults(), 250); },
        setFilter(name, value) { this[name] = value; return this.fetchResults(); },
        setDestinationId(id) { return this.setFilter('destinationId', id); },
        setVisibility(visibility) { return this.setFilter('visibility', visibility); },
        setLevel(level) { return this.setFilter('level', level); },
        setTab(tab) { return this.setFilter('tab', tab); },
        resetFilters() {
            for (const name of Object.keys(config.filters)) this[name] = '';
            return this.fetchResults();
        },
        retryResults() { return failedUrl ? this.fetchResults(failedUrl) : undefined; },
        handleNavClick(event) {
            const link = event.target.closest('a');
            if (!link || !this.resultsElement()?.contains(link) || !new URL(link.href, window.location.href).searchParams.has('page')) return;
            event.preventDefault();
            return this.fetchResults(link.href);
        },
        destroy() { destroyed = true; requestSequence++; clearTimeout(timer); controller?.abort(); },
    };
};
