<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('author_id', 36)->comment('User tạo bài đăng tự do.');
            $table->longText('content')->comment('Nội dung bài đăng cộng đồng.');
            $table->enum('status', ['pending_review', 'published', 'rejected', 'hidden'])->default('pending_review')->comment('Trạng thái kiểm duyệt bài cộng đồng.');
            $table->char('reviewed_by', 36)->nullable()->comment('Admin/nhân viên kiểm duyệt bài.');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm kiểm duyệt bài.');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối hoặc ẩn bài.');
            $table->unsignedBigInteger('view_count')->default(0)->comment('Số lượt xem bài cộng đồng.');
            $table->unsignedInteger('like_count')->default(0)->comment('Số lượt thích tổng hợp từ community_post_likes.');
            $table->unsignedInteger('comment_count')->default(0)->comment('Số bình luận tổng hợp từ community_post_comments.');
            $table->timestamps();
            $table->index('status', 'community_posts_status_index');
            $table->index(['status', 'created_at'], 'community_posts_status_created_at_index');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
