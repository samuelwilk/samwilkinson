import { Controller } from '@hotwired/stimulus';

/**
 * Manages photo selection and batch download for authenticated collections.
 *
 * Handles:
 * - Individual photo checkbox selection
 * - Select all/deselect all toggle
 * - Selected count display
 * - Download button state management
 * - Batch photo downloads
 */
export default class extends Controller {
    static targets = ['photoCheckbox', 'selectAllButton', 'downloadButton', 'selectedCount'];
    static values = {
        collectionSlug: String
    };

    connect() {
        this.updateUI();
    }

    /**
     * Handle individual checkbox state changes
     */
    updateSelection() {
        this.updateUI();
    }

    /**
     * Toggle select all/deselect all based on current state
     */
    toggleSelectAll() {
        const allSelected = this.allCheckboxesSelected();

        this.photoCheckboxTargets.forEach(checkbox => {
            checkbox.checked = !allSelected;
        });

        this.updateUI();
    }

    /**
     * Download all selected photos
     */
    downloadSelected() {
        const selectedPhotos = this.getSelectedPhotoIds();

        if (selectedPhotos.length === 0) {
            return;
        }

        // Download each photo by creating hidden iframes
        // This allows multiple downloads without popup blockers
        selectedPhotos.forEach((photoId, index) => {
            setTimeout(() => {
                this.downloadPhoto(photoId);
            }, index * 100); // Stagger downloads by 100ms
        });
    }

    /**
     * Trigger download for a single photo
     * @param {number} photoId
     */
    downloadPhoto(photoId) {
        const url = `/stills/albums/${this.collectionSlugValue}/photos/${photoId}/download`;

        // Create hidden iframe to trigger download
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);

        // Remove iframe after download starts
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000);
    }

    /**
     * Update UI elements based on selection state
     */
    updateUI() {
        const selectedCount = this.getSelectedPhotoIds().length;
        const allSelected = this.allCheckboxesSelected();

        // Update selected count text
        if (this.hasSelectedCountTarget) {
            this.selectedCountTarget.textContent = selectedCount === 1
                ? '1 photo selected'
                : `${selectedCount} photos selected`;
        }

        // Update download button state
        if (this.hasDownloadButtonTarget) {
            this.downloadButtonTarget.disabled = selectedCount === 0;
        }

        // Update select all button text
        if (this.hasSelectAllButtonTarget) {
            this.selectAllButtonTarget.textContent = allSelected ? 'Deselect All' : 'Select All';
        }
    }

    /**
     * Get array of selected photo IDs
     * @returns {number[]}
     */
    getSelectedPhotoIds() {
        return this.photoCheckboxTargets
            .filter(checkbox => checkbox.checked)
            .map(checkbox => parseInt(checkbox.dataset.photoId, 10));
    }

    /**
     * Check if all checkboxes are selected
     * @returns {boolean}
     */
    allCheckboxesSelected() {
        if (this.photoCheckboxTargets.length === 0) {
            return false;
        }
        return this.photoCheckboxTargets.every(checkbox => checkbox.checked);
    }
}
