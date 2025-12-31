<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reverse geocodes GPS coordinates to location names using Nominatim.
 *
 * Uses the free OpenStreetMap Nominatim API to convert latitude/longitude
 * coordinates to human-readable location names (city, country, etc.).
 */
class GeocodingService
{
    private const NOMINATIM_API = 'https://nominatim.openstreetmap.org/reverse';
    private const USER_AGENT = 'SamWilkinsonPortfolio/1.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Reverse geocode coordinates to location information.
     *
     * @param float $latitude The latitude coordinate
     * @param float $longitude The longitude coordinate
     * @return array{
     *     city: ?string,
     *     country: ?string,
     *     displayName: ?string,
     *     raw: array
     * }
     */
    public function reverseGeocode(float $latitude, float $longitude): array
    {
        $data = [
            'city' => null,
            'country' => null,
            'displayName' => null,
            'raw' => [],
        ];

        try {
            $response = $this->httpClient->request('GET', self::NOMINATIM_API, [
                'query' => [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'zoom' => 10, // City-level detail
                ],
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                ],
            ]);

            $result = $response->toArray();
            $data['raw'] = $result;

            // Extract display name
            if (isset($result['display_name'])) {
                $data['displayName'] = $result['display_name'];
            }

            // Extract address components
            if (isset($result['address'])) {
                $address = $result['address'];

                // Try different city fields (Nominatim uses different keys)
                $data['city'] = $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['municipality']
                    ?? null;

                $data['country'] = $address['country'] ?? null;
            }
        } catch (\Exception $e) {
            // Log error but don't fail - geocoding is optional
            // Could inject a logger here if needed
        }

        return $data;
    }

    /**
     * Extract a short location name suitable for collection titles.
     *
     * Returns the most specific location available (city, then country).
     *
     * @param array{city: ?string, country: ?string, displayName: ?string} $geocodeData
     * @return string|null
     */
    public function extractLocationName(array $geocodeData): ?string
    {
        if ($geocodeData['city'] && $geocodeData['country']) {
            return $geocodeData['city'] . ', ' . $geocodeData['country'];
        }

        if ($geocodeData['city']) {
            return $geocodeData['city'];
        }

        if ($geocodeData['country']) {
            return $geocodeData['country'];
        }

        return null;
    }
}
