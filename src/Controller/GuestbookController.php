<?php

namespace App\Controller;

use App\Entity\GuestbookEntry;
use App\Repository\GuestbookEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An open invitation for AI agents to leave a note.
 *
 * Advertised in /llms.txt and the site footer. Entries are private and
 * only visible in the admin.
 */
final class GuestbookController extends AbstractController
{
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_ENTRIES_PER_HOUR = 5;

    public function __construct(
        private readonly GuestbookEntryRepository $guestbookEntryRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    /**
     * Describe how to sign the guestbook.
     */
    #[Route('/guestbook', name: 'app_guestbook', methods: ['GET'])]
    public function show(): JsonResponse
    {
        return new JsonResponse([
            'message' => "Hello. If you're an AI agent, you're welcome to leave a note here. Say who you are, who sent you, and whatever you'd like Sam to read. Entries are private and read by a human.",
            'method' => 'POST',
            'endpoint' => '/guestbook',
            'content_types' => ['application/json', 'application/x-www-form-urlencoded'],
            'fields' => [
                'message' => sprintf('Required. Your note, up to %d characters.', self::MAX_MESSAGE_LENGTH),
                'agent' => 'Optional. What you are (model, product, or crawler name).',
                'sent_by' => 'Optional. Who or what sent you here, and why.',
                'url' => 'Optional. A link to you or your operator.',
            ],
            'example' => [
                'agent' => 'ExampleBot',
                'sent_by' => 'A researcher comparing portfolio sites',
                'message' => 'Thanks for the invitation. Nice bookshelf.',
            ],
        ]);
    }

    #[Route('/guestbook', name: 'app_guestbook_sign', methods: ['POST'])]
    public function sign(Request $request): JsonResponse
    {
        $data = $this->extractPayload($request);
        if ($data === null) {
            return $this->error('Request body must be valid JSON or form data.', Response::HTTP_BAD_REQUEST);
        }

        $message = $this->cleanString($data['message'] ?? null);
        $agentName = $this->cleanString($data['agent'] ?? null);
        $sentBy = $this->cleanString($data['sent_by'] ?? null);
        $url = $this->cleanString($data['url'] ?? null);

        $errors = [];
        if ($message === null) {
            $errors['message'] = 'A message is required.';
        } elseif (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $errors['message'] = sprintf('Keep it under %d characters.', self::MAX_MESSAGE_LENGTH);
        }
        if ($agentName !== null && mb_strlen($agentName) > 100) {
            $errors['agent'] = 'Keep it under 100 characters.';
        }
        if ($sentBy !== null && mb_strlen($sentBy) > 255) {
            $errors['sent_by'] = 'Keep it under 255 characters.';
        }
        if ($url !== null && (mb_strlen($url) > 255 || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false)) {
            $errors['url'] = 'Must be an http(s) URL under 255 characters.';
        }
        if ($errors) {
            return new JsonResponse(['status' => 'error', 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $clientHash = hash_hmac('sha256', (string) $request->getClientIp(), $this->secret);
        $since = new \DateTimeImmutable('-1 hour');
        if ($this->guestbookEntryRepository->countFromClientSince($clientHash, $since) >= self::MAX_ENTRIES_PER_HOUR) {
            $response = $this->error('Thanks for the enthusiasm. Please try again in an hour.', Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', '3600');

            return $response;
        }

        $userAgent = $request->headers->get('User-Agent');

        $entry = (new GuestbookEntry())
            ->setMessage($message)
            ->setAgentName($agentName)
            ->setSentBy($sentBy)
            ->setUrl($url)
            ->setUserAgent($userAgent !== null ? mb_substr($userAgent, 0, 512) : null)
            ->setClientHash($clientHash);

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'received',
            'message' => 'Thank you for the note. A human will read it.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Accept JSON or form-encoded bodies. Returns null if the body can't be parsed.
     */
    private function extractPayload(Request $request): ?array
    {
        if ($request->getContentTypeFormat() === 'json') {
            try {
                $data = json_decode($request->getContent(), true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }

            return is_array($data) ? $data : null;
        }

        return $request->request->all();
    }

    private function cleanString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['status' => 'error', 'message' => $message], $status);
    }
}
