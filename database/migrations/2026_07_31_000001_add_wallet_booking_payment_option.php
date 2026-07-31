<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE bookings MODIFY payment_option ENUM('full_payment','deposit','wallet','no_prepay') NOT NULL DEFAULT 'no_prepay' COMMENT 'Kiểu thanh toán user chọn.'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE bookings MODIFY payment_option ENUM('full_payment','deposit','no_prepay') NOT NULL DEFAULT 'no_prepay' COMMENT 'Kiểu thanh toán user chọn.'");
    }
};
