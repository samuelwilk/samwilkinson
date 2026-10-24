<?php

namespace App\Repository;

use App\Entity\GuestbookEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GuestbookEntry>
 */
class GuestbookEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuestbookEntry::class);
    }

    /**
     * Count entries from one client since the given time (for rate limiting)
     */
    public function countFromClientSince(string $clientHash, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.clientHash = :hash')
            ->andWhere('g.createdAt >= :since')
            ->setParameter('hash', $clientHash)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
