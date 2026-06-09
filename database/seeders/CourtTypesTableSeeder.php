<?php

namespace Database\Seeders;

use App\Models\CourtType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourtTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('court_types')) {
            return;
        }

        $this->normalizeOldNames();

        CourtType::withTrashed()->where('name', 'Badminton')->forceDelete();

        $parentsData = [
            ['Bóng Đá', 'Môn bóng đá', 0],
            ['Cầu lông', 'Môn cầu lông', 0],
            ['Pickleball', 'Môn pickleball', 0],
            ['Bóng rổ', 'Môn bóng rổ', 0],
            ['Bóng chuyền', 'Môn bóng chuyền', 0],
            ['Tennis', 'Môn tennis', 0],
        ];

        $parents = [];
        foreach ($parentsData as [$name, $description, $playerCount]) {
            $parent = CourtType::withTrashed()->updateOrCreate(
                ['name' => $name],
                [
                    'parent_id' => null,
                    'description' => $description,
                    'player_count' => $playerCount,
                    'is_active' => true,
                ],
            );

            if ($parent->trashed()) {
                $parent->restore();
            }

            $parents[$name] = $parent->id;
        }

        $childrenData = [
            ['Bóng Đá (Sân 11)', 'Sân bóng đá 11 người.', 22, 'Bóng Đá'],
            ['Bóng Đá (Sân 7)', 'Sân bóng đá 7 người.', 14, 'Bóng Đá'],
            ['Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông tiêu chuẩn.', 4, 'Cầu lông'],
            ['Pickleball (Sân tiêu chuẩn)', 'Sân pickleball tiêu chuẩn.', 4, 'Pickleball'],
            ['Bóng rổ (Sân tiêu chuẩn)', 'Sân bóng rổ tiêu chuẩn.', 10, 'Bóng rổ'],
            ['Bóng chuyền (Sân tiêu chuẩn)', 'Sân bóng chuyền tiêu chuẩn.', 12, 'Bóng chuyền'],
            ['Tennis (Sân tiêu chuẩn)', 'Sân tennis tiêu chuẩn.', 4, 'Tennis'],
        ];

        foreach ($childrenData as [$name, $description, $playerCount, $parentName]) {
            $child = CourtType::withTrashed()->updateOrCreate(
                ['name' => $name],
                [
                    'parent_id' => $parents[$parentName] ?? null,
                    'description' => $description,
                    'player_count' => $playerCount,
                    'is_active' => true,
                ],
            );

            if ($child->trashed()) {
                $child->restore();
            }
        }
    }

    private function normalizeOldNames(): void
    {
        $renames = [
            'Bóng đá 5 người' => 'Bóng Đá (Sân 11)',
            'Bóng đá 7 người' => 'Bóng Đá (Sân 7)',
            'BĂ³ng ÄĂ¡' => 'Bóng Đá',
            'Cáº§u lĂ´ng' => 'Cầu lông',
            'BĂ³ng rá»•' => 'Bóng rổ',
            'BĂ³ng chuyá»n' => 'Bóng chuyền',
            'BĂ³ng ÄĂ¡ (SĂ¢n 11)' => 'Bóng Đá (Sân 11)',
            'BĂ³ng ÄĂ¡ (SĂ¢n 7)' => 'Bóng Đá (Sân 7)',
            'Cáº§u lĂ´ng (SĂ¢n tiĂªu chuáº©n)' => 'Cầu lông (Sân tiêu chuẩn)',
            'Pickleball (SĂ¢n tiĂªu chuáº©n)' => 'Pickleball (Sân tiêu chuẩn)',
            'BĂ³ng rá»• (SĂ¢n tiĂªu chuáº©n)' => 'Bóng rổ (Sân tiêu chuẩn)',
            'BĂ³ng chuyá»n (SĂ¢n tiĂªu chuáº©n)' => 'Bóng chuyền (Sân tiêu chuẩn)',
            'Tennis (SĂ¢n tiĂªu chuáº©n)' => 'Tennis (Sân tiêu chuẩn)',
        ];

        foreach ($renames as $oldName => $newName) {
            $old = CourtType::withTrashed()->where('name', $oldName)->first();

            if (! $old) {
                continue;
            }

            $existing = CourtType::withTrashed()->where('name', $newName)->where('id', '!=', $old->id)->first();

            if (! $existing) {
                $old->forceFill(['name' => $newName])->save();
                continue;
            }

            $this->moveCourtTypeReferences($old->id, $existing->id);
            $old->forceDelete();
        }
    }

    private function moveCourtTypeReferences(int $oldId, int $newId): void
    {
        if (Schema::hasColumn('court_types', 'parent_id')) {
            DB::table('court_types')->where('parent_id', $oldId)->update(['parent_id' => $newId]);
        }

        foreach (['venue_courts', 'price_slots', 'holiday_prices', 'partner_application_courts'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'court_type_id')) {
                DB::table($table)->where('court_type_id', $oldId)->update(['court_type_id' => $newId]);
            }
        }
    }
}
