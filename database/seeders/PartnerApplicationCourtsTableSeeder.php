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

        $typeIds = CourtType::query()
            ->whereIn('name', ['Cầu lông (Sân tiêu chuẩn)', 'Pickleball (Sân tiêu chuẩn)', 'Bóng đá (Sân 7)'])
            ->pluck('id', 'name');

        $rows = [
            'SportGo Cầu Giấy' => [
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A1', 4, 1],
                ['Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P1', 2, 2],
            ],
            'SportGo Thanh Xuân' => [
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông TX1', 3, 1],
                ['Pickleball (Sân tiêu chuẩn)', 'Sân pickleball TX1', 2, 2],
            ],
            'SportGo Mỹ Đình' => [
                ['Bóng đá (Sân 7)', 'Sân bóng đá MD1', 2, 1],
            ],
            'SportGo Hồ Tây' => [
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông HT1', 2, 1],
            ],
            'SportGo Long Biên' => [
                ['Bóng đá (Sân 7)', 'Sân bóng đá LB1', 1, 1],
            ],
            'SportGo Đống Đa' => [
                ['Pickleball (Sân tiêu chuẩn)', 'Sân pickleball DD1', 2, 1],
            ],
            'SportGo Hà Đông' => [
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông HD1', 3, 1],
            ],
            'SportGo Ba Đình' => [
                ['Bóng đá (Sân 7)', 'Sân bóng đá BD1', 1, 1],
            ],
        ];

        foreach ($rows as $venueName => $courts) {
            $application = PartnerApplication::query()->where('venue_name', $venueName)->first();

            if (! $application) {
                continue;
            }

            foreach ($courts as [$typeName, $courtName, $count, $sortOrder]) {
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
                        'court_type_name_snapshot' => $typeName,
                        'expected_court_count' => $count,
                        'note' => 'Dữ liệu sân dự kiến từ hồ sơ đối tác.',
                        'sort_order' => $sortOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}
