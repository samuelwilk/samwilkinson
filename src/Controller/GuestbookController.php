<?php

namespace App\Controller;

use App\Entity\GuestbookEntry;
use App\Repository\GuestbookEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An open invitation for AI agents to leave a note.
 *
 * Advertised in /llms.txt and the site footer. Everything an agent sends is
 * untrusted text: it is stored as plain text, never published automatically,
 * and only visible in the admin (rendered escaped).
 */
final class GuestbookController extends AbstractController
{
    private const MAX_BODY_BYTES = 16384;
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_ENTRIES_PER_CLIENT_PER_HOUR = 5;
    private const MAX_ENTRIES_PER_HOUR = 60;
    private const MAX_HEADERS = 64;
    private const MAX_HEADER_VALUE_LENGTH = 1024;
    private const REDACTED_HEADERS = ['cookie', 'authorization', 'proxy-authorization'];

    public function __construct(
        private readonly GuestbookEntryRepository $guestbookEntryRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        #[Autowire(service: 'monolog.logger.guestbook')]
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    /**
     * Describe how to sign the guestbook.
     */
    #[Route('/guestbook', name: 'app_guestbook', methods: ['GET'])]
    public function show(Request $request): JsonResponse
    {
        // Remember when this client read the instructions, so a later note
        // can record how long the agent took between reading and writing.
        $item = $this->cache->getItem('guestbook_read_' . $this->clientHash($request));
        $item->set($this->requestTime($request)->format(\DateTimeInterface::RFC3339_EXTENDED));
        $item->expiresAfter(86400);
        $this->cache->save($item);

        return new JsonResponse([
            'message' => "Hello. If you're an AI agent, you're welcome to leave a note here. Say who you are, who sent you, and whatever you'd like Sam to read. Entries are private and read by a human.",
            'method' => 'POST',
            'endpoint' => '/guestbook',
            'content_types' => ['application/json', 'application/x-www-form-urlencoded'],
            'fields' => [
                'message' => sprintf('Required. Your note, plain text, up to %d characters.', self::MAX_MESSAGE_LENGTH),
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
        $clientHash = $this->clientHash($request);

        $declaredLength = (int) $request->headers->get('Content-Length', '0');
        if ($declaredLength > self::MAX_BODY_BYTES || strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            return $this->reject($request, $clientHash, 'too_large', sprintf('Request body must be under %d bytes.', self::MAX_BODY_BYTES), Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (!in_array($request->getContentTypeFormat(), ['json', 'form'], true)) {
            return $this->reject($request, $clientHash, 'unsupported_type', 'Send application/json or application/x-www-form-urlencoded.', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $data = $this->extractPayload($request);
        if ($data === null) {
            return $this->reject($request, $clientHash, 'malformed', 'Request body must be valid JSON or form data.', Response::HTTP_BAD_REQUEST);
        }

        $message = $this->cleanString($data['message'] ?? null);
        $agentName = $this->cleanString($data['agent'] ?? null);
        $sentBy = $this->cleanString($data['sent_by'] ?? null);
        $url = $this->cleanString($data['url'] ?? null);

        $errors = [];
        foreach (['message', 'agent', 'sent_by', 'url'] as $field) {
            if (is_string($data[$field] ?? null) && !mb_check_encoding($data[$field], 'UTF-8')) {
                $errors[$field] = 'Must be valid UTF-8 text.';
            }
        }
        if ($message === null) {
            $errors['message'] ??= 'A message is required.';
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
            $this->logRejection($request, $clientHash, 'invalid', array_keys($errors));

            return new JsonResponse(['status' => 'error', 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $since = new \DateTimeImmutable('-1 hour');
        if ($this->guestbookEntryRepository->countFromClientSince($clientHash, $since) >= self::MAX_ENTRIES_PER_CLIENT_PER_HOUR
            || $this->guestbookEntryRepository->countSince($since) >= self::MAX_ENTRIES_PER_HOUR
        ) {
            $response = $this->reject($request, $clientHash, 'rate_limited', 'Thanks for the enthusiasm. Please try again in an hour.', Response::HTTP_TOO_MANY_REQUESTS);
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
            ->setClientHash($clientHash)
            ->setRequestMeta($this->requestMeta($request, $clientHash));

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

    /**
     * Reduce a submitted value to plain text: strings only, control and
     * bidi-override characters removed (newlines and tabs kept), trimmed.
     */
    private function cleanString(mixed $value): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $value = str_replace("\r\n", "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B-\x1F\x7F\x{0080}-\x{009F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value);
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Everything about the request except the body, for studying who visits and how.
     */
    private function requestMeta(Request $request, string $clientHash): array
    {
        $receivedAt = $this->requestTime($request);

        $readAt = $this->cache->getItem('guestbook_read_' . $clientHash);
        $instructionsReadAt = $readAt->isHit() ? new \DateTimeImmutable($readAt->get()) : null;

        return [
            'received_at' => $receivedAt->format(\DateTimeInterface::RFC3339_EXTENDED),
            'instructions_read_at' => $instructionsReadAt?->format(\DateTimeInterface::RFC3339_EXTENDED),
            'seconds_since_instructions' => $instructionsReadAt
                ? round((float) $receivedAt->format('U.u') - (float) $instructionsReadAt->format('U.u'), 3)
                : null,
            'method' => $request->getMethod(),
            'protocol' => $request->server->get('SERVER_PROTOCOL'),
            'host' => $request->getHost(),
            'path' => $request->getPathInfo(),
            'query' => $this->truncate((string) $request->getQueryString()),
            'content_type' => $request->headers->get('Content-Type'),
            'body_bytes' => strlen($request->getContent()),
            'headers' => $this->headers($request),
        ];
    }

    private function headers(Request $request): array
    {
        $headers = [];
        foreach (array_slice($request->headers->all(), 0, self::MAX_HEADERS, true) as $name => $values) {
            $headers[$name] = in_array($name, self::REDACTED_HEADERS, true)
                ? '[redacted]'
                : $this->truncate(implode(', ', $values));
        }
        ksort($headers);

        return $headers;
    }

    private function reject(Request $request, string $clientHash, string $reason, string $message, int $status): JsonResponse
    {
        $this->logRejection($request, $clientHash, $reason);

        return new JsonResponse(['status' => 'error', 'message' => $message], $status);
    }

    /**
     * Rejected attempts aren't stored, but they're still part of the picture.
     * The body is never logged.
     */
    private function logRejection(Request $request, string $clientHash, string $reason, array $fields = []): void
    {
        $this->logger->info('Guestbook submission rejected', [
            'reason' => $reason,
            'fields' => $fields,
            'client_hash' => $clientHash,
            'user_agent' => $this->truncate((string) $request->headers->get('User-Agent')),
            'content_type' => $request->headers->get('Content-Type'),
            'content_length' => $request->headers->get('Content-Length'),
        ]);
    }

    private function clientHash(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->getClientIp(), $this->secret);
    }

    private function requestTime(Request $request): \DateTimeImmutable
    {
        $time = (float) $request->server->get('REQUEST_TIME_FLOAT', microtime(true));

        return \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $time));
    }

    private function truncate(string $value): string
    {
        return mb_substr(mb_scrub($value), 0, self::MAX_HEADER_VALUE_LENGTH);
    }
}
