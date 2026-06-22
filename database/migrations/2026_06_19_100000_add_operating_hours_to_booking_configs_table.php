<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_configs', function (Blueprint $table): void {
            $table->unsignedInteger('min_advance_booking_minutes')->default(30)->after('max_duration_minutes');
            $table->json('weekly_operating_hours')->nullable()->after('min_advance_booking_minutes');
            $table->json('special_operating_hours')->nullable()->after('weekly_operating_hours');
        });
    }

    public function down(): void
    {
        Schema::table('booking_configs', function (Blueprint $table): void {
            $table->dropColumn([
                'min_advance_booking_minutes',
                'weekly_operating_hours',
                'special_operating_hours',
            ]);
        });
    }
};
