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
        Schema::create('complaints', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('complaint_type')->comment('Loại khiếu nại: venue với sân hoặc system với hệ thống. Giá trị enum: venue=theo cụm sân; system=hệ thống.; VD: booking_reminder');
            $table->char('booking_id', 36)->nullable()->comment('Booking liên quan nếu khiếu nại phát sinh từ booking.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('venue_cluster_id', 36)->nullable()->comment('Cụm sân liên quan nếu là khiếu nại với sân.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('customer_id', 36)->comment('User gửi khiếu nại.; VD: 10000000-0000-0000-0000-000000000001');
            $table->text('content')->comment('Nội dung khiếu nại.; VD: Nội dung mẫu dùng để demo.');
            $table->string('status')->comment('Trạng thái khiếu nại: open, processing, resolved, rejected, closed. Giá trị enum: open=đang mở; processing=đang xử lý; resolved=đã xử lý; rejected=bị từ chối; closed=đã đóng.; VD: confirmed');
            $table->char('assigned_to', 36)->nullable()->comment('Nhân viên/chủ sân/admin được giao xử lý.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('resolved_by', 36)->nullable()->comment('Người kết luận xử lý khiếu nại.; VD: 10000000-0000-0000-0000-000000000001');
            $table->text('resolve_note')->nullable()->comment('Kết quả xử lý hoặc phản hồi cho người khiếu nại.; VD: Nội dung mẫu dùng để demo.');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối/đóng khiếu nại.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamp('resolved_at')->nullable()->comment('Thời điểm xử lý xong.; VD: 2026-06-15 18:00:00');
            $table->timestamps();
            $table->index(['status', 'created_at'], 'complaints_status_created_at_index');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
