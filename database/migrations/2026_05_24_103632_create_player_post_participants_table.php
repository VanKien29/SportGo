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
        Schema::create('player_post_participants', function (Blueprint $table) {
            $table->id();
            $table->char('post_id', 36)->comment('Bài tuyển mà user muốn tham gia.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('user_id', 36)->comment('User quan tâm hoặc tham gia.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('status')->comment('Trạng thái tham gia: pending, approved, rejected, cancelled. Giá trị enum: pending=chờ xử lý; approved=đã duyệt; rejected=bị từ chối; cancelled=đã hủy.; VD: confirmed');
            $table->text('message')->nullable()->comment('Tin nhắn/ghi chú khi user xin tham gia hoặc người tạo phản hồi.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamp('responded_at')->nullable()->comment('Thời điểm người tạo bài phản hồi yêu cầu tham gia.; VD: 18:00:00');
            $table->timestamps();
            $table->unique(['post_id', 'user_id'], 'player_post_participants_post_id_user_id_unique');
            $table->index(['user_id', 'status'], 'player_post_participants_user_id_status_index');
            $table->foreign('post_id')->references('id')->on('player_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_post_participants');
    }
};
