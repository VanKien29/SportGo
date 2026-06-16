<?php

namespace Database\Seeders;

use App\Models\SeverityLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SeverityLevelsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('severity_levels')) {
            return;
        }

        $levels = [
            ['code' => 'mild', 'name' => 'Nhẹ - Hơi khó chịu, không nguy hiểm', 'multiplier' => 1.0, 'sort_order' => 1],
            ['code' => 'moderate', 'name' => 'Vừa - Vi phạm rõ ràng', 'multiplier' => 2.0, 'sort_order' => 2],
            ['code' => 'severe', 'name' => 'Nghiêm trọng - Gây hại, cần xử lý gấp', 'multiplier' => 3.0, 'sort_order' => 3],
        ];

        foreach ($levels as $level) {
            SeverityLevel::query()->updateOrCreate(['code' => $level['code']], $level);
        }
    }
}
