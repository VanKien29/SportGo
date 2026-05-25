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
        Schema::create('player_ratings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('rater_id', 36)->comment('Người đánh giá.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('rated_user_id', 36)->comment('Người được đánh giá.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('post_id', 36)->nullable()->comment('Bài tuyển/giao lưu làm ngữ cảnh đánh giá; nullable nếu nghiệp vụ sau này dùng booking context khác.; VD: 10000000-0000-0000-0000-000000000001');
            $table->tinyInteger('rating')->comment('Điểm đánh giá người chơi.; VD: 60');
            $table->text('comment')->nullable()->comment('Nhận xét về người chơi.; VD: Nội dung mẫu dùng để demo.');
            $table->json('tags')->nullable()->comment('JSON nhãn đánh giá như đúng giờ, thân thiện.; VD: {"key":"value"}');
            $table->timestamps();
            $table->unique(['rater_id', 'rated_user_id', 'post_id'], 'player_ratings_context_unique');
            $table->index(['rated_user_id', 'created_at'], 'player_ratings_rated_user_id_created_at_index');
            $table->foreign('post_id')->references('id')->on('player_posts')->onDelete('set null');
            $table->foreign('rated_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('rater_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_ratings');
    }
};
