<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    /**
     * Find all active tokens.
     *
     * @return ApiToken[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a valid token by checking all active tokens.
     */
    public function findValidToken(string $plainToken): ?ApiToken
    {
        $tokens = $this->findActive();

        foreach ($tokens as $token) {
            if ($token->verifyToken($plainToken) && $token->isValid()) {
                return $token;
            }
        }

        return null;
    }
}
