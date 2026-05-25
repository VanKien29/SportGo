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
        Schema::create('system_posts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('author_id', 36)->nullable()->comment('Admin/nhân viên tạo bài viết.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('title', 255)->comment('Tiêu đề bài viết hệ thống.; VD: Sân Cầu Lông A1');
            $table->string('slug', 255)->unique()->comment('Slug duy nhất để mở chi tiết bài viết.; VD: san-cau-long-a1');
            $table->longText('content')->comment('Nội dung bài viết hệ thống.; VD: Nội dung mẫu dùng để demo.');
            $table->string('thumbnail_path', 1000)->nullable()->comment('Đường dẫn ảnh đại diện bài viết.; VD: uploads/demo/san-a1.jpg');
            $table->string('status')->comment('Trạng thái bài: draft, published, hidden. Giá trị enum: draft=bản nháp; published=đã hiển thị; hidden=đã ẩn.; VD: confirmed');
            $table->timestamp('published_at')->nullable()->comment('Thời điểm bài được công khai.; VD: 2026-06-15 18:00:00');
            $table->bigInteger('view_count')->comment('Số lượt xem bài viết hệ thống nếu cần hiển thị.; VD: 60');
            $table->timestamps();
            $table->foreign('author_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_posts');
    }
};
