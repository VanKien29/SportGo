<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('internal_receipts')) {
            return;
        }

        Schema::create('internal_receipts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('receipt_code', 40)->unique()->comment('Mã phiếu/hóa đơn nội bộ.');
            $table->enum('receipt_type', ['platform_fee', 'withdrawal', 'refund', 'payment'])->comment('Nghiệp vụ phát sinh phiếu.');
            $table->string('receiptable_type', 100)->comment('Loại đối tượng phát sinh phiếu.');
            $table->string('receiptable_id', 100)->comment('ID đối tượng phát sinh phiếu.');
            $table->char('issued_to_user_id', 36)->nullable()->comment('User nhận phiếu, nếu có.');
            $table->char('issued_by', 36)->nullable()->comment('Admin/người tạo phiếu.');
            $table->string('title', 255)->comment('Tiêu đề phiếu.');
            $table->decimal('amount', 14, 2)->default(0.00)->comment('Số tiền ghi nhận trên phiếu.');
            $table->string('currency', 10)->default('VND')->comment('Đơn vị tiền tệ.');
            $table->enum('status', ['draft', 'issued', 'cancelled'])->default('issued')->comment('Trạng thái phiếu.');
            $table->timestamp('issued_at')->nullable()->comment('Thời điểm phát hành.');
            $table->timestamp('cancelled_at')->nullable()->comment('Thời điểm hủy phiếu.');
            $table->text('cancel_reason')->nullable()->comment('Lý do hủy phiếu.');
            $table->string('file_path', 500)->nullable()->comment('Đường dẫn file PDF/HTML nếu có xuất file.');
            $table->json('metadata')->nullable()->comment('Dữ liệu phụ để render phiếu.');
            $table->timestamps();

            $table->index(['receiptable_type', 'receiptable_id'], 'internal_receipts_receiptable_index');
            $table->index(['receipt_type', 'status'], 'internal_receipts_type_status_index');
            $table->index('issued_at', 'internal_receipts_issued_at_index');
            $table->foreign('issued_to_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_receipts');
    }
};
