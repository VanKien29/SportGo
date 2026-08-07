<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_configs', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_configs', 'custom_time_periods')) {
                $table->json('custom_time_periods')->nullable()->after('special_operating_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_configs', function (Blueprint $table): void {
            if (Schema::hasColumn('booking_configs', 'custom_time_periods')) {
                $table->dropColumn('custom_time_periods');
            }
        });
    }
};
