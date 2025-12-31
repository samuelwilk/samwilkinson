<?php

namespace App\Entity;

use App\Repository\PhotoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $fileHash = null;

    #[Vich\UploadableField(mapping: 'photos', fileNameProperty: 'filename')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $takenAt = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Collection $collection = null;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(nullable: true)]
    private ?float $aspectRatio = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $exifData = null;

    #[ORM\Column(nullable: true)]
    private ?int $iso = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $focalLength = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $aperture = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $shutterSpeed = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $exposureCompensation = null;

    #[ORM\Column]
    private ?bool $isPublished = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $uploadedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $sortOrder = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $useForIndexCover = false;

    public function __construct()
    {
        $this->uploadedAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isPublished = false;
        $this->useForIndexCover = false;
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

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): static
    {
        $this->fileHash = $fileHash;

        return $this;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            // Update updatedAt to force cache refresh
            $this->updatedAt = new \DateTime();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getTakenAt(): ?\DateTimeInterface
    {
        return $this->takenAt;
    }

    public function setTakenAt(?\DateTimeInterface $takenAt): static
    {
        $this->takenAt = $takenAt;

        return $this;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function setCollection(?Collection $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getAspectRatio(): ?float
    {
        return $this->aspectRatio;
    }

    public function setAspectRatio(?float $aspectRatio): static
    {
        $this->aspectRatio = $aspectRatio;

        return $this;
    }

    public function calculateAspectRatio(): void
    {
        if ($this->width && $this->height) {
            $this->aspectRatio = round($this->width / $this->height, 2);
        }
    }

    public function getExifData(): ?array
    {
        return $this->exifData;
    }

    public function setExifData(?array $exifData): static
    {
        $this->exifData = $exifData;

        return $this;
    }

    public function getExifValue(string $key): mixed
    {
        return $this->exifData[$key] ?? null;
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

    public function getUploadedAt(): ?\DateTimeInterface
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeInterface $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

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

    public function useForIndexCover(): ?bool
    {
        return $this->useForIndexCover;
    }

    public function setUseForIndexCover(bool $useForIndexCover): static
    {
        $this->useForIndexCover = $useForIndexCover;

        return $this;
    }

    public function getIso(): ?int
    {
        return $this->iso;
    }

    public function setIso(?int $iso): static
    {
        $this->iso = $iso;

        return $this;
    }

    public function getFocalLength(): ?string
    {
        return $this->focalLength;
    }

    public function setFocalLength(?string $focalLength): static
    {
        $this->focalLength = $focalLength;

        return $this;
    }

    public function getAperture(): ?string
    {
        return $this->aperture;
    }

    public function setAperture(?string $aperture): static
    {
        $this->aperture = $aperture;

        return $this;
    }

    public function getShutterSpeed(): ?string
    {
        return $this->shutterSpeed;
    }

    public function setShutterSpeed(?string $shutterSpeed): static
    {
        $this->shutterSpeed = $shutterSpeed;

        return $this;
    }

    public function getExposureCompensation(): ?string
    {
        return $this->exposureCompensation;
    }

    public function setExposureCompensation(?string $exposureCompensation): static
    {
        $this->exposureCompensation = $exposureCompensation;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title ?? $this->filename ?? 'Photo #' . $this->id;
    }
}
