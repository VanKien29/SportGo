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
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('payment_id', 36)->comment('Payment mà log này thuộc về.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('event_type', 50)->comment('Loại sự kiện: request, response, webhook, error hoặc trạng thái tương đương.; VD: booking_reminder');
            $table->json('request_payload')->nullable()->comment('JSON payload gửi đi hoặc webhook nhận vào.; VD: {"key":"value"}');
            $table->json('response_payload')->nullable()->comment('JSON phản hồi từ gateway hoặc kết quả xử lý webhook.; VD: {"key":"value"}');
            $table->string('status_before', 20)->nullable()->comment('Trạng thái payment trước sự kiện.; VD: confirmed');
            $table->string('status_after', 20)->nullable()->comment('Trạng thái payment sau sự kiện.; VD: confirmed');
            $table->string('gateway_txn_id', 100)->nullable()->comment('Mã giao dịch gateway liên quan tới log.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('error_code', 100)->nullable()->comment('Mã lỗi gateway/hệ thống nếu có.; VD: SPORTGO_CODE_001');
            $table->text('error_message')->nullable()->comment('Thông điệp lỗi chi tiết để tra soát.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm ghi log.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->index(['payment_id', 'created_at'], 'payment_logs_payment_id_created_at_index');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
