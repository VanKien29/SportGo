<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_location_change_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_location_change_requests', 'new_province_code')) {
                $table->string('new_province_code', 20)->nullable()->after('new_province');
            }

            if (! Schema::hasColumn('venue_location_change_requests', 'new_ward_code')) {
                $table->string('new_ward_code', 20)->nullable()->after('new_ward');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_location_change_requests', function (Blueprint $table): void {
            foreach (['new_ward_code', 'new_province_code'] as $column) {
                if (Schema::hasColumn('venue_location_change_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
