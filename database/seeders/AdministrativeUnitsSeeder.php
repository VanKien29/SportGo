<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AdministrativeUnitsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('administrative_units')) {
            return;
        }

        $path = database_path('data/administrative_units.json');
        if (! is_file($path)) {
            throw new RuntimeException('Missing database/data/administrative_units.json.');
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows)) {
            throw new RuntimeException('Invalid administrative_units.json.');
        }

        $now = now();
        collect($rows)
            ->map(fn (array $row): array => [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'name_en' => $row['name_en'] ?? null,
                'full_name' => $row['full_name'] ?? null,
                'type' => (string) $row['type'],
                'parent_code' => $row['parent_code'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->chunk(1000)
            ->each(function ($chunk): void {
                DB::table('administrative_units')->upsert(
                    $chunk->all(),
                    ['code'],
                    ['id', 'name', 'name_en', 'full_name', 'type', 'parent_code', 'is_active', 'updated_at']
                );
            });
    }
}
