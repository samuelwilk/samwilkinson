<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ApiToken;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Validates API tokens for bulk photo upload authentication.
 *
 * Verifies Bearer tokens against stored hashed tokens and ensures
 * they are active and not expired. Updates lastUsedAt timestamp
 * on successful validation.
 */
class ApiTokenValidator
{
    public function __construct(
        private readonly ApiTokenRepository $tokenRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Validate a Bearer token.
     *
     * @param string $token The plain-text token from the Authorization header
     * @return bool True if token is valid and active
     */
    public function validate(string $token): bool
    {
        $apiToken = $this->tokenRepository->findValidToken($token);

        if ($apiToken === null) {
            return false;
        }

        // Mark token as used and persist
        $apiToken->markAsUsed();
        $this->entityManager->flush();

        return true;
    }

    /**
     * Validate a Bearer token and return the ApiToken entity if valid.
     *
     * @param string $token The plain-text token from the Authorization header
     * @return ApiToken|null The ApiToken entity if valid, null otherwise
     */
    public function validateAndGet(string $token): ?ApiToken
    {
        $apiToken = $this->tokenRepository->findValidToken($token);

        if ($apiToken === null) {
            return null;
        }

        // Mark token as used and persist
        $apiToken->markAsUsed();
        $this->entityManager->flush();

        return $apiToken;
    }
}
