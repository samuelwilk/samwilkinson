<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Enum\PublishStatus;
use App\Message\ProcessBulkUploadMessage;
use App\Repository\CollectionRepository;
use App\Service\ApiTokenValidator;
use App\Service\PhotoUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API endpoint for bulk photo uploads from iPhone.
 *
 * Accepts multipart/form-data with photos and dispatches async processing.
 */
#[Route('/api/photos', name: 'api_photos_')]
class BulkUploadController extends AbstractController
{
    public function __construct(
        private readonly ApiTokenValidator $tokenValidator,
        private readonly MessageBusInterface $messageBus,
        private readonly PhotoUploadService $photoUploadService,
        private readonly CollectionRepository $collectionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Bulk upload photos to a collection.
     *
     * Request format:
     * - Method: POST
     * - Content-Type: multipart/form-data
     * - Authorization: Bearer {token}
     * - Body:
     *   - photos[]: Array of image files (required)
     *   - collection_id: ID of existing collection (optional)
     *   - collection_name: Name for new collection (optional, used if collection_id not provided)
     *   - publish_status: "draft" or "published" (optional)
     *     - Defaults to "draft" for new collections
     *     - Inherits collection's status when adding to existing collections (if empty/null)
     *
     * Response:
     * {
     *   "status": "processing",
     *   "message": "Upload queued for processing",
     *   "photo_count": 5,
     *   "collection_id": 123,
     *   "publish_status": "draft"
     * }
     */
    #[Route('/bulk-upload', name: 'bulk_upload', methods: ['POST'])]
    public function bulkUpload(Request $request, LoggerInterface $logger): JsonResponse
    {
        // DEBUG: Log incoming request
        $logger->debug('=== BULK UPLOAD REQUEST ===');
        $logger->debug('Content-Type: ' . $request->headers->get('Content-Type'));
        $logger->debug('Request params: ' . json_encode($request->request->all()));
        $logger->debug('Files keys: ' . json_encode(array_keys($request->files->all())));
        $logger->debug('Files data: ' . print_r($request->files->all(), true));

        // Validate API token
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            error_log('ERROR: Missing or invalid Authorization header');
            return new JsonResponse([
                'error' => 'Missing or invalid Authorization header',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix
        if (!$this->tokenValidator->validate($token)) {
            error_log('ERROR: Invalid or expired API token');
            return new JsonResponse([
                'error' => 'Invalid or expired API token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validate request parameters
        $photos = $request->files->get('photos');
        error_log('Photos type: ' . gettype($photos));
        error_log('Photos value: ' . print_r($photos, true));

        if (!is_array($photos) || empty($photos)) {
            error_log('ERROR: No photos provided or photos is not an array');
            return new JsonResponse([
                'error' => 'No photos provided. Include photos[] in form data.',
                'debug' => [
                    'photos_type' => gettype($photos),
                    'files_keys' => array_keys($request->files->all()),
                    'request_params' => $request->request->all(),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $collectionId = $request->request->get('collection_id');
        $collectionName = $request->request->get('collection_name');
        $publishStatusString = $request->request->get('publish_status');

        // Treat empty string as null for publish_status
        if ($publishStatusString === '' || $publishStatusString === null) {
            $publishStatusString = null;
        }

        // Validate publish status (optional - defaults based on new vs existing collection)
        if ($publishStatusString !== null) {
            try {
                $publishStatus = PublishStatus::from($publishStatusString);
            } catch (\ValueError $e) {
                return new JsonResponse([
                    'error' => 'Invalid publish_status. Must be "draft" or "published".',
                ], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $publishStatus = null; // Will be set later based on context
        }

        // Validate collection parameters
        // Treat empty string as null for collection_id
        if ($collectionId === '' || $collectionId === null) {
            $collectionId = null;
        } else {
            $collectionId = (int) $collectionId;
        }

        // If no collection specified, default collection name
        if ($collectionId === null && ($collectionName === null || $collectionName === '')) {
            $collectionName = 'Untitled Collection';
        }

        // Save uploaded files to temporary directory (shared between containers)
        $tempDir = $this->getParameter('kernel.project_dir') . '/var/data/uploads_temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFiles = [];
        foreach ($photos as $photo) {
            if (!$photo instanceof UploadedFile) {
                continue;
            }

            // Validate file is an image
            if (!str_starts_with($photo->getMimeType() ?? '', 'image/')) {
                return new JsonResponse([
                    'error' => sprintf('Invalid file type: %s. Only images are allowed.', $photo->getClientOriginalName()),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Generate unique filename
            $filename = uniqid('upload_', true) . '_' . $photo->getClientOriginalName();
            $tempPath = $tempDir . '/' . $filename;

            // Move to temp directory
            $photo->move($tempDir, $filename);
            $tempFiles[] = $tempPath;
        }

        if (empty($tempFiles)) {
            return new JsonResponse([
                'error' => 'No valid image files found.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create or load collection synchronously so we can return the ID
        if ($collectionId === null) {
            // Create new collection from uploaded files
            $uploadedFiles = [];
            foreach ($tempFiles as $tempPath) {
                if (file_exists($tempPath)) {
                    $uploadedFiles[] = new UploadedFile(
                        $tempPath,
                        basename($tempPath),
                        null,
                        null,
                        true
                    );
                }
            }

            $collection = $this->photoUploadService->createCollectionFromPhotos(
                $collectionName ?? 'Untitled Collection',
                $uploadedFiles
            );

            $this->entityManager->persist($collection);
            $this->entityManager->flush();

            $collectionId = $collection->getId();
            $collectionName = $collection->getName();

            // Default to 'draft' for new collections if not specified
            if ($publishStatus === null) {
                $publishStatus = PublishStatus::Draft;
            }
        } else {
            // Verify existing collection exists
            $collection = $this->collectionRepository->find($collectionId);
            if ($collection === null) {
                return new JsonResponse([
                    'error' => sprintf('Collection with ID %d not found', $collectionId),
                ], Response::HTTP_NOT_FOUND);
            }
            $collectionName = $collection->getName();

            // Inherit collection's publish status if not explicitly specified
            if ($publishStatus === null) {
                $publishStatus = $collection->isPublished() ? PublishStatus::Published : PublishStatus::Draft;
            }
        }

        // Dispatch async message
        $message = new ProcessBulkUploadMessage(
            $collectionId,
            $collectionName,
            $tempFiles,
            $publishStatus
        );

        $this->messageBus->dispatch($message);

        // Return success response
        return new JsonResponse([
            'status' => 'processing',
            'message' => 'Upload queued for processing',
            'photo_count' => count($tempFiles),
            'collection_id' => $collectionId,
            'collection_name' => $collectionName,
            'publish_status' => $publishStatus->value,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * List all collections for selection in iOS Shortcut.
     *
     * Response:
     * {
     *   "collections": [
     *     {
     *       "id": 123,
     *       "name": "Beach Day",
     *       "slug": "beach-day",
     *       "photo_count": 15,
     *       "created_at": "2024-12-30T10:00:00+00:00"
     *     }
     *   ]
     * }
     */
    #[Route('/collections', name: 'collections', methods: ['GET'])]
    public function collections(Request $request, LoggerInterface $logger): JsonResponse
    {
        // Validate API token
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse([
                'error' => 'Missing or invalid Authorization header',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = substr($authHeader, 7);
        if (!$this->tokenValidator->validate($token)) {
            return new JsonResponse([
                'error' => 'Invalid or expired API token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Fetch all collections ordered by most recent first
        $collections = $this->collectionRepository->createQueryBuilder('c')
            ->leftJoin('c.photos', 'p')
            ->addSelect('COUNT(p.id) as photo_count')
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Format response
        $collectionsData = array_map(function ($result) {
            $collection = is_array($result) ? $result[0] : $result;
            $photoCount = is_array($result) ? $result['photo_count'] : 0;

            return [
                'id' => $collection->getId(),
                'name' => $collection->getName(),
                'slug' => $collection->getSlug(),
                'photo_count' => (int) $photoCount,
                'created_at' => $collection->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $collections);

        return new JsonResponse([
            'collections' => $collectionsData,
        ]);
    }

    /**
     * Health check endpoint for API status.
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Debug endpoint - dumps all received data.
     * NO AUTHENTICATION - for debugging only!
     */
    #[Route('/debug', name: 'debug', methods: ['POST'])]
    public function debug(Request $request, LoggerInterface $logger): JsonResponse
    {
        $logger->error('=== DEBUG ENDPOINT ===');
        $logger->error('Method: ' . $request->getMethod());
        $logger->error('Content-Type: ' . $request->headers->get('Content-Type'));
        $logger->error('All headers: ' . json_encode($request->headers->all()));
        $logger->error('Request params: ' . json_encode($request->request->all()));
        $logger->error('Files: ' . print_r($request->files->all(), true));

        // Validate API token
        $authHeader = $request->headers->get('Authorization');
        $tokenValidation = [
            'header_present' => $authHeader !== null,
            'header_value' => $authHeader ? substr($authHeader, 0, 20) . '...' : null,
            'has_bearer_prefix' => $authHeader && str_starts_with($authHeader, 'Bearer '),
            'token_valid' => false,
            'token_extracted' => null,
        ];

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenValidation['token_extracted'] = substr($token, 0, 10) . '...' . substr($token, -10);
            $tokenValidation['token_length'] = strlen($token);
            $tokenValidation['token_valid'] = $this->tokenValidator->validate($token);
        }

        $logger->error('Token validation: ' . json_encode($tokenValidation));

        $files = [];
        foreach ($request->files->all() as $key => $file) {
            if ($file instanceof UploadedFile) {
                $files[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            } elseif (is_array($file)) {
                $files[$key] = array_map(function ($f) {
                    return $f instanceof UploadedFile ? [
                        'name' => $f->getClientOriginalName(),
                        'size' => $f->getSize(),
                        'mime' => $f->getMimeType(),
                    ] : 'not a file';
                }, $file);
            }
        }

        return new JsonResponse([
            'received' => 'ok',
            'method' => $request->getMethod(),
            'content_type' => $request->headers->get('Content-Type'),
            'token_validation' => $tokenValidation,
            'request_params' => $request->request->all(),
            'files_keys' => array_keys($request->files->all()),
            'files_detail' => $files,
        ]);
    }
}
