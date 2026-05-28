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

        CourtType::withTrashed()->where('name', 'Badminton')->forceDelete();

        $courtTypes = [
            ['Bóng đá 5 người', 'Sân bóng đá mini 5 người.', 10],
            ['Bóng đá 7 người', 'Sân bóng đá 7 người.', 14],
            ['Cầu lông', 'Sân cầu lông tiêu chuẩn.', 4],
            ['Pickleball', 'Sân pickleball tiêu chuẩn.', 4],
            ['Bóng rổ', 'Sân bóng rổ.', 10],
            ['Bóng chuyền', 'Sân bóng chuyền.', 12],
            ['Tennis', 'Sân tennis.', 4],
        ];

        foreach ($courtTypes as [$name, $description, $playerCount]) {
            $courtType = CourtType::withTrashed()->updateOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'player_count' => $playerCount,
                    'is_active' => true,
                ]
            );

            if ($courtType->trashed()) {
                $courtType->restore();
            }
        }
    }
}
