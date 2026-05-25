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
        Schema::create('notifications', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->comment('Người nhận thông báo.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('type', 50)->comment('Loại thông báo như booking_reminder, complaint_assigned, partner_application_pending.; VD: booking_reminder');
            $table->string('title', 255)->comment('Tiêu đề thông báo hiển thị ở chuông.; VD: Sân Cầu Lông A1');
            $table->text('body')->nullable()->comment('Nội dung ngắn của thông báo.; VD: Nội dung mẫu dùng để demo.');
            $table->string('reference_type', 100)->nullable()->comment('Loại đối tượng để app điều hướng khi bấm thông báo; logical reference.; VD: booking_reminder');
            $table->string('reference_id', 100)->nullable()->comment('ID đối tượng để app điều hướng khi bấm thông báo.; VD: 10000000-0000-0000-0000-000000000001');
            $table->json('data')->nullable()->comment('JSON dữ liệu phụ cho FE như số phút nhắc lịch.; VD: {"key":"value"}');
            $table->boolean('is_read')->comment('Đánh dấu user đã đọc thông báo hay chưa.; VD: true');
            $table->timestamp('read_at')->nullable()->comment('Thời điểm user đọc thông báo.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm tạo thông báo.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->index(['reference_type', 'reference_id'], 'notifications_reference_type_reference_id_index');
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_id_is_read_created_at_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
