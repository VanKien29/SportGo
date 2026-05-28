<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_posts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('booking_id', 36)->comment('Booking bắt buộc gắn với bài tuyển.');
            $table->char('author_id', 36)->comment('Người tạo bài tuyển.');
            $table->string('title', 255)->comment('Tiêu đề bài tuyển.');
            $table->text('description')->nullable()->comment('Nội dung mô tả buổi giao lưu.');
            $table->unsignedSmallInteger('needed_players')->default(1)->comment('Số người cần tuyển thêm.');
            $table->decimal('cost_per_player', 12, 2)->nullable()->comment('Chi phí/người chỉ để hiển thị.');
            $table->enum('status', ['open', 'full', 'closed', 'cancelled'])->default('open')->comment('Trạng thái bài tuyển.');
            $table->text('status_reason')->nullable()->comment('Lý do đóng/hủy.');
            $table->timestamps();
            $table->index(['author_id', 'created_at'], 'player_posts_author_id_created_at_index');
            $table->index(['booking_id', 'status'], 'player_posts_booking_id_status_index');
            $table->index('status', 'player_posts_status_index');
            $table->index('title', 'player_posts_title_index');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('player_posts'); }
};
