import { Controller } from '@hotwired/stimulus';

/**
 * Navigation Scroll Controller
 * Implements smart hide/show behavior: hides on scroll down, reveals on scroll up
 *
 * Behavior:
 * - Hides navigation when scrolling down past threshold
 * - Shows navigation when scrolling up
 * - Economy of motion: navigation only appears when needed
 * - Smooth cubic-bezier transition (300ms)
 */
export default class extends Controller {
  static values = {
    threshold: { type: Number, default: 100 }
  };

  connect() {
    this.lastScrollY = window.scrollY;
    this.ticking = false;

    // Bind scroll handler
    this.handleScroll = this.handleScroll.bind(this);
    this.update = this.update.bind(this);

    // Listen to scroll events
    window.addEventListener('scroll', this.handleScroll, { passive: true });

    // Initial state
    this.update();
  }

  disconnect() {
    window.removeEventListener('scroll', this.handleScroll);
  }

  /**
   * Handle scroll event with requestAnimationFrame for performance
   */
  handleScroll() {
    if (!this.ticking) {
      window.requestAnimationFrame(this.update);
      this.ticking = true;
    }
  }

  /**
   * Update navigation visibility based on scroll position
   */
  update() {
    const currentScrollY = window.scrollY;

    // Determine scroll direction
    const scrollingDown = currentScrollY > this.lastScrollY;
    const scrollingUp = currentScrollY < this.lastScrollY;

    // Hide when scrolling down past threshold
    if (scrollingDown && currentScrollY > this.thresholdValue) {
      this.hide();
    }
    // Show when scrolling up or near top
    else if (scrollingUp || currentScrollY <= this.thresholdValue) {
      this.show();
    }

    // Update last scroll position
    this.lastScrollY = currentScrollY;
    this.ticking = false;
  }

  /**
   * Hide navigation (slide up out of view)
   */
  hide() {
    this.element.classList.add('nav-hidden');
    this.element.classList.remove('nav-visible');
  }

  /**
   * Show navigation (slide down into view)
   */
  show() {
    this.element.classList.remove('nav-hidden');
    this.element.classList.add('nav-visible');
  }
}
