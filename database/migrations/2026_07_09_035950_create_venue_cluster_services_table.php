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
        Schema::create('service_categories', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255)->comment('Tên danh mục dịch vụ.');
            $table->string('status', 20)->default('active')->comment('Trạng thái danh mục (active, inactive).');
            $table->text('description')->nullable()->comment('Mô tả danh mục.');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('venue_cluster_services', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('ID của cụm sân chứa dịch vụ này.');
            $table->char('category_id', 36)->comment('ID của danh mục dịch vụ liên kết.');
            $table->string('name', 255)->comment('Tên dịch vụ/sản phẩm.');
            $table->decimal('price', 15, 2)->comment('Giá bán hoặc cho thuê (VND).');
            $table->string('unit', 50)->comment('Đơn vị tính (chai, lượt, tiếng, cái, quả...).');
            $table->string('status', 20)->default('active')->comment('Trạng thái kinh doanh (active, inactive, out_of_stock).');
            $table->text('description')->nullable()->comment('Mô tả dịch vụ.');
            $table->timestamps();

            // Foreign keys & Index
            $table->foreign('venue_cluster_id')
                ->references('id')
                ->on('venue_clusters')
                ->onDelete('cascade');

            $table->foreign('category_id')
                ->references('id')
                ->on('service_categories')
                ->onDelete('restrict');

            $table->index('venue_cluster_id');
            $table->index('category_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_cluster_services');
        Schema::dropIfExists('service_categories');
    }
};
