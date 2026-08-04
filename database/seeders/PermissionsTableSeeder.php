<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Services\Auth\SystemPermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Permission::query()->where('code', 'auth.login')->delete();

        foreach (SystemPermissionCatalog::definitions() as $code => $definition) {
            Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'group_name' => $definition['group_name'],
                ]
            );
        }
    }
}
