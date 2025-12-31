import { Controller } from '@hotwired/stimulus';

/*
 * View toggle controller for switching between showcase and grid views
 * Manages button states, view visibility, and persists preference
 */
export default class extends Controller {
    static targets = ['showcaseButton', 'gridButton', 'showcase', 'grid', 'header'];

    connect() {
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Get collection slug from URL for sessionStorage key
        this.collectionSlug = this.getCollectionSlug();

        // Restore saved preference or use default (showcase)
        this.restorePreference();

        // Listen for browser back/forward
        window.addEventListener('popstate', this.handlePopState.bind(this));

        // Set up keyboard shortcuts
        this.setupKeyboardShortcuts();
    }

    disconnect() {
        window.removeEventListener('popstate', this.handlePopState.bind(this));
        document.removeEventListener('keydown', this.handleKeydown);
    }

    getCollectionSlug() {
        // Extract slug from URL path /stills/albums/{slug}
        const pathParts = window.location.pathname.split('/');
        return pathParts[pathParts.length - 1] || 'default';
    }

    restorePreference() {
        // Check URL hash first
        const hash = window.location.hash.slice(1); // Remove #
        if (hash === 'grid') {
            this.showGrid();
            return;
        } else if (hash === 'showcase') {
            this.showShowcase();
            return;
        }

        // Check sessionStorage
        const savedView = sessionStorage.getItem(`stills-view-${this.collectionSlug}`);
        if (savedView === 'showcase') {
            this.showShowcase();
        } else {
            // Default to grid (to avoid overlay on initial load)
            this.showGrid();
        }
    }

    showShowcase() {
        // Update button states
        this.showcaseButtonTarget.classList.remove('bg-bone', 'text-graphite', 'hover:bg-stone/10');
        this.showcaseButtonTarget.classList.add('bg-persimmon', 'text-bone');
        this.showcaseButtonTarget.setAttribute('aria-pressed', 'true');

        this.gridButtonTarget.classList.remove('bg-persimmon', 'text-bone');
        this.gridButtonTarget.classList.add('bg-bone', 'text-graphite', 'hover:bg-stone/10');
        this.gridButtonTarget.setAttribute('aria-pressed', 'false');

        // Hide header and grid, show showcase
        if (this.hasHeaderTarget) {
            this.headerTarget.classList.add('hidden');
        }
        this.gridTarget.classList.add('hidden');
        this.showcaseTarget.classList.remove('hidden');

        // Save preference
        sessionStorage.setItem(`stills-view-${this.collectionSlug}`, 'showcase');

        // Update URL hash without scrolling
        this.updateHash('showcase');

        // Announce to screen readers
        this.announceViewChange('Showcase view');
    }

    showGrid() {
        // Update button states
        this.gridButtonTarget.classList.remove('bg-bone', 'text-graphite', 'hover:bg-stone/10');
        this.gridButtonTarget.classList.add('bg-persimmon', 'text-bone');
        this.gridButtonTarget.setAttribute('aria-pressed', 'true');

        this.showcaseButtonTarget.classList.remove('bg-persimmon', 'text-bone');
        this.showcaseButtonTarget.classList.add('bg-bone', 'text-graphite', 'hover:bg-stone/10');
        this.showcaseButtonTarget.setAttribute('aria-pressed', 'false');

        // Show header and grid, hide showcase
        if (this.hasHeaderTarget) {
            this.headerTarget.classList.remove('hidden');
        }
        this.showcaseTarget.classList.add('hidden');
        this.gridTarget.classList.remove('hidden');

        // Save preference
        sessionStorage.setItem(`stills-view-${this.collectionSlug}`, 'grid');

        // Update URL hash without scrolling
        this.updateHash('grid');

        // Announce to screen readers
        this.announceViewChange('Grid view');

        // Trigger masonry layout after view is visible
        setTimeout(() => {
            const masonryElement = this.gridTarget.querySelector('[data-controller="stills-masonry"]');
            if (masonryElement) {
                const masonryController = this.application.getControllerForElementAndIdentifier(
                    masonryElement,
                    'stills-masonry'
                );
                if (masonryController && typeof masonryController.relayout === 'function') {
                    masonryController.relayout();
                }
            }
        }, 100);
    }

    updateHash(view) {
        // Update URL hash without triggering scroll or popstate
        const newHash = `#${view}`;
        if (window.location.hash !== newHash) {
            history.replaceState(null, '', newHash);
        }
    }

    handlePopState(event) {
        // Restore view based on hash when user navigates back/forward
        this.restorePreference();
    }

    setupKeyboardShortcuts() {
        this.handleKeydown = (event) => {
            // Only trigger if not typing in an input
            if (event.target.matches('input, textarea, select')) {
                return;
            }

            if (event.key === 'g' || event.key === 'G') {
                event.preventDefault();
                this.showGrid();
            } else if (event.key === 's' || event.key === 'S') {
                event.preventDefault();
                this.showShowcase();
            }
        };

        document.addEventListener('keydown', this.handleKeydown);
    }

    announceViewChange(viewName) {
        // Create or update live region for screen reader announcement
        let liveRegion = document.getElementById('view-toggle-announcement');

        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.id = 'view-toggle-announcement';
            liveRegion.setAttribute('role', 'status');
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            liveRegion.className = 'sr-only';
            document.body.appendChild(liveRegion);
        }

        liveRegion.textContent = `Switched to ${viewName}`;
    }
}
