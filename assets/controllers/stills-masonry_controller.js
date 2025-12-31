import { Controller } from '@hotwired/stimulus';

/*
 * Responsive masonry grid controller for photo collections
 * Distributes images across columns while maintaining aspect ratios
 * Balances column heights by placing each image in the shortest column
 */
export default class extends Controller {
    static targets = ['item', 'grid'];
    static values = {
        columns: { type: Number, default: 3 }, // Desktop default
        gap: { type: Number, default: 24 },     // Gap in pixels
    };

    connect() {
        this.resizeObserver = null;
        this.intersectionObserver = null;
        this.resizeTimeout = null;
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Initialize layout
        this.calculateColumns();
        this.layoutMasonry();

        // Set up responsive handling
        this.setupResizeObserver();
        this.setupLazyLoading();
    }

    disconnect() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        if (this.intersectionObserver) {
            this.intersectionObserver.disconnect();
        }
        if (this.resizeTimeout) {
            clearTimeout(this.resizeTimeout);
        }
    }

    setupResizeObserver() {
        if (!window.ResizeObserver) return;

        this.resizeObserver = new ResizeObserver(() => {
            // Debounce resize calculations
            if (this.resizeTimeout) {
                clearTimeout(this.resizeTimeout);
            }

            this.resizeTimeout = setTimeout(() => {
                this.calculateColumns();
                this.layoutMasonry();
            }, 300);
        });

        this.resizeObserver.observe(this.element);
    }

    setupLazyLoading() {
        if (!window.IntersectionObserver) {
            // Fallback: load all images immediately
            this.itemTargets.forEach(item => this.loadImage(item));
            return;
        }

        const options = {
            root: null,
            rootMargin: '50px', // Start loading slightly before visible
            threshold: 0.01,
        };

        this.intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    this.intersectionObserver.unobserve(entry.target);
                }
            });
        }, options);

        this.itemTargets.forEach(item => {
            this.intersectionObserver.observe(item);
        });
    }

    loadImage(item) {
        const img = item.querySelector('img[data-src]');
        if (!img) return;

        const src = img.dataset.src;
        if (!src) return;

        // Create new image to preload
        const loader = new Image();
        loader.onload = () => {
            img.src = src;
            img.removeAttribute('data-src');

            // Fade in animation (unless reduced motion)
            if (!this.prefersReducedMotion) {
                img.style.opacity = '0';
                img.style.transition = 'opacity 0.3s ease-out';

                // Force reflow
                img.offsetHeight;

                img.style.opacity = '1';
            }
        };

        loader.src = src;
    }

    calculateColumns() {
        const width = this.element.offsetWidth;

        // Responsive breakpoints with generous whitespace
        if (width < 768) {
            // Mobile: 1 column
            this.columnsValue = 1;
            this.gapValue = 32;
        } else if (width < 1024) {
            // Tablet: 2 columns
            this.columnsValue = 2;
            this.gapValue = 48;
        } else {
            // Desktop: 3 columns with generous gaps
            this.columnsValue = 3;
            this.gapValue = 48;
        }
    }

    layoutMasonry() {
        const items = this.itemTargets;
        if (items.length === 0) return;

        const columns = this.columnsValue;
        const gap = this.gapValue;

        // Calculate column width
        const totalGapWidth = (columns - 1) * gap;
        const availableWidth = this.element.offsetWidth - totalGapWidth;
        const columnWidth = Math.floor(availableWidth / columns);

        // Initialize column heights array
        const columnHeights = new Array(columns).fill(0);

        // Layout each item
        items.forEach((item, index) => {
            // Get aspect ratio from data attribute
            const aspectRatio = parseFloat(item.dataset.aspectRatio) || 1.5;

            // Calculate item height based on column width and aspect ratio
            const itemHeight = Math.round(columnWidth / aspectRatio);

            // Find shortest column
            const shortestColumn = columnHeights.indexOf(Math.min(...columnHeights));

            // Calculate position
            const x = shortestColumn * (columnWidth + gap);
            const y = columnHeights[shortestColumn];

            // Apply position with GPU-accelerated transform
            item.style.position = 'absolute';
            item.style.width = `${columnWidth}px`;
            item.style.height = `${itemHeight}px`;
            item.style.transform = `translate3d(${x}px, ${y}px, 0)`;

            // Add transition for smooth repositioning (unless reduced motion)
            if (!this.prefersReducedMotion) {
                item.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            }

            // Update column height
            columnHeights[shortestColumn] += itemHeight + gap;
        });

        // Set container height to tallest column
        const containerHeight = Math.max(...columnHeights) - gap;
        if (this.hasGridTarget) {
            this.gridTarget.style.height = `${containerHeight}px`;
        } else {
            this.element.style.height = `${containerHeight}px`;
        }
    }

    // Public method to force re-layout (can be called from other controllers)
    relayout() {
        this.calculateColumns();
        this.layoutMasonry();
    }
}
