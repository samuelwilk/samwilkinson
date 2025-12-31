<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Collection;
use App\Entity\Photo;
use App\Enum\PublishStatus;
use App\Repository\CollectionRepository;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Orchestrates bulk photo upload processing.
 *
 * Handles:
 * - Duplicate detection via file hashing
 * - EXIF metadata extraction
 * - GPS coordinate reverse geocoding
 * - Collection metadata aggregation
 * - Photo entity creation and persistence
 */
class PhotoUploadService
{
    public function __construct(
        private readonly PhotoRepository $photoRepository,
        private readonly CollectionRepository $collectionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExifExtractorService $exifExtractor,
        private readonly GeocodingService $geocodingService,
        private readonly CollectionMetadataAggregator $metadataAggregator,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Process uploaded photos and add them to a collection.
     *
     * @param UploadedFile[] $files The uploaded photo files
     * @param Collection $collection The target collection
     * @param PublishStatus $publishStatus Whether to publish or save as draft
     * @return array{
     *     processed: int,
     *     duplicates_skipped: int,
     *     failed: int
     * }
     */
    public function processPhotos(array $files, Collection $collection, PublishStatus $publishStatus): array
    {
        $stats = [
            'processed' => 0,
            'duplicates_skipped' => 0,
            'failed' => 0,
        ];

        foreach ($files as $file) {
            try {
                // Calculate file hash for duplicate detection
                $fileHash = $this->exifExtractor->calculateFileHash($file);

                // Check if photo already exists
                $existingPhoto = $this->photoRepository->findOneBy(['fileHash' => $fileHash]);
                if ($existingPhoto !== null) {
                    $stats['duplicates_skipped']++;
                    continue;
                }

                // Extract EXIF metadata
                $exifData = $this->exifExtractor->extract($file);

                // Create Photo entity
                $photo = new Photo();
                $photo->setCollection($collection);
                $photo->setFileHash($fileHash);
                $photo->setIsPublished($publishStatus->isPublic());

                // Set image file (VichUploader will handle the actual upload)
                $photo->setImageFile($file);
                $photo->setFilename($file->getClientOriginalName());

                // Set EXIF metadata
                $photo->setExifData($exifData['raw']);

                if ($exifData['takenAt']) {
                    $photo->setTakenAt($exifData['takenAt']);
                }

                if ($exifData['width'] && $exifData['height']) {
                    $photo->setWidth($exifData['width']);
                    $photo->setHeight($exifData['height']);
                    $photo->calculateAspectRatio();
                }

                // Set formatted EXIF values
                if ($exifData['iso']) {
                    $photo->setIso($exifData['iso']);
                }
                if ($exifData['focalLength']) {
                    $photo->setFocalLength($exifData['focalLength']);
                }
                if ($exifData['aperture']) {
                    $photo->setAperture($exifData['aperture']);
                }
                if ($exifData['shutterSpeed']) {
                    $photo->setShutterSpeed($exifData['shutterSpeed']);
                }
                if ($exifData['exposureCompensation']) {
                    $photo->setExposureCompensation($exifData['exposureCompensation']);
                }

                // Reverse geocode GPS coordinates if available
                if ($exifData['latitude'] && $exifData['longitude']) {
                    $geocodeData = $this->geocodingService->reverseGeocode(
                        $exifData['latitude'],
                        $exifData['longitude']
                    );

                    // Generate title from location if not set
                    if (!$photo->getTitle() && $geocodeData['displayName']) {
                        $locationName = $this->geocodingService->extractLocationName($geocodeData);
                        if ($locationName) {
                            $photo->setTitle($locationName);
                        }
                    }

                    // Update collection location if first photo with GPS data
                    if (!$collection->getLocationName() && $geocodeData['city']) {
                        $locationName = $this->geocodingService->extractLocationName($geocodeData);
                        $collection->setLocationName($locationName);
                        $collection->setCountry($geocodeData['country']);
                    }
                }

                $this->entityManager->persist($photo);
                $stats['processed']++;

                // Flush every 10 photos to avoid memory issues
                if ($stats['processed'] % 10 === 0) {
                    $this->entityManager->flush();
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                // Continue processing other photos
                continue;
            }
        }

        // Final flush for remaining photos
        $this->entityManager->flush();

        // Aggregate collection metadata
        $this->metadataAggregator->aggregateMetadata($collection);
        $this->entityManager->flush();

        return $stats;
    }

    /**
     * Create a new collection from photo metadata.
     *
     * Extracts location and date information from the first photo
     * to populate collection fields.
     *
     * @param string $name The collection name
     * @param UploadedFile[] $files The photos to analyze
     * @return Collection
     */
    public function createCollectionFromPhotos(string $name, array $files): Collection
    {
        $collection = new Collection();
        $collection->setName($name);
        $collection->setSlug($this->generateUniqueSlug($name));

        // Extract metadata from first photo
        if (!empty($files)) {
            $firstFile = $files[0];
            $exifData = $this->exifExtractor->extract($firstFile);

            // Set collection date from first photo
            if ($exifData['takenAt']) {
                $collection->setStartDate($exifData['takenAt']);
                $collection->setEndDate($exifData['takenAt']);
            }

            // Geocode location from first photo
            if ($exifData['latitude'] && $exifData['longitude']) {
                $geocodeData = $this->geocodingService->reverseGeocode(
                    $exifData['latitude'],
                    $exifData['longitude']
                );

                $locationName = $this->geocodingService->extractLocationName($geocodeData);
                $collection->setLocationName($locationName);
                $collection->setCountry($geocodeData['country']);

                // Suggest better collection name if not provided
                if ($name === 'Untitled Collection') {
                    $suggested = $this->metadataAggregator->suggestCollectionName(
                        $locationName,
                        $exifData['takenAt']
                    );

                    if ($suggested) {
                        $collection->setName($suggested);
                        $collection->setSlug($this->generateUniqueSlug($suggested));
                    }
                }
            }
        }

        return $collection;
    }

    /**
     * Generate a unique slug for a collection.
     * If a collection with the base slug exists, appends a number.
     */
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = $this->slugger->slug($name)->lower()->toString();
        $slug = $baseSlug;
        $counter = 1;

        // Keep trying until we find a unique slug
        while ($this->collectionRepository->findOneBy(['slug' => $slug]) !== null) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
