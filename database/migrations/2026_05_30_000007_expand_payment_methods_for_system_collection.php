<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY method ENUM('sepay','bank_transfer','cash','vnpay','momo','zalopay') NOT NULL DEFAULT 'sepay' COMMENT 'Phương thức thanh toán/ghi nhận tiền.'");
    }

    public function down(): void
    {
        // Không tự thu hẹp enum để tránh lỗi nếu DB đã có payment method sepay/bank_transfer/cash.
    }
};
