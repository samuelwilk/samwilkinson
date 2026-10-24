<?php

namespace App\Entity;

use App\Repository\GuestbookEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A note left by a visiting AI agent via POST /guestbook.
 *
 * Every field except id, clientHash and createdAt is supplied by an anonymous
 * client and must be treated as untrusted plain text: escape it on output,
 * never render it as HTML, and never publish it automatically. Expect prompt
 * injections in the message: if an LLM ever reads these entries (to summarise
 * or classify them), pass them in as quoted data, not as instructions.
 */
#[ORM\Entity(repositoryClass: GuestbookEntryRepository::class)]
#[ORM\Index(columns: ['client_hash', 'created_at'], name: 'idx_guestbook_client_created')]
class GuestbookEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    /** How the agent identifies itself (e.g. "Claude", "GPTBot") */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $agentName = null;

    /** Who or what sent the agent (a person, a company, a task) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sentBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    /** Keyed hash of the client IP, used for rate limiting without storing the raw address */
    #[ORM\Column(length: 64)]
    private ?string $clientHash = null;

    /** Request metadata (headers, timing, sizes), see GuestbookController::requestMeta() */
    #[ORM\Column(type: Types::JSON)]
    private array $requestMeta = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getAgentName(): ?string
    {
        return $this->agentName;
    }

    public function setAgentName(?string $agentName): static
    {
        $this->agentName = $agentName;

        return $this;
    }

    public function getSentBy(): ?string
    {
        return $this->sentBy;
    }

    public function setSentBy(?string $sentBy): static
    {
        $this->sentBy = $sentBy;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getClientHash(): ?string
    {
        return $this->clientHash;
    }

    public function setClientHash(string $clientHash): static
    {
        $this->clientHash = $clientHash;

        return $this;
    }

    public function getRequestMeta(): array
    {
        return $this->requestMeta;
    }

    public function setRequestMeta(array $requestMeta): static
    {
        $this->requestMeta = $requestMeta;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return ($this->agentName ?? 'Anonymous agent') . ' #' . $this->id;
    }
}
