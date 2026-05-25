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
        Schema::create('slot_locks', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân bị giữ/khóa; denormalized để lọc nhanh.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('venue_court_id', 36)->nullable()->comment('Sân con bị giữ/khóa; nullable khi khóa cả cụm.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('lock_scope')->comment('Phạm vi khóa: court cho một sân con, cluster cho cả cụm. Giá trị enum: court=một sân con; cluster=cả cụm sân.; VD: giá trị mẫu');
            $table->date('booking_date')->comment('Ngày bị giữ/khóa.; VD: 2026-06-15');
            $table->time('start_time')->comment('Giờ bắt đầu khoảng giữ/khóa.; VD: 18:00:00');
            $table->time('end_time')->comment('Giờ kết thúc khoảng giữ/khóa.; VD: 20:00:00');
            $table->string('locked_by', 100)->comment('Định danh người/session tạo lock; dùng được cả trước khi user có booking.; VD: true');
            $table->char('booking_id', 36)->nullable()->comment('Booking liên quan nếu lock sinh từ quá trình đặt sân.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('lock_type')->comment('Loại lock: auto giữ tạm 20 phút hoặc manual do chủ sân khóa. Giá trị enum: auto=tự động; manual=thủ công.; VD: booking_reminder');
            $table->timestamp('expires_at')->comment('Thời điểm lock tự hết hạn; auto lock dùng để giải phóng slot.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm tạo lock.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->index(['venue_court_id', 'booking_date', 'start_time', 'end_time'], 'slot_locks_court_time_index');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->foreign('venue_court_id')->references('id')->on('venue_courts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_locks');
    }
};
