<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('player_ratings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('rater_id', 36)->comment('Người đánh giá.');
            $table->char('rated_user_id', 36)->comment('Người được đánh giá.');
            $table->char('post_id', 36)->nullable()->comment('Bài tuyển làm ngữ cảnh đánh giá.');
            $table->unsignedTinyInteger('rating')->comment('Điểm đánh giá.');
            $table->text('comment')->nullable()->comment('Nhận xét.');
            $table->json('tags')->nullable()->comment('JSON nhãn đánh giá.');
            $table->timestamps();
            $table->unique(['rater_id', 'rated_user_id', 'post_id'], 'player_ratings_context_unique');
            $table->index(['rated_user_id', 'created_at'], 'player_ratings_rated_user_id_created_at_index');
            $table->foreign('post_id')->references('id')->on('player_posts')->onDelete('set null');
            $table->foreign('rated_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('rater_id')->references('id')->on('users')->onDelete('restrict');
        });
    }
    public function down(): void { Schema::dropIfExists('player_ratings'); }
};
