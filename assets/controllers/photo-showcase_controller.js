import { Controller } from '@hotwired/stimulus';

/*
 * Photo showcase controller for vertical cinematic scroll
 * Handles lazy loading, scroll-based animations, and photo reveals
 */
export default class extends Controller {
    static targets = ['photo'];

    connect() {
        this.intersectionObserver = null;
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Initialize lazy loading and scroll animations
        this.setupLazyLoading();
        this.setupScrollAnimations();
    }

    disconnect() {
        if (this.intersectionObserver) {
            this.intersectionObserver.disconnect();
        }
    }

    setupLazyLoading() {
        if (!window.IntersectionObserver) {
            // Fallback: load all images immediately
            this.photoTargets.forEach(photo => this.loadImage(photo));
            return;
        }

        const options = {
            root: null,
            rootMargin: '100px', // Start loading before visible
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

        this.photoTargets.forEach(photo => {
            this.intersectionObserver.observe(photo);
        });
    }

    setupScrollAnimations() {
        if (this.prefersReducedMotion) return;

        // Set up scroll-based reveal animations
        const options = {
            root: null,
            rootMargin: '-10% 0px -10% 0px', // Trigger when 10% into viewport
            threshold: 0,
        };

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.revealPhoto(entry.target);
                    revealObserver.unobserve(entry.target);
                }
            });
        }, options);

        this.photoTargets.forEach(photo => {
            // Initially hidden for reveal animation
            photo.style.opacity = '0';
            photo.style.transform = 'translateY(30px)';
            revealObserver.observe(photo);
        });
    }

    loadImage(photo) {
        const img = photo.querySelector('img[data-src]');
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
                img.style.transition = 'opacity 0.6s ease-out';

                // Force reflow
                img.offsetHeight;

                img.style.opacity = '1';
            }
        };

        loader.onerror = () => {
            console.error(`Failed to load image: ${src}`);
        };

        loader.src = src;
    }

    revealPhoto(photo) {
        if (this.prefersReducedMotion) {
            photo.style.opacity = '1';
            photo.style.transform = 'none';
            return;
        }

        // Smooth reveal animation
        photo.style.transition = 'opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)';

        // Use setTimeout to ensure transition applies
        setTimeout(() => {
            photo.style.opacity = '1';
            photo.style.transform = 'translateY(0)';
        }, 50);
    }

    // Public method to force reload all images (can be called from other controllers)
    reloadImages() {
        this.photoTargets.forEach(photo => this.loadImage(photo));
    }
}
