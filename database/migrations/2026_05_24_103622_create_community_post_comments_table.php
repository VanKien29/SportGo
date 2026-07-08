<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->comment('Bài cộng đồng được bình luận.');
            $table->unsignedBigInteger('user_id')->comment('User viết bình luận.');
            $table->longText('content')->comment('Nội dung bình luận.');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Bình luận cha nếu là trả lời.');
            $table->enum('status', ['visible', 'hidden'])->default('visible')->comment('Trạng thái bình luận.');
            $table->timestamps();
            $table->index(['post_id', 'status', 'created_at'], 'community_post_comments_post_id_status_created_at_index');
            $table->foreign('parent_id')->references('id')->on('community_post_comments')->onDelete('set null');
            $table->foreign('post_id')->references('id')->on('community_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_comments');
    }
};
