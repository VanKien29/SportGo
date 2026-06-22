<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venue_clusters')) {
            Schema::table('venue_clusters', function (Blueprint $table): void {
                if (! Schema::hasColumn('venue_clusters', 'province_code')) {
                    $table->string('province_code', 20)->nullable()->after('province')
                        ->comment('Ma tinh/thanh pho tu administrative_units.');
                }

                if (! Schema::hasColumn('venue_clusters', 'district_code')) {
                    $table->string('district_code', 20)->nullable()->after('province_code')
                        ->comment('Ma quan/huyen tu administrative_units.');
                }

                if (! Schema::hasColumn('venue_clusters', 'ward_code')) {
                    $table->string('ward_code', 20)->nullable()->after('ward')
                        ->comment('Ma phuong/xa tu administrative_units.');
                }
            });
        }

        if (Schema::hasTable('partner_applications')) {
            Schema::table('partner_applications', function (Blueprint $table): void {
                if (! Schema::hasColumn('partner_applications', 'venue_province_code')) {
                    $table->string('venue_province_code', 20)->nullable()->after('venue_province')
                        ->comment('Ma tinh/thanh pho cua cum san dang ky.');
                }

                if (! Schema::hasColumn('partner_applications', 'venue_district_code')) {
                    $table->string('venue_district_code', 20)->nullable()->after('venue_district')
                        ->comment('Ma quan/huyen cua cum san dang ky.');
                }

                if (! Schema::hasColumn('partner_applications', 'venue_ward_code')) {
                    $table->string('venue_ward_code', 20)->nullable()->after('venue_ward')
                        ->comment('Ma phuong/xa cua cum san dang ky.');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('partner_applications')) {
            Schema::table('partner_applications', function (Blueprint $table): void {
                foreach (['venue_ward_code', 'venue_district_code', 'venue_province_code'] as $column) {
                    if (Schema::hasColumn('partner_applications', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('venue_clusters')) {
            Schema::table('venue_clusters', function (Blueprint $table): void {
                foreach (['ward_code', 'district_code', 'province_code'] as $column) {
                    if (Schema::hasColumn('venue_clusters', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
