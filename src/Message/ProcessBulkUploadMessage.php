<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\PublishStatus;

/**
 * Message to trigger async bulk photo upload processing.
 *
 * Dispatched when a bulk upload request is received via the API.
 * Processed by a background worker to handle photo uploads asynchronously.
 */
class ProcessBulkUploadMessage
{
    /**
     * @param int $collectionId The ID of the collection to add photos to (null if creating new)
     * @param string|null $collectionName The name for a new collection (if collectionId is null)
     * @param string[] $filePaths Temporary file paths of uploaded photos
     * @param PublishStatus $publishStatus Whether to publish photos or save as drafts
     */
    public function __construct(
        private readonly ?int $collectionId,
        private readonly ?string $collectionName,
        private readonly array $filePaths,
        private readonly PublishStatus $publishStatus,
    ) {
    }

    public function getCollectionId(): ?int
    {
        return $this->collectionId;
    }

    public function getCollectionName(): ?string
    {
        return $this->collectionName;
    }

    /**
     * @return string[]
     */
    public function getFilePaths(): array
    {
        return $this->filePaths;
    }

    public function getPublishStatus(): PublishStatus
    {
        return $this->publishStatus;
    }
}
