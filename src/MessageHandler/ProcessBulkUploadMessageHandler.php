<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Collection;
use App\Message\ProcessBulkUploadMessage;
use App\Repository\CollectionRepository;
use App\Service\PhotoUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Processes bulk photo uploads asynchronously.
 *
 * Handles photo uploads in a background worker, allowing the API
 * endpoint to return immediately without blocking.
 */
#[AsMessageHandler]
class ProcessBulkUploadMessageHandler
{
    public function __construct(
        private readonly PhotoUploadService $photoUploadService,
        private readonly CollectionRepository $collectionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ProcessBulkUploadMessage $message): void
    {
        // Load or create collection
        if ($message->getCollectionId() !== null) {
            $collection = $this->collectionRepository->find($message->getCollectionId());

            if ($collection === null) {
                throw new \RuntimeException(sprintf(
                    'Collection with ID %d not found',
                    $message->getCollectionId()
                ));
            }
        } else {
            // Create new collection from photo metadata
            $collection = $this->createCollection($message);
        }

        // Convert file paths to UploadedFile objects
        $files = [];
        foreach ($message->getFilePaths() as $filePath) {
            if (!file_exists($filePath)) {
                // Skip missing files, they may have been cleaned up
                continue;
            }

            $files[] = new UploadedFile(
                $filePath,
                basename($filePath),
                null,
                null,
                true // test mode to accept any file path
            );
        }

        if (empty($files)) {
            throw new \RuntimeException('No valid files found for processing');
        }

        // Process photos
        $stats = $this->photoUploadService->processPhotos(
            $files,
            $collection,
            $message->getPublishStatus()
        );

        // Log results (in production, this could trigger a notification)
        $totalFiles = count($message->getFilePaths());
        if ($stats['failed'] > 0) {
            throw new \RuntimeException(sprintf(
                'Bulk upload completed with errors: %d processed, %d duplicates skipped, %d failed out of %d total',
                $stats['processed'],
                $stats['duplicates_skipped'],
                $stats['failed'],
                $totalFiles
            ));
        }

        // Success - could trigger a success notification here
    }

    private function createCollection(ProcessBulkUploadMessage $message): Collection
    {
        $files = [];
        foreach ($message->getFilePaths() as $filePath) {
            if (file_exists($filePath)) {
                $files[] = new UploadedFile(
                    $filePath,
                    basename($filePath),
                    null,
                    null,
                    true
                );
            }
        }

        $collectionName = $message->getCollectionName() ?? 'Untitled Collection';
        $collection = $this->photoUploadService->createCollectionFromPhotos($collectionName, $files);

        $this->entityManager->persist($collection);
        $this->entityManager->flush();

        return $collection;
    }
}
