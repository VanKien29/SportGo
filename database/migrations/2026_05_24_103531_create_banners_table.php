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
        Schema::create('banners', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('title', 255)->comment('Tiêu đề banner để admin quản lý và FE có thể hiển thị.; VD: Sân Cầu Lông A1');
            $table->string('image_path', 1000)->comment('Đường dẫn ảnh banner đã upload; không lưu binary.; VD: uploads/demo/san-a1.jpg');
            $table->string('link_url', 1000)->nullable()->comment('URL hoặc deep link khi user bấm banner.; VD: uploads/demo/san-a1.jpg');
            $table->string('position', 50)->comment('Vị trí hiển thị banner như home.; VD: home');
            $table->integer('sort_order')->comment('Thứ tự hiển thị banner.; VD: 1');
            $table->boolean('is_active')->comment('Banner có đang bật hay không.; VD: true');
            $table->timestamp('starts_at')->nullable()->comment('Thời điểm bắt đầu hiển thị banner.; VD: 2026-06-15 18:00:00');
            $table->timestamp('ends_at')->nullable()->comment('Thời điểm kết thúc hiển thị banner.; VD: 2026-06-15 18:00:00');
            $table->char('created_by', 36)->nullable()->comment('Admin tạo banner.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('updated_by', 36)->nullable()->comment('Admin cập nhật banner.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
