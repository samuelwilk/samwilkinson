<?php

namespace App\Repository;

use App\Entity\Collection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Collection>
 */
class CollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Collection::class);
    }

    /**
     * Find all collections with photo count
     */
    public function findAllWithPhotoCount(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.photos', 'p')
            ->addSelect('COUNT(p.id) as photoCount')
            ->groupBy('c.id')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find public (non-restricted) collections
     */
    public function findPublic(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isRestricted = :restricted')
            ->setParameter('restricted', false)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
