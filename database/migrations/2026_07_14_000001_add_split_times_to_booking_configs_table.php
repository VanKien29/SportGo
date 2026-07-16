<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_configs', function (Blueprint $table) {
            $table->time('morning_end_time')->default('12:00:00')->after('fixed_close_time');
            $table->time('afternoon_end_time')->default('18:00:00')->after('morning_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('booking_configs', function (Blueprint $table) {
            $table->dropColumn(['morning_end_time', 'afternoon_end_time']);
        });
    }
};
