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
        Schema::create('community_post_likes', function (Blueprint $table) {
            $table->id();
            $table->char('post_id', 36)->comment('Bài cộng đồng được thích.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('user_id', 36)->comment('User bấm thích.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm bấm thích.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->unique(['post_id', 'user_id'], 'community_post_likes_post_id_user_id_unique');
            $table->foreign('post_id')->references('id')->on('community_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_post_likes');
    }
};
