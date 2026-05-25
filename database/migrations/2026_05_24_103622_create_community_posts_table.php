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
        Schema::create('community_posts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('author_id', 36)->comment('User tạo bài đăng tự do.; VD: 10000000-0000-0000-0000-000000000001');
            $table->longText('content')->comment('Nội dung bài đăng cộng đồng.; VD: Nội dung mẫu dùng để demo.');
            $table->string('status')->comment('Trạng thái kiểm duyệt: pending_review, published, rejected, hidden. Giá trị enum: pending_review=chờ kiểm duyệt; published=đã hiển thị; rejected=bị từ chối; hidden=đã ẩn.; VD: confirmed');
            $table->char('reviewed_by', 36)->nullable()->comment('Admin/nhân viên kiểm duyệt bài.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm kiểm duyệt bài.; VD: 2026-06-15 18:00:00');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối hoặc ẩn bài.; VD: Nội dung mẫu dùng để demo.');
            $table->bigInteger('view_count')->comment('Số lượt xem bài cộng đồng.; VD: 60');
            $table->integer('like_count')->comment('Số lượt thích tổng hợp từ community_post_likes.; VD: 60');
            $table->integer('comment_count')->comment('Số bình luận tổng hợp từ community_post_comments.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamps();
            $table->index(['status', 'created_at'], 'community_posts_status_created_at_index');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
