<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_wallets', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('owner_id', 36)->unique()->comment('Chủ sân sở hữu ví.');
            $table->decimal('available_balance', 14, 2)->default(0.00)->comment('Số dư có thể rút.');
            $table->decimal('pending_withdrawal_balance', 14, 2)->default(0.00)->comment('Số tiền đang giữ cho lệnh rút.');
            $table->decimal('total_earned', 14, 2)->default(0.00)->comment('Tổng tiền hệ thống đã thu hộ.');
            $table->decimal('total_withdrawn', 14, 2)->default(0.00)->comment('Tổng tiền đã chi trả cho chủ sân.');
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_wallets');
    }
};
