<?php

namespace App\Entity;

use App\Repository\CollectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CollectionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Collection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $locationName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Photo $coverPhoto = null;

    #[ORM\Column]
    private ?bool $isRestricted = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accessPassword = null;

    #[ORM\Column]
    private ?bool $allowDownloads = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $visualStyle = null;

    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'collection', orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'takenAt' => 'DESC'])]
    private DoctrineCollection $photos;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $sortOrder = null;

    #[ORM\Column]
    private ?bool $isPublished = false;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isRestricted = false;
        $this->allowDownloads = false;
        $this->isPublished = false;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLocationName(): ?string
    {
        return $this->locationName;
    }

    public function setLocationName(?string $locationName): static
    {
        $this->locationName = $locationName;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getCoverPhoto(): ?Photo
    {
        // Prioritize photo marked for index cover
        foreach ($this->photos as $photo) {
            if ($photo->useForIndexCover() && $photo->isPublished()) {
                return $photo;
            }
        }

        // Fallback to explicit cover photo or first photo
        return $this->coverPhoto ?? $this->photos->first() ?: null;
    }

    public function setCoverPhoto(?Photo $coverPhoto): static
    {
        $this->coverPhoto = $coverPhoto;

        return $this;
    }

    public function isRestricted(): ?bool
    {
        return $this->isRestricted;
    }

    public function setIsRestricted(bool $isRestricted): static
    {
        $this->isRestricted = $isRestricted;

        return $this;
    }

    public function getAccessPassword(): ?string
    {
        return $this->accessPassword;
    }

    public function setAccessPassword(?string $accessPassword): static
    {
        // Hash password if provided and not already hashed
        if ($accessPassword !== null && !$this->isPasswordHashed($accessPassword)) {
            $this->accessPassword = password_hash($accessPassword, PASSWORD_DEFAULT);
        } else {
            $this->accessPassword = $accessPassword;
        }

        return $this;
    }

    /**
     * Check if a password string is already hashed.
     */
    private function isPasswordHashed(string $password): bool
    {
        // password_hash() produces strings starting with $2y$ (bcrypt)
        return str_starts_with($password, '$2y$') || str_starts_with($password, '$2a$');
    }

    /**
     * Verify a plain-text password against the stored hash.
     */
    public function verifyPassword(string $password): bool
    {
        if ($this->accessPassword === null) {
            return false;
        }

        return password_verify($password, $this->accessPassword);
    }

    public function allowDownloads(): ?bool
    {
        return $this->allowDownloads;
    }

    public function setAllowDownloads(bool $allowDownloads): static
    {
        $this->allowDownloads = $allowDownloads;

        return $this;
    }

    public function getVisualStyle(): ?array
    {
        return $this->visualStyle;
    }

    public function setVisualStyle(?array $visualStyle): static
    {
        $this->visualStyle = $visualStyle;

        return $this;
    }

    public function getVisualStyleValue(string $key): mixed
    {
        return $this->visualStyle[$key] ?? null;
    }

    /**
     * @return DoctrineCollection<int, Photo>
     */
    public function getPhotos(): DoctrineCollection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setCollection($this);
        }

        return $this;
    }

    public function removePhoto(Photo $photo): static
    {
        if ($this->photos->removeElement($photo)) {
            if ($photo->getCollection() === $this) {
                $photo->setCollection(null);
            }
        }

        return $this;
    }

    public function getPhotoCount(): int
    {
        return $this->photos->count();
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(?int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    /**
     * Calculate aging effect seed based on start date.
     * Older collections get stronger aging effects.
     */
    public function getAgingIntensity(): float
    {
        if (!$this->startDate) {
            return 0.0;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->startDate);
        $years = $diff->y + ($diff->m / 12);

        // Return value between 0.0 (new) and 1.0 (very old, 10+ years)
        return min(1.0, $years / 10);
    }

    public function __toString(): string
    {
        return $this->name ?? 'Collection #' . $this->id;
    }
}
