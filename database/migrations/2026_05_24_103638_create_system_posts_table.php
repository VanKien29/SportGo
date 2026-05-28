<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('system_posts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('author_id', 36)->nullable()->comment('Admin/nhân viên tạo bài viết.');
            $table->string('title', 255)->comment('Tiêu đề bài viết.');
            $table->string('slug', 255)->unique()->comment('Slug duy nhất.');
            $table->longText('content')->comment('Nội dung bài viết.');
            $table->string('thumbnail_path', 1000)->nullable()->comment('Đường dẫn ảnh đại diện.');
            $table->enum('status', ['draft', 'published', 'hidden'])->default('draft')->comment('Trạng thái bài viết.');
            $table->timestamp('published_at')->nullable()->comment('Thời điểm công khai.');
            $table->unsignedBigInteger('view_count')->default(0)->comment('Số lượt xem.');
            $table->timestamps();
            $table->index('status', 'system_posts_status_index');
            $table->index('published_at', 'system_posts_published_at_index');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('set null');
        });
    }
    public function down(): void { Schema::dropIfExists('system_posts'); }
};
