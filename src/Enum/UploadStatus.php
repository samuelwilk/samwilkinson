<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Represents the processing status of a bulk photo upload.
 *
 * Used to track the lifecycle of async photo processing through
 * the Symfony Messenger queue.
 */
enum UploadStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Check if this status represents a terminal state (done or failed).
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    /**
     * Check if this status represents a successful completion.
     */
    public function isSuccess(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Check if this status represents a failure state.
     */
    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
