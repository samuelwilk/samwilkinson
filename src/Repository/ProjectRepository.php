<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Find all published projects
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find featured projects
     */
    public function findFeatured(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isFeatured = :featured')
            ->setParameter('published', true)
            ->setParameter('featured', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find projects by tag
     */
    public function findByTag(string $tag): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->andWhere('JSON_CONTAINS(p.tags, :tag) = 1')
            ->setParameter('published', true)
            ->setParameter('tag', json_encode($tag))
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
