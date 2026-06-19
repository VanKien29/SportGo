<?php

namespace App\Services\Partner;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PartnerLocationService
{
    public function provinces(): array
    {
        return Cache::remember('partner_locations_v2_provinces', now()->addWeek(), function (): array {
            try {
                $payload = Http::timeout(15)->get(config('services.provinces_vn.base_url') . '/api/v2/')->json();
                $provinces = is_array($payload) ? $payload : [];

                return collect($provinces)
                    ->map(fn (array $province) => [
                        'code' => (string) ($province['code'] ?? ''),
                        'name' => (string) ($province['name'] ?? ''),
                        'codename' => (string) ($province['codename'] ?? ''),
                        'division_type' => (string) ($province['division_type'] ?? ''),
                    ])
                    ->filter(fn (array $province) => $province['code'] !== '' && $province['name'] !== '')
                    ->values()
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function wards(string $provinceCode): array
    {
        return Cache::remember('partner_locations_v2_wards_' . $provinceCode, now()->addWeek(), function () use ($provinceCode): array {
            try {
                $payload = Http::timeout(15)
                    ->get(config('services.provinces_vn.base_url') . '/api/v2/p/' . urlencode($provinceCode), [
                        'depth' => 2,
                    ])
                    ->json();

                return collect($payload['wards'] ?? [])
                    ->map(fn (array $ward) => [
                        'code' => (string) ($ward['code'] ?? ''),
                        'name' => (string) ($ward['name'] ?? ''),
                        'codename' => (string) ($ward['codename'] ?? ''),
                        'division_type' => (string) ($ward['division_type'] ?? ''),
                        'province_code' => (string) ($ward['province_code'] ?? $provinceCode),
                    ])
                    ->filter(fn (array $ward) => $ward['code'] !== '' && $ward['name'] !== '')
                    ->values()
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function provinceByCode(?string $code): ?array
    {
        if (! $code) {
            return null;
        }

        return collect($this->provinces())->firstWhere('code', (string) $code);
    }

    public function wardByCode(?string $provinceCode, ?string $wardCode): ?array
    {
        if (! $provinceCode || ! $wardCode) {
            return null;
        }

        return collect($this->wards((string) $provinceCode))->firstWhere('code', (string) $wardCode);
    }

    public function assertWardBelongsToProvince(string $provinceCode, string $wardCode): bool
    {
        return (bool) $this->wardByCode($provinceCode, $wardCode);
    }

    public function matchFromAddress(?string $address): array
    {
        if (! $address) {
            return [];
        }

        $normalizedAddress = $this->normalize($address);
        $matchedProvince = collect($this->provinces())->first(
            fn (array $province) => str_contains($normalizedAddress, $this->normalize($province['name']))
                || str_contains($normalizedAddress, $this->normalize(Str::after($province['name'], ' ')))
        );

        if (! $matchedProvince) {
            return [];
        }

        $matchedWard = collect($this->wards($matchedProvince['code']))->first(
            fn (array $ward) => str_contains($normalizedAddress, $this->normalize($ward['name']))
                || str_contains($normalizedAddress, $this->normalize(Str::after($ward['name'], ' ')))
        );

        return array_filter([
            'province_code' => $matchedProvince['code'],
            'province' => $matchedProvince['name'],
            'ward_code' => $matchedWard['code'] ?? null,
            'ward' => $matchedWard['name'] ?? null,
        ]);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
