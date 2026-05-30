<?php

namespace Database\Seeders;

use App\Models\CourtType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CourtTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('court_types')) {
            return;
        }

        // Đổi tên các loại sân cũ sang tên mới để giữ nguyên khoá ngoại ID cho các bảng liên quan như price_slots
        $renameMap = [
            'Bóng đá 5 người' => 'Bóng Đá (Sân 11)',
            'Bóng đá 7 người' => 'Bóng Đá (Sân 7)',
        ];

        foreach ($renameMap as $oldName => $newName) {
            $existing = CourtType::withTrashed()->where('name', $oldName)->first();
            if ($existing) {
                $newExists = CourtType::withTrashed()->where('name', $newName)->exists();
                if (!$newExists) {
                    $existing->update(['name' => $newName]);
                }
            }
        }

        CourtType::withTrashed()->where('name', 'Badminton')->forceDelete();

        // 1. Seed danh mục cha
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
                ]
            );
            if ($parent->trashed()) {
                $parent->restore();
            }
            $parents[$name] = $parent->id;
        }

        // 2. Seed danh mục con
        $childrenData = [
            ['Bóng Đá (Sân 11)', 'Có chân thì đá không chân thì nhót', 22, 'Bóng Đá'],
            ['Bóng Đá (Sân 7)', 'Có chân thì đá không chân thì nhót', 14, 'Bóng Đá'],
            ['Cầu lông (Sân tiêu chuẩn)', 'Đi mây về gió', 4, 'Cầu lông'],
            ['Pickleball (Sân tiêu chuẩn)', 'Sân pickleball tiêu chuẩn.', 4, 'Pickleball'],
            ['Bóng rổ (Sân tiêu chuẩn)', 'Sân bóng rổ.', 10, 'Bóng rổ'],
            ['Bóng chuyền (Sân tiêu chuẩn)', 'Sân bóng chuyền.', 12, 'Bóng chuyền'],
            ['Tennis (Sân tiêu chuẩn)', 'Sân tennis.', 4, 'Tennis'],
        ];

        foreach ($childrenData as [$name, $description, $playerCount, $parentName]) {
            $parentId = $parents[$parentName] ?? null;
            $child = CourtType::withTrashed()->updateOrCreate(
                ['name' => $name],
                [
                    'parent_id' => $parentId,
                    'description' => $description,
                    'player_count' => $playerCount,
                    'is_active' => true,
                ]
            );
            if ($child->trashed()) {
                $child->restore();
            }
        }
    }
}
