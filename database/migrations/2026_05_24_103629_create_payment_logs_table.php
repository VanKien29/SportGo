<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('payment_id', 36)->comment('Payment mà log này thuộc về.');
            $table->string('event_type', 50)->comment('Loại sự kiện.');
            $table->json('request_payload')->nullable()->comment('JSON payload gửi đi.');
            $table->json('response_payload')->nullable()->comment('JSON phản hồi từ gateway.');
            $table->string('status_before', 20)->nullable()->comment('Trạng thái payment trước.');
            $table->string('status_after', 20)->nullable()->comment('Trạng thái payment sau.');
            $table->string('gateway_txn_id', 100)->nullable()->comment('Mã giao dịch gateway.');
            $table->string('error_code', 100)->nullable()->comment('Mã lỗi nếu có.');
            $table->text('error_message')->nullable()->comment('Thông điệp lỗi chi tiết.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm ghi log.');
            $table->index(['payment_id', 'created_at'], 'payment_logs_payment_id_created_at_index');
            $table->index('event_type', 'payment_logs_event_type_index');
            $table->index('gateway_txn_id', 'payment_logs_gateway_txn_id_index');
            $table->index('error_code', 'payment_logs_error_code_index');
            $table->index('created_at', 'payment_logs_created_at_index');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
