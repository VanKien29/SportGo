<?php

namespace Database\Seeders;

use App\Models\VnProvince;
use App\Models\VnWard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VietnamLocationsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('vn_provinces') || ! Schema::hasTable('vn_wards')) {
            return;
        }

        VnWard::query()->delete();
        VnProvince::query()->delete();

        $now = now();

        VnProvince::query()->insert([
            [
                'code' => 'demo-hn',
                'name' => 'Hà Nội (demo/cache)',
                'codename' => 'ha_noi_demo_cache',
                'division_type' => 'city',
                'phone_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        VnWard::query()->insert([
            [
                'code' => 'demo-hn-ba-dinh',
                'name' => 'Ba Đình (demo/cache)',
                'codename' => 'ba_dinh_demo_cache',
                'division_type' => 'ward',
                'province_code' => 'demo-hn',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'demo-hn-cau-giay',
                'name' => 'Cầu Giấy (demo/cache)',
                'codename' => 'cau_giay_demo_cache',
                'division_type' => 'ward',
                'province_code' => 'demo-hn',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'demo-hn-ha-dong',
                'name' => 'Hà Đông (demo/cache)',
                'codename' => 'ha_dong_demo_cache',
                'division_type' => 'ward',
                'province_code' => 'demo-hn',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
