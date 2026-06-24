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
        Schema::create('affiliate_products', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('ID của cụm sân chứa sản phẩm này.');
            $table->string('name', 255)->comment('Tên sản phẩm tiếp thị liên kết.');
            $table->text('description')->nullable()->comment('Mô tả sản phẩm.');
            $table->string('image_path', 255)->nullable()->comment('Đường dẫn ảnh sản phẩm.');
            $table->decimal('price', 15, 2)->nullable()->comment('Giá bán hiển thị (VND).');
            $table->decimal('original_price', 15, 2)->nullable()->comment('Giá gốc/giá so sánh để hiển thị giảm giá.');
            $table->text('affiliate_url')->comment('Đường dẫn link tiếp thị liên kết Shopee, Lazada...');
            $table->string('platform_name', 100)->nullable()->comment('Tên nền tảng (Shopee, Lazada, Tiki, TikTok Shop...).');
            $table->boolean('is_active')->default(true)->comment('Trạng thái kích hoạt ẩn/hiện sản phẩm.');
            $table->unsignedInteger('click_count')->default(0)->comment('Số lượng click chuyển tiếp của sản phẩm.');
            $table->timestamps();

            // Foreign key & Index
            $table->foreign('venue_cluster_id')
                ->references('id')
                ->on('venue_clusters')
                ->onDelete('cascade');

            $table->index('venue_cluster_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_products');
    }
};
