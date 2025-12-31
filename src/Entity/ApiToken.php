<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * API token for authenticating bulk photo upload requests.
 *
 * Tokens are hashed using password_hash() for security.
 * The plain-text token is only shown once during creation.
 */
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ApiToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $hashedToken = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastUsedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isActive = true;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        // Prevent updating createdAt
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHashedToken(): ?string
    {
        return $this->hashedToken;
    }

    public function setHashedToken(string $hashedToken): static
    {
        $this->hashedToken = $hashedToken;

        return $this;
    }

    /**
     * Hash and store a plain-text token.
     */
    public function setPlainToken(string $plainToken): static
    {
        $this->hashedToken = password_hash($plainToken, PASSWORD_DEFAULT);

        return $this;
    }

    /**
     * Verify a plain-text token against the stored hash.
     */
    public function verifyToken(string $plainToken): bool
    {
        if ($this->hashedToken === null) {
            return false;
        }

        return password_verify($plainToken, $this->hashedToken);
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeInterface $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    /**
     * Mark this token as used (updates lastUsedAt).
     */
    public function markAsUsed(): static
    {
        $this->lastUsedAt = new \DateTime();

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * Check if this token is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTime();
    }

    /**
     * Check if this token is valid (active and not expired).
     */
    public function isValid(): bool
    {
        return $this->isActive && !$this->isExpired();
    }

    public function __toString(): string
    {
        return $this->name ?? 'API Token #' . $this->id;
    }
}
