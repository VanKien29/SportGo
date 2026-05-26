<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('title', 255)->comment('Tiêu đề banner để admin quản lý và FE có thể hiển thị.');
            $table->string('image_path', 1000)->comment('Đường dẫn ảnh banner đã upload; không lưu binary.');
            $table->string('link_url', 1000)->nullable()->comment('URL hoặc deep link khi user bấm banner.');
            $table->string('position', 50)->comment('Vị trí hiển thị banner như home.');
            $table->integer('sort_order')->default(0)->comment('Thứ tự hiển thị banner.');
            $table->boolean('is_active')->default(true)->comment('Banner có đang bật hay không.');
            $table->timestamp('starts_at')->nullable()->comment('Thời điểm bắt đầu hiển thị banner.');
            $table->timestamp('ends_at')->nullable()->comment('Thời điểm kết thúc hiển thị banner.');
            $table->char('created_by', 36)->nullable()->comment('Admin tạo banner.');
            $table->char('updated_by', 36)->nullable()->comment('Admin cập nhật banner.');
            $table->timestamps();
            $table->index('position', 'banners_position_index');
            $table->index('is_active', 'banners_is_active_index');
            $table->index('sort_order', 'banners_sort_order_index');
            $table->index('starts_at', 'banners_starts_at_index');
            $table->index('ends_at', 'banners_ends_at_index');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
