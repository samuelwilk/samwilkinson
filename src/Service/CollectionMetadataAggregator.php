<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Collection;
use App\Entity\Photo;

/**
 * Aggregates metadata across photos in a collection.
 *
 * Automatically determines collection-level metadata (date range,
 * location) from the photos it contains.
 */
class CollectionMetadataAggregator
{
    /**
     * Update collection metadata based on its photos.
     *
     * Sets:
     * - startDate: Earliest photo takenAt date
     * - endDate: Latest photo takenAt date
     * - locationName: Most common location from photos (if available)
     * - country: Most common country from photos (if available)
     *
     * @param Collection $collection The collection to update
     */
    public function aggregateMetadata(Collection $collection): void
    {
        $photos = $collection->getPhotos()->toArray();

        if (empty($photos)) {
            return;
        }

        // Aggregate dates
        $this->aggregateDates($collection, $photos);

        // Aggregate locations (from EXIF GPS data if available)
        // For now, we'll skip this since we don't have direct access to
        // geocoded location data in the Photo entity yet.
        // This can be enhanced later when photos store location metadata.
    }

    /**
     * Aggregate date range from photos.
     *
     * @param Collection $collection
     * @param Photo[] $photos
     */
    private function aggregateDates(Collection $collection, array $photos): void
    {
        $dates = [];

        foreach ($photos as $photo) {
            if ($photo->getTakenAt()) {
                $dates[] = $photo->getTakenAt();
            }
        }

        if (empty($dates)) {
            return;
        }

        // Sort dates to find min and max
        usort($dates, fn($a, $b) => $a <=> $b);

        $startDate = reset($dates);
        $endDate = end($dates);

        // Only update if not already set or if we found a wider range
        if (!$collection->getStartDate() || $startDate < $collection->getStartDate()) {
            $collection->setStartDate($startDate);
        }

        if (!$collection->getEndDate() || $endDate > $collection->getEndDate()) {
            $collection->setEndDate($endDate);
        }
    }

    /**
     * Suggest a collection name based on location and date.
     *
     * @param string|null $locationName The location name from geocoding
     * @param \DateTimeInterface|null $startDate The start date
     * @return string|null A suggested collection name
     */
    public function suggestCollectionName(?string $locationName, ?\DateTimeInterface $startDate): ?string
    {
        if (!$locationName && !$startDate) {
            return null;
        }

        $parts = [];

        if ($locationName) {
            $parts[] = $locationName;
        }

        if ($startDate) {
            $parts[] = $startDate->format('Y');
        }

        return implode(' ', $parts);
    }
}
