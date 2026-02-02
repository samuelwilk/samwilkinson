import { Controller } from '@hotwired/stimulus';

/*
 * Controller for /stills collections index page
 * Clears hash fragments from view toggles when returning to collections
 */
export default class extends Controller {
    connect() {
        // Clear any hash fragments when landing on collections page
        // This prevents #grid or #showcase from persisting after browser back
        this.clearHash();

        // Handle browser back/forward navigation
        this.boundHandlePopState = this.handlePopState.bind(this);
        window.addEventListener('popstate', this.boundHandlePopState);

        // Also listen for hashchange in case hash is added via some other means
        this.boundHandleHashChange = this.handleHashChange.bind(this);
        window.addEventListener('hashchange', this.boundHandleHashChange);
    }

    disconnect() {
        window.removeEventListener('popstate', this.boundHandlePopState);
        window.removeEventListener('hashchange', this.boundHandleHashChange);
    }

    clearHash() {
        if (window.location.hash) {
            // Use replaceState to avoid adding to browser history
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }

    handlePopState(event) {
        // Clear hash if one appears (e.g., navigating back from album)
        this.clearHash();
    }

    handleHashChange(event) {
        // Immediately clear any hash changes on the collections page
        // View toggles (#grid, #showcase) are only relevant on album pages
        this.clearHash();
    }
}
