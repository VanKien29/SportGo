<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('booking_id', 36)->comment('Booking được thanh toán.; VD: 10000000-0000-0000-0000-000000000001');
            $table->decimal('amount', 12, 2)->comment('Số tiền của lần thanh toán này.; VD: 120000.00');
            $table->string('payment_kind')->comment('Loại thanh toán: full, deposit hoặc partial. Giá trị enum: full=thanh toán đủ hoặc đã đủ người; deposit=đặt cọc; partial=thanh toán một phần.; VD: deposit');
            $table->string('method')->comment('Cổng thanh toán đã chốt: vnpay, momo, zalopay. Giá trị enum: vnpay=VNPay; momo=MoMo; zalopay=ZaloPay.; VD: vnpay');
            $table->string('gateway_txn_id', 100)->unique()->nullable()->comment('Mã giao dịch từ cổng thanh toán, unique để chống xử lý trùng webhook.; VD: 10000000-0000-0000-0000-000000000001');
            $table->json('gateway_response')->nullable()->comment('JSON phản hồi cuối hoặc dữ liệu tóm tắt từ gateway.; VD: {"key":"value"}');
            $table->string('status')->comment('Trạng thái payment: pending, paid, failed, refunded. Giá trị enum: pending=chờ xử lý; paid=đã thanh toán; failed=thất bại; refunded=đã hoàn tiền.; VD: confirmed');
            $table->timestamp('paid_at')->nullable()->comment('Thời điểm thanh toán thành công.; VD: 2026-06-15 18:00:00');
            $table->timestamps();
            $table->index(['booking_id', 'status'], 'payments_booking_id_status_index');
            $table->index(['status', 'created_at'], 'payments_status_created_at_index');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
