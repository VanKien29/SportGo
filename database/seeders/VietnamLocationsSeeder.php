<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Models\VnProvince;
use App\Models\VnWard;

class VietnamLocationsSeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign key checks to truncate tables safely
        Schema::disableForeignKeyConstraints();
        VnWard::truncate();
        VnProvince::truncate();
        Schema::enableForeignKeyConstraints();

        $this->command->info('Fetching location data from provinces.open-api.vn v2...');

        // Fetch provinces and their wards directly (depth = 2 in v2 API)
        $response = Http::withoutVerifying()->timeout(60)->get('https://provinces.open-api.vn/api/v2/?depth=2');

        if (!$response->successful()) {
            $this->command->error('Failed to fetch data from API. Please try again.');
            return;
        }

        $provinces = $response->json();
        $this->command->info('Starting database seeding...');

        $provinceData = [];
        $wardData = [];

        foreach ($provinces as $p) {
            $provinceCode = (string)$p['code'];
            
            $provinceData[] = [
                'code'          => $provinceCode,
                'name'          => $p['name'],
                'codename'      => $p['codename'] ?? null,
                'division_type' => $p['division_type'] ?? null,
                'phone_code'    => $p['phone_code'] ?? null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            // In v2 API depth=2, wards belong directly to the province
            if (isset($p['wards']) && is_array($p['wards'])) {
                foreach ($p['wards'] as $w) {
                    $wardData[] = [
                        'code'          => (string)$w['code'],
                        'name'          => $w['name'],
                        'codename'      => $w['codename'] ?? null,
                        'division_type' => $w['division_type'] ?? null,
                        'province_code' => $provinceCode,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }
            }
        }

        // Chunk insertions to prevent database limitations
        $this->command->info('Inserting provinces: ' . count($provinceData));
        foreach (array_chunk($provinceData, 100) as $chunk) {
            VnProvince::insert($chunk);
        }

        $this->command->info('Inserting wards: ' . count($wardData));
        foreach (array_chunk($wardData, 500) as $chunk) {
            VnWard::insert($chunk);
        }

        $this->command->info('Location seeding completed successfully!');
    }
}
