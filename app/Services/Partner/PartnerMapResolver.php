<?php

namespace App\Services\Partner;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PartnerMapResolver
{
    public function __construct(private readonly PartnerLocationService $locations)
    {
    }

    public function resolve(string $url): array
    {
        $finalUrl = $this->finalUrl($url);
        $coordinates = $this->extractCoordinates($finalUrl) ?: $this->extractCoordinates($url);
        $address = [];

        if ($coordinates) {
            $address = $this->reverseGeocode($coordinates['latitude'], $coordinates['longitude']);
        }

        $location = $this->locations->matchFromAddress($address['address'] ?? null);

        return [
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
            'address' => $address['address'] ?? null,
            'province_code' => $location['province_code'] ?? null,
            'province' => $location['province'] ?? $address['province'] ?? null,
            'ward_code' => $location['ward_code'] ?? null,
            'ward' => $location['ward'] ?? $address['ward'] ?? null,
            'district' => null,
            'final_url' => $finalUrl,
        ];
    }

    public function reverse(float $latitude, float $longitude): array
    {
        $address = $this->reverseGeocode($latitude, $longitude);
        $location = $this->locations->matchFromAddress($address['address'] ?? null);

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address['address'] ?? null,
            'province_code' => $location['province_code'] ?? null,
            'province' => $location['province'] ?? $address['province'] ?? null,
            'ward_code' => $location['ward_code'] ?? null,
            'ward' => $location['ward'] ?? $address['ward'] ?? null,
            'district' => null,
        ];
    }

    private function finalUrl(string $url): string
    {
        if (! function_exists('curl_init')) {
            try {
                $response = Http::timeout(15)
                    ->withoutVerifying()
                    ->withOptions(['allow_redirects' => ['max' => 10]])
                    ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                    ->get($url);

                return $response->effectiveUri()?->__toString() ?? $url;
            } catch (\Throwable) {
                return $url;
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $body = curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Nếu body chứa redirect URL dạng meta refresh hoặc JS redirect
        if ($body && $finalUrl === $url && $httpCode >= 200 && $httpCode < 400) {
            if (preg_match('/content=["\']0;\s*url=(https?:\/\/[^"\'>\s]+)/i', $body, $metaMatch)) {
                return $this->finalUrl($metaMatch[1]);
            }
            if (preg_match('/window\.location\s*=\s*["\']([^"\']+)/', $body, $jsMatch)) {
                return $jsMatch[1];
            }
        }

        return $finalUrl;
    }

    private function extractCoordinates(string $url): ?array
    {
        $decoded = urldecode($url);
        $patterns = [
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/!8m2!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/data=.*!8m2!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/[?&](?:q|ll|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/place\/[^\/@]*\/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
            '/dir\/[^\/@]*\/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
            '/[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $matches)) {
                $lat = (float) $matches[1];
                $lng = (float) $matches[2];

                // Validate coordinate ranges
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    return [
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ];
                }
            }
        }

        return null;
    }

    private function reverseGeocode(float $latitude, float $longitude): array
    {
        // Provider 1: Nominatim OpenStreetMap
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept-Language' => 'vi-VN,vi;q=0.9',
                    'Referer' => 'https://sportgo.vn/',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'addressdetails' => 1,
                    'accept-language' => 'vi',
                    'email' => 'contact@sportgo.vn',
                ]);

            if ($response->successful()) {
                $payload = $response->json();
                $address = $payload['address'] ?? [];
                $displayName = $payload['display_name'] ?? null;

                if ($displayName) {
                    return [
                        'address' => $displayName,
                        'province' => $address['city'] ?? $address['state'] ?? $address['province'] ?? null,
                        'ward' => $address['quarter']
                            ?? $address['suburb']
                            ?? $address['neighbourhood']
                            ?? $address['village']
                            ?? $address['town']
                            ?? $address['municipality']
                            ?? $address['city_district']
                            ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Nominatim reverse geocode failed: ' . $e->getMessage());
        }

        // Provider 2 Fallback: BigDataCloud
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get('https://api.bigdatacloud.net/data/reverse-geocode-client', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'localityLanguage' => 'vi',
                ]);

            if ($response->successful()) {
                $payload = $response->json();
                $admin = $payload['localityInfo']['administrative'] ?? [];
                $names = array_column($admin, 'name');
                $addressStr = implode(', ', array_reverse($names));

                return [
                    'address' => $addressStr ?: null,
                    'province' => $payload['principalSubdivision'] ?? null,
                    'ward' => $payload['locality'] ?? $payload['city'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('BigDataCloud reverse geocode failed: ' . $e->getMessage());
        }

        return [];
    }
}
