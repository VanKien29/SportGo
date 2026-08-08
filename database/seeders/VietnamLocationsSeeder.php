<?php

namespace Database\Seeders;

use App\Models\VnProvince;
use App\Models\VnWard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class VietnamLocationsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('vn_provinces') || ! Schema::hasTable('vn_wards')) {
            return;
        }

        $now = now();
        $baseUrl = config('services.provinces_vn.base_url', 'https://provinces.open-api.vn');

        Cache::forget('partner_locations_v2_provinces');

        try {
            // Lấy dữ liệu Tỉnh/Thành phố và Phường/Xã từ API
            $provincesRes = Http::timeout(25)->withoutVerifying()->get("{$baseUrl}/api/v2/");
            $wardsRes = Http::timeout(35)->withoutVerifying()->get("{$baseUrl}/api/v2/w/");

            if ($provincesRes->successful() && $wardsRes->successful()) {
                $provincesData = $provincesRes->json() ?? [];
                $wardsData = $wardsRes->json() ?? [];

                if (! empty($provincesData)) {
                    Schema::disableForeignKeyConstraints();
                    VnWard::query()->truncate();
                    VnProvince::query()->truncate();
                    Schema::enableForeignKeyConstraints();

                    $provinceRecords = collect($provincesData)->map(fn ($p) => [
                        'code' => (string) ($p['code'] ?? ''),
                        'name' => (string) ($p['name'] ?? ''),
                        'codename' => (string) ($p['codename'] ?? ''),
                        'division_type' => (string) ($p['division_type'] ?? ''),
                        'phone_code' => isset($p['phone_code']) ? (int) $p['phone_code'] : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->filter(fn ($p) => $p['code'] !== '' && $p['name'] !== '')->values()->all();

                    foreach (array_chunk($provinceRecords, 100) as $chunk) {
                        VnProvince::query()->insert($chunk);
                    }

                    $wardRecords = collect($wardsData)->map(fn ($w) => [
                        'code' => (string) ($w['code'] ?? ''),
                        'name' => (string) ($w['name'] ?? ''),
                        'codename' => (string) ($w['codename'] ?? ''),
                        'division_type' => (string) ($w['division_type'] ?? ''),
                        'province_code' => (string) ($w['province_code'] ?? ''),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->filter(fn ($w) => $w['code'] !== '' && $w['name'] !== '' && $w['province_code'] !== '')->values()->all();

                    foreach (array_chunk($wardRecords, 500) as $chunk) {
                        VnWard::query()->insert($chunk);
                    }

                    $this->command?->info('Đã seed thành công ' . count($provinceRecords) . ' Tỉnh/Thành phố và ' . count($wardRecords) . ' Phường/Xã từ API!');
                }
            }
        } catch (\Throwable $e) {
            $this->command?->error('Lỗi tải dữ liệu từ API Vietnam Locations: ' . $e->getMessage());
        }
    }
}
    