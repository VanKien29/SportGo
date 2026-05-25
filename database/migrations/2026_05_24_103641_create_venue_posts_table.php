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
        Schema::create('venue_posts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cụm sân sở hữu bài đăng.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('author_id', 36)->comment('Chủ sân/nhân viên tạo bài đăng.; VD: 10000000-0000-0000-0000-000000000001');
            $table->longText('content')->comment('Nội dung bài đăng của sân.; VD: Nội dung mẫu dùng để demo.');
            $table->string('status')->comment('Trạng thái kiểm duyệt: pending_review, published, rejected, hidden. Giá trị enum: pending_review=chờ kiểm duyệt; published=đã hiển thị; rejected=bị từ chối; hidden=đã ẩn.; VD: confirmed');
            $table->char('reviewed_by', 36)->nullable()->comment('Admin/nhân viên kiểm duyệt bài sân.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm kiểm duyệt bài sân.; VD: 2026-06-15 18:00:00');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối hoặc ẩn bài sân.; VD: Nội dung mẫu dùng để demo.');
            $table->bigInteger('view_count')->comment('Số lượt xem bài sân.; VD: 60');
            $table->integer('like_count')->comment('Số lượt thích bài sân nếu FE dùng.; VD: 60');
            $table->integer('comment_count')->comment('Số bình luận bài sân nếu FE dùng.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamps();
            $table->index(['venue_cluster_id', 'status'], 'venue_posts_venue_cluster_id_status_index');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_posts');
    }
};
