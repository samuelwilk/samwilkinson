<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Gallery\ShowcaseBuilder;
use App\Repository\CollectionRepository;
use App\Service\CollectionAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class StillsController extends AbstractController
{
    #[Route('/stills', name: 'app_stills')]
    public function index(CollectionRepository $collectionRepository): Response
    {
        // Fetch only published collections ordered by sortOrder ASC, then startDate DESC
        $collections = $collectionRepository->createQueryBuilder('c')
            ->leftJoin('c.photos', 'p')
            ->addSelect('p')
            ->where('c.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.startDate', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('stills/index.html.twig', [
            'collections' => $collections,
        ]);
    }

    #[Route('/stills/albums/{slug}', name: 'app_stills_album', requirements: ['slug' => '[a-z0-9-]+'])]
    public function album(string $slug, CollectionRepository $collectionRepository, ShowcaseBuilder $showcaseBuilder, CollectionAuthenticator $authenticator): Response
    {
        // Fetch collection by slug with published photos
        $collection = $collectionRepository->createQueryBuilder('c')
            ->leftJoin('c.photos', 'p')
            ->addSelect('p')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();

        // Return 404 if collection not found
        if (!$collection) {
            throw $this->createNotFoundException('Collection not found');
        }

        // Check if collection is restricted and user is not authenticated
        if ($collection->isRestricted() && !$authenticator->isUnlocked($collection)) {
            return $this->render('stills/password.html.twig', [
                'collection' => $collection,
            ]);
        }

        // Filter to only published photos
        $publishedPhotos = array_filter(
            $collection->getPhotos()->toArray(),
            fn($photo) => $photo->isPublished()
        );

        // Sort by sortOrder
        usort($publishedPhotos, fn($a, $b) => ($a->getSortOrder() ?? 999) <=> ($b->getSortOrder() ?? 999));

        // Format photos for ShowcaseBuilder
        $photosData = array_map(function ($photo) use ($collection) {
            return [
                'url' => '/uploads/photos/' . $photo->getFilename(),
                'alt' => $photo->getTitle() ?? '',
                'width' => $photo->getWidth(),
                'height' => $photo->getHeight(),
                'location' => $collection->getLocationName(),
                'year' => $photo->getTakenAt() ? $photo->getTakenAt()->format('Y') : null,
                'iso' => $photo->getIso(),
                'focalLength' => $photo->getFocalLength(),
                'aperture' => $photo->getAperture(),
                'shutterSpeed' => $photo->getShutterSpeed(),
                'exposureCompensation' => $photo->getExposureCompensation(),
            ];
        }, $publishedPhotos);

        // Build showcase panels using seeded layout
        $panels = $showcaseBuilder->build($photosData, $collection->getSlug());

        return $this->render('stills/album.html.twig', [
            'collection' => $collection,
            'photos' => array_values($publishedPhotos),
            'panels' => $panels,
        ]);
    }

    #[Route('/stills/albums/{slug}/unlock', name: 'app_stills_unlock', methods: ['POST'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function unlock(string $slug, Request $request, CollectionRepository $collectionRepository, CollectionAuthenticator $authenticator): Response
    {
        // Fetch collection
        $collection = $collectionRepository->findOneBy(['slug' => $slug]);

        if (!$collection) {
            throw $this->createNotFoundException('Collection not found');
        }

        // Verify CSRF token
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('unlock-collection-' . $slug, $submittedToken)) {
            $this->addFlash('error', 'Invalid request. Please try again.');
            return $this->redirectToRoute('app_stills_album', ['slug' => $slug]);
        }

        // Get password from request
        $password = $request->request->get('password', '');

        // Attempt to unlock
        if ($authenticator->unlock($collection, $password)) {
            // Success - redirect to album
            $this->addFlash('success', 'Access granted');
            return $this->redirectToRoute('app_stills_album', ['slug' => $slug]);
        }

        // Failed - redirect back with error
        $this->addFlash('error', 'Incorrect password');
        return $this->redirectToRoute('app_stills_album', ['slug' => $slug]);
    }

    #[Route('/stills/albums/{slug}/photos/{id}/download', name: 'app_stills_download', requirements: ['slug' => '[a-z0-9-]+', 'id' => '\d+'])]
    public function downloadPhoto(string $slug, int $id, CollectionRepository $collectionRepository, CollectionAuthenticator $authenticator): Response
    {
        // Fetch collection
        $collection = $collectionRepository->findOneBy(['slug' => $slug]);

        if (!$collection) {
            throw $this->createNotFoundException('Collection not found');
        }

        // Check access control:
        // 1. Collection must be restricted (password-protected)
        // 2. User must be authenticated
        // 3. Collection must allow downloads
        if (!$collection->isRestricted()) {
            throw $this->createAccessDeniedException('Downloads are only available for password-protected collections');
        }

        if (!$authenticator->isUnlocked($collection)) {
            throw $this->createAccessDeniedException('You must unlock this collection to download photos');
        }

        if (!$collection->allowDownloads()) {
            throw $this->createAccessDeniedException('Downloads are not enabled for this collection');
        }

        // Find photo in collection
        $photo = null;
        foreach ($collection->getPhotos() as $p) {
            if ($p->getId() === $id && $p->isPublished()) {
                $photo = $p;
                break;
            }
        }

        if (!$photo) {
            throw $this->createNotFoundException('Photo not found in this collection');
        }

        // Get file path
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/photos/' . $photo->getFilename();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Photo file not found');
        }

        // Create response with download headers
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $photo->getFilename()
        );

        return $response;
    }
}
