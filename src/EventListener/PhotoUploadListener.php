<?php

namespace App\EventListener;

use App\Entity\Photo;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoUploadListener
{
    public function prePersist(Photo $photo, PrePersistEventArgs $args): void
    {
        $this->extractImageMetadata($photo);
    }

    public function preUpdate(Photo $photo, PreUpdateEventArgs $args): void
    {
        $this->extractImageMetadata($photo);
    }

    private function extractImageMetadata(Photo $photo): void
    {
        $imageFile = $photo->getImageFile();

        if (!$imageFile instanceof UploadedFile) {
            return;
        }

        $path = $imageFile->getPathname();

        // Extract image dimensions
        $imageInfo = getimagesize($path);
        if ($imageInfo) {
            $photo->setWidth($imageInfo[0]);
            $photo->setHeight($imageInfo[1]);
            $photo->calculateAspectRatio();
        }

        // Extract EXIF data if available
        if (function_exists('exif_read_data') && exif_imagetype($path) === IMAGETYPE_JPEG) {
            try {
                $exifData = @exif_read_data($path, 0, true);

                if ($exifData) {
                    // Store the full EXIF data
                    $photo->setExifData($exifData);

                    // Try to extract the date taken
                    if (isset($exifData['EXIF']['DateTimeOriginal'])) {
                        try {
                            $dateTaken = \DateTime::createFromFormat('Y:m:d H:i:s', $exifData['EXIF']['DateTimeOriginal']);
                            if ($dateTaken) {
                                $photo->setTakenAt($dateTaken);
                            }
                        } catch (\Exception $e) {
                            // If date parsing fails, leave it null
                        }
                    } elseif (isset($exifData['IFD0']['DateTime'])) {
                        try {
                            $dateTaken = \DateTime::createFromFormat('Y:m:d H:i:s', $exifData['IFD0']['DateTime']);
                            if ($dateTaken) {
                                $photo->setTakenAt($dateTaken);
                            }
                        } catch (\Exception $e) {
                            // If date parsing fails, leave it null
                        }
                    }
                }
            } catch (\Exception $e) {
                // If EXIF extraction fails, continue without it
            }
        }
    }
}
