<?php

namespace Database\Seeders;

use App\Models\CourtType;
use App\Models\PartnerApplication;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PartnerApplicationCourtsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_application_courts') || ! Schema::hasTable('partner_applications') || ! Schema::hasTable('court_types')) {
            return;
        }

        $typeIds = CourtType::query()->whereIn('name', [
            'Cầu lông (Sân tiêu chuẩn)',
            'Pickleball (Sân tiêu chuẩn)',
            'Bóng Đá (Sân 7)',
        ])->pluck('id', 'name');

        $rows = [
            'SportGo Cầu Giấy' => [
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A1', 1],
                ['Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P1', 2],
            ],
            'SportGo Thanh Xuân' => [
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông TX1', 1],
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông TX2', 2],
                ['Pickleball (Sân tiêu chuẩn)', 'Sân pickleball TX1', 3],
            ],
            'Sân Demo Hồ Tây' => [
                ['Bóng Đá (Sân 7)', 'Sân bóng đá HT1', 1],
            ],
        ];

        foreach ($rows as $venueName => $courts) {
            $application = PartnerApplication::query()->where('venue_name', $venueName)->first();

            if (! $application) {
                continue;
            }

            foreach ($courts as [$typeName, $courtName, $sortOrder]) {
                $courtTypeId = $typeIds[$typeName] ?? null;

                if (! $courtTypeId) {
                    continue;
                }

                DB::table('partner_application_courts')->updateOrInsert(
                    [
                        'partner_application_id' => $application->id,
                        'name' => $courtName,
                    ],
                    [
                        'court_type_id' => $courtTypeId,
                        'sort_order' => $sortOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
