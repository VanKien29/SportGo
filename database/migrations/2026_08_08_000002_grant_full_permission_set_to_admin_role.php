<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                ],
                [],
            );
        }
    }

    public function down(): void
    {
        // Không thu hồi quyền khi rollback vì không thể khôi phục chính xác
        // các quyền Admin đã được cấp thủ công trước thời điểm migration.
    }
};
