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
            ->whereIn('name', ['Cầu lông (Sân tiêu chuẩn)', 'Pickleball (Sân tiêu chuẩn)', 'Bóng Đá (Sân 7)'])
            ->pluck('id', 'name');

        $rows = [
            'Green Sport Ba Đình' => [
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A1', 2, 1],
                ['Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P1', 2, 2],
            ],
            'Sun Sport Cầu Giấy' => [
                ['Bóng Đá (Sân 7)', 'Sân bóng đá F1', 1, 1],
            ],
            'Victory Sport Hà Đông' => [
                ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông V1', 1, 1],
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
