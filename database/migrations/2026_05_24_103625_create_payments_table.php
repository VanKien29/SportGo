<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('payment_code', 50)->unique()->comment('Mã thanh toán nội bộ của hệ thống.');
            $table->char('booking_id', 36)->comment('Booking được thanh toán.');
            $table->decimal('amount', 12, 2)->comment('Số tiền của lần thanh toán này.');
            $table->enum('payment_kind', ['full', 'deposit', 'partial'])->default('partial')->comment('Loại thanh toán.');
            $table->enum('method', ['vnpay', 'momo', 'zalopay'])->comment('Cổng thanh toán đã chốt.');
            $table->string('gateway_txn_id', 100)->unique()->nullable()->comment('Mã giao dịch từ cổng thanh toán.');
            $table->json('gateway_response')->nullable()->comment('JSON phản hồi từ gateway.');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->comment('Trạng thái payment.');
            $table->timestamp('paid_at')->nullable()->comment('Thời điểm thanh toán thành công.');
            $table->timestamps();
            $table->index(['booking_id', 'status'], 'payments_booking_id_status_index');
            $table->index(['status', 'created_at'], 'payments_status_created_at_index');
            $table->index('method', 'payments_method_index');
            $table->index('status', 'payments_status_index');
            $table->index('paid_at', 'payments_paid_at_index');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
