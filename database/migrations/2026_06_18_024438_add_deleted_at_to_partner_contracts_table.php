<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_contracts')) {
            if (!Schema::hasColumn('partner_contracts', 'deleted_at')) {
                Schema::table('partner_contracts', function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('partner_contracts') && Schema::hasColumn('partner_contracts', 'deleted_at')) {
            Schema::table('partner_contracts', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
