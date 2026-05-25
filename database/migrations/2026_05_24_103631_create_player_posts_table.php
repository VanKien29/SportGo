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
        Schema::create('player_posts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('booking_id', 36)->comment('Booking bắt buộc gắn với bài tuyển; không hỗ trợ bài tuyển tự do.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('author_id', 36)->comment('Người tạo bài tuyển.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('title', 255)->comment('Tiêu đề bài tuyển hiển thị tại chi tiết sân/lịch booking.; VD: Sân Cầu Lông A1');
            $table->text('description')->nullable()->comment('Nội dung mô tả buổi giao lưu và ghi chú chi phí nếu cần.; VD: Nội dung mẫu dùng để demo.');
            $table->smallInteger('needed_players')->comment('Số người cần tuyển thêm.; VD: 60');
            $table->decimal('cost_per_player', 12, 2)->nullable()->comment('Chi phí/người chỉ để hiển thị/tham khảo, không phải nghiệp vụ thanh toán bắt buộc.; VD: 120000.00');
            $table->string('status')->comment('Trạng thái bài tuyển: open, full, closed, cancelled. Giá trị enum: open=đang mở; full=thanh toán đủ hoặc đã đủ người; closed=đã đóng; cancelled=đã hủy.; VD: confirmed');
            $table->text('status_reason')->nullable()->comment('Lý do đóng/hủy bài tuyển.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamps();
            $table->index(['author_id', 'created_at'], 'player_posts_author_id_created_at_index');
            $table->index(['booking_id', 'status'], 'player_posts_booking_id_status_index');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_posts');
    }
};
