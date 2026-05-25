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
        Schema::create('community_post_comments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('post_id', 36)->comment('Bài cộng đồng được bình luận.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('user_id', 36)->comment('User viết bình luận.; VD: 10000000-0000-0000-0000-000000000001');
            $table->longText('content')->comment('Nội dung bình luận.; VD: Nội dung mẫu dùng để demo.');
            $table->char('parent_id', 36)->nullable()->comment('Bình luận cha nếu là trả lời bình luận.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('status')->comment('Trạng thái bình luận: visible hoặc hidden. Giá trị enum: visible=hiển thị; hidden=đã ẩn.; VD: confirmed');
            $table->timestamps();
            $table->index(['post_id', 'status', 'created_at'], 'community_post_comments_post_id_status_created_at_index');
            $table->foreign('parent_id')->references('id')->on('community_post_comments')->onDelete('set null');
            $table->foreign('post_id')->references('id')->on('community_posts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_post_comments');
    }
};
