<?php

namespace App\Services\Partner;

use Illuminate\Support\Facades\Http;

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

    private function finalUrl(string $url): string
    {
        if (! function_exists('curl_init')) {
            return $url;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SportGo/1.0 (+https://sportgo.local)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        curl_close($ch);

        return $finalUrl;
    }

    private function extractCoordinates(string $url): ?array
    {
        $decoded = urldecode($url);
        $patterns = [
            '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/[?&](?:q|ll|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $matches)) {
                return [
                    'latitude' => (float) $matches[1],
                    'longitude' => (float) $matches[2],
                ];
            }
        }

        return null;
    }

    private function reverseGeocode(float $latitude, float $longitude): array
    {
        try {
            $payload = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'SportGo/1.0 (+https://sportgo.local)'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'addressdetails' => 1,
                    'accept-language' => 'vi',
                    'zoom' => 18,
                ])
                ->json();

            $address = $payload['address'] ?? [];

            return [
                'address' => $payload['display_name'] ?? null,
                'province' => $address['city'] ?? $address['state'] ?? $address['province'] ?? null,
                'ward' => $address['quarter']
                    ?? $address['suburb']
                    ?? $address['neighbourhood']
                    ?? $address['village']
                    ?? $address['town']
                    ?? $address['municipality']
                    ?? null,
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
