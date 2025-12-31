<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Represents the publication status of a photo or collection.
 *
 * Used by the bulk upload API to determine if uploaded photos
 * should be immediately visible or saved as drafts for review.
 */
enum PublishStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    /**
     * Check if this status means the content is publicly visible.
     */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
