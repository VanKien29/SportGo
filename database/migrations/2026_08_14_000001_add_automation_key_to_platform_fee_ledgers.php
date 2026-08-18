<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venue_platform_fee_ledgers')) {
            return;
        }

        if (! Schema::hasColumn('venue_platform_fee_ledgers', 'automation_key')) {
            Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
                $table->string('automation_key', 160)
                    ->nullable()
                    ->after('creation_source');
            });
        }

        if (Schema::hasColumn('venue_platform_fee_ledgers', 'automation_key')) {
            Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
                $table->unique('automation_key', 'vpfl_automation_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('venue_platform_fee_ledgers')) {
            return;
        }

        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
            $table->dropUnique('vpfl_automation_key_unique');
        });

        if (Schema::hasColumn('venue_platform_fee_ledgers', 'automation_key')) {
            Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
                $table->dropColumn('automation_key');
            });
        }
    }
};
