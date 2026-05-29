<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_wallet_ledgers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('owner_wallet_id', 36)->comment('Ví chủ sân được ghi nhận.');
            $table->char('owner_id', 36)->comment('Chủ sân được hưởng tiền.');
            $table->char('venue_cluster_id', 36)->nullable()->comment('Cụm sân phát sinh doanh thu.');
            $table->char('booking_id', 36)->nullable()->comment('Booking phát sinh doanh thu.');
            $table->char('payment_id', 36)->nullable()->comment('Payment phát sinh dòng tiền.');
            $table->enum('type', ['credit', 'debit', 'hold', 'release'])->comment('Loại biến động số dư.');
            $table->decimal('amount', 14, 2)->comment('Số tiền biến động.');
            $table->decimal('balance_before', 14, 2)->comment('Số dư trước biến động.');
            $table->decimal('balance_after', 14, 2)->comment('Số dư sau biến động.');
            $table->string('reference_code', 100)->nullable()->comment('Mã tham chiếu nội bộ/gateway.');
            $table->text('description')->nullable()->comment('Ghi chú nghiệp vụ.');
            $table->json('metadata')->nullable()->comment('Thông tin bổ sung.');
            $table->timestamps();

            $table->index(['owner_id', 'created_at'], 'owner_wallet_ledgers_owner_created_at_index');
            $table->index(['venue_cluster_id', 'created_at'], 'owner_wallet_ledgers_cluster_created_at_index');
            $table->index('booking_id', 'owner_wallet_ledgers_booking_id_index');
            $table->index('payment_id', 'owner_wallet_ledgers_payment_id_index');
            $table->unique(['payment_id', 'type'], 'owner_wallet_ledgers_payment_type_unique');

            $table->foreign('owner_wallet_id')->references('id')->on('owner_wallets')->onDelete('restrict');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('set null');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_wallet_ledgers');
    }
};
