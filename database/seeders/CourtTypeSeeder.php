<?php

namespace Database\Seeders;

use App\Models\CourtType;
use Illuminate\Database\Seeder;

class CourtTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Sân bóng đá', 'player_count' => 14],
            ['name' => 'Sân bóng rổ', 'player_count' => 10],
            ['name' => 'Sân cầu lông', 'player_count' => 4],
            ['name' => 'Sân tennis', 'player_count' => 4],
            ['name' => 'Sân bóng bàn', 'player_count' => 4],
        ] as $courtType) {
            CourtType::firstOrCreate(
                ['name' => $courtType['name']],
                ['player_count' => $courtType['player_count'], 'is_active' => true]
            );
        }
    }
}
