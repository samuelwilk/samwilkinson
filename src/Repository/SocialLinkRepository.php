<?php

namespace App\Repository;

use App\Entity\SocialLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialLink>
 */
class SocialLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialLink::class);
    }

    /**
     * Find all visible social links ordered by sort order
     */
    public function findVisible(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isVisible = :visible')
            ->setParameter('visible', true)
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
