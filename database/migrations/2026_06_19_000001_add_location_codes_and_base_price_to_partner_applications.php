<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_applications', 'venue_province_code')) {
                $table->string('venue_province_code', 20)->nullable()->after('venue_province');
            }

            if (! Schema::hasColumn('partner_applications', 'venue_ward_code')) {
                $table->string('venue_ward_code', 20)->nullable()->after('venue_ward');
            }

            if (! Schema::hasColumn('partner_applications', 'base_price_per_hour')) {
                $table->unsignedInteger('base_price_per_hour')->default(0)->after('court_count_total');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            foreach (['base_price_per_hour', 'venue_ward_code', 'venue_province_code'] as $column) {
                if (Schema::hasColumn('partner_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
