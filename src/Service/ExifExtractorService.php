<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Extracts EXIF metadata from uploaded photos.
 *
 * Handles GPS coordinates, capture date/time, camera settings,
 * and other technical metadata stored in photo files.
 */
class ExifExtractorService
{
    /**
     * Extract EXIF data from an uploaded photo file.
     *
     * @param UploadedFile $file The uploaded photo file
     * @return array{
     *     raw: array,
     *     takenAt: ?\DateTimeInterface,
     *     latitude: ?float,
     *     longitude: ?float,
     *     width: ?int,
     *     height: ?int,
     *     camera: ?string,
     *     lens: ?string,
     *     focalLength: ?string,
     *     aperture: ?string,
     *     shutterSpeed: ?string,
     *     iso: ?int,
     *     exposureCompensation: ?string
     * }
     */
    public function extract(UploadedFile $file): array
    {
        $data = [
            'raw' => [],
            'takenAt' => null,
            'latitude' => null,
            'longitude' => null,
            'width' => null,
            'height' => null,
            'camera' => null,
            'lens' => null,
            'focalLength' => null,
            'aperture' => null,
            'shutterSpeed' => null,
            'iso' => null,
            'exposureCompensation' => null,
        ];

        $exif = @exif_read_data($file->getPathname(), null, true);

        if ($exif === false) {
            return $data;
        }

        $data['raw'] = $exif;

        // Extract capture date/time
        if (isset($exif['EXIF']['DateTimeOriginal'])) {
            $data['takenAt'] = \DateTime::createFromFormat('Y:m:d H:i:s', $exif['EXIF']['DateTimeOriginal']) ?: null;
        } elseif (isset($exif['IFD0']['DateTime'])) {
            $data['takenAt'] = \DateTime::createFromFormat('Y:m:d H:i:s', $exif['IFD0']['DateTime']) ?: null;
        }

        // Extract GPS coordinates
        if (isset($exif['GPS'])) {
            $data['latitude'] = $this->getGpsCoordinate($exif['GPS'], 'Latitude');
            $data['longitude'] = $this->getGpsCoordinate($exif['GPS'], 'Longitude');
        }

        // Extract image dimensions
        if (isset($exif['COMPUTED']['Width'])) {
            $data['width'] = (int) $exif['COMPUTED']['Width'];
        }
        if (isset($exif['COMPUTED']['Height'])) {
            $data['height'] = (int) $exif['COMPUTED']['Height'];
        }

        // Extract camera info
        if (isset($exif['IFD0']['Make'], $exif['IFD0']['Model'])) {
            $data['camera'] = trim($exif['IFD0']['Make'] . ' ' . $exif['IFD0']['Model']);
        }

        // Extract lens info
        if (isset($exif['EXIF']['LensModel'])) {
            $data['lens'] = $exif['EXIF']['LensModel'];
        }

        // Extract focal length
        if (isset($exif['EXIF']['FocalLength'])) {
            $data['focalLength'] = $this->formatFraction($exif['EXIF']['FocalLength']) . 'mm';
        }

        // Extract aperture (F-stop)
        if (isset($exif['EXIF']['FNumber'])) {
            $data['aperture'] = 'f/' . $this->formatFraction($exif['EXIF']['FNumber']);
        }

        // Extract shutter speed
        if (isset($exif['EXIF']['ExposureTime'])) {
            $data['shutterSpeed'] = $this->formatShutterSpeed($exif['EXIF']['ExposureTime']);
        }

        // Extract ISO
        if (isset($exif['EXIF']['ISOSpeedRatings'])) {
            $data['iso'] = is_array($exif['EXIF']['ISOSpeedRatings'])
                ? (int) $exif['EXIF']['ISOSpeedRatings'][0]
                : (int) $exif['EXIF']['ISOSpeedRatings'];
        }

        // Extract Exposure Compensation (EV)
        if (isset($exif['EXIF']['ExposureBiasValue'])) {
            $data['exposureCompensation'] = $this->formatExposureCompensation($exif['EXIF']['ExposureBiasValue']);
        }

        return $data;
    }

    /**
     * Calculate SHA256 hash of a file for duplicate detection.
     */
    public function calculateFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getPathname());
    }

    /**
     * Convert GPS coordinates from EXIF format to decimal degrees.
     *
     * @param array $gps The GPS EXIF data
     * @param string $type 'Latitude' or 'Longitude'
     * @return float|null The coordinate in decimal degrees
     */
    private function getGpsCoordinate(array $gps, string $type): ?float
    {
        $hemisphere = $gps['GPS' . $type . 'Ref'] ?? null;
        $coordinate = $gps['GPS' . $type] ?? null;

        if (!$hemisphere || !$coordinate) {
            return null;
        }

        // Convert degrees/minutes/seconds to decimal
        $degrees = $this->evalFraction($coordinate[0]);
        $minutes = $this->evalFraction($coordinate[1]);
        $seconds = $this->evalFraction($coordinate[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        // Apply hemisphere (S and W are negative)
        if (in_array($hemisphere, ['S', 'W'])) {
            $decimal *= -1;
        }

        return $decimal;
    }

    /**
     * Evaluate a fraction string like "24/1" to a float.
     */
    private function evalFraction(string $fraction): float
    {
        $parts = explode('/', $fraction);

        if (count($parts) !== 2) {
            return (float) $fraction;
        }

        $numerator = (float) $parts[0];
        $denominator = (float) $parts[1];

        if ($denominator === 0.0) {
            return 0.0;
        }

        return $numerator / $denominator;
    }

    /**
     * Format a fraction for display.
     */
    private function formatFraction(string $fraction): string
    {
        $value = $this->evalFraction($fraction);
        return number_format($value, 1);
    }

    /**
     * Format shutter speed for display (e.g., "1/250" or "2s").
     */
    private function formatShutterSpeed(string $fraction): string
    {
        $value = $this->evalFraction($fraction);

        if ($value >= 1) {
            return number_format($value, 1) . 's';
        }

        return '1/' . number_format(1 / $value, 0);
    }

    /**
     * Format exposure compensation for display (e.g., "+1.0 EV", "-0.7 EV", "0 EV").
     */
    private function formatExposureCompensation(string $fraction): string
    {
        $value = $this->evalFraction($fraction);

        if ($value == 0) {
            return '0 EV';
        }

        $sign = $value > 0 ? '+' : '';
        return $sign . number_format($value, 1) . ' EV';
    }
}
