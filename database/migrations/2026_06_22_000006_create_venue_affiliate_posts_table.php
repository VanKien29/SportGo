<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_affiliate_posts', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cum san hien thi bai affiliate.');
            $table->char('author_id', 36)->comment('Chu san dang bai affiliate.');
            $table->string('title', 255)->comment('Tieu de san pham hoac bai dang.');
            $table->text('description')->nullable()->comment('Mo ta chi tiet.');
            $table->decimal('price', 12, 2)->nullable()->comment('Gia tham khao, khong giao dich trong he thong.');
            $table->enum('affiliate_platform', ['tiktok_shop', 'shopee', 'lazada', 'other'])->default('other')
                ->comment('Nen tang affiliate.');
            $table->string('affiliate_url', 2000)->comment('Link san pham tren nen tang ngoai.');
            $table->string('category', 100)->nullable()->comment('Danh muc san pham.');
            $table->enum('status', ['pending_review', 'published', 'rejected', 'hidden'])->default('pending_review')
                ->comment('Trang thai kiem duyet.');
            $table->char('reviewed_by', 36)->nullable()->comment('Admin kiem duyet.');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('status_reason')->nullable()->comment('Ly do tu choi hoac an bai.');
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('click_count')->default(0)->comment('So lan click link affiliate.');
            $table->timestamp('expires_at')->nullable()->comment('Han dang bai.');
            $table->timestamps();

            $table->index(['venue_cluster_id', 'status'], 'venue_affiliate_posts_cluster_status_index');
            $table->index(['author_id', 'status'], 'venue_affiliate_posts_author_status_index');
            $table->index(['affiliate_platform', 'status'], 'venue_affiliate_posts_platform_status_index');
            $table->index('expires_at', 'venue_affiliate_posts_expires_at_index');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_affiliate_posts');
    }
};
