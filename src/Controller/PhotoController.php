<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Photo;
use App\Repository\PhotoRepository;
use App\Service\CollectionAuthenticator;
use Aws\S3\S3Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Serves photos from private R2 storage with access control.
 *
 * Photos are stored in a private R2 bucket. This controller:
 * 1. Checks if user has access to the photo's collection
 * 2. Generates a temporary signed URL (valid for 1 hour)
 * 3. Redirects to the signed URL
 */
class PhotoController extends AbstractController
{
    private const SIGNED_URL_EXPIRY = '+1 hour';

    public function __construct(
        private readonly PhotoRepository $photoRepository,
        private readonly CollectionAuthenticator $collectionAuthenticator,
        private readonly S3Client $s3Client,
        private readonly string $r2Bucket,
        private readonly string $r2Endpoint,
        private readonly string $projectDir,
        private readonly string $appEnv,
    ) {
    }

    /**
     * Serve a photo by ID with access control.
     */
    #[Route('/photo/{id}', name: 'photo_view', requirements: ['id' => '\d+'])]
    public function view(int $id): Response
    {
        $photo = $this->photoRepository->find($id);

        if (!$photo) {
            throw new NotFoundHttpException('Photo not found');
        }

        return $this->servePhoto($photo);
    }

    /**
     * Serve a photo by filename (VichUploader compatibility).
     *
     * This route matches VichUploader's generated URLs: /uploads/photos/{filename}
     */
    #[Route('/uploads/photos/{filename}', name: 'photo_view_by_filename', requirements: ['filename' => '.+'])]
    public function viewByFilename(string $filename): Response
    {
        $photo = $this->photoRepository->findOneBy(['filename' => $filename]);

        if (!$photo) {
            throw new NotFoundHttpException('Photo not found');
        }

        return $this->servePhoto($photo);
    }

    /**
     * Check access and serve photo (local file in dev, signed URL in prod).
     */
    private function servePhoto(Photo $photo): Response
    {
        $collection = $photo->getCollection();

        // Check if collection exists
        if (!$collection) {
            throw new NotFoundHttpException('Photo has no collection');
        }

        // Check access using CollectionAuthenticator
        if (!$this->collectionAuthenticator->isUnlocked($collection)) {
            throw new AccessDeniedHttpException(
                'Access denied. This photo belongs to a password-protected collection.'
            );
        }

        // Check if photo is published (admins can see unpublished photos)
        if (!$photo->isPublished() && !$this->isGranted('ROLE_ADMIN')) {
            throw new NotFoundHttpException('Photo not found');
        }

        // In development, serve local file directly
        if ($this->appEnv === 'dev') {
            return $this->serveLocalFile($photo);
        }

        // In production, generate signed R2 URL and redirect
        $signedUrl = $this->generateSignedUrl($photo);

        return new RedirectResponse($signedUrl);
    }

    /**
     * Serve a local file (development only).
     */
    private function serveLocalFile(Photo $photo): Response
    {
        $filePath = $this->projectDir . '/public/uploads/photos/' . $photo->getFilename();

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Photo file not found on disk');
        }

        return new BinaryFileResponse($filePath);
    }

    /**
     * Generate a temporary signed URL for the photo in R2.
     */
    private function generateSignedUrl(Photo $photo): string
    {
        $objectKey = 'photos/' . $photo->getFilename();

        $cmd = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $this->r2Bucket,
            'Key' => $objectKey,
        ]);

        $request = $this->s3Client->createPresignedRequest($cmd, self::SIGNED_URL_EXPIRY);

        return (string) $request->getUri();
    }
}
