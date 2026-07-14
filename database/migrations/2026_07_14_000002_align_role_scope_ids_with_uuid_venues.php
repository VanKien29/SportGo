<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['user_roles', 'user_permission_revokes'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'scope_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('scope_id', 100)->default('0')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['user_roles', 'user_permission_revokes'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'scope_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('scope_id')->default(0)->change();
            });
        }
    }
};
