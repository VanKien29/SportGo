<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_post_participants', function (Blueprint $table) {
            $table->id();
            $table->char('post_id', 36)->comment('Bài tuyển mà user muốn tham gia.');
            $table->char('user_id', 36)->comment('User quan tâm hoặc tham gia.');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending')->comment('Trạng thái tham gia.');
            $table->text('message')->nullable()->comment('Tin nhắn/ghi chú.');
            $table->timestamp('responded_at')->nullable()->comment('Thời điểm người tạo bài phản hồi.');
            $table->timestamps();
            $table->unique(['post_id', 'user_id'], 'player_post_participants_post_id_user_id_unique');
            $table->index('status', 'player_post_participants_status_index');
            $table->index(['user_id', 'status'], 'player_post_participants_user_id_status_index');
            $table->foreign('post_id')->references('id')->on('player_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('player_post_participants'); }
};
