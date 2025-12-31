<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Ensures only one photo per collection can have useForIndexCover = true.
 *
 * When a photo is marked as the index cover, this listener automatically
 * unsets the flag on any other photos in the same collection.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Photo::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Photo::class)]
final class PhotoIndexCoverListener
{
    public function prePersist(Photo $photo, PrePersistEventArgs $event): void
    {
        $this->ensureOnlyOneIndexCover($photo, $event->getObjectManager());
    }

    public function preUpdate(Photo $photo, PreUpdateEventArgs $event): void
    {
        // Only process if useForIndexCover was changed
        if (!$event->hasChangedField('useForIndexCover')) {
            return;
        }

        $this->ensureOnlyOneIndexCover($photo, $event->getObjectManager());
    }

    private function ensureOnlyOneIndexCover(Photo $photo, $entityManager): void
    {
        // Only process if this photo is being set as index cover
        if (!$photo->useForIndexCover()) {
            return;
        }

        $collection = $photo->getCollection();
        if (!$collection) {
            return;
        }

        // Find other photos in the same collection with useForIndexCover = true
        $repository = $entityManager->getRepository(Photo::class);
        $otherIndexCoverPhotos = $repository->createQueryBuilder('p')
            ->where('p.collection = :collection')
            ->andWhere('p.useForIndexCover = :true')
            ->andWhere('p.id != :photoId OR p.id IS NULL')
            ->setParameter('collection', $collection)
            ->setParameter('true', true)
            ->setParameter('photoId', $photo->getId())
            ->getQuery()
            ->getResult();

        // Unset useForIndexCover on all other photos
        foreach ($otherIndexCoverPhotos as $otherPhoto) {
            $otherPhoto->setUseForIndexCover(false);
            $entityManager->persist($otherPhoto);
        }
    }
}
