import { Controller } from '@hotwired/stimulus';

/**
 * Mobile Menu Controller
 * Handles the architectural slide-in panel navigation for mobile devices
 *
 * Behavior:
 * - Panel slides in from right (80% width)
 * - Overlay dims remaining 20% of content
 * - Focus trapping within open menu
 * - Closes on overlay click, Esc key, or link click
 */
export default class extends Controller {
  static targets = ['panel', 'overlay', 'button', 'links'];

  connect() {
    this.focusableElements = [];
    this.previouslyFocusedElement = null;

    // Track screen size to prevent desktop activation
    this.isDesktop = window.matchMedia('(min-width: 769px)').matches;
    this.mediaQuery = window.matchMedia('(min-width: 769px)');
    this.mediaQuery.addEventListener('change', (e) => {
      this.isDesktop = e.matches;
      // Close menu if switching to desktop
      if (this.isDesktop && this.isOpen()) {
        this.close();
      }
    });

    // Bind methods for event listeners
    this.handleKeydown = this.handleKeydown.bind(this);
  }

  disconnect() {
    // Clean up event listeners
    document.removeEventListener('keydown', this.handleKeydown);
    this.enableBodyScroll();
  }

  /**
   * Toggle menu open/closed
   */
  toggle(event) {
    event?.preventDefault();

    if (this.isOpen()) {
      this.close();
    } else {
      this.open();
    }
  }

  /**
   * Open mobile menu with architectural slide animation
   */
  open() {
    // Prevent opening on desktop screens
    if (this.isDesktop) {
      return;
    }

    // Store currently focused element
    this.previouslyFocusedElement = document.activeElement;

    // Add open class to panel and overlay
    this.panelTarget.classList.add('open');
    this.overlayTarget.classList.add('open');

    // Update button aria-expanded
    this.buttonTarget.setAttribute('aria-expanded', 'true');

    // Disable body scroll
    this.disableBodyScroll();

    // Setup focus trapping
    this.setupFocusTrap();

    // Listen for Esc key
    document.addEventListener('keydown', this.handleKeydown);

    // Focus first link after animation completes
    setTimeout(() => {
      const firstLink = this.linksTarget.querySelector('.mobile-nav-link');
      firstLink?.focus();
    }, 300);
  }

  /**
   * Close mobile menu
   */
  close() {
    // Remove open classes
    this.panelTarget.classList.remove('open');
    this.overlayTarget.classList.remove('open');

    // Update button aria-expanded
    this.buttonTarget.setAttribute('aria-expanded', 'false');

    // Re-enable body scroll
    this.enableBodyScroll();

    // Remove keydown listener
    document.removeEventListener('keydown', this.handleKeydown);

    // Return focus to button
    if (this.previouslyFocusedElement) {
      this.previouslyFocusedElement.focus();
      this.previouslyFocusedElement = null;
    }
  }

  /**
   * Check if menu is currently open
   */
  isOpen() {
    return this.panelTarget.classList.contains('open');
  }

  /**
   * Handle keyboard events (Esc to close, Tab for focus trapping)
   */
  handleKeydown(event) {
    if (event.key === 'Escape') {
      this.close();
      return;
    }

    if (event.key === 'Tab') {
      this.handleTabKey(event);
    }
  }

  /**
   * Setup focus trapping within the menu
   */
  setupFocusTrap() {
    this.focusableElements = Array.from(
      this.panelTarget.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      )
    );
  }

  /**
   * Handle Tab key for focus trapping
   */
  handleTabKey(event) {
    if (this.focusableElements.length === 0) return;

    const firstElement = this.focusableElements[0];
    const lastElement = this.focusableElements[this.focusableElements.length - 1];

    // Shift + Tab on first element -> focus last
    if (event.shiftKey && document.activeElement === firstElement) {
      event.preventDefault();
      lastElement.focus();
    }
    // Tab on last element -> focus first
    else if (!event.shiftKey && document.activeElement === lastElement) {
      event.preventDefault();
      firstElement.focus();
    }
  }

  /**
   * Disable body scroll when menu is open
   */
  disableBodyScroll() {
    document.body.style.overflow = 'hidden';
    document.body.style.touchAction = 'none';
  }

  /**
   * Re-enable body scroll when menu closes
   */
  enableBodyScroll() {
    document.body.style.overflow = '';
    document.body.style.touchAction = '';
  }
}
