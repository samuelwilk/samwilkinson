import { Controller } from '@hotwired/stimulus';

/*
 * Photo lightbox controller for enlarged image viewing in grid view
 * Features:
 * - Modal with enlarged photo
 * - Navigation between photos (prev/next)
 * - Keyboard navigation (arrow keys, Escape)
 * - Swipe gestures for mobile
 * - Display EXIF metadata
 */
export default class extends Controller {
    static targets = ['modal', 'image', 'title', 'exif', 'counter'];
    static values = {
        photos: Array,
        currentIndex: Number
    };

    connect() {
        this.touchStartX = 0;
        this.touchEndX = 0;
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.navbar = document.querySelector('.site-nav');
    }

    disconnect() {
        this.removeKeyboardListener();
    }

    open(event) {
        const photoIndex = parseInt(event.currentTarget.dataset.photoIndex);
        this.currentIndexValue = photoIndex;
        this.showPhoto(photoIndex);
        this.modalTarget.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Hide navbar
        if (this.navbar) {
            this.navbar.style.opacity = '0';
            this.navbar.style.pointerEvents = 'none';
            this.navbar.style.transition = 'opacity 200ms ease-out';
        }

        this.addKeyboardListener();
    }

    close() {
        this.modalTarget.classList.add('hidden');
        document.body.style.overflow = '';

        // Show navbar
        if (this.navbar) {
            this.navbar.style.opacity = '1';
            this.navbar.style.pointerEvents = 'auto';
        }

        this.removeKeyboardListener();
    }

    closeOnBackdrop(event) {
        // Close if clicking on modal background, but not on image, buttons, or info overlays
        const clickedElement = event.target;
        const isImage = clickedElement.tagName === 'IMG';
        const isButton = clickedElement.closest('button');
        const isOverlay = clickedElement.closest('[data-photo-lightbox-target="title"]') ||
                         clickedElement.closest('[data-photo-lightbox-target="exif"]') ||
                         clickedElement.closest('[data-photo-lightbox-target="counter"]');

        if (!isImage && !isButton && !isOverlay) {
            this.close();
        }
    }

    showPhoto(index) {
        const photo = this.photosValue[index];
        if (!photo) return;

        // Update image
        this.imageTarget.src = photo.url;
        this.imageTarget.alt = photo.title || '';

        // Update title
        if (photo.title) {
            this.titleTarget.textContent = photo.title;
            this.titleTarget.classList.remove('hidden');
        } else {
            this.titleTarget.classList.add('hidden');
        }

        // Update EXIF data - Museum label format with proper spacing
        const exifParts = [];
        if (photo.focalLength) exifParts.push(photo.focalLength);
        if (photo.aperture) exifParts.push(photo.aperture);
        if (photo.shutterSpeed) exifParts.push(photo.shutterSpeed);
        if (photo.iso) exifParts.push(`ISO ${photo.iso}`);
        if (photo.exposureCompensation) exifParts.push(photo.exposureCompensation);

        if (exifParts.length > 0) {
            // Use thin space (U+2009) for refined spacing between values
            this.exifTarget.textContent = exifParts.join('  •  ');
            this.exifTarget.classList.remove('hidden');
        } else {
            this.exifTarget.classList.add('hidden');
        }

        // Update counter - Refined format with proper spacing
        const current = String(index + 1).padStart(2, '0');
        const total = String(this.photosValue.length).padStart(2, '0');
        this.counterTarget.textContent = `${current} / ${total}`;

        this.currentIndexValue = index;
    }

    prev() {
        let newIndex = this.currentIndexValue - 1;
        if (newIndex < 0) {
            newIndex = this.photosValue.length - 1;
        }
        this.showPhoto(newIndex);
    }

    next() {
        let newIndex = this.currentIndexValue + 1;
        if (newIndex >= this.photosValue.length) {
            newIndex = 0;
        }
        this.showPhoto(newIndex);
    }

    addKeyboardListener() {
        this.handleKeydown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                this.prev();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                this.next();
            }
        };

        document.addEventListener('keydown', this.handleKeydown);
    }

    removeKeyboardListener() {
        if (this.handleKeydown) {
            document.removeEventListener('keydown', this.handleKeydown);
        }
    }

    // Swipe gesture handling
    touchStart(event) {
        this.touchStartX = event.changedTouches[0].screenX;
    }

    touchEnd(event) {
        this.touchEndX = event.changedTouches[0].screenX;
        this.handleSwipe();
    }

    handleSwipe() {
        const swipeThreshold = 50; // Minimum distance for swipe
        const diff = this.touchStartX - this.touchEndX;

        if (Math.abs(diff) < swipeThreshold) {
            return; // Not a significant swipe
        }

        if (diff > 0) {
            // Swiped left - show next photo
            this.next();
        } else {
            // Swiped right - show previous photo
            this.prev();
        }
    }
}
