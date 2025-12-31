<?php

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * Find all published photos
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.takenAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find photos by collection
     */
    public function findByCollection(int $collectionId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.collection = :collection')
            ->setParameter('collection', $collectionId)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.takenAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
