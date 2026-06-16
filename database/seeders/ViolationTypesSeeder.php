<?php

namespace Database\Seeders;

use App\Models\ViolationType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ViolationTypesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('violation_types')) {
            return;
        }

        $types = [
            ['code' => 'spam', 'name' => 'Spam / Quảng cáo rác', 'base_score' => 1, 'is_immediate' => false],
            ['code' => 'offensive_lang', 'name' => 'Ngôn ngữ xúc phạm', 'base_score' => 2, 'is_immediate' => false],
            ['code' => 'misinformation', 'name' => 'Thông tin sai lệch', 'base_score' => 3, 'is_immediate' => false],
            ['code' => 'fraud', 'name' => 'Lừa đảo / Giả mạo', 'base_score' => 5, 'is_immediate' => false],
            ['code' => 'adult_content', 'name' => 'Nội dung người lớn (18+)', 'base_score' => 8, 'is_immediate' => true],
            ['code' => 'illegal_content', 'name' => 'Vi phạm pháp luật', 'base_score' => 10, 'is_immediate' => true],
        ];

        foreach ($types as $type) {
            ViolationType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type + ['is_active' => true],
            );
        }
    }
}
