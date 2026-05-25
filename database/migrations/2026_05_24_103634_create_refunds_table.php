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
        Schema::create('refunds', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('payment_id', 36)->comment('Payment gốc cần hoàn tiền.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('booking_id', 36)->comment('Booking liên quan, denormalized từ payment để tra cứu nhanh.; VD: 10000000-0000-0000-0000-000000000001');
            $table->decimal('amount', 12, 2)->comment('Số tiền yêu cầu hoàn.; VD: 120000.00');
            $table->text('reason')->nullable()->comment('Lý do người dùng/chủ sân/hệ thống yêu cầu hoàn.; VD: Nội dung mẫu dùng để demo.');
            $table->string('status')->comment('Trạng thái refund: pending_confirmation, processing, completed, failed, rejected. Giá trị enum: pending_confirmation=chờ xác nhận; processing=đang xử lý; completed=hoàn thành; failed=thất bại; rejected=bị từ chối.; VD: confirmed');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối/thất bại/không xác nhận hoàn tiền.; VD: Nội dung mẫu dùng để demo.');
            $table->char('processed_by', 36)->nullable()->comment('Admin/nhân viên xử lý hoàn tiền.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('processed_at')->nullable()->comment('Thời điểm xử lý hoàn tiền.; VD: 2026-06-15 18:00:00');
            $table->timestamps();
            $table->index(['booking_id', 'status'], 'refunds_booking_id_status_index');
            $table->index(['status', 'created_at'], 'refunds_status_created_at_index');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('restrict');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
