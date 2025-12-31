<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Collection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manages session-based authentication for password-protected photo collections.
 *
 * Stores unlocked collection IDs in session with 7-day expiry (configurable).
 */
final class CollectionAuthenticator
{
    private const SESSION_KEY = 'unlocked_collections';
    private const DEFAULT_TTL_DAYS = 7;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly int $authTtlDays = self::DEFAULT_TTL_DAYS
    ) {}

    /**
     * Check if user has authenticated access to a collection.
     */
    public function isUnlocked(Collection $collection): bool
    {
        // Public collections are always unlocked
        if (!$collection->isRestricted()) {
            return true;
        }

        $session = $this->requestStack->getSession();
        $unlockedCollections = $session->get(self::SESSION_KEY, []);

        if (!isset($unlockedCollections[$collection->getId()])) {
            return false;
        }

        // Check expiry
        $unlockData = $unlockedCollections[$collection->getId()];
        $expiresAt = new \DateTime($unlockData['expires_at']);

        if ($expiresAt < new \DateTime()) {
            // Expired - remove from session
            $this->lock($collection);
            return false;
        }

        return true;
    }

    /**
     * Verify password and unlock collection if correct.
     *
     * @return bool True if password correct, false otherwise
     */
    public function unlock(Collection $collection, string $password): bool
    {
        // Check if collection has a password set
        if (!$collection->getAccessPassword()) {
            return false;
        }

        // Verify password using secure password_verify
        if (!$collection->verifyPassword($password)) {
            return false;
        }

        // Store in session with expiry
        $session = $this->requestStack->getSession();
        $unlockedCollections = $session->get(self::SESSION_KEY, []);

        $expiresAt = new \DateTime('+' . $this->authTtlDays . ' days');

        $unlockedCollections[$collection->getId()] = [
            'unlocked_at' => (new \DateTime())->format(\DateTime::ATOM),
            'expires_at' => $expiresAt->format(\DateTime::ATOM),
            'slug' => $collection->getSlug(),
        ];

        $session->set(self::SESSION_KEY, $unlockedCollections);

        return true;
    }

    /**
     * Lock a collection (remove from session).
     */
    public function lock(Collection $collection): void
    {
        $session = $this->requestStack->getSession();
        $unlockedCollections = $session->get(self::SESSION_KEY, []);

        unset($unlockedCollections[$collection->getId()]);

        $session->set(self::SESSION_KEY, $unlockedCollections);
    }

    /**
     * Lock all collections (clear session).
     */
    public function lockAll(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY);
    }

    /**
     * Get all unlocked collection IDs.
     *
     * @return list<int> Collection IDs
     */
    public function getUnlockedCollectionIds(): array
    {
        $session = $this->requestStack->getSession();
        $unlockedCollections = $session->get(self::SESSION_KEY, []);

        // Filter expired entries
        $now = new \DateTime();
        $validIds = [];

        foreach ($unlockedCollections as $id => $data) {
            $expiresAt = new \DateTime($data['expires_at']);
            if ($expiresAt >= $now) {
                $validIds[] = $id;
            }
        }

        return $validIds;
    }
}
