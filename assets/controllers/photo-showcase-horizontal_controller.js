import { Controller } from '@hotwired/stimulus';

/*
 * Horizontal scroll photo showcase controller
 * Creates unique deterministic layouts per collection using seeded randomization
 * Inspired by virtual gallery aesthetic with overlapping photos and prairie line positioning
 */

// Simple hash function to convert string to seed number
function hashCode(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash = hash & hash; // Convert to 32-bit integer
    }
    return Math.abs(hash);
}

// Seeded pseudo-random number generator (Linear Congruential Generator)
class SeededRandom {
    constructor(seed) {
        this.seed = seed % 2147483647;
        if (this.seed <= 0) this.seed += 2147483646;
    }

    next() {
        this.seed = (this.seed * 16807) % 2147483647;
        return (this.seed - 1) / 2147483646;
    }
}

export default class extends Controller {
    static targets = ['photo'];
    static values = {
        collectionSlug: String
    };

    connect() {
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.intersectionObserver = null;
        this.imageObserver = null;
        this.currentPhotoIndex = 0;

        // Disable scrollbars
        this.element.style.scrollbarWidth = 'none'; // Firefox
        this.element.style.msOverflowStyle = 'none'; // IE/Edge

        // Initialize seeded random generator
        this.random = new SeededRandom(hashCode(this.collectionSlugValue));

        // Generate layout
        this.generateLayout();

        // Set up observers
        this.setupScrollObserver();
        this.setupLazyLoading();
        this.setupKeyboardNavigation();
        this.setupHorizontalScroll();
        this.setupTouchScroll(); // Add touch handling for mobile

        // Set up live region for screen readers
        this.setupLiveRegion();
    }

    disconnect() {
        if (this.intersectionObserver) {
            this.intersectionObserver.disconnect();
        }
        if (this.imageObserver) {
            this.imageObserver.disconnect();
        }
        if (this.handleWheel) {
            window.removeEventListener('wheel', this.handleWheel);
        }
        if (this.handleKeydown) {
            document.removeEventListener('keydown', this.handleKeydown);
        }
        if (this.handleTouchStart) {
            this.element.removeEventListener('touchstart', this.handleTouchStart);
        }
        if (this.handleTouchMove) {
            this.element.removeEventListener('touchmove', this.handleTouchMove);
        }
        if (this.handleTouchEnd) {
            this.element.removeEventListener('touchend', this.handleTouchEnd);
        }
    }

    generateLayout() {
        const sizes = [
            { height: 50, name: 'small' },
            { height: 65, name: 'medium' },
            { height: 80, name: 'large' }
        ];
        const tracks = [20, 45, 65]; // Prairie line positions (%) - adjusted to prevent label clipping

        // Calculate left offset to align with page content
        // Find the header container to match its left position
        const headerContainer = document.querySelector('.max-w-7xl');
        let leftOffset = 48; // fallback
        if (headerContainer) {
            const rect = headerContainer.getBoundingClientRect();
            const computedStyle = window.getComputedStyle(headerContainer);
            const paddingLeft = parseFloat(computedStyle.paddingLeft);
            leftOffset = rect.left + paddingLeft;
        }

        let cumulativeLeft = leftOffset;

        this.photoTargets.forEach((photo, index) => {
            const aspectRatio = parseFloat(photo.dataset.aspectRatio) || 1.5;

            // Generate deterministic properties from seeded random
            const sizeIndex = Math.floor(this.random.next() * sizes.length);
            const size = sizes[sizeIndex];
            const heightVh = size.height;

            const trackIndex = Math.floor(this.random.next() * tracks.length);
            const trackPosition = tracks[trackIndex];

            const overlap = 50 + Math.floor(this.random.next() * 100); // 50-150px
            const zIndexOffset = Math.floor(this.random.next() * 100);

            // Calculate dimensions
            const heightPx = (heightVh / 100) * window.innerHeight;
            const widthPx = heightPx * aspectRatio;

            // Calculate position
            const left = cumulativeLeft;
            const top = `${trackPosition}%`;
            const zIndex = (index * 10) + zIndexOffset;

            // Apply styles
            photo.style.position = 'absolute';
            photo.style.left = `${left}px`;
            photo.style.top = top;
            photo.style.transform = `translateY(-50%)`; // Center on track
            photo.style.width = `${widthPx}px`;
            photo.style.height = `${heightPx}px`;
            photo.style.zIndex = zIndex;
            photo.style.transition = this.prefersReducedMotion
                ? 'opacity 0s'
                : 'opacity 800ms cubic-bezier(0.4, 0, 0.2, 1), transform 800ms cubic-bezier(0.4, 0, 0.2, 1)';

            // Store data for later
            photo.dataset.photoIndex = index;
            photo.dataset.leftPosition = left;
            photo.dataset.trackPosition = trackPosition;

            // Initially hidden for reveal animation
            photo.style.opacity = '0';
            if (!this.prefersReducedMotion) {
                photo.style.transform = `translateY(-50%) translateX(100px)`;
            }

            // Update cumulative position (with overlap)
            cumulativeLeft += widthPx - overlap;
        });

        // Set container width to accommodate all photos
        const totalWidth = cumulativeLeft + 400; // Add padding at end
        const innerContainer = this.element.querySelector('div[style*="height: 110vh"]');
        if (innerContainer) {
            innerContainer.style.width = `${totalWidth}px`;
        }

        // Trigger initial reveal for photos in viewport
        setTimeout(() => {
            this.photoTargets.forEach((photo, index) => {
                if (index < 3) { // Show first few photos immediately
                    photo.style.opacity = '1';
                    photo.style.transform = `translateY(-50%) translateX(0)`;
                }
            });
        }, 100);
    }

    setupScrollObserver() {
        const options = {
            root: this.element,
            rootMargin: '0px 200px 0px 200px', // Start transition before visible
            threshold: [0, 0.25, 0.5, 0.75, 1.0]
        };

        this.intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const photo = entry.target;
                const trackPosition = photo.dataset.trackPosition;

                if (entry.isIntersecting) {
                    // Photo entering view
                    photo.style.opacity = '1';
                    if (!this.prefersReducedMotion) {
                        photo.style.transform = `translateY(-50%) translateX(0)`;
                    } else {
                        photo.style.transform = `translateY(-50%)`;
                    }

                    // Update current photo index for keyboard nav
                    if (entry.intersectionRatio > 0.5) {
                        this.currentPhotoIndex = parseInt(photo.dataset.photoIndex);
                    }
                } else {
                    // Photo exiting view - only hide if it's ahead (to the right)
                    if (entry.boundingClientRect.left > entry.rootBounds.right) {
                        photo.style.opacity = '0';
                        if (!this.prefersReducedMotion) {
                            photo.style.transform = `translateY(-50%) translateX(100px)`;
                        }
                    }
                }
            });
        }, options);

        this.photoTargets.forEach(photo => {
            this.intersectionObserver.observe(photo);
        });
    }

    setupLazyLoading() {
        if (!window.IntersectionObserver) {
            // Fallback: load all images immediately
            this.photoTargets.forEach(photo => this.loadImage(photo));
            return;
        }

        const options = {
            root: this.element,
            rootMargin: '200px', // Load before visible
            threshold: 0.01
        };

        this.imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    this.imageObserver.unobserve(entry.target);
                }
            });
        }, options);

        this.photoTargets.forEach(photo => {
            this.imageObserver.observe(photo);
        });
    }

    loadImage(photo) {
        const img = photo.querySelector('img[data-src]');
        if (!img) return;

        const src = img.dataset.src;
        if (!src) return;

        const loader = new Image();
        loader.onload = () => {
            img.src = src;
            img.removeAttribute('data-src');

            // Fade in image
            if (!this.prefersReducedMotion) {
                img.style.opacity = '0';
                img.style.transition = 'opacity 600ms ease-out';

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

    setupKeyboardNavigation() {
        this.handleKeydown = (event) => {
            // Don't interfere with typing in inputs
            if (event.target.matches('input, textarea, select')) {
                return;
            }

            const rect = this.element.getBoundingClientRect();
            const showcaseAtTop = rect.top <= 0;

            // Activate gallery when scrolled to it
            if (showcaseAtTop && !this.isGalleryActive) {
                this.isGalleryActive = true;
            }

            // Handle arrow keys for horizontal gallery scroll when showcase is active
            if (this.isGalleryActive && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                const scrollLeft = this.element.scrollLeft;
                const maxScrollLeft = this.element.scrollWidth - this.element.clientWidth;
                const atStart = scrollLeft <= 1;
                const atEnd = scrollLeft >= maxScrollLeft - 1;

                if (event.key === 'ArrowDown') {
                    if (!atEnd) {
                        event.preventDefault();
                        this.element.scrollLeft += 150; // Scroll right
                    }
                    // At end, allow page scroll down
                } else if (event.key === 'ArrowUp') {
                    if (!atStart) {
                        event.preventDefault();
                        this.element.scrollLeft -= 150; // Scroll left
                    } else {
                        // At start - deactivate gallery for page scroll up
                        this.isGalleryActive = false;
                    }
                }
                return;
            }

            switch (event.key) {
                case 'ArrowRight':
                    event.preventDefault();
                    this.navigateToPhoto(this.currentPhotoIndex + 1);
                    break;
                case 'ArrowLeft':
                    event.preventDefault();
                    this.navigateToPhoto(this.currentPhotoIndex - 1);
                    break;
                case 'Home':
                    event.preventDefault();
                    this.navigateToPhoto(0);
                    break;
                case 'End':
                    event.preventDefault();
                    this.navigateToPhoto(this.photoTargets.length - 1);
                    break;
            }
        };

        document.addEventListener('keydown', this.handleKeydown);

        // Set up focus management
        this.photoTargets.forEach((photo, index) => {
            photo.setAttribute('tabindex', '0');
            photo.setAttribute('role', 'img');

            const img = photo.querySelector('img');
            const alt = img?.alt || `Photo ${index + 1}`;
            photo.setAttribute('aria-label', `Photo ${index + 1} of ${this.photoTargets.length}: ${alt}`);

            photo.addEventListener('focus', () => {
                this.scrollToPhoto(index);
                this.currentPhotoIndex = index;
                this.announcePhoto(index);
            });
        });
    }

    navigateToPhoto(index) {
        // Bounds check
        if (index < 0 || index >= this.photoTargets.length) {
            return;
        }

        this.currentPhotoIndex = index;
        this.scrollToPhoto(index);
        this.photoTargets[index].focus();
        this.announcePhoto(index);
    }

    scrollToPhoto(index) {
        const photo = this.photoTargets[index];
        if (!photo) return;

        const left = parseInt(photo.dataset.leftPosition);
        const photoWidth = photo.offsetWidth;
        const containerWidth = this.element.offsetWidth;

        // Center the photo in viewport
        const scrollLeft = left - (containerWidth / 2) + (photoWidth / 2);

        this.element.scrollTo({
            left: scrollLeft,
            behavior: 'smooth'
        });
    }

    setupHorizontalScroll() {
        this.isGalleryActive = false;

        // Convert vertical wheel scroll to horizontal scroll when user has scrolled to showcase
        this.handleWheel = (event) => {
            // Only handle vertical scroll
            if (Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
                const rect = this.element.getBoundingClientRect();

                // Check if showcase top has reached or passed the top of viewport
                const showcaseAtTop = rect.top <= 0;

                // Check horizontal scroll boundaries
                const scrollLeft = this.element.scrollLeft;
                const maxScrollLeft = this.element.scrollWidth - this.element.clientWidth;
                const atStart = scrollLeft <= 1;
                const atEnd = scrollLeft >= maxScrollLeft - 1;

                // Multiplier for faster horizontal scroll
                const scrollMultiplier = 1.125;

                // Activate gallery when scrolled to it
                if (showcaseAtTop && !this.isGalleryActive) {
                    this.isGalleryActive = true;
                }

                // Gallery is active - capture ALL scroll until user scrolls all the way back to start
                if (this.isGalleryActive) {
                    // Scrolling down (positive deltaY)
                    if (event.deltaY > 0) {
                        if (!atEnd) {
                            // Still have horizontal scroll room, convert to horizontal
                            event.preventDefault();
                            this.element.scrollLeft += event.deltaY * scrollMultiplier;
                        } else {
                            // At end - allow page scroll to continue below
                            // Gallery stays active though
                        }
                    }
                    // Scrolling up (negative deltaY)
                    else if (event.deltaY < 0) {
                        if (!atStart) {
                            // Still scrolling through gallery - capture scroll
                            event.preventDefault();
                            this.element.scrollLeft += event.deltaY * scrollMultiplier;
                        } else {
                            // At start of gallery - deactivate and allow page scroll up
                            this.isGalleryActive = false;
                        }
                    }
                }
            }
        };

        window.addEventListener('wheel', this.handleWheel, { passive: false });
    }

    setupTouchScroll() {
        // Touch state
        this.touchState = {
            startX: 0,
            startY: 0,
            startScrollLeft: 0,
            lastX: 0,
            lastY: 0,
            lastTime: 0,
            velocityX: 0,
            isScrolling: false,
            scrollingHorizontally: null // null = not determined yet, true/false = determined
        };

        this.handleTouchStart = (event) => {
            const touch = event.touches[0];
            this.touchState.startX = touch.clientX;
            this.touchState.startY = touch.clientY;
            this.touchState.startScrollLeft = this.element.scrollLeft;
            this.touchState.lastX = touch.clientX;
            this.touchState.lastY = touch.clientY;
            this.touchState.lastTime = Date.now();
            this.touchState.velocityX = 0;
            this.touchState.isScrolling = false;
            this.touchState.scrollingHorizontally = null;
        };

        this.handleTouchMove = (event) => {
            if (!event.touches.length) return;

            const touch = event.touches[0];
            const deltaX = touch.clientX - this.touchState.startX;
            const deltaY = touch.clientY - this.touchState.startY;
            const absDeltaX = Math.abs(deltaX);
            const absDeltaY = Math.abs(deltaY);

            // Determine scroll direction on first significant movement
            if (this.touchState.scrollingHorizontally === null && (absDeltaX > 10 || absDeltaY > 10)) {
                this.touchState.scrollingHorizontally = absDeltaX > absDeltaY;
            }

            // If we've determined this is a horizontal scroll
            if (this.touchState.scrollingHorizontally) {
                event.preventDefault(); // Prevent vertical scroll

                const scrollLeft = this.element.scrollLeft;
                const maxScrollLeft = this.element.scrollWidth - this.element.clientWidth;
                const atStart = scrollLeft <= 1;
                const atEnd = scrollLeft >= maxScrollLeft - 1;

                // Check if showcase is at top of viewport
                const rect = this.element.getBoundingClientRect();
                const showcaseAtTop = rect.top <= 0;

                // Activate gallery when scrolled to it
                if (showcaseAtTop && !this.isGalleryActive) {
                    this.isGalleryActive = true;
                }

                // Only handle horizontal scrolling if gallery is active
                if (this.isGalleryActive) {
                    // Calculate velocity for momentum
                    const currentTime = Date.now();
                    const timeDelta = currentTime - this.touchState.lastTime;
                    if (timeDelta > 0) {
                        const moveDelta = touch.clientX - this.touchState.lastX;
                        this.touchState.velocityX = moveDelta / timeDelta;
                    }

                    // Apply horizontal scroll (inverted because dragging right should scroll left)
                    const newScrollLeft = this.touchState.startScrollLeft - deltaX;

                    // Allow scrolling but let browser handle edge behavior
                    this.element.scrollLeft = newScrollLeft;

                    this.touchState.isScrolling = true;
                }

                this.touchState.lastX = touch.clientX;
                this.touchState.lastY = touch.clientY;
                this.touchState.lastTime = Date.now();
            }
            // If vertical scroll, allow default behavior
        };

        this.handleTouchEnd = (event) => {
            // Apply momentum scrolling if there was horizontal movement
            if (this.touchState.scrollingHorizontally && this.touchState.isScrolling) {
                const momentumVelocity = this.touchState.velocityX * 200; // Scale velocity

                // Apply momentum with easing
                if (Math.abs(momentumVelocity) > 10) {
                    const targetScrollLeft = this.element.scrollLeft - momentumVelocity;
                    const maxScrollLeft = this.element.scrollWidth - this.element.clientWidth;
                    const clampedTargetScrollLeft = Math.max(0, Math.min(targetScrollLeft, maxScrollLeft));

                    this.element.scrollTo({
                        left: clampedTargetScrollLeft,
                        behavior: 'smooth'
                    });
                }
            }

            // Reset touch state
            this.touchState.isScrolling = false;
            this.touchState.scrollingHorizontally = null;
        };

        // Add touch event listeners
        this.element.addEventListener('touchstart', this.handleTouchStart, { passive: true });
        this.element.addEventListener('touchmove', this.handleTouchMove, { passive: false });
        this.element.addEventListener('touchend', this.handleTouchEnd, { passive: true });
    }

    setupLiveRegion() {
        let liveRegion = document.getElementById('photo-showcase-announcement');

        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.id = 'photo-showcase-announcement';
            liveRegion.setAttribute('role', 'status');
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            liveRegion.className = 'sr-only';
            document.body.appendChild(liveRegion);
        }

        this.liveRegion = liveRegion;
    }

    announcePhoto(index) {
        if (!this.liveRegion) return;

        const photo = this.photoTargets[index];
        const img = photo.querySelector('img');
        const alt = img?.alt || `Photo ${index + 1}`;

        this.liveRegion.textContent = `Viewing photo ${index + 1} of ${this.photoTargets.length}: ${alt}`;
    }
}
