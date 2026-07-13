<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SafeDataConsistencyRepairSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Safe data repair is not part of the deterministic fresh seed dataset.');
    }
}
